<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\EventListener;

use JsonSchema\Validator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Toflar\ComposerResolver\Event\PostActionEvent;

class ValidateComposerJsonSchemaSubscriber implements EventSubscriberInterface
{
    /**
     * @param PostActionEvent $event
     */
    public function onPostAction(PostActionEvent $event)
    {
        $request = $event->getRequest();

        $errors = $this->validateComposerJsonSchema($event->getRequest()->getContent());
        if (0 !== count($errors)) {
            $event->setResponse(
                new JsonResponse([
                    'msg'       => 'Your provided composer.json does not comply with the composer.json schema!',
                    'errors'    => $errors
                ], 400)
            );

            $event->stopPropagation();
        }

        $event->setRequest($request);
    }


    /**
     * Validates a composer.json string against the composer-schema.json
     * and returns an array of errors in case there are any or an empty
     * one if the json is valid.
     *
     * @param string $composerJson
     *
     * @return array
     */
    private function validateComposerJsonSchema(string $composerJson) : array
    {
        $errors             = [];
        $composerJsonData   = json_decode($composerJson);

        $schemaFile         = __DIR__ . '/../../vendor/composer/composer/res/composer-schema.json';
        // Prepend with file:// only when not using a special schema already (e.g. in the phar)
        if (false === strpos($schemaFile, '://')) {
            $schemaFile = 'file://' . $schemaFile;
        }
        $schemaData         = (object) ['$ref' => $schemaFile];

        $validator          = new Validator();
        $validator->check($composerJsonData, $schemaData);

        if (!$validator->isValid()) {
            foreach ((array) $validator->getErrors() as $error) {
                $errors[] = ($error['property'] ? $error['property'] . ' : ' : '') . $error['message'];
            }
        }

        return $errors;
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
