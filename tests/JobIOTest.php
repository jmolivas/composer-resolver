<?php

namespace Toflar\ComposerResolver\Test;

use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Toflar\ComposerResolver\JobIO;

class JobIOTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $jobIO = new JobIO(
            new ArrayInput([]),
            new NullOutput(),
            new HelperSet()
        );

        $this->assertInstanceOf('Toflar\ComposerResolver\JobIO', $jobIO);
    }

    public function testOverwritesCallParent()
    {
        $mock = $this->getMockBuilder(JobIO::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['overwrite', 'overwriteError'])
            ->getMock();

        $mock->expects($this->once())
            ->method('write')
            ->withAnyParameters();

        $mock->expects($this->once())
            ->method('writeError')
            ->withAnyParameters();

        /** @var JobIO $mock */
        $mock->overwrite('test');
        $mock->overwriteError('test');
    }
}
