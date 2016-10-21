<?php

namespace Toflar\ComposerResolver\Tests;

use Toflar\ComposerResolver\Job;
use Toflar\ComposerResolver\JobOutput;

class JobOutputTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $jobOutput = new JobOutput();
        $this->assertInstanceOf('Toflar\ComposerResolver\JobOutput', $jobOutput);
    }

    public function testSettersAndGetters()
    {
        $jobOutput = new JobOutput();

        $onUpdate = function() {
            // I am stupid
        };
        $job = new Job('foobar', Job::STATUS_PROCESSING, 'composerJson');

        $jobOutput->setOnUpdate($onUpdate);
        $jobOutput->setJob($job);

        $this->assertSame($onUpdate, $jobOutput->getOnUpdate());
        $this->assertSame($job, $jobOutput->getJob());
    }

    public function testWrite()
    {
        $iWasCalled = false;

        $onUpdate = function() use (&$iWasCalled) {
            $iWasCalled = true;
        };
        $job = new Job('foobar', Job::STATUS_PROCESSING, 'composerJson');
        $job->setComposerOutput('output');

        $jobOutput = new JobOutput();
        $jobOutput->setJob($job);
        $jobOutput->setOnUpdate($onUpdate);

        $jobOutput->write('message', true);

        $this->assertSame('outputmessage' . "\n", $job->getComposerOutput());
        $this->assertTrue($iWasCalled);
    }

    public function testNoWriteIfNoJob()
    {
        $iWasCalled = false;

        $onUpdate = function() use (&$iWasCalled) {
            $iWasCalled = true;
        };
        $jobOutput = new JobOutput();
        $jobOutput->setOnUpdate($onUpdate);

        $jobOutput->write('message', true);

        $this->assertFalse($iWasCalled);
    }
}
