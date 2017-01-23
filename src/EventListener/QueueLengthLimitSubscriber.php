<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Toflar\ComposerResolver\Event\PostActionEvent;
use Toflar\ComposerResolver\Queue;

class QueueLengthLimitSubscriber implements EventSubscriberInterface
{
    /**
     * @var Queue
     */
    private $queue;

    /**
     * @var int
     */
    private $workers;

    /**
     * @var int
     */
    private $maxFactor;

    /**
     * QueueLengthLimitListener constructor.
     *
     * @param Queue $queue
     * @param int   $workers   Number of workers
     * @param int   $maxFactor Defines maximum jobs allowed on queue by a factor ($workers * $maxFactor)
     */
    public function __construct(Queue $queue, int $workers, int $maxFactor)
    {
        $this->queue = $queue;
        $this->workers = $workers;
        $this->maxFactor = $maxFactor;
    }

    /**
     * @param PostActionEvent $event
     */
    public function onPostAction(PostActionEvent $event)
    {
        // Check maximum allowed on queue
        $maximum = (int) $this->workers * $this->maxFactor;

        if ($this->queue->getLength() >= $maximum) {
            $event->setResponse(
                new Response(
                    'Maximum number of jobs reached. Try again later.',
                    503
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
