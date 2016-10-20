<?php

namespace Toflar\ComposerResolver\Worker;

use Composer\Factory;
use Composer\Installer;
use Predis\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Toflar\ComposerResolver\Job;
use Toflar\ComposerResolver\JobIO;
use Toflar\ComposerResolver\JobOutput;

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
    private $mockRunResult = null;

    /**
     * Resolver constructor.
     *
     * @param Client          $predis
     * @param LoggerInterface $logger
     * @param string          $jobsDir
     * @param string          $queueKey
     * @param int             $ttl
     */
    public function __construct(Client $predis, LoggerInterface $logger, string $jobsDir, string $queueKey, int $ttl)
    {
        $this->predis = $predis;
        $this->logger = $logger;
        $this->jobsDir = $jobsDir;
        $this->queueKey = $queueKey;
        $this->ttl = $ttl;
    }

    /**
     * @param int $mockRunResult
     */
    public function setMockRunResult(int $mockRunResult)
    {
        $this->mockRunResult = $mockRunResult;
    }

    /**
     * Runs the resolver
     *
     * @param int $pollingFrequency
     */
    public function run(int $pollingFrequency)
    {
        $predis = $this->predis;
        $job    = $predis->blpop($this->queueKey, $pollingFrequency);

        if (null !== $job && null !== ($jobData = $predis->get('jobs:' . $job[1]))) {
            $job = Job::createFromArray(json_decode($jobData, true));

            // Set status to processing
            $job->setStatus(Job::STATUS_PROCESSING);
            $predis->setex('jobs:' . $job->getId(), $this->ttl, json_encode($job));

            // Process
            try {
                $job = $this->resolve($job);
            } catch (\Throwable $t) {
                $this->logger->error('Error during resolving process: ' . $t->getMessage(), [
                    'line'  => $t->getLine(),
                    'file'  => $t->getFile(),
                    'trace' => $t->getTrace()
                ]);
            }

            // Finished
            $predis->setex('jobs:' . $job->getId(), $this->ttl, json_encode($job));

            $this->logger->info('Finished working on job ' . $job->getId());
        }
    }

    /**
     * Resolves a given job.
     *
     * @param Job   $job
     *
     * @return Job
     * @throws \Exception
     */
    private function resolve(Job $job) : Job
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
        $out = null !== $this->mockRunResult ?: $installer->run();

        // Only fetch the composer.lock if the result is fine
        if (0 === $out) {
            $job->setComposerLock((string) file_get_contents($composerLock))
                ->setStatus(Job::STATUS_FINISHED);
        } else {
            $job->setStatus(Job::STATUS_FINISHED_WITH_ERRORS);
        }

        $this->logger->debug('Resolved job.', [
            'job' => $job,
            'installer' => $installer,
        ]);

        // Remove job dir
        $fs->remove($jobDir);

        return $job;
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
            ->setDryRun(true) // Dry run (= no autoload dump, no scripts)
            ->setWriteLock(true) // Still write the lock file
            ->setVerbose(true) // Always verbose for composer. Verbosity is managed on the JobIO
        ;

        // Job specific options
        $options = $job->getComposerOptions();
        $args    = (array) !isset($options['args']) ? [] : $options['args'];
        $options = (array) !isset($options['options']) ? [] : $options['options'];

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
        if (isset($options['no-dev'])) {
            $installer->setDevMode($options['no-dev']);
        }

        // Options: no-suggest
        if (isset($options['no-suggest'])) {
            $installer->setSkipSuggest($options['no-suggest']);
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
        $options   = (array) !isset($options['options']) ? [] : $options['options'];
        $verbosity = isset($options['verbosity']) ? $options['verbosity'] : OutputInterface::VERBOSITY_NORMAL;

        // Basically just a dummy but it makes sure we don't have any interactivity!
        $input = new ArrayInput([]);
        $input->setInteractive(false);
        $output = new JobOutput($verbosity);
        $output->setJob($job);
        $output->setOnUpdate(function(Job $job) use ($predis, $ttl) {
            $predis->setex('jobs:' . $job->getId(), $ttl, json_encode($job));
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
}
