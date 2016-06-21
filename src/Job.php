<?php

namespace Toflar\ComposerResolver;

class Job implements \JsonSerializable
{
    const STATUS_QUEUED     = 'queued';
    const STATUS_PROCESSING = 'processing';
    const STATUS_FINISHED   = 'finished';

    private $jobId;
    private $status;
    private $payload;

    public function __construct(
        string $jobId,
        string $status,
        string $payload
    ) {
        $this->jobId    = $jobId;
        $this->status   = $status;
        $this->payload  = $payload;
    }

    public function getJobId() : string
    {
        return $this->jobId;
    }

    public function setJobId(string $jobId) : self
    {
        $this->jobId = $jobId;

        return $this;
    }

    public function getStatus() : string
    {
        return $this->status;
    }

    public function setStatus(string $status) : self
    {
        $this->status = $status;

        return $this;
    }

    public function getPayload() : string
    {
        return $this->payload;
    }

    public function setPayload(string $payload) : string
    {
        $this->payload = $payload;
    }

    public function jsonSerialize() : array
    {
        return $this->getAsArray();
    }

    public function getAsArray() : array
    {
        return [
            'jobId'     => $this->jobId,
            'status'    => $this->status,
            'payload'   => $this->payload
        ];
    }

    public static function createFromArray(array $array) : self
    {
        return new static(
            $array['jobId'],
            $array['status'],
            $array['payload']
        );
    }
}
