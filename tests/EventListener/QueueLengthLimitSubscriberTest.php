<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests\EventListener;

use Toflar\ComposerResolver\Event\PostActionEvent;
use Toflar\ComposerResolver\EventListener\QueueLengthLimitSubscriber;
use Toflar\ComposerResolver\Queue;

/**
 * Class QueueLengthLimitSubscriberTest
 *
 * @package Toflar\ComposerResolver\Tests\EventListener
 */
class QueueLengthLimitSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $subscriber = new QueueLengthLimitSubscriber(
            $this->getQueue(5),
            5,
            5
        );

        $this->assertInstanceOf('Toflar\ComposerResolver\EventListener\QueueLengthLimitSubscriber', $subscriber);
    }

    public function testGetSubscribedEvents()
    {
        $this->assertArrayHasKey(PostActionEvent::EVENT_NAME, QueueLengthLimitSubscriber::getSubscribedEvents());
    }

    public function testNoResponseIfLimitNotReached()
    {
        $event = new PostActionEvent();
        $subscriber = new QueueLengthLimitSubscriber(
            $this->getQueue(5),
            5,
            5
        );

        $subscriber->onPostAction($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testResponseIfLimitExceeded()
    {
        $event = new PostActionEvent();
        $subscriber = new QueueLengthLimitSubscriber(
            $this->getQueue(25),
            5,
            5
        );

        $subscriber->onPostAction($event);

        $this->assertSame(503, $event->getResponse()->getStatusCode());
        $this->assertSame('Maximum number of jobs reached. Try again later.', $event->getResponse()->getContent());
    }

    private function getQueue($length)
    {
        $mock = $this->createMock(Queue::class);

        $mock
            ->expects($this->any())
            ->method('getLength')
            ->willReturn($length);

        return $mock;
    }
}
