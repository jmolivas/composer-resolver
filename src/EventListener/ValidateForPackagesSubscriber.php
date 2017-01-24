<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Response;
use Toflar\ComposerResolver\Event\PostActionEvent;

class ValidateForPackagesSubscriber implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private $expression;

    /**
     * ValidatePlatformConfigSubscriber constructor.
     *
     * @param string $expression
     */
    public function __construct(string $expression)
    {
        $this->expression = $expression;
    }

    /**
     * @param PostActionEvent $event
     */
    public function onPostAction(PostActionEvent $event)
    {
        $composerJsonData = json_decode($event->getRequest()->getContent(), true);

        if (!isset($composerJsonData['require']) && '' !== $this->expression) {
            $this->setResponse($event, $this->getRequireValidationMsg());
            return;
        }

        $el = new ExpressionLanguage();

        try {
            $packages = array_keys($composerJsonData['require']);
            if (!$el->evaluate($this->expression, ['p' => $packages])) {
                $this->setResponse($event, $this->getRequireValidationMsg());
                return;
            }

        } catch (\Exception $e) {
            $this->setResponse($event, $this->getExpressionInvalidMsg($e));
        }
    }

    /**
     * @param PostActionEvent $event
     * @param string          $msg
     */
    private function setResponse(PostActionEvent $event, $msg)
    {
        $event->setResponse(
            new Response($msg, 400)
        );

        $event->stopPropagation();
    }

    /**
     * @return string
     */
    private function getRequireValidationMsg() : string
    {
        return 'Your composer.json misses certain packages in its "require" key that must be present. You are not allowed to use this service.';
    }

    /**
     * @param \Exception $e
     *
     * @return string
     */
    private function getExpressionInvalidMsg(\Exception $e) : string
    {
        return sprintf('The configured expression "%s" seems to be invalid: %s',
            $this->expression,
            $e->getMessage()
        );
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
