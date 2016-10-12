<?php

$app = require_once __DIR__ . '/../app.php';

// Controller
$app->register(new Silex\Provider\ServiceControllerServiceProvider());

$app['jobs.controller'] = function() use ($app) {
    return new \Toflar\ComposerResolver\Controller\JobsController(
        $app['predis'],
        $app['url_generator'],
        $app['logger'],
        $app['redis.jobs.queueKey'],
        $app['redis.jobs.ttl']
    );
};

$app->post('/jobs', 'jobs.controller:postAction')
    ->bind('jobs_post');
$app->get('/jobs/{jobId}', 'jobs.controller:getAction')
    ->bind('jobs_get');
$app->get('/jobs/{jobId}/composerLock', 'jobs.controller:getComposerLockAction')
    ->bind('jobs_get_composer_lock');
$app->get('/jobs/{jobId}/composerOutput', 'jobs.controller:getComposerOutputAction')
    ->bind('jobs_get_composer_output');

// Run app
$app->run();
