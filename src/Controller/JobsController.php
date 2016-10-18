<?php

namespace Toflar\ComposerResolver\Controller;

use JsonSchema\Validator;
use Predis\Client;
use Psr\Log\LoggerInterface;
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
    /**
     * @var Client
     */
    private $redis;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $queueKey;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var int
     */
    private $atpj;

    /**
     * @var int
     */
    private $workers;

    /**
     * JobsController constructor.
     *
     * @param Client                $redis
     * @param UrlGeneratorInterface $urlGenerator
     * @param LoggerInterface       $logger
     * @param string                $queueKey
     * @param int                   $ttl
     * @param int                   $atpj Average time needed per job in seconds
     * @param int                   $workers  Number of workers
     */
    public function __construct(
        Client $redis,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger,
        string $queueKey,
        int $ttl,
        int $atpj,
        int $workers
    ) {
        $this->redis        = $redis;
        $this->urlGenerator = $urlGenerator;
        $this->logger       = $logger;
        $this->queueKey     = $queueKey;
        $this->ttl          = $ttl;
        $this->atpj         = $atpj;
        $this->workers      = $workers;
    }

    /**
     * Index request. Gives information about the system.
     *
     * @return Response
     */
    public function indexAction() : Response
    {
        $numberOfJobsInQueue = $this->redis->llen($this->queueKey);
        $numbersOfWorkers    = $this->workers;
        $approxWaitingTime   = $numberOfJobsInQueue * $this->atpj / $numbersOfWorkers;

        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@$approxWaitingTime");
        $humanReadable = $dtF->diff($dtT)->format('%i min %s s');

        return new JsonResponse([
            'approxWaitingTime'      => $approxWaitingTime,
            'approxWaitingTimeHuman' => $humanReadable,
            'numberOfJobsInQueue'    => $numberOfJobsInQueue,
            'numberOfWorkers'        => $numbersOfWorkers
        ]);
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

        if (null === json_decode($composerJson)) {
            return new Response(
                'Your composer.json does not contain valid json content.',
                400
            );
        }

        $composerJson = $this->sanitizeComposerJson($composerJson);

        $errors = $this->validateComposerJsonSchema($composerJson);
        if (0 !== count($errors)) {
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
        $jobId = uniqid('', true);
        $job   = new Job($jobId, Job::STATUS_QUEUED, $composerJson);

        // Check for job options
        if ($request->headers->has('Composer-Resolver-Command')) {
            try {
                $options = Job::createComposerOptionsFromCommandLineArguments(
                    $request->headers->get('Composer-Resolver-Command')
                );
                $job->setComposerOptions($options);
            } catch (\Exception $e) {
                return new Response(
                    'You provided Composer-Resolver-Command header. The content of it is not accepted. Check the manual for the correct usage.',
                    400
                );
            }
        }

        $this->logger->debug('Created a new job and will push it to the queue now.', [
            'job' => $job
        ]);

        $this->redis->setex('jobs:' . $job->getId(), $this->ttl, json_encode($job));
        $this->redis->rpush($this->queueKey, [$job->getId()]);

        return new JsonResponse(
            $this->prepareResponseData($job),
            201,
            ['Location' => $this->urlGenerator->generate('jobs_get', [
                'jobId' => $job->getId()
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
        $job = $this->fetchJob($jobId);

        if (null === $job) {
            return new Response('Job not found.', 404);
        }

        $httpStatus = Job::STATUS_FINISHED === $job->getStatus() ? 200 : 202;
        $data       = $this->prepareResponseData($job);

        // Add locations for composer.lock and output
        $data['links'] = [
            'composerLock'  => $this->urlGenerator->generate('jobs_get_composer_lock', [
                'jobId' => $job->getId()
            ]),
            'composerOutput'  => $this->urlGenerator->generate('jobs_get_composer_output', [
                'jobId' => $job->getId()
            ]),
        ];

        return new JsonResponse(
            $data,
            $httpStatus
        );
    }

    /**
     * Returns the composer.lock for a given job id.
     *
     * @param string $jobId
     *
     * @return Response
     */
    public function getComposerLockAction(string $jobId) : Response
    {
        $job = $this->fetchJob($jobId);

        if (null === $job) {
            return new Response('Job not found.', 404);
        }

        return new JsonResponse($job->getComposerLock());
    }

    /**
     * Returns the composer output for a given job id.
     *
     * @param string $jobId
     *
     * @return Response
     */
    public function getComposerOutputAction(string $jobId) : Response
    {
        $job = $this->fetchJob($jobId);

        if (null === $job) {
            return new Response('Job not found.', 404);
        }

        return new Response($job->getComposerOutput());
    }

    /**
     * Fetch a job.
     *
     * @param string $jobId
     *
     * @return null|Job
     */
    private function fetchJob(string $jobId)
    {
        $jobData = $this->redis->get('jobs:' . $jobId);

        if (null === $jobData) {

            return null;
        }

        return Job::createFromArray(json_decode($jobData, true));
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
        $composerJsonData   = json_decode($composerJson, true);

        // Check for presence of platform config
        if (!is_array($composerJsonData['config']['platform'])) {

            return false;
        }

        return true;
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
     * Prepare the default job response array.
     *
     * @param Job $job
     *
     * @return array
     */
    private function prepareResponseData(Job $job) : array
    {
        return [
            'jobId'   => $job->getId(),
            'status'  => $job->getStatus()
        ];
    }
}
