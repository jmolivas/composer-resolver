<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = new Silex\Application();

// Console
$app->register(new Knp\Provider\ConsoleServiceProvider(), array(
    'console.name'              => 'Composer Resolver Console',
    'console.version'           => '1.0.0',
    'console.project_directory' => __DIR__
));

// Redis
$app->register(new Predis\Silex\ClientServiceProvider(), [
    'predis.parameters' => 'redis:6379',
    'predis.options'    => [
        'prefix'  => 'composer-resolver:',
        'profile' => '3.2',
    ],
]);

$app['redis.jobs.queueKey']               = 'env(COMPOSER-RESOLVER-JOBS-QUEUE-KEY)';
$app['redis.jobs.workerPollingFrequency'] = 'env(COMPOSER-RESOLVER-POLLING-FREQUENCY)';
$app['redis.jobs.ttl']                    = 'env(COMPOSER-RESOLVER-JOBS-TTL)';
$app['redis.jobs.atpj']                   = 'env(COMPOSER-RESOLVER-JOBS-ATPJ)';
$app['redis.jobs.workers']                = 'env(COMPOSER-RESOLVER-WORKERS)';

// Define defaults if env vars are not set
$app['env(COMPOSER-RESOLVER-JOBS-QUEUE-KEY)']    = 'jobs-queue';
$app['env(COMPOSER-RESOLVER-POLLING-FREQUENCY)'] = 5;
$app['env(COMPOSER-RESOLVER-JOBS-TTL)']          = 600;
$app['env(COMPOSER-RESOLVER-JOBS-ATPJ)']         = 30;
$app['env(COMPOSER-RESOLVER-WORKERS)']           = 1;

// Log everything to stout
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => 'php://stdout',
));

// Resolver
$app['composer-resolver'] = new \Toflar\ComposerResolver\Worker\Resolver(
    $app['predis'],
    $app['logger'],
    __DIR__ . '/jobs',
    $app['redis.jobs.queueKey'],
    $app['redis.jobs.ttl']
);

return $app;
