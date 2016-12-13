<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Worker;

use Composer\Factory;
use Composer\Installer;
use Composer\Package\Package;
use Composer\Repository\ArrayRepository;
use Composer\Semver\VersionParser;
use Predis\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Toflar\ComposerResolver\Job;
use Toflar\ComposerResolver\JobIO;
use Toflar\ComposerResolver\JobOutput;
use Toflar\ComposerResolver\Tests\Worker\ResolvingResultTest;

/**
 * Class Resolver
 *
 * @package Toflar\ComposerResolver\Worker
 * @author  Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class Resolver
{
    /**
     * @var Client
     */
    private $predis;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $jobsDir;

    /**
     * @var string
     */
    private $queueKey;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var int
     */
    private $maxRetriesPerJob;

    /**
     * @var int
     */
    private $secondsToWaitBeforeRetry;

    /**
     * @var int
     */
    private $mockRunResult = null;

    /**
     * @var string
     */
    private $mockComposerLock = null;

    /**
     * @var bool
     */
    private $terminateAfterRun = true;

    /**
     * @var ResolvingResult
     */
    private $lastResult;

    /**
     * Resolver constructor.
     *
     * @param Client          $predis
     * @param LoggerInterface $logger
     * @param string          $jobsDir
     * @param string          $queueKey
     * @param int             $ttl
     */
    public function __construct(Client $predis, LoggerInterface $logger, string $jobsDir, string $queueKey, int $ttl, int $maxRetriesPerJob, int $secondsToWaitBeforeRetry)
    {
        $this->predis = $predis;
        $this->logger = $logger;
        $this->jobsDir = $jobsDir;
        $this->queueKey = $queueKey;
        $this->ttl = $ttl;
        $this->maxRetriesPerJob = $maxRetriesPerJob;
        $this->secondsToWaitBeforeRetry = $secondsToWaitBeforeRetry;
    }

    /**
     * @param int $mockRunResult
     *
     * @return Resolver
     */
    public function setMockRunResult(int $mockRunResult)
    {
        $this->mockRunResult = $mockRunResult;

        return $this;
    }

    /**
     * @param string $mockComposerLock
     *
     * @return Resolver
     */
    public function setMockComposerLock(string $mockComposerLock)
    {
        $this->mockComposerLock = $mockComposerLock;

        return $this;
    }

    /**
     * @return ResolvingResult|null
     */
    public function getLastResult() : ?ResolvingResult
    {
        return $this->lastResult;
    }

    /**
     * @return mixed
     */
    public function getTerminateAfterRun()
    {
        return $this->terminateAfterRun;
    }

    /**
     * @param bool $terminateAfterRun
     *
     * @return Resolver
     */
    public function setTerminateAfterRun(bool $terminateAfterRun)
    {
        $this->terminateAfterRun = $terminateAfterRun;

        return $this;
    }

    /**
     * Runs the resolver
     *
     * @param int $pollingFrequency
     */
    public function run(int $pollingFrequency)
    {
        $this->sleep($pollingFrequency);

        // Handle backup of jobs
        $this->checkForBackedUpJobs();

        $job = $this->predis->lpop($this->queueKey);

        if (null !== $job && null !== ($jobData = $this->predis->get($this->getJobKey($job[1])))) {

            $job = Job::createFromArray(json_decode($jobData, true));

            // Ignore jobs that have been finished already (might happen for backed up jobs)
            // In such a case, ignore the current job and immediately run again without sleep() so it fetches
            // the next one.
            if (Job::STATUS_FINISHED === $job->getStatus()
                || Job::STATUS_FINISHED_WITH_ERRORS === $job->getStatus()
            ) {
                return $this->run(0);
            }

            // Set status to processing
            $job->setStatus(Job::STATUS_PROCESSING);

            // Increase retries
            $job->increaseRetries();

            // Store the job again
            $this->predis->setex($this->getJobKey($job->getId()), $this->ttl, json_encode($job));

            // Put on the backup list
            $this->predis->rpush($this->getBackupQueueKey(), [$job->getId()]);

            // Process
            try {
                $this->lastResult = $this->resolve($job);

            } catch (\Throwable $t) {

                $this->lastResult = new ResolvingResult($job, 2, null);

                $this->logger->error('Error during resolving process: ' . $t->getMessage(), [
                    'line'  => $t->getLine(),
                    'file'  => $t->getFile(),
                    'trace' => $t->getTrace()
                ]);

                $job->setComposerOutput($job->getComposerOutput() . PHP_EOL . 'An error occured during resolving process.');
                $job->setStatus(Job::STATUS_FINISHED_WITH_ERRORS);
            }

            // Finished
            $this->predis->setex($this->getJobKey($job->getId()), $this->ttl, json_encode($job));
            $this->logger->info('Finished working on job ' . $job->getId());

            if ($this->terminateAfterRun) {
                $this->terminate();
            }
        }
    }

    /**
     * Terminates the process
     */
    public function terminate()
    {
        // @codeCoverageIgnoreStart
        $this->logger->info('Terminating worker process now.');
        exit;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Sleeps for n seconds
     *
     * @param int $seconds
     */
    public function sleep(int $seconds)
    {
        // @codeCoverageIgnoreStart
        sleep($seconds);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Check for backed up jobs and put them back on the real queue if needed.
     */
    private function checkForBackedUpJobs()
    {
        $job = $this->predis->lpop($this->getBackupQueueKey());

        if (null === $job) {
            return;
        }

        if (null === ($jobData = $this->predis->get($this->getJobKey($job[1])))) {
            return;
        }

        $job = Job::createFromArray(json_decode($jobData, true));

        // We now have a job of the backup queue
        // We check for max retries and processing start time now
        // If max retries is not exceeded and start time is younger than
        // what we set as maximum, we put it back on the backup queue so the
        // next worker does the same thing again.

        // Seconds
        $dtStart = $job->getProcessingStartTime();

        // Handle null case (ignore the job)
        if (null === $dtStart) {
            return;
        }

        $dtNow = new \DateTime();
        $dtStart->add(new \DateInterval('PT' . $this->secondsToWaitBeforeRetry . 'S'));

        // Maybe still some worker working on it, check later (put back on the back up list)
        // at the very end (rpush)
        if ($dtNow < $dtStart) {
            $this->predis->rpush($this->getBackupQueueKey(), [$job->getId()]);
            return;
        }

        // Max retries
        if ($job->getRetries() >= $this->maxRetriesPerJob) {
            // max retries exceeded: noop which means the job doesn't get back on any
            // list and is thus ignored
            // no need to delete the job details as they will get deleted by
            // redis when the TTL expires
            return;
        }

        // Real back up functionality because we now put it back on the real queue
        // on the first position (lpush)
        $this->predis->lpush($this->queueKey, [$job->getId()]);
    }

    /**
     * Resolves a given job.
     *
     * @param Job   $job
     *
     * @return ResolvingResult
     * @throws \Exception
     */
    private function resolve(Job $job) : ResolvingResult
    {
        // Create the composer.json in a temporary jobs directory where we
        // work on
        $jobDir         = $this->jobsDir . '/' . $job->getId();
        $composerJson   =  $jobDir . '/' . 'composer.json';
        $composerLock   =  $jobDir . '/' . 'composer.lock';

        $fs = new Filesystem();
        $fs->dumpFile($composerJson, $job->getComposerJson());

        // Set working environment
        chdir($jobDir);
        putenv('COMPOSER_HOME=' . $jobDir);
        putenv('COMPOSER=' . $composerJson);

        // Run installer
        $io = $this->getIO($job);
        $installer = $this->getInstaller($io, $job);
        $out = null !== $this->mockRunResult ? $this->mockRunResult : $installer->run();

        // Only fetch the composer.lock if the result is fine
        if (0 === $out) {
            $lockContent = (string) (null !== $this->mockComposerLock)
                ? $this->mockComposerLock
                : file_get_contents($composerLock);

            $job->setComposerLock($lockContent)
                    ->setStatus(Job::STATUS_FINISHED);
        } else {
            $job->setStatus(Job::STATUS_FINISHED_WITH_ERRORS);
        }

        $job->setComposerOutput($job->getComposerOutput() . PHP_EOL . 'Finished Composer Cloud resolving.');

        $this->logger->info('Resolved job ' . $job->getId());

        // Remove job dir
        $fs->remove($jobDir);

        return new ResolvingResult($job, $out, $installer);
    }

    /**
     * Get the installer.
     *
     * @param JobIO $io
     * @param Job   $job
     *
     * @return Installer
     */
    private function getInstaller(JobIO $io, Job $job)
    {
        // Plugins are always disabled for security reasons
        $composer = Factory::create($io, null, true);

        $composer->getInstallationManager()->addInstaller(new Installer\NoopInstaller());

        // General settings
        $installer = Installer::create($io, $composer)
            ->setUpdate(true) // Update
            ->setDryRun(false) // Disable dry run
            ->setWriteLock(true) // Still write the lock file
            ->setVerbose(true) // Always verbose for composer. Verbosity is managed on the JobIO
            ->setDevMode(true) // Default is true, use --no-dev to disable
            ->setDumpAutoloader(false)
            ->setExecuteOperations(false)
        ;

        // Check if additional installed packages are provided
        $extra = $composer->getPackage()->getExtra();
        $extra = array_key_exists('composer-resolver', $extra) ? $extra['composer-resolver'] : null;

        if (null !== $extra) {
            if (isset($extra['installed-repository'])) {
                $additionalInstalledRepo = new ArrayRepository();
                $versionParser = new VersionParser();

                foreach ($extra['installed-repository'] as $package => $version) {

                    try {
                        $constraint = $versionParser->parseConstraints($version);
                        $additionalInstalledRepo->addPackage(
                            new Package(
                                $package,
                                $version,
                                $constraint->getPrettyString()
                            )
                        );
                    } catch (\Exception $e) {
                        // Ignore silently and continue with other packages
                        // This should not happen anyway, validate the data
                        // before the job is even added to the queue
                    }
                }

                $installer->setAdditionalInstalledRepository($additionalInstalledRepo);
            }
        }

        // Job specific options
        $options = $job->getComposerOptions();
        $args    = (array) ((!isset($options['args'])) ? [] : $options['args']);
        $options = (array) ((!isset($options['options'])) ? [] : $options['options']);

        // Args: packages
        if (isset($args['packages']) && is_array($args['packages']) && 0 !== count($args['packages'])) {
            $installer->setUpdateWhitelist($args['packages']);
        }

        // Options: prefer-source
        if (isset($options['prefer-source'])) {
            $installer->setPreferSource($options['prefer-source']);
        }

        // Options: prefer-dist
        if (isset($options['prefer-dist'])) {
            $installer->setPreferDist($options['prefer-dist']);
        }

        // Options: no-dev
        if (isset($options['no-dev']) && true === $options['no-dev']) {
            $installer->setDevMode(false);
        }

        // Options: no-suggest
        if (isset($options['no-suggest']) && true === $options['no-suggest']) {
            $installer->setSkipSuggest(true);
        }

        // Options: prefer-stable
        if (isset($options['prefer-stable'])) {
            $installer->setPreferStable($options['prefer-stable']);
        }

        // Options: prefer-lowest
        if (isset($options['prefer-lowest'])) {
            $installer->setPreferLowest($options['prefer-lowest']);
        }

        return $installer;
    }

    /**
     * Gets the IO based on job settings.
     *
     * @param Job   $job
     *
     * @return JobIO
     */
    private function getIO(Job $job)
    {
        $predis    = $this->predis;
        $ttl       = $this->ttl;
        $options   = $job->getComposerOptions();
        $options   = (array) ((!isset($options['options'])) ? [] : $options['options']);
        $verbosity = isset($options['verbosity']) ? $options['verbosity'] : OutputInterface::VERBOSITY_NORMAL;

        // Basically just a dummy but it makes sure we don't have any interactivity!
        $input = new ArrayInput([]);
        $input->setInteractive(false);
        $output = new JobOutput($verbosity);
        $output->setJob($job);
        $output->setOnUpdate(function(Job $job) use ($predis, $ttl) {
            $predis->setex($this->getJobKey($job->getId()), $ttl, json_encode($job));
        });

        if (isset($options['ansi']) && true == $options['ansi']) {
            $output->setDecorated(true);
        }

        if (isset($options['no-ansi']) && true == $options['no-ansi']) {
            $output->setDecorated(false);
        }

        $io = new JobIO($input, $output, new HelperSet());

        if (isset($options['profile']) && true == $options['profile']) {
            $io->enableDebugging(microtime(true));
        }

        return $io;
    }

    /**
     * Get the job key.
     *
     * @param string $jobId
     *
     * @return string
     */
    private function getJobKey(string $jobId) : string
    {
        return $this->queueKey . ':jobs:' . $jobId;
    }

    /**
     * @return string
     */
    private function getBackupQueueKey() : string
    {
        return $this->queueKey . '_backup';
    }
}
