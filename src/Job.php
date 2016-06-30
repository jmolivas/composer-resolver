<?php

namespace Toflar\ComposerResolver;

/**
 * Class Job
 *
 * @package Toflar\ComposerResolver
 * @author  Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class Job implements \JsonSerializable
{
    const STATUS_QUEUED     = 'queued';
    const STATUS_PROCESSING = 'processing';
    const STATUS_FINISHED   = 'finished';

    private $id;
    private $status;
    private $composerJson;
    private $composerLock;
    private $composerOutput;

    /**
     * Job constructor.
     *
     * @param string $id
     * @param string $status
     * @param string $composerJson
     */
    public function __construct(
        string $id,
        string $status,
        string $composerJson
    ) {
        $this->id           = $id;
        $this->status       = $status;
        $this->composerJson = $composerJson;
    }

    /**
     * Gets the job id.
     *
     * @return string
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * Gets the job status.
     *
     * @return string
     */
    public function getStatus() : string
    {
        return $this->status;
    }

    /**
     * Sets the job status.
     *
     * @param string $status
     *
     * @return Job
     */
    public function setStatus(string $status) : self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get the composer.json
     *
     * @return string
     */
    public function getComposerJson() : string
    {
        return $this->composerJson;
    }

    /**
     * Set the composer.json
     *
     * @param string $composerJson
     */
    public function setComposerJson(string $composerJson) : self
    {
        $this->composerJson = $composerJson;

        return $this;
    }

    /**
     * Get the composer.lock
     *
     * @return string
     */
    public function getComposerLock() : string
    {
        return (string) $this->composerLock;
    }


    /**
     * Set the composer.lock
     *
     * @param string $composerLock
     */
    public function setComposerLock(string $composerLock) : self
    {
        $this->composerLock = $composerLock;

        return $this;
    }

    /**
     * Get the composer output
     *
     * @return string
     */
    public function getComposerOutput() : string
    {
        return (string) $this->composerOutput;
    }


    /**
     * Set the composer output
     *
     * @param string $composerOutput
     */
    public function setComposerOutput(string $composerOutput) : self
    {
        $this->composerOutput = $composerOutput;

        return $this;
    }

    /**
     * Get the job data as an array.
     *
     * @return array
     */
    public function getAsArray() : array
    {
        return [
            'id'                => $this->id,
            'status'            => $this->status,
            'composerJson'      => $this->composerJson,
            'composerLock'      => $this->composerLock,
            'composerOutput'    => $this->composerOutput,
        ];
    }

    /**
     * Implements the JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize() : array
    {
        return $this->getAsArray();
    }

    /**
     * Create a job from an array.
     *
     * @param array $array
     *
     * @return Job
     */
    public static function createFromArray(array $array) : self
    {
        $job = new static(
            $array['id'],
            $array['status'],
            $array['composerJson']
        );

        if (isset($array['composerLock'])) {
            $job->setComposerLock((string) $array['composerLock']);
        }

        if (isset($array['composerOutput'])) {
            $job->setComposerOutput((string) $array['composerOutput']);
        }

        return $job;
    }
}
