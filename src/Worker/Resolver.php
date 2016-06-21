<?php

namespace Toflar\ComposerResolver\Worker;

use Composer\Factory;
use Composer\Installer;
use Composer\IO\NullIO;
use Toflar\ComposerResolver\Job;

class Resolver
{
    /**
     *
     */
    private function runNext()
    {
        $io = new NullIO();
        $composer = Factory::create($io, null, true);
        $composer->getInstallationManager()->addInstaller(new Installer\NoopInstaller());
        $install = Installer::create($io, $composer);
        $install->setDumpAutoloader(false);
        $install->run();

    }

    public function resolve(Job $job)
    {
        var_dump($job->getAsArray());
    }
}
