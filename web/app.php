<?php

require_once __DIR__ . '/../vendor/autoload.php';

use \Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
$app['composer-resolver'] = new \Toflar\ComposerResolver\Resolver();

$app->post('/jobs', function(Request $request) use ($app) {
   return $app['composer-resolver']->post($request);
});

$app->get('/jobs/{jobId}', function ($jobId) use ($app) {
    return $app['composer-resolver']->get($jobId);
});

$app->run();
