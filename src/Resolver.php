<?php

namespace Toflar\ComposerResolver;


use Composer\Factory;
use Composer\Installer;
use Composer\IO\NullIO;
use Predis\Client;
use Symfony\Component\HttpFoundation\Request;

class Resolver
{
    /**
     * Redis Client
     * @var Client
     */
    private $client;

    /**
     * Resolver constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public function post(Request $request) : string
    {

        return 'you posted';
    }

    /**
     * @param string $jobId
     *
     * @return string
     */
    public function get(string $jobId) : string
    {
        return 'you wanted job: ' . $jobId;
    }

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
}
