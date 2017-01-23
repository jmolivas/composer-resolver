<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests\Worker;

use Composer\Installer;
use Monolog\Logger;
use Predis\Client;
use Psr\Log\LoggerInterface;
use Toflar\ComposerResolver\Job;
use Toflar\ComposerResolver\JobIO;
use Toflar\ComposerResolver\Queue;
use Toflar\ComposerResolver\Worker\Resolver;

class ResolverTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $resolver = new Resolver(
            $this->createMock(Queue::class),
            $this->createMock(LoggerInterface::class),
            __DIR__
        );
        $this->assertInstanceOf('Toflar\ComposerResolver\Worker\Resolver', $resolver);
    }

    public function testRunIgnoresIfNoJobFound()
    {
        $queue = $this->createMock(Queue::class);

        $queue
            ->expects($this->once())
            ->method('getNextJob')
            ->willReturn(null);

        // Test updateJob won't be called
        $queue
            ->expects($this->never())
            ->method('updateJob');

        $resolver = $this->getResolver(
            $queue,
            $logger = $this->createMock(Logger::class),
            __DIR__
        );

        $resolver->run(5);
    }

    public function testTerminateAfterRun()
    {
        $pollingFrequency = 5;
        $jobData = [
            'id' => 'foobar.id',
            'status' => Job::STATUS_PROCESSING,
            'composerJson' => '{very invalid stuff which will force an exception thrown}',
        ];

        $resolver = $this->getResolver(
            $this->getQueue($pollingFrequency, $jobData),
            $logger = $this->createMock(Logger::class),
            __DIR__,
            true // this is the key for this test
        );

        $this->assertTrue($resolver->getTerminateAfterRun());

        $resolver->run($pollingFrequency);
    }

    public function testLogsOnExceptionDuringRun()
    {
        $pollingFrequency = 5;
        $jobData = [
            'id' => 'foobar.id',
            'status' => Job::STATUS_PROCESSING,
            'composerJson' => '{very invalid stuff which will force an exception thrown}',
        ];
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringStartsWith('Error during resolving process:'),
                $this->callback(function($array) {
                    return array_key_exists('line', $array)
                        && array_key_exists('file', $array)
                        && array_key_exists('trace', $array);
                })
            );

        $resolver = $this->getResolver(
            $this->getQueue($pollingFrequency, $jobData),
            $logger,
            __DIR__
        );

        $resolver->run($pollingFrequency);
    }

    public function testUnsuccessfulRun()
    {
        $pollingFrequency = 5;
        $jobData = [
            'id' => 'foobar.id',
            'status' => Job::STATUS_PROCESSING,
            'composerJson' => '{"name":"whatever/whatever","description":"whatever","config":{"platform":{"php":"7.0.11"}}}',
        ];

        $resolver = $this->getResolver(
            $this->getQueue($pollingFrequency, $jobData),
            $this->createMock(LoggerInterface::class),
            __DIR__
        );

        $resolver->setMockRunResult(1);
        $resolver->run($pollingFrequency);
        $result = $resolver->getLastResult();

        $this->assertSame(Job::STATUS_FINISHED_WITH_ERRORS, $result->getJob()->getStatus());
    }

    /**
     * Does not test the outcome of composer itself as composer has its dedicated
     * tests. We do test if the worker does set the correct settings on the installer
     * and if it behaves correctly itself.
     *
     * @dataProvider successfulRunDataProvider
     */
    public function testSuccessfulRun($jobData, $installerAssertionProperties, $shouldDebugEnabled, $shouldBeDecorated)
    {
        $pollingFrequency = 5;

        $resolver = $this->getResolver(
            $this->getQueue($pollingFrequency, $jobData),
            $this->createMock(LoggerInterface::class),
            __DIR__
        );

        $resolver->setMockRunResult(0);
        $resolver->setMockComposerLock('composer-lock-result');
        $resolver->run($pollingFrequency);
        $result = $resolver->getLastResult();

        // Test the installer values by using reflection as unfortunately
        // a lot of variables do not have any getter method
        $io = null;

        foreach ($this->getPropertiesOfClassIncludingParents($result->getInstaller()) as $k => $v) {
            if (in_array($k, array_keys($installerAssertionProperties))) {

                if ('not-null' == $installerAssertionProperties[$k]) {
                    $this->assertNotNull($v);
                } else {
                    $this->assertSame(
                        $installerAssertionProperties[$k],
                        $v
                    );
                }
            }

            if ('io' === $k) {
                $io = $v;
            }
        }

        // Assert startTime
        /* @var JobIO $io */
        $ioProperties = $this->getPropertiesOfClassIncludingParents($io);

        if ($shouldDebugEnabled) {
            $this->assertNotNull($ioProperties['startTime']);
        } else {
            $this->assertNull($ioProperties['startTime']);
        }

        // Assert decorated (ansi)
        $this->assertSame($shouldBeDecorated, $io->getOutput()->isDecorated());

        $this->assertSame(Job::STATUS_FINISHED, $result->getJob()->getStatus());
        $this->assertSame('composer-lock-result', $result->getJob()->getComposerLock());
    }

    public function successfulRunDataProvider()
    {
        return [
            'Test default settings' => [
                [
                    'id' => 'foobar.id',
                    'status' => Job::STATUS_PROCESSING,
                    'composerJson' => '{"name":"whatever/whatever","description":"whatever","config":{"platform":{"php":"7.0.11"}}}',
                ],
                [
                    'preferSource'                  => false,
                    'preferDist'                    => false,
                    'devMode'                       => true,
                    'skipSuggest'                   => false,
                    'preferStable'                  => false,
                    'preferLowest'                  => false,
                    'updateWhitelist'               => null,
                    'additionalInstalledRepository' => null,
                ],
                null,
                false
            ],
            'Test update white list ' => [
                [
                    'id' => 'foobar.id',
                    'status' => Job::STATUS_PROCESSING,
                    'composerJson' => '{"name":"whatever/whatever","description":"whatever","config":{"platform":{"php":"7.0.11"}}}',
                    'composerOptions' => [
                        'args' => [
                            'packages' => ['package/one']
                        ],
                        'options' => []
                    ]
                ],
                [
                    'preferSource'                  => false,
                    'preferDist'                    => false,
                    'devMode'                       => true,
                    'skipSuggest'                   => false,
                    'preferStable'                  => false,
                    'preferLowest'                  => false,
                    'updateWhitelist'               => ['package/one' => 0],
                    'additionalInstalledRepository' => null,
                ],
                null,
                false
            ],
            'Test all other composer related options' => [
                [
                    'id' => 'foobar.id',
                    'status' => Job::STATUS_PROCESSING,
                    'composerJson' => '{"name":"whatever/whatever","description":"whatever","config":{"platform":{"php":"7.0.11"}}}',
                    'composerOptions' => [
                        'args' => [
                            'packages' => ['package/one']
                        ],
                        'options' => [
                            'prefer-source' => true,
                            'prefer-dist'   => true,
                            'no-dev'        => true,
                            'no-suggest'    => true,
                            'prefer-stable' => true,
                            'prefer-lowest' => true,
                        ]
                    ]
                ],
                [
                    'preferSource'                  => true,
                    'preferDist'                    => true,
                    'devMode'                       => false,
                    'skipSuggest'                   => true,
                    'preferStable'                  => true,
                    'preferLowest'                  => true,
                    'updateWhitelist'               => ['package/one' => 0],
                    'additionalInstalledRepository' => null,
                ],
                null,
                false
            ],
            'Test console output related options' => [
                [
                    'id' => 'foobar.id',
                    'status' => Job::STATUS_PROCESSING,
                    'composerJson' => '{"name":"whatever/whatever","description":"whatever","config":{"platform":{"php":"7.0.11"}}}',
                    'composerOptions' => [
                        'options' => [
                            'profile' => true,
                            'ansi'    => true,
                        ]
                    ]
                ],
                [
                    'preferSource'                  => false,
                    'preferDist'                    => false,
                    'devMode'                       => true,
                    'skipSuggest'                   => false,
                    'preferStable'                  => false,
                    'preferLowest'                  => false,
                    'updateWhitelist'               => null,
                    'additionalInstalledRepository' => null,
                ],
                true,
                true
            ],
            'Test no-ansi console output option' => [
                [
                    'id' => 'foobar.id',
                    'status' => Job::STATUS_PROCESSING,
                    'composerJson' => '{"name":"whatever/whatever","description":"whatever","config":{"platform":{"php":"7.0.11"}}}',
                    'composerOptions' => [
                        'options' => [
                            'no-ansi' => true,
                        ]
                    ]
                ],
                [
                    'preferSource'                  => false,
                    'preferDist'                    => false,
                    'devMode'                       => true,
                    'skipSuggest'                   => false,
                    'preferStable'                  => false,
                    'preferLowest'                  => false,
                    'updateWhitelist'               => null,
                    'additionalInstalledRepository' => null,
                ],
                null,
                false
            ],
            'Test additional repository' => [
                [
                    'id' => 'foobar.id',
                    'status' => Job::STATUS_PROCESSING,
                    'composerJson' => '{"name":"whatever","description":"whatever","config":{"platform":{"php":"7.0.11"}},"extra":{"composer-resolver":{"installed-repository":{"my\/package":"1.0.0"}}}}',
                    'composerOptions' => [
                        'options' => [
                            'no-ansi' => true,
                        ]
                    ]
                ],
                [
                    'preferSource'                  => false,
                    'preferDist'                    => false,
                    'devMode'                       => true,
                    'skipSuggest'                   => false,
                    'preferStable'                  => false,
                    'preferLowest'                  => false,
                    'updateWhitelist'               => null,
                    'additionalInstalledRepository' => 'not-null',
                ],
                null,
                false
            ],
        ];
    }

    private function getQueue($pollingFrequency, $jobData)
    {
        $job = Job::createFromArray($jobData);
        $mock = $this->createMock(Queue::class);

        $mock->expects($this->any())
            ->method('getNextJob')
            ->with($pollingFrequency)
            ->willReturn($job);

        return $mock;
    }

    /**
     * @return Resolver
     */
    private function getResolver($queue, $logger, $jobsDir, $shouldTerminate = false)
    {
        $mock = $this->getMockBuilder(Resolver::class)
            ->setConstructorArgs([$queue, $logger, $jobsDir])
            ->setMethods(['terminate'])
            ->getMock();

        $mock->expects($shouldTerminate ? $this->once() : $this->never())
            ->method('terminate');

        /** @var Resolver $mock */
        $mock->setTerminateAfterRun($shouldTerminate);

        return $mock;
    }

    private function getPropertiesOfClassIncludingParents($instance)
    {
        $properties = [];
        try {
            $reflection = new \ReflectionClass($instance);
            do {
                /* @var $p \ReflectionProperty */
                foreach ($reflection->getProperties() as $property) {
                    $property->setAccessible(true);
                    $properties[$property->getName()] = $property->getValue($instance);
                }
            } while ($reflection = $reflection->getParentClass());
        } catch (\ReflectionException $e) { }

        return $properties;
    }
}
