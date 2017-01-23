<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Toflar\ComposerResolver\Event\PostActionEvent;

class SanitizeComposerJsonSubscriber implements EventSubscriberInterface
{
    /**
     * @param PostActionEvent $event
     */
    public function onPostAction(PostActionEvent $event)
    {
        $request = $event->getRequest();

        $composerJson = $this->sanitizeComposerJson($request->getContent());

        $request = new Request(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $composerJson
        );

        $event->setRequest($request);
    }

    /**
     * Tries to sanitize the content of the composer.json.
     * It e.g. removes version hints, invalid platform parameters etc.
     *
     * @param string $composerJson
     *
     * @return string
     */
    private function sanitizeComposerJson(string $composerJson) : string
    {
        $json = json_decode($composerJson, true);

        // Unset "composer-plugin-api" if present in platform config
        unset($json['config']['platform']['composer-plugin-api']);

        // Unset version information
        unset($json['version']);
        unset($json['version_normalized']);

        if (isset($json['repositories'])) {
            foreach ((array) $json['repositories'] as $k => $repository) {

                // Ignore local paths on repositories information
                if (isset($repository['url'])
                    && is_string($repository['url'])
                    && '/' === $repository['url'][0]
                ) {
                    unset($json['repositories'][$k]);
                }

                // Ignore artifact repositories
                if (isset($repository['type'])
                    && 'artifact' === $repository['type']
                ) {
                    unset($json['repositories'][$k]);
                }
            }
        }

        return json_encode($json);
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
