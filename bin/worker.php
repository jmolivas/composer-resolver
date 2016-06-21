<?php

$app = require_once __DIR__ . '/../app.php';

while(true) {

    /* @var \Predis\Client $predis */
    $predis   = $app['predis'];

    /** @var \Toflar\ComposerResolver\Worker\Resolver $resolver */
    $resolver =  $app['composer-resolver'];

    $job = $predis->blpop([$app['redis.jobs.queueKey']], 5); // Only check every 5 seconds
    if (null !== $job && null !== ($jobData = $predis->get('jobs:' . $job[1]))) {
        $job = \Toflar\ComposerResolver\Job::createFromArray(json_decode($jobData, true));
        $resolver->resolve($job);
    }
}
