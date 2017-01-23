<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Toflar\ComposerResolver\Event\PostActionEvent;
use Toflar\ComposerResolver\EventListener\CheckInvalidJsonSubscriber;
use Toflar\ComposerResolver\EventListener\SanitizeComposerJsonSubscriber;


/**
 * Class SanitizeComposerJsonSubscriberTest
 *
 * @package Toflar\ComposerResolver\Tests\EventListener
 */
class SanitizeComposerJsonSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $subscriber = new SanitizeComposerJsonSubscriber();

        $this->assertInstanceOf('Toflar\ComposerResolver\EventListener\SanitizeComposerJsonSubscriber', $subscriber);
    }

    public function testGetSubscribedEvents()
    {
        $this->assertArrayHasKey(PostActionEvent::EVENT_NAME, SanitizeComposerJsonSubscriber::getSubscribedEvents());
    }

    public function testRequestIsSanitized()
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
        $subscriber = new SanitizeComposerJsonSubscriber();

        $subscriber->onPostAction($event);

        $json = json_decode($event->getRequest()->getContent(), true);

        $this->assertCount(1, $json['repositories']);
        $this->assertArrayNotHasKey('version', $json);
        $this->assertArrayNotHasKey('version_normalized', $json);
    }
}
