<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Toflar\ComposerResolver\Event\PostActionEvent;
use Toflar\ComposerResolver\Job;
use Toflar\ComposerResolver\Queue;

/**
 * Class JobsController
 *
 * @package Toflar\ComposerResolver\Controller
 * @author  Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class JobsController
{
    /**
     * @var Queue
     */
    private $queue;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

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
     * @param Queue                 $queue
     * @param UrlGeneratorInterface $urlGenerator
     * @param LoggerInterface       $logger
     * @param int                   $atpj Average time needed per job in seconds
     * @param int                   $workers Number of workers
     */
    public function __construct(
        Queue $queue,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher,
        int $atpj,
        int $workers
    ) {
        $this->queue            = $queue;
        $this->urlGenerator     = $urlGenerator;
        $this->logger           = $logger;
        $this->eventDispatcher  = $eventDispatcher;
        $this->atpj             = $atpj;
        $this->workers          = $workers;
    }

    /**
     * Index request. Gives information about the system.
     *
     * @return Response
     */
    public function indexAction() : Response
    {
        $numberOfJobsInQueue = $this->queue->getLength();
        $numbersOfWorkers    = $this->workers;
        $approxWaitingTime   = (int) ($numberOfJobsInQueue * $this->atpj / $numbersOfWorkers);

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
        $originalComposerJson = $request->getContent();

        $event = new PostActionEvent(PostActionEvent::EVENT_NAME);
        $event->setRequest($request);

        $this->eventDispatcher->dispatch(PostActionEvent::EVENT_NAME, $event);

        if (null !== ($response = $event->getResponse())) {

            return $response;
        }

        $composerJson = $request->getContent();

        // Create the job
        $jobId = uniqid('', true);
        $job   = new Job($jobId, Job::STATUS_QUEUED, $composerJson);
        $job->setOriginalComposerJson($originalComposerJson);

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

        $this->queue->addJob($job);

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
        $job = $this->queue->getJob($jobId);

        if (null === $job) {
            return new Response('Job not found.', 404);
        }

        $httpStatus = $job->isFinished() ? 200 : 202;
        $data       = $this->prepareResponseData($job);

        // Add locations for composer.lock and output
        $data['links'] = [
            'composerJson'  => $this->urlGenerator->generate('jobs_get_composer_json', [
                'jobId' => $job->getId()
            ]),
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
     * Stops a given job id.
     *
     * @param string $jobId
     *
     * @return Response
     */
    public function deleteAction(string $jobId) : Response
    {
        $job = $this->queue->getJob($jobId);

        if (null !== $job) {
            $this->queue->deleteJob($job);
        }

        return new Response('Job stopped and deleted.', 200);
    }

    /**
     * Returns the composer.json that was supplied for a given job id.
     *
     * @param string $jobId
     *
     * @return Response
     */
    public function getOriginalComposerJsonAction(string $jobId) : Response
    {
        $job = $this->queue->getJob($jobId);

        if (null === $job) {
            return new Response('Job not found.', 404);
        }

        return new JsonResponse($job->getOriginalComposerJson(), 200, [], true);
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
        $job = $this->queue->getJob($jobId);

        if (null === $job) {
            return new Response('Job not found.', 404);
        }

        return new JsonResponse($job->getComposerLock(), 200, [], true);
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
        $job = $this->queue->getJob($jobId);

        if (null === $job) {
            return new Response('Job not found.', 404);
        }

        return new Response($job->getComposerOutput());
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
