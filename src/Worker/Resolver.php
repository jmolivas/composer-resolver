<?php

namespace Toflar\ComposerResolver\Worker;

use Composer\Factory;
use Composer\Installer;
use Composer\IO\IOInterface;
use Predis\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Toflar\ComposerResolver\Job;
use Toflar\ComposerResolver\JobIO;

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
     * Runs the resolver
     *
     * @param int $pollingFrequency
     */
    public function run(int $pollingFrequency)
    {
        $predis = $this->predis;
        $ttl    = $this->ttl;
        $job    = $predis->blpop($this->queueKey, $pollingFrequency);

        if (null !== $job && null !== ($jobData = $predis->get('jobs:' . $job[1]))) {
            $job = Job::createFromArray(json_decode($jobData, true));

            // Set status to processing
            $job->setStatus(Job::STATUS_PROCESSING);
            $predis->setex('jobs:' . $job->getId(), $this->ttl, json_encode($job));

            // Create IO Bridge
            $jobIO = new JobIO($job, function() use ($predis, $job, $ttl) {
                $predis->setex('jobs:' . $job->getId(), $ttl, json_encode($job));
            });

            // Process
            try {
                $job = $this->resolve($job, $jobIO);
            } catch (\Throwable $t) {
                $this->logger->error('Error during resolving process: ' . $t->getMessage(), [
                    'line'  => $t->getLine(),
                    'file'  => $t->getFile(),
                    'trace' => $t->getTrace()
                ]);
            }

            // Finished
            $predis->setex('jobs:' . $job->getId(), $ttl, json_encode($job));

            $this->logger->info('Finished working on job ' . $job->getId());
        }
    }


    /**
     * Resolves a given job.
     *
     * @param Job         $job
     * @param IOInterface $io
     *
     * @return Job
     * @throws \Exception
     */
    private function resolve(Job $job, IOInterface $io) : Job
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
        $installer = $this->getInstaller($io);
        $out = $installer->run();

        // Only fetch the composer.lock if the result is fine
        if (0 === $out) {
            $job->setComposerLock((string) file_get_contents($composerLock))
                ->setStatus(Job::STATUS_FINISHED);
        } else {
            $job->setStatus(Job::STATUS_FINISHED_WITH_ERRORS);
        }

        // Remove job dir
        $fs->remove($jobDir);

        return $job;
    }

    /**
     * Get the installer.
     *
     * @param IOInterface $io
     *
     * @return Installer
     */
    private function getInstaller(IOInterface $io)
    {
        $composer = $this->getComposer($io);
        $composer->getInstallationManager()->addInstaller(
            new Installer\NoopInstaller());
        $installer = Installer::create($io, $composer)
            ->setUpdate(true) // Update
            ->setDryRun(true) // Dry run (= no autoload dump, no scripts)
            ->setDevMode(true) // Enable dev
            ->setWriteLock(true) // Still write the lock file
            ->setVerbose(true) // Always verbose for composer. Verbosity is managed on the JobIO
        ;

        return $installer;
    }

    /**
     * Get composer.
     *
     * @param IOInterface $io
     *
     * @return \Composer\Composer
     */
    private function getComposer(IOInterface $io)
    {
        // TODO: support extra parameters for the resolver
        $disablePlugins = false;

        // TODO: verbosity on  IO

        return Factory::create($io, null, $disablePlugins);
    }
}
