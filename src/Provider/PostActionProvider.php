<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\EventListenerProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Toflar\ComposerResolver\EventListener\CheckInvalidJsonSubscriber;
use Toflar\ComposerResolver\EventListener\QueueLengthLimitSubscriber;
use Toflar\ComposerResolver\EventListener\SanitizeComposerJsonSubscriber;
use Toflar\ComposerResolver\EventListener\ValidateComposerJsonSchemaSubscriber;
use Toflar\ComposerResolver\EventListener\ValidatePlatformConfigSubscriber;

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
        $app['listener.check_invalid_json'] = function (Container $app) {
            return new CheckInvalidJsonSubscriber();
        };
        $app['listener.sanitize_composer_json'] = function (Container $app) {
            return new SanitizeComposerJsonSubscriber();
        };
        $app['listener.validate_composer_json_schema'] = function (Container $app) {
            return new ValidateComposerJsonSchemaSubscriber();
        };
        $app['listener.validate_platform_config'] = function (Container $app) {
            return new ValidatePlatformConfigSubscriber();
        };
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addSubscriber($app['listener.queue_length_limit']);
        $dispatcher->addSubscriber($app['listener.check_invalid_json']);
        $dispatcher->addSubscriber($app['listener.sanitize_composer_json']);
        $dispatcher->addSubscriber($app['listener.validate_composer_json_schema']);
        $dispatcher->addSubscriber($app['listener.validate_platform_config']);
    }
}
