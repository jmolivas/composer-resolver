<?php

declare(strict_types=1);

$app = require_once __DIR__ . '/../app.php';

// Controller
$app->register(new Silex\Provider\ServiceControllerServiceProvider());

$app['jobs.controller'] = function() use ($app) {
    return new \Toflar\ComposerResolver\Controller\JobsController(
        $app['queue'],
        $app['url_generator'],
        $app['logger'],
        $app['redis.jobs.atpj'],
        $app['redis.jobs.workers'],
        $app['redis.jobs.maxFactor']
    );
};

$app->get('/', 'jobs.controller:indexAction')
    ->bind('jobs_index');
$app->post('/jobs', 'jobs.controller:postAction')
    ->bind('jobs_post');
$app->get('/jobs/{jobId}', 'jobs.controller:getAction')
    ->bind('jobs_get');
$app->delete('/jobs/{jobId}', 'jobs.controller:deleteAction')
    ->bind('jobs_delete');
$app->get('/jobs/{jobId}/composerLock', 'jobs.controller:getComposerLockAction')
    ->bind('jobs_get_composer_lock');
$app->get('/jobs/{jobId}/composerOutput', 'jobs.controller:getComposerOutputAction')
    ->bind('jobs_get_composer_output');

// Run app
$app->run();
