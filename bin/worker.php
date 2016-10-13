<?php

$app = require_once __DIR__ . '/../app.php';

while(true) {
    /* @var \Predis\Client $predis */
    $predis   = $app['predis'];
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger   = $app['logger'];
    $ttl      = $app['redis.jobs.ttl'];

    /** @var \Toflar\ComposerResolver\Worker\Resolver $resolver */
    $resolver =  $app['composer-resolver'];

    $logger->info('Worker checks for jobs in queue');

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
        } catch (\Throwable $t) {
            $logger->error('Error during resolving process: ' . $t->getMessage(), [
                'line'  => $t->getLine(),
                'file'  => $t->getFile(),
                'trace' => $t->getTrace()
            ]);
        }

        // Finished
        $predis->setex('jobs:' . $job->getId(), $ttl, json_encode($job));

        $logger->info('Finished working on job ' . $job->getId());
    }
}
