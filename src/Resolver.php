<?php

namespace Toflar\ComposerResolver;


use Symfony\Component\HttpFoundation\Request;

class Resolver
{
    public function post(Request $request)
    {
        return 'you posted';
    }

    public function get($jobId)
    {
        return 'you wanted job: ' . $jobId;
    }
}
