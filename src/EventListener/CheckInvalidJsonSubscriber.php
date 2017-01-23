<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Toflar\ComposerResolver\Event\PostActionEvent;

class CheckInvalidJsonSubscriber implements EventSubscriberInterface
{
    /**
     * @param PostActionEvent $event
     */
    public function onPostAction(PostActionEvent $event)
    {
        if (null === json_decode($event->getRequest()->getContent())) {
            $event->setResponse(
                new Response(
                    'Your composer.json does not contain valid json content.',
                    400
                )
            );

            $event->stopPropagation();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            PostActionEvent::EVENT_NAME => 'onPostAction'
        ];
    }
}
