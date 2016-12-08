<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests\Worker;

use Composer\Installer;
use Monolog\Logger;
use Predis\Client;
use Psr\Log\LoggerInterface;
use Toflar\ComposerResolver\Job;
use Toflar\ComposerResolver\JobIO;
use Toflar\ComposerResolver\Worker\Resolver;

class ResolverTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $resolver = new Resolver(
            $this->createMock(Client::class),
            $this->createMock(LoggerInterface::class),
            __DIR__,
            'whatever',
            5
        );
        $this->assertInstanceOf('Toflar\ComposerResolver\Worker\Resolver', $resolver);
    }

    public function testTerminateAfterRun()
    {
        $queueKey = 'whatever';
        $pollingFrequency = 5;
        $jobData = [
            'id' => 'foobar.id',
            'status' => Job::STATUS_PROCESSING,
            'composerJson' => '{very invalid stuff which will force an exception thrown}',
        ];

        $resolver = $this->getResolver(
            $this->getRedis($queueKey, $pollingFrequency, $jobData),
            $logger = $this->createMock(Logger::class),
            __DIR__,
            $queueKey,
            5,
            true // this is the key for this test
        );

        $this->assertTrue($resolver->getTerminateAfterRun());

        $resolver->run($pollingFrequency);
    }

    public function testLogsOnExceptionDuringRun()
    {
        $queueKey = 'whatever';
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
            $this->getRedis($queueKey, $pollingFrequency, $jobData),
            $logger,
            __DIR__,
            $queueKey,
            5
        );

        $resolver->run($pollingFrequency);
    }

    public function testUnsuccessfulRun()
    {
        $installerRef = null;
        $jobRef = null;
        $queueKey = 'whatever';
        $pollingFrequency = 5;
        $jobData = [
            'id' => 'foobar.id',
            'status' => Job::STATUS_PROCESSING,
            'composerJson' => '{"name":"whatever/whatever","description":"whatever","config":{"platform":{"php":"7.0.11"}}}',
        ];

        $resolver = $this->getResolver(
            $this->getRedis($queueKey, $pollingFrequency, $jobData),
            $this->getLogger($installerRef, $jobRef),
            __DIR__,
            $queueKey,
            5
        );

        $resolver->setMockRunResult(1);
        $resolver->run($pollingFrequency);

        /** @var Job $jobRef */
        $this->assertSame(Job::STATUS_FINISHED_WITH_ERRORS, $jobRef->getStatus());
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
        $installerRef = null;
        $jobRef = null;
        $queueKey = 'whatever';
        $pollingFrequency = 5;

        $resolver = $this->getResolver(
            $this->getRedis($queueKey, $pollingFrequency, $jobData),
            $this->getLogger($installerRef, $jobRef),
            __DIR__,
            $queueKey,
            5
        );

        $resolver->setMockRunResult(0);
        $resolver->setMockComposerLock('composer-lock-result');
        $resolver->run($pollingFrequency);

        // Test the installer values by using reflection as unfortunately
        // a lot of variables do not have any getter method
        $io = null;

        /** @var Installer $installerRef */
        foreach ($this->getPropertiesOfClassIncludingParents($installerRef) as $k => $v) {
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

        /** @var Job $jobRef */
        $this->assertSame(Job::STATUS_FINISHED, $jobRef->getStatus());
        $this->assertSame('composer-lock-result', $jobRef->getComposerLock());
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

    private function getRedis($queueKey, $pollingFrequency, $jobData)
    {
        $mock = $this->createMock(Client::class);
        $mock->expects($this->at(0))
            ->method('__call')
            ->with(
                $this->equalTo('blpop'),
                $this->callback(function($args) use ($queueKey, $pollingFrequency) {
                    try {
                        $this->assertSame($queueKey, $args[0]);
                        $this->assertSame($pollingFrequency, $args[1]);
                        return true;
                    } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                        return false;
                    }
                })
            )
            ->willReturn(['whatever', $jobData['id']])
        ;

        $mock->expects($this->at(1))
            ->method('__call')
            ->with(
                $this->equalTo('get'),
                $this->callback(function($args) use ($jobData, $queueKey) {
                    try {
                        $this->assertSame($queueKey . ':jobs:' . $jobData['id'], $args[0]);
                        return true;
                    } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                        return false;
                    }
                })
            )
            ->willReturn(json_encode($jobData))
        ;

        return $mock;
    }

    /**
     * @return Resolver
     */
    private function getResolver($redis, $logger, $jobsDir, $queueKey, $ttl, $shouldTerminate = false)
    {
        $mock = $this->getMockBuilder(Resolver::class)
            ->setConstructorArgs([$redis, $logger, $jobsDir, $queueKey, $ttl])
            ->setMethods(['terminate'])
            ->getMock();

        $mock->expects($shouldTerminate ? $this->once() : $this->never())
            ->method('terminate');

        /** @var Resolver $mock */
        $mock->setTerminateAfterRun($shouldTerminate);

        return $mock;
    }

    private function getLogger(&$installerRef, &$jobRef)
    {
        $mock = $this->createMock(LoggerInterface::class);

        $mock->expects($this->any())
            ->method('debug')
            ->with(
                $this->equalTo('Resolved job.'),
                $this->callback(function($args) use (&$installerRef, &$jobRef) {
                    $installerRef = $args['installer'];
                    $jobRef = $args['job'];
                    return true;
                })
            )
        ;

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
