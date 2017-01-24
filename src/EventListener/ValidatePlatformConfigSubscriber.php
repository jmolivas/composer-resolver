<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Toflar\ComposerResolver\Event\PostActionEvent;

class ValidatePlatformConfigSubscriber implements EventSubscriberInterface
{
    /**
     * @param PostActionEvent $event
     */
    public function onPostAction(PostActionEvent $event)
    {
        $composerJsonData = json_decode($event->getRequest()->getContent(), true);

        // Check for presence of platform config
        if (!isset($composerJsonData['config']['platform']) || !is_array($composerJsonData['config']['platform'])) {

            $event->setResponse(
                new Response(
                    'Your composer.json must provide a platform configuration (see https://getcomposer.org/doc/06-config.md#platform). Otherwise, you will not get the correct dependencies for your specific platform needs.',
                    400
                )
            );

            $event->stopPropagation();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents() : array
    {
        return [
            PostActionEvent::EVENT_NAME => 'onPostAction'
        ];
    }
}
