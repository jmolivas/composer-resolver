<?php

$app = require_once __DIR__ . '/../app.php';

while(true) {

    /** @var \Toflar\ComposerResolver\Worker\Resolver $resolver */
    $resolver =  $app['composer-resolver'];
    $resolver->run($app['redis.jobs.workerPollingFrequency']);
}
