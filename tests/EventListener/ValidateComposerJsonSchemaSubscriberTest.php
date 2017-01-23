<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Toflar\ComposerResolver\Event\PostActionEvent;
use Toflar\ComposerResolver\EventListener\SanitizeComposerJsonSubscriber;
use Toflar\ComposerResolver\EventListener\ValidateComposerJsonSchemaSubscriber;

/**
 * Class ValidateComposerJsonSchemaSubscriberTest
 *
 * @package Toflar\ComposerResolver\Tests\EventListener
 */
class ValidateComposerJsonSchemaSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $subscriber = new ValidateComposerJsonSchemaSubscriber();

        $this->assertInstanceOf('Toflar\ComposerResolver\EventListener\ValidateComposerJsonSchemaSubscriber', $subscriber);
    }

    public function testGetSubscribedEvents()
    {
        $this->assertArrayHasKey(PostActionEvent::EVENT_NAME, ValidateComposerJsonSchemaSubscriber::getSubscribedEvents());
    }

    public function testValidComposerJson()
    {
        $composerJson = [
            'name' => 'whatever',
            'description' => 'whatever',
            'config' => [
                'platform' => [
                    'php' => '7.0.11'
                ],
            ],
            // This is needed to check if the composer.json is correctly sanitized
            'repositories' => [
                // valid one
                [
                    'type' => 'vcs',
                    'url' => 'http://whatever.com'
                ],
                // local one - invalid
                [
                    'type' => 'git',
                    'url' => '/usr/foobar/repos/project/bundle'
                ],
                // artifact one - invalid
                [
                    'type' => 'artifact',
                    'url' => './repos/artifact'
                ],
            ],
            // Check for extra
            'extra' => [
                'composer-resolver' => [
                    'installed-repository' => [
                        'my/package' => '1.0.0'
                    ]
                ]
            ]
        ];

        $request = new Request([], [], [], [], [], [], json_encode($composerJson));

        $event = new PostActionEvent();
        $event->setRequest($request);
        $subscriber = new ValidateComposerJsonSchemaSubscriber();

        $subscriber->onPostAction($event);
        
        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }


    public function testInValidComposerJson()
    {
        $composerJson = [
            'name' => 'whatever',
            'description' => 'whatever',
            'version' => '1.0.0',
            'version_normalized' => '1.0.0',
            'config' => [
                'platform' => [
                    'php' => '7.0.11'
                ],
            ],
            // This is needed to check if the composer.json is correctly sanitized
            'repositories' => [
                // valid one
                [
                    'type' => 'vcs',
                    'url' => 'http://whatever.com'
                ],
                // local one - invalid
                [
                    'type' => 'git',
                    'url' => '/usr/foobar/repos/project/bundle'
                ],
                // artifact one - invalid
                [
                    'type' => 'artifact',
                    'url' => './repos/artifact'
                ],
            ],
            // Check for extra
            'extra' => [
                'composer-resolver' => [
                    'installed-repository' => [
                        'my/package' => '1.0.0'
                    ]
                ]
            ]
        ];

        $request = new Request([], [], [], [], [], [], json_encode($composerJson));

        $event = new PostActionEvent();
        $event->setRequest($request);
        $subscriber = new ValidateComposerJsonSchemaSubscriber();

        $subscriber->onPostAction($event);

        $json = json_decode($event->getResponse()->getContent(), true);

        $this->assertTrue($event->isPropagationStopped());
        $this->assertInstanceOf(JsonResponse::class, $event->getResponse());
        $this->assertSame(400, $event->getResponse()->getStatusCode());
        $this->assertSame(
            'Your provided composer.json does not comply with the composer.json schema!',
            $json['msg']
        );
        $this->assertSame('The property version_normalized is not defined and the definition does not allow additional properties', $json['errors'][0]);
    }
}
