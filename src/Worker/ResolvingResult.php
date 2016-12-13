<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Worker;

use Composer\Installer;
use Toflar\ComposerResolver\Job;

/**
 * Class ResolvingResult
 *
 * @package Toflar\ComposerResolver\Worker
 */
class ResolvingResult
{
    /**
     * @var Job
     */
    private $job;

    /**
     * @var int
     */
    private $outCode;

    /**
     * @var Installer
     */
    private $installer;

    /**
     * ResolvingResult constructor.
     *
     * @param Job       $job
     * @param int       $outCode
     * @param Installer $installer
     */
    public function __construct(Job $job, int $outCode, Installer $installer = null)
    {
        $this->job = $job;
        $this->outCode = $outCode;
        $this->installer = $installer;
    }

    /**
     * @return Job
     */
    public function getJob(): Job
    {
        return $this->job;
    }

    /**
     * @return int
     */
    public function getOutCode() : int
    {
        return $this->outCode;
    }

    /**
     * @return Installer|null
     */
    public function getInstaller(): ?Installer
    {
        return $this->installer;
    }
}
