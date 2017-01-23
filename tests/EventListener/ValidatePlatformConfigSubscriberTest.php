<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Toflar\ComposerResolver\Event\PostActionEvent;
use Toflar\ComposerResolver\EventListener\ValidatePlatformConfigSubscriber;

/**
 * Class ValidatePlatformConfigSubscriberTest
 *
 * @package Toflar\ComposerResolver\Tests\EventListener
 */
class ValidatePlatformConfigSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $subscriber = new ValidatePlatformConfigSubscriber();

        $this->assertInstanceOf('Toflar\ComposerResolver\EventListener\ValidatePlatformConfigSubscriber', $subscriber);
    }

    public function testGetSubscribedEvents()
    {
        $this->assertArrayHasKey(PostActionEvent::EVENT_NAME, ValidatePlatformConfigSubscriber::getSubscribedEvents());
    }

    public function testValidPlatformConfig()
    {
        $composerJson = [
            'name' => 'whatever',
            'description' => 'whatever',
            'config' => [
                'platform' => [
                    'php' => '7.0.11'
                ],
            ],
        ];

        $request = new Request([], [], [], [], [], [], json_encode($composerJson));

        $event = new PostActionEvent();
        $event->setRequest($request);
        $subscriber = new ValidatePlatformConfigSubscriber();

        $subscriber->onPostAction($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testInValidPlatformConfig()
    {
        $composerJson = [
            'name' => 'whatever',
            'description' => 'whatever',
        ];

        $request = new Request([], [], [], [], [], [], json_encode($composerJson));

        $event = new PostActionEvent();
        $event->setRequest($request);
        $subscriber = new ValidatePlatformConfigSubscriber();

        $subscriber->onPostAction($event);

        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame(400, $event->getResponse()->getStatusCode());
        $this->assertSame(
            'Your composer.json must provide a platform configuration (see https://getcomposer.org/doc/06-config.md#platform). Otherwise, you will not get the correct dependencies for your specific platform needs.',
            $event->getResponse()->getContent()
        );
    }
}
