<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\EventListener;

use Composer\Semver\VersionParser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Toflar\ComposerResolver\Event\PostActionEvent;

class ValidateExtrasSubscriber implements EventSubscriberInterface
{
    /**
     * @param PostActionEvent $event
     */
    public function onPostAction(PostActionEvent $event)
    {
        if (!$this->validateExtras($event->getRequest()->getContent())) {
            $event->setResponse(
                new Response(
                    'Your composer.json does not provide a valid configuration for the extras definition for the key "composer-resolver".',
                    400
                )
            );

            $event->stopPropagation();
        }
    }


    /**
     * You can provide additional data for the composer resolver via the extra
     * section of the composer.json. This has to be valid though.
     *
     * @param string $composerJson
     *
     * @return bool
     */
    private function validateExtras(string $composerJson) : bool
    {
        $composerJsonData = json_decode($composerJson, true);

        if (!isset($composerJsonData['extra'])
            || !isset($composerJsonData['extra']['composer-resolver'])
            || !isset($composerJsonData['extra']['composer-resolver']['installed-repository'])
        ) {

            return true;
        }

        $extra = $composerJsonData['extra']['composer-resolver'];

        // Validate "installed-repository"
        $versionParser = new VersionParser();

        foreach ((array) $extra['installed-repository'] as $package => $version) {
            try {
                $versionParser->parseConstraints($version);
            } catch (\Exception $e) {

                return false;
            }
        }

        return true;
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
