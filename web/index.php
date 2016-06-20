<?php

$app = require_once __DIR__ . '/../app.php';

$app->register(new Silex\Provider\ServiceControllerServiceProvider());

$app['jobs.controller'] = function() use ($app) {
    return new \Toflar\ComposerResolver\Controller\JobsController($app['predis']);
};

$app->post('/jobs', 'jobs.controller:postAction');
$app->get('/jobs/{jobId}', 'jobs.controller:getAction');

$app->run();
