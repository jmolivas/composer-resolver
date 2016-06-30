<?php

namespace Toflar\ComposerResolver\Worker;

use Composer\Factory;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Symfony\Component\Filesystem\Filesystem;
use Toflar\ComposerResolver\Job;
use Toflar\ComposerResolver\JobBridgeInput;

/**
 * Class Resolver
 *
 * @package Toflar\ComposerResolver\Worker
 * @author  Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class Resolver implements ResolverInterface
{
    private $jobsDir;

    /**
     * Resolver constructor.
     *
     * @param string      $jobsDir
     */
    public function __construct(string $jobsDir)
    {
        $this->jobsDir = $jobsDir;
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
    public function resolve(Job $job, IOInterface $io) : Job
    {
        // Already a worker on it or done already.
        if (Job::STATUS_QUEUED !== $job->getStatus()) {

            return $job;
        }

        // Create the composer.json in a temporary jobs directory where we
        // work on
        $jobDir         = $this->jobsDir . '/' . $job->getId();
            $composerJson   =  $jobDir . '/' . 'composer.json';
        $composerLock   =  $jobDir . '/' . 'composer.lock';

        $fs = new Filesystem();
        $fs->dumpFile($composerJson, $job->getComposerJson());
        putenv('COMPOSER=' . $composerJson);

        // Run installer
        $installer = $this->getInstaller($io);
        $installer->run();

        // Get the composer.lock
        $job->setStatus(Job::STATUS_FINISHED)
            ->setComposerLock((string) file_get_contents($composerLock));

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
        $installer = Installer::create($io, $composer);
        $installer->setUpdate(true);
        $installer->setDryRun(false);
        $installer->setDumpAutoloader(false);
        $installer->setRunScripts(false);
        $installer->setDevMode(false);

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
        $disablePlugins = true;

        return Factory::create($io, null, $disablePlugins);
    }
}
