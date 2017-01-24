<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Toflar\ComposerResolver\Event\PostActionEvent;
use Toflar\ComposerResolver\EventListener\ValidateForPackagesSubscriber;
use Toflar\ComposerResolver\Queue;

/**
 * Class ValidateForPackagesSubscriberTest
 *
 * @package Toflar\ComposerResolver\Tests\EventListener
 */
class ValidateForPackagesSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $subscriber = new ValidateForPackagesSubscriber('');

        $this->assertInstanceOf('Toflar\ComposerResolver\EventListener\ValidateForPackagesSubscriber', $subscriber);
    }

    public function testGetSubscribedEvents()
    {
        $this->assertArrayHasKey(PostActionEvent::EVENT_NAME, ValidateForPackagesSubscriber::getSubscribedEvents());
    }

    public function testNoRequireButExpressionProvided()
    {
        $expression = '"foo" in p';
        $composerJson = [
            'platform' => [
                'php' => '7.0.11'
            ],
        ];

        $request = new Request([], [], [], [], [], [], json_encode($composerJson));
        $event = new PostActionEvent();
        $event->setRequest($request);

        $subscriber = new ValidateForPackagesSubscriber($expression);

        $subscriber->onPostAction($event);

        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame(400, $event->getResponse()->getStatusCode());
        $this->assertSame(
            'Your composer.json misses certain packages in its "require" key that must be present. You are not allowed to use this service.',
            $event->getResponse()->getContent()
        );
    }

    /**
     * @dataProvider successfulExpressionsProvider
     */
    public function testNoResponseIfSuccessfulExpression($expression)
    {
        $request = $this->getRequest();

        $event = new PostActionEvent();
        $event->setRequest($request);
        $subscriber = new ValidateForPackagesSubscriber($expression);

        $subscriber->onPostAction($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    /**
     * @dataProvider unsuccessfulExpressionsProvider
     */
    public function testResponseIfUnsuccessfulExpression($expression)
    {
        $request = $this->getRequest();

        $event = new PostActionEvent();
        $event->setRequest($request);
        $subscriber = new ValidateForPackagesSubscriber($expression);

        $subscriber->onPostAction($event);

        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame(400, $event->getResponse()->getStatusCode());
        $this->assertSame(
            'Your composer.json misses certain packages in its "require" key that must be present. You are not allowed to use this service.',
            $event->getResponse()->getContent()
        );
    }

    /**
     * @dataProvider invalidExpressionsProvider
     */
    public function testResponseIfInvalidExpression($expression, $message)
    {
        $request = $this->getRequest();

        $event = new PostActionEvent();
        $event->setRequest($request);
        $subscriber = new ValidateForPackagesSubscriber($expression);

        $subscriber->onPostAction($event);

        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame(400, $event->getResponse()->getStatusCode());
        $this->assertSame(
            'The configured expression "' . $expression . '" seems to be invalid: ' . $message,
            $event->getResponse()->getContent()
        );
    }

    private function getRequest()
    {
        $composerJson = [
            'require' => [
                'package/different' => '1.*',
                'package/bundle' => '1.*',
                'providerA/one' => '1.*',
                'providerA/b' => '1.*',
            ],
        ];

        return new Request([], [], [], [], [], [], json_encode($composerJson));
    }

    public function successfulExpressionsProvider()
    {
        return [
            ['"package/bundle" in p'],
            ['"package/different" in p'],
            ['"package/different" in p || "nonsense" in p'],
        ];
    }

    public function unsuccessfulExpressionsProvider()
    {
        return [
            ['"package/must-be-present" in p'],
            ['"package/different" in p && "nonsense" in p'],
        ];
    }

    public function invalidExpressionsProvider()
    {
        return [
            ['', 'Unexpected token "end of expression" of value "" around position 1.'],
            ['([foo in p( so invalid!', 'Unclosed "(" around position 10.'],
        ];
    }
}
