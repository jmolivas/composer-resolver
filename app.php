<?php

require_once __DIR__ . '/vendor/autoload.php';

$fetchEnvVar = function($key, $default) {
    if (false === ($value = getenv($key))) {
        return $default;
    }

    return $value;
};

$app = new Silex\Application();

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

// Log everything to stout
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => 'php://stdout',
));

// Resolver
$app['composer-resolver'] = new \Toflar\ComposerResolver\Worker\Resolver(
    __DIR__ . '/jobs'
);

return $app;
