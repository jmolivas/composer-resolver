<?php

namespace Toflar\ComposerResolver;

use Symfony\Component\Console\Output\Output;

/**
 * Class JobOutput
 *
 * @author  Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class JobOutput extends Output
{
    /** @var Job */
    private $job;

    /** @var \Closure */
    private $onUpdate;

    /**
     * @return Job
     */
    public function getJob() : Job
    {
        return $this->job;
    }

    /**
     * @param Job $job
     */
    public function setJob(Job $job)
    {
        $this->job = $job;
    }

    /**
     * @return \Closure
     */
    public function getOnUpdate(): \Closure
    {
        return $this->onUpdate;
    }

    /**
     * @param \Closure $onUpdate
     */
    public function setOnUpdate(\Closure $onUpdate)
    {
        $this->onUpdate = $onUpdate;
    }

    /**
     * Writes a message to the output.
     *
     * @param string $message A message to write to the output
     * @param bool   $newline Whether to add a newline or not
     */
    protected function doWrite($message, $newline)
    {
        if (!$this->job instanceof Job) {
            return;
        }

        $currentOutput = $this->job->getComposerOutput();
        $currentOutput .= $message;

        if (true === $newline) {
            $currentOutput .= PHP_EOL;
        }

        $this->job->setComposerOutput($currentOutput);

        if (null !== $this->onUpdate) {
            $onUpdate = $this->onUpdate;
            $onUpdate($this->job);
        }
    }
}
