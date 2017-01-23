<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests;

use Pimple\Container;
use Predis\Client;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Toflar\ComposerResolver\Event\PostActionEvent;
use Toflar\ComposerResolver\EventListener\CheckInvalidJsonSubscriber;
use Toflar\ComposerResolver\EventListener\QueueLengthLimitSubscriber;
use Toflar\ComposerResolver\EventListener\SanitizeComposerJsonSubscriber;
use Toflar\ComposerResolver\EventListener\ValidateComposerJsonSchemaSubscriber;
use Toflar\ComposerResolver\Provider\PostActionProvider;
use Toflar\ComposerResolver\Queue;

/**
 * Class PostActionProviderTest
 *
 * @package Toflar\ComposerResolver
 */
class PostActionProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $provider = new PostActionProvider();

        $this->assertInstanceOf('Toflar\ComposerResolver\Provider\PostActionProvider', $provider);
    }

    public function testRegister()
    {
        $app = new Container();

        $provider = new PostActionProvider();
        $provider->register($app);

        $this->assertArrayHasKey('listener.queue_length_limit', $app);
    }

    public function testSubscribe()
    {
        $app = new Container();
        $app['queue'] = new Queue(
            $this->createMock(Client::class),
            'queueKey',
            600
        );
        $app['redis.jobs.workers'] = 1;
        $app['redis.jobs.maxFactor'] = 5;
        $dispatcher = new EventDispatcher();

        $provider = new PostActionProvider();
        $provider->register($app);
        $provider->subscribe($app, $dispatcher);

        $listeners = $dispatcher->getListeners(PostActionEvent::EVENT_NAME);

        $this->assertInstanceOf(QueueLengthLimitSubscriber::class, $listeners[0][0]);
        $this->assertInstanceOf(CheckInvalidJsonSubscriber::class, $listeners[1][0]);
        $this->assertInstanceOf(SanitizeComposerJsonSubscriber::class, $listeners[2][0]);
        $this->assertInstanceOf(ValidateComposerJsonSchemaSubscriber::class, $listeners[3][0]);
    }
}
