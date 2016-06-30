<?php

namespace Toflar\ComposerResolver\Worker;

use Composer\IO\IOInterface;
use Toflar\ComposerResolver\Job;

/**
 * Interface ResolverInterface
 *
 * @package Toflar\ComposerResolver\Worker
 * @author  Yanick Witschi <yanick.witschi@terminal42.ch>
 */
interface ResolverInterface
{
    /**
     * Resolves a given job.
     *
     * @param Job         $job
     * @param IOInterface $io
     *
     * @return Job
     * @throws \Exception
     */
    public function resolve(Job $job, IOInterface $io) : Job;
}
