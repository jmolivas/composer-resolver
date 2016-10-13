<?php

$app = require_once __DIR__ . '/../app.php';

$writeln = function($ln) { echo $ln . "\n"; };

while(true) {
    /* @var \Predis\Client $predis */
    $predis   = $app['predis'];
    $ttl      = $app['redis.jobs.ttl'];

    /** @var \Toflar\ComposerResolver\Worker\Resolver $resolver */
    $resolver =  $app['composer-resolver'];

    $writeln('Worker checks for jobs in queue');

    $job = $predis->blpop([$app['redis.jobs.queueKey']], $app['redis.jobs.workerPollingFrequency']);

    if (null !== $job && null !== ($jobData = $predis->get('jobs:' . $job[1]))) {
        $job = \Toflar\ComposerResolver\Job::createFromArray(json_decode($jobData, true));

        // Set status to processing
        $job->setStatus(\Toflar\ComposerResolver\Job::STATUS_PROCESSING);
        $predis->setex('jobs:' . $job->getId(), $ttl, json_encode($job));

        // Create IO Bridge
        $jobIO = new \Toflar\ComposerResolver\JobIO($job, function() use ($predis, $job, $ttl) {
            $predis->setex('jobs:' . $job->getId(), $ttl, json_encode($job));
        });

        // Process
        try {
            $job = $resolver->resolve($job, $jobIO);
        } catch (\Exception $e) {
            $writeln('Exception during resolving process: ' . $e->getMessage());
        }

        // Finished
        $predis->setex('jobs:' . $job->getId(), $ttl, json_encode($job));

        $writeln('Finished working on job ' . $job->getId());
    }
}
