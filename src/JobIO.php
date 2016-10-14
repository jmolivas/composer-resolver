<?php

namespace Toflar\ComposerResolver;


use Composer\IO\IOInterface;
use Composer\IO\NullIO;

/**
 * Class JobIO
 *
 * @package Toflar\ComposerResolver
 * @author  Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class JobIO extends NullIO implements IOInterface
{
    private $job;
    private $onUpdate;
    private $verbosity;

    /**
     * JobBridgeInput constructor.
     *
     * @param Job      $job
     * @param callable $onUpdate
     */
    public function __construct(Job $job, Callable $onUpdate)
    {
        $this->job      = $job;
        $this->onUpdate = $onUpdate;

        $this->verbosity = self::NORMAL;
    }

    /**
     * Get the job.
     *
     * @return Job
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * @return int
     */
    public function getVerbosity()
    {
        return $this->verbosity;
    }

    /**
     * @param int $verbosity
     */
    public function setVerbosity($verbosity)
    {
        $this->verbosity = $verbosity;
    }

    /**
     * {@inheritDoc}
     */
    public function write($messages, $newline = true, $verbosity = self::NORMAL)
    {
        $this->appendToOutput($messages, $newline, $verbosity);
    }

    /**
     * {@inheritDoc}
     */
    public function writeError($messages, $newline = true, $verbosity = self::NORMAL)
    {
        $this->appendToOutput($messages, $newline, $verbosity);
    }

    /**
     * {@inheritDoc}
     */
    public function overwrite($messages, $newline = true, $size = 80, $verbosity = self::NORMAL)
    {
        $this->appendToOutput($messages, $newline, $verbosity);
    }

    /**
     * {@inheritDoc}
     */
    public function overwriteError($messages, $newline = true, $size = 80, $verbosity = self::NORMAL)
    {
        $this->appendToOutput($messages, $newline, $verbosity);
    }

    /**
     * Append to output.
     *
     * @param string|array  $messages
     * @param bool          $newline
     * @param int           $verbosity
     */
    private function appendToOutput($messages, $newline, $verbosity)
    {
        if ($verbosity > $this->verbosity) {
            return;
        }

        if (!is_array($messages)) {
            $messages = [$messages];
        }

        $currentOutput = $this->job->getComposerOutput();

        foreach ($messages as $msg) {
            $currentOutput .= $msg;
        }

        if (true === $newline) {
            $currentOutput .= "\n";
        }

        $this->job->setComposerOutput($currentOutput);

        $onUpdate = $this->onUpdate;
        if (is_callable($onUpdate)) {
            $onUpdate();
        }
    }
}
