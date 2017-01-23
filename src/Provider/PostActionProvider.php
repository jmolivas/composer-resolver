<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\EventListenerProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Toflar\ComposerResolver\EventListener\QueueLengthLimitSubscriber;

class PostActionProvider implements ServiceProviderInterface, EventListenerProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $app)
    {
        $app['listener.queue_length_limit'] = function (Container $app) {
            return new QueueLengthLimitSubscriber(
                $app['queue'],
                $app['redis.jobs.workers'],
                $app['redis.jobs.maxFactor']
            );
        };
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addSubscriber($app['listener.queue_length_limit']);
    }
}
