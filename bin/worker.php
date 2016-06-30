<?php

$app = require_once __DIR__ . '/../app.php';

while(true) {
    /* @var \Predis\Client $predis */
    $predis   = $app['predis'];
    $ttl      = $app['redis.jobs.ttl'];

    /** @var \Toflar\ComposerResolver\Worker\Resolver $resolver */
    $resolver =  $app['composer-resolver'];

    echo 'Worker checks for jobs in queue';

    $job = $predis->blpop([$app['redis.jobs.queueKey']], 5); // Only check every 5 seconds

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
        $job = $resolver->resolve($job, $jobIO);

        // Finished
        $predis->setex('jobs:' . $job->getId(), $ttl, json_encode($job));

        echo 'Finished working on job ' . $job->getId();
    }
}
