<?php

$app = require_once __DIR__ . '/../app.php';

/** @var \Predis\Client $test */
$test = $app['predis'];
$test->pubSubLoop(function($data) {
    var_dump($data);
});
