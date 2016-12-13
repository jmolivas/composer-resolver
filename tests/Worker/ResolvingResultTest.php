<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests\Worker;

use Composer\Installer;
use Toflar\ComposerResolver\Job;
use Toflar\ComposerResolver\Worker\ResolvingResult;

class ResolvingResultTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $job = new Job('foobar', 'whatever', 'whatever');
        $installer = $this->createMock(Installer::class);
        $result = new ResolvingResult($job, 0, $installer);

        $this->assertInstanceOf('Toflar\ComposerResolver\Worker\ResolvingResult', $result);
    }

    public function testGetters()
    {
        $job = new Job('foobar', 'whatever', 'composerJson');
        $installer = $this->createMock(Installer::class);
        $result = new ResolvingResult($job, 0, $installer);

        $this->assertSame('foobar', $result->getJob()->getId());
        $this->assertSame('whatever', $result->getJob()->getStatus());
        $this->assertInstanceOf(Installer::class, $result->getInstaller());
        $this->assertSame($result->getOutCode(), 0);
    }
}
