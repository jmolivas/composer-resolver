<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = new Silex\Application();

// Redis
$app->register(new Predis\Silex\ClientServiceProvider(), [
    'predis.parameters' => 'redis:6379',
    'predis.options'    => [
        'prefix'  => 'composer-resolver:',
        'profile' => '3.2',
    ],
]);

$app['redis.jobs.queueKey'] = 'jobs-queue';
$app['redis.jobs.ttl']      = 600; // 10 Minutes by default

// Log everything to stout
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => 'php://stdout',
));

// Resolver
$app['composer-resolver'] = new \Toflar\ComposerResolver\Worker\Resolver(
    __DIR__ . '/jobs'
);

return $app;
