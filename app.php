<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = new Silex\Application();

// Env closure
$getEnvClosure = function ($key, $default = null) {
    return function() use ($key, $default) {
        $envValue = getenv($key);

        if (false !== $envValue) {
            return $envValue;
        }

        return $default;
    };
};

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

$app['redis.jobs.queueKey']               = $getEnvClosure('COMPOSER_RESOLVER_JOBS_QUEUE_KEY', 'jobs_queue');
$app['redis.jobs.workerPollingFrequency'] = $getEnvClosure('COMPOSER_RESOLVER_POLLING_FREQUENCY', 5);
$app['redis.jobs.ttl']                    = $getEnvClosure('COMPOSER_RESOLVER_JOBS_TTL', 600);
$app['redis.jobs.atpj']                   = $getEnvClosure('COMPOSER_RESOLVER_JOBS_ATPJ', 30);
$app['redis.jobs.workers']                = $getEnvClosure('COMPOSER_RESOLVER_WORKERS', 1);

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
