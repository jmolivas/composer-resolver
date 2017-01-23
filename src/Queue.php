<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver;

use Predis\Client;

class Queue
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $queueKey;

    /**
     * @var
     */
    private $ttl;

    /**
     * Queue constructor.
     *
     * @param Client $client
     * @param string $queueKey
     * @param        $ttl
     */
    public function __construct(Client $client, $queueKey, $ttl)
    {
        $this->client   = $client;
        $this->queueKey = $queueKey;
        $this->ttl      = $ttl;
    }

    /**
     * Adds a new job onto the queue.
     *
     * @param Job $job
     *
     * @return Queue
     */
    public function addJob(Job $job) : self
    {
        $this->updateJob($job);
        $this->client->rpush($this->queueKey, [$job->getId()]);

        return $this;
    }

    /**
     * Fetches a job by its id.
     *
     * @param string $jobId
     *
     * @return Job|null
     */
    public function getJob(string $jobId) : ?Job
    {
        $jobData = $this->client->get($this->getJobKey($jobId));

        if (null === $jobData) {
            return null;
        }

        return Job::createFromArray(json_decode($jobData, true));
    }

    /**
     * Deletes a job by its id.
     *
     * @param Job $job
     *
     * @return Queue
     */
    public function deleteJob(Job $job) : self
    {
        $this->client->lrem($this->queueKey, 0, $job->getId());
        $this->client->del([$this->getJobKey($job->getId())]);

        return $this;
    }

    /**
     * Get the next job from the queue. Returns null if there's none.
     *
     * @param int $pollingFrequency
     *
     * @return Job|null
     */
    public function getNextJob(int $pollingFrequency) : ?Job
    {
        $jobId = $this->client->blpop([$this->queueKey], $pollingFrequency);

        if (null === $jobId) {
            return null;
        }

        return $this->getJob($jobId[1]);
    }

    /**
     * Gets the number of jobs on the queue.
     *
     * @return int
     */
    public function getLength() : int
    {
        return (int) $this->client->llen($this->queueKey);
    }

    /**
     * @param Job $job
     *
     * @return Queue
     */
    public function updateJob(Job $job) : self
    {
        $this->client->setex(
            $this->getJobKey($job->getId()),
            $this->ttl,
            json_encode($job)
        );

        return $this;
    }

    /**
     * Get the job key.
     *
     * @param string $jobId
     *
     * @return string
     */
    private function getJobKey(string $jobId) : string
    {
        return $this->queueKey . ':jobs:' . $jobId;
    }
}
