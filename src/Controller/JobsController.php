<?php

namespace Toflar\ComposerResolver\Controller;

use JsonSchema\Validator;
use Predis\Client;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Toflar\ComposerResolver\Job;

/**
 * Class JobsController
 *
 * @package Toflar\ComposerResolver\Controller
 * @author  Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class JobsController
{
    private $redis;
    private $urlGenerator;
    private $queueKey;
    private $ttl;

    /**
     * JobsController constructor.
     *
     * @param Client                $redis
     * @param UrlGeneratorInterface $urlGenerator
     * @param string                $queueKey
     * @param int                   $ttl
     */
    public function __construct(
        Client $redis,
        UrlGeneratorInterface $urlGenerator,
        string $queueKey,
        int $ttl
    ) {
        $this->redis        = $redis;
        $this->urlGenerator = $urlGenerator;
        $this->queueKey     = $queueKey;
        $this->ttl          = $ttl;
    }

    /**
     * Post a composer.json file which will then get a job created for.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function postAction(Request $request) : Response
    {
        $composerJson = $request->getContent();
        $errors = $this->validateComposerJsonSchema($composerJson);
        if (0 !== $errors) {
            return new JsonResponse([
                'msg'       => 'Your provided composer.json does not comply with the composer.json schema!',
                'errors'    => $errors
            ], 400);
        }

        if (!$this->validatePlatformConfig($composerJson)) {
            return new Response(
                'Your composer.json must provide a platform configuration (see https://getcomposer.org/doc/06-config.md#platform). Otherwise, you will not get the correct dependencies for your specific platform needs.',
                400
            );
        }

        // Create the job
        $jobId = uniqid();
        $job   = new Job($jobId, Job::STATUS_QUEUED, $composerJson);
        $this->redis->setex('jobs:' . $job->getJobId(), $this->ttl, json_encode($job));

        return new JsonResponse(
            $this->prepareResponseData($job),
            201,
            ['Location' => $this->urlGenerator->generate('jobs_get', [
                'jobId' => $job->getJobId()
            ])]
        );
    }

    /**
     * Returns the information for a given job id.
     *
     * @param string $jobId
     *
     * @return Response
     */
    public function getAction(string $jobId) : Response
    {
        $jobData = $this->redis->get('jobs:' . $jobId);
        if (null === $jobData) {
            return new Response('Job not found.', 404);
        }

        $job = Job::createFromArray(json_decode($jobData, true));
        $status = Job::STATUS_FINISHED === $job->getStatus() ? 200 : 202;

        return new JsonResponse(
            $this->prepareResponseData($job),
            $status
        );
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
        $schemaData         = json_decode(file_get_contents($schemaFile));
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
     * As the composer resolver resolves a composer.json for a different project
     * the provided composer.json file MUST include the platform configuration
     * otherwise dependencies will be resolved completely pointless.
     *
     * @param string $composerJson
     *
     * @return bool
     */
    private function validatePlatformConfig(string $composerJson) : bool
    {
        $composerJsonData   = json_decode($composerJson);

        // Check for presence of platform config
        if (!is_array($composerJsonData['config']['platform'])) {

            return false;
        }

        return true;
    }

    /**
     * Prepare the default job response array.
     *
     * @param Job $job
     *
     * @return array
     */
    private function prepareResponseData(Job $job) : array
    {
        return [
            'jobId'     => $job->getJobId(),
            'status'    => $job->getStatus()
        ];
    }
}
