<?php

require_once __DIR__ . '/vendor/autoload.php';

$fetchEnvVar = function($key, $default) {
    if (false === ($value = getenv($key))) {
        return $default;
    }

    return $value;
};

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

$app['redis.jobs.queueKey']               = $fetchEnvVar('COMPOSER-RESOLVER-JOBS-QUEUE-KEY', 'jobs-queue');
$app['redis.jobs.workerPollingFrequency'] = $fetchEnvVar('COMPOSER-RESOLVER-POLLING-FREQUENCY', 5);
$app['redis.jobs.ttl']                    = $fetchEnvVar('COMPOSER-RESOLVER-JOBS-TTL', 600);
$app['redis.jobs.atpj']                   = $fetchEnvVar('COMPOSER-RESOLVER-JOBS-ATPJ', 30);
$app['redis.jobs.workers']                = $fetchEnvVar('COMPOSER-RESOLVER-WORKERS', 1);

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
