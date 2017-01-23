<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Toflar\ComposerResolver\Event\PostActionEvent;

class PostActionEventTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $event = new PostActionEvent(PostActionEvent::EVENT_NAME);

        $this->assertInstanceOf('Toflar\ComposerResolver\Event\PostActionEvent', $event);
    }

    public function testSettersAndGetters()
    {
        $request = new Request(['foobar' => 'test']);
        $response = new Response('foobar', 204);

        $event = new PostActionEvent(PostActionEvent::EVENT_NAME);
        $event->setRequest($request);
        $event->setResponse($response);

        $this->assertSame('test', $event->getRequest()->query->get('foobar'));
        $this->assertSame('foobar', $event->getResponse()->getContent());
        $this->assertSame(204, $event->getResponse()->getStatusCode());

        $event->setResponse(null);
        $this->assertNull($event->getResponse());
    }
}
