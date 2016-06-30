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

    private $jobId;
    private $status;
    private $payload;

    /**
     * Job constructor.
     *
     * @param string $jobId
     * @param string $status
     * @param string $payload
     */
    public function __construct(
        string $jobId,
        string $status,
        string $payload
    ) {
        $this->jobId    = $jobId;
        $this->status   = $status;
        $this->payload  = $payload;
    }

    /**
     * Gets the job id.
     *
     * @return string
     */
    public function getJobId() : string
    {
        return $this->jobId;
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
     * Gets the job payload.
     *
     * @return string
     */
    public function getPayload() : string
    {
        return $this->payload;
    }

    /**
     * Sets the job payload.
     *
     * @param string $payload
     *
     * @return string
     */
    public function setPayload(string $payload) : string
    {
        $this->payload = $payload;
    }

    /**
     * Get the job data as an array.
     *
     * @return array
     */
    public function getAsArray() : array
    {
        return [
            'jobId'     => $this->jobId,
            'status'    => $this->status,
            'payload'   => $this->payload
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
        return new static(
            $array['jobId'],
            $array['status'],
            $array['payload']
        );
    }
}
