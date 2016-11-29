<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = new \Toflar\ComposerResolver\Application();

// Redis
$app->register(new Predis\Silex\ClientServiceProvider(), [
    'predis.parameters' => 'redis:6379',
    'predis.options'    => [
        'prefix'  => 'composer-resolver:',
        'profile' => '3.2',
    ],
]);

$app['redis.jobs.queueKey']               = $app->env('COMPOSER_RESOLVER_JOBS_QUEUE_KEY', 'jobs_queue');
$app['redis.jobs.workerPollingFrequency'] = $app->env('COMPOSER_RESOLVER_POLLING_FREQUENCY', 1, 'int');
$app['redis.jobs.ttl']                    = $app->env('COMPOSER_RESOLVER_JOBS_TTL', 600, 'int');
$app['redis.jobs.atpj']                   = $app->env('COMPOSER_RESOLVER_JOBS_ATPJ', 60, 'int');
$app['redis.jobs.maxFactor']              = $app->env('COMPOSER_RESOLVER_JOBS_MAX_FACTOR', 20, 'int');
$app['redis.jobs.workers']                = $app->env('COMPOSER_RESOLVER_WORKERS', 1, 'int');
$app['worker.terminate_after_run']        = $app->env('COMPOSER_RESOLVER_TERMINATE_AFTER_RUN', true, 'bool');

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
