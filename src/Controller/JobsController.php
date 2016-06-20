<?php

namespace Toflar\ComposerResolver\Controller;


use Predis\Client;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class JobsController
{
    /**
     * Redis Client
     * @var Client
     */
    private $client;

    /**
     * JobsController constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function postAction(Request $request)
    {
        // Create the job
        $jobId = uniqid('composer-resolver');
        $this->client->set('jobs' . $jobId, 'foobar');
        /*$this->client->lpush('jobs', [$jobId]);*/
        $this->client->publish('jobs-notify', 'new-job');

        return new JsonResponse('hi');
    }

    public function getAction($jobId)
    {
        $data = $this->client->get('jobs-' . $jobId);
        return new JsonResponse($data);
    }
}
