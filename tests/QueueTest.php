<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests;

use Predis\Client;
use Toflar\ComposerResolver\Job;
use Toflar\ComposerResolver\Queue;

class QueueTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $queue = $this->getQueue($this->createMock(Client::class));
        $this->assertInstanceOf('Toflar\ComposerResolver\Queue', $queue);
    }

    public function testUpdateJob()
    {
        $queueKey = 'very-different';
        $ttl = 50;
        $job = new Job('foobar', Job::STATUS_QUEUED, 'foobar');

        $client = $this->createMock(Client::class);

        $client
            ->expects($this->once())
            ->method('__call')
            ->with(
                $this->equalTo('setex'),
                $this->callback(function($args) use ($job, $queueKey, $ttl) {
                    try {
                        $this->assertSame($queueKey . ':jobs:' . $job->getId(), $args[0]);
                        $this->assertSame($ttl, $args[1]);
                        $this->assertSame(json_encode($job), $args[2]);
                        return true;
                    } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                        return false;
                    }
                })
            );

        $queue = $this->getQueue($client, $queueKey, $ttl);

        $queue->updateJob($job);
    }


    public function testAddJob()
    {
        $queueKey = 'very-different';
        $job = new Job('foobar', Job::STATUS_QUEUED, 'foobar');

        $client = $this->createMock(Client::class);

        $client
            ->expects($this->once())
            ->method('__call')
            ->with(
                $this->equalTo('rpush'),
                $this->callback(function($args) use ($job, $queueKey) {
                    try {
                        $this->assertSame($queueKey, $args[0]);
                        $this->assertSame($job->getId(), $args[1][0]);
                        return true;
                    } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                        return false;
                    }
                })
            );

        // We mock the queue because we mock the updateJob method
        // which is already tested in a separate test
        $queue = $this->getMockBuilder(Queue::class)
            ->setConstructorArgs([$client, $queueKey, 600])
            ->setMethods(['updateJob'])
            ->getMock();
        $queue->expects($this->once())
            ->method('updateJob');

        $queue->addJob($job);
    }

    public function testGetJob()
    {
        $job = new Job('foobar', Job::STATUS_QUEUED, 'foobar');

        $client = $this->createMock(Client::class);

        $client
            ->expects($this->exactly(2))
            ->method('__call')
            ->with($this->equalTo('get'))
            ->willReturnOnConsecutiveCalls(json_encode($job), null)
        ;

        $queue = $this->getQueue($client);

        $jobFromQueue = $queue->getJob('foobar');
        $this->assertSame($job->getId(), $jobFromQueue->getId());
        $this->assertSame($job->getStatus(), $jobFromQueue->getStatus());

        $jobFromQueue = $queue->getJob('i-do-not-exist');
        $this->assertNull($jobFromQueue);
    }

    public function testDeleteJob()
    {
        $job = new Job('foobar', Job::STATUS_QUEUED, 'foobar');

        $client = $this->createMock(Client::class);

        $client
            ->expects($this->exactly(2))
            ->method('__call')
            ->withConsecutive([
                    $this->equalTo('lrem'),
                    $this->callback(function($args) use ($job) {
                        try {
                            $this->assertSame('queueKey', $args[0]);
                            $this->assertSame(0, $args[1]);
                            $this->assertSame($job->getId(), $args[2]);
                            return true;
                        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                            return false;
                        }
                    })
                ], [
                    $this->equalTo('del'),
                    $this->callback(function($args) use ($job) {
                        try {
                            $this->assertSame('queueKey:jobs:' . $job->getId(), $args[0][0]);
                            return true;
                        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                            return false;
                        }
                    })
                ]
            );

        $queue = $this->getQueue($client);

        $queue->deleteJob($job);
    }

    public function testGetNextJob()
    {
        $job = new Job('foobar', Job::STATUS_QUEUED, 'foobar');
        $pollingFrequency = 10;
        $client = $this->createMock(Client::class);

        $client
            ->expects($this->exactly(2))
            ->method('__call')
            ->with(
                $this->equalTo('blpop'),
                $this->callback(function($args) {
                    try {
                        $this->assertSame('foobar', $args[0][0]);
                        $this->assertSame(10, $args[1]);
                        return true;
                    } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                        return false;
                    }
                })
            )
            ->willReturnOnConsecutiveCalls(['foobar', 'unique.id'], null)
        ;

        $queue = $this->getMockBuilder(Queue::class)
            ->setConstructorArgs([$client, 'foobar', 600])
            ->setMethods(['getJob'])
            ->getMock();

        $queue->expects($this->once())
            ->method('getJob')
            ->with('unique.id')
            ->willReturn($job);

        $result = $queue->getNextJob($pollingFrequency);

        $this->assertSame($job->getId(), $result->getId());
        $this->assertSame($job->getStatus(), $result->getStatus());

        $result = $queue->getNextJob($pollingFrequency);
        $this->assertNull($result);
    }

    public function testGetLength()
    {
        $queueKey = 'whateverKey';
        $client = $this->createMock(Client::class);

        $client
            ->expects($this->exactly(1))
            ->method('__call')
            ->with(
                $this->equalTo('llen'),
                $this->callback(function($args) use ($queueKey) {
                    try {
                        $this->assertSame($queueKey, $args[0]);
                        return true;
                    } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                        return false;
                    }
                })
            )
            ->willReturn(11)
        ;

        $queue = $this->getQueue($client, $queueKey);

        $this->assertSame(11,$queue->getLength());
    }

    private function getQueue($client, $queueKey = 'queueKey', $ttl = 600)
    {
        return new Queue(
            $client,
            $queueKey,
            $ttl
        );
    }
}
