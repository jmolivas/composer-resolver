<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Toflar\ComposerResolver\Event\PostActionEvent;
use Toflar\ComposerResolver\EventListener\CheckInvalidJsonSubscriber;
use Toflar\ComposerResolver\EventListener\QueueLengthLimitSubscriber;
use Toflar\ComposerResolver\Queue;

/**
 * Class CheckInvalidJsonSubscriberTest
 *
 * @package Toflar\ComposerResolver\Tests\EventListener
 */
class CheckInvalidJsonSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $subscriber = new CheckInvalidJsonSubscriber();

        $this->assertInstanceOf('Toflar\ComposerResolver\EventListener\CheckInvalidJsonSubscriber', $subscriber);
    }

    public function testGetSubscribedEvents()
    {
        $this->assertArrayHasKey(PostActionEvent::EVENT_NAME, CheckInvalidJsonSubscriber::getSubscribedEvents());
    }

    public function testNoResponseIfValidJson()
    {
        $request = new Request([], [], [], [], [], [], '{"i-am-perfectly-valid": "json"}');

        $event = new PostActionEvent();
        $event->setRequest($request);
        $subscriber = new CheckInvalidJsonSubscriber();

        $subscriber->onPostAction($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testResponseIfInvalidJson()
    {
        $request = new Request([], [], [], [], [], [], 'invalid-json');

        $event = new PostActionEvent();
        $event->setRequest($request);
        $subscriber = new CheckInvalidJsonSubscriber();

        $subscriber->onPostAction($event);

        $this->assertSame(400, $event->getResponse()->getStatusCode());
        $this->assertSame('Your composer.json does not contain valid json content.', $event->getResponse()->getContent());
    }
}
