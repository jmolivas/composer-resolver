<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Toflar\ComposerResolver\Event\PostActionEvent;
use Toflar\ComposerResolver\EventListener\ValidateExtrasSubscriber;

/**
 * Class ValidateExtrasSubscriberTest
 *
 * @package Toflar\ComposerResolver\Tests\EventListener
 */
class ValidateExtrasSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $subscriber = new ValidateExtrasSubscriber();

        $this->assertInstanceOf('Toflar\ComposerResolver\EventListener\ValidateExtrasSubscriber', $subscriber);
    }

    public function testGetSubscribedEvents()
    {
        $this->assertArrayHasKey(PostActionEvent::EVENT_NAME, ValidateExtrasSubscriber::getSubscribedEvents());
    }

    /**
     * @dataProvider validExtrasProvider
     */
    public function testValidExtras($extra)
    {
        $composerJson = [
            'name' => 'whatever',
            'description' => 'whatever',
            'config' => [
                'platform' => [
                    'php' => '7.0.11'
                ],
            ],
            'extra' => $extra
        ];

        $request = new Request([], [], [], [], [], [], json_encode($composerJson));

        $event = new PostActionEvent();
        $event->setRequest($request);
        $subscriber = new ValidateExtrasSubscriber();

        $subscriber->onPostAction($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testInvalidExtras()
    {
        $composerJson = [
            'name' => 'whatever',
            'description' => 'whatever',
            'config' => [
                'platform' => [
                    'php' => '7.0.11'
                ],
            ],
            'extra' => [
                'composer-resolver' => [
                    'installed-repository' => [
                        'i-am-so-wrong' => 'I am wrong'
                    ]
                ]
            ]
        ];

        $request = new Request([], [], [], [], [], [], json_encode($composerJson));

        $event = new PostActionEvent();
        $event->setRequest($request);
        $subscriber = new ValidateExtrasSubscriber();

        $subscriber->onPostAction($event);

        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame(400, $event->getResponse()->getStatusCode());
        $this->assertSame(
            'Your composer.json does not provide a valid configuration for the extras definition for the key "composer-resolver".',
            $event->getResponse()->getContent()
        );
    }

    public function validExtrasProvider()
    {
        return [
            [
                ['composer-resolver' => [
                    'installed-repository' => [
                        'my/package' => '1.0.0'
                    ]
                ]],
            ],
            [null]
        ];
    }
}
