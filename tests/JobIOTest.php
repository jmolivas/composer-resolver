<?php

namespace Toflar\ComposerResolver\Test;

use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
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
            ->with(
                $this->equalTo('test'),
                $this->isTrue(),
                $this->equalTo(OutputInterface::VERBOSITY_NORMAL)
            );

        $mock->expects($this->once())
            ->method('writeError')
            ->with(
                $this->equalTo('msg'),
                $this->isFalse(),
                $this->equalTo(OutputInterface::VERBOSITY_VERY_VERBOSE)
            );
        /** @var JobIO $mock */
        $mock->overwrite('test', true, null, OutputInterface::VERBOSITY_NORMAL);
        $mock->overwriteError('msg', false, null, OutputInterface::VERBOSITY_VERY_VERBOSE);
    }
}
