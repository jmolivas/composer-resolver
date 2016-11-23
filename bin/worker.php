<?php

$app = require_once __DIR__ . '/../app.php';

/** @var \Toflar\ComposerResolver\Worker\Resolver $resolver */
$resolver =  $app['composer-resolver'];

if (false === $app['worker.terminate_after_run']) {
    $resolver->setTerminateAfterRun(false);
}

while (true) {
    $resolver->run($app['redis.jobs.workerPollingFrequency']);
}

