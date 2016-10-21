<?php

namespace Toflar\ComposerResolver\Test\Worker;

use Composer\Installer;
use Monolog\Logger;
use Predis\Client;
use Psr\Log\LoggerInterface;
use Toflar\ComposerResolver\Job;
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

        $resolver = new Resolver(
            $this->getRedis($queueKey, $pollingFrequency, $jobData),
            $logger,
            __DIR__,
            $queueKey,
            5
        );

        $resolver->run($pollingFrequency);
    }

    /**
     * Does not test the outcome of composer itself as composer has its dedicated
     * tests. We do test if the worker does set the correct settings on the installer
     * and if it behaves correctly itself.
     *
     * @dataProvider successfulRunDataProvider
     */
    public function testSuccessfulRun($jobData, $installerAssertionProperties)
    {
        $installerRef = null;
        $queueKey = 'whatever';
        $pollingFrequency = 5;

        $resolver = new Resolver(
            $this->getRedis($queueKey, $pollingFrequency, $jobData),
            $this->getLogger($installerRef),
            __DIR__,
            $queueKey,
            5
        );

        $resolver->setMockRunResult(0);
        $resolver->run($pollingFrequency);

        // Test the installer values by using reflection as unfortunately
        // a lot of variables do not have any getter method

        /** @var Installer $installerRef */
        $reflection = new \ReflectionClass($installerRef);

        foreach ($reflection->getProperties() as $property) {
            if (in_array($property->getName(), array_keys($installerAssertionProperties))) {
                $property->setAccessible(true);
                $this->assertSame(
                    $installerAssertionProperties[$property->getName()],
                    $property->getValue($installerRef)
                );
            }
        }
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
                    'preferSource'      => false,
                    'preferDist'        => false,
                    'devMode'           => true,
                    'skipSuggest'       => false,
                    'preferStable'      => false,
                    'preferLowest'      => false,
                    'updateWhitelist'   => null
                ]
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
                    'preferSource'      => false,
                    'preferDist'        => false,
                    'devMode'           => true,
                    'skipSuggest'       => false,
                    'preferStable'      => false,
                    'preferLowest'      => false,
                    'updateWhitelist'   => ['package/one' => 0]
                ]
            ],
            'Test all other options' => [
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
                    'preferSource'      => true,
                    'preferDist'        => true,
                    'devMode'           => false,
                    'skipSuggest'       => true,
                    'preferStable'      => true,
                    'preferLowest'      => true,
                    'updateWhitelist'   => ['package/one' => 0]
                ]
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
                $this->callback(function($args) use ($jobData) {
                    try {
                        $this->assertSame('jobs:' . $jobData['id'], $args[0]);
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

    private function getLogger(&$installerRef)
    {
        $mock = $this->createMock(LoggerInterface::class);

        $mock->expects($this->any())
            ->method('debug')
            ->with(
                $this->equalTo('Resolved job.'),
                $this->callback(function($args) use (&$installerRef) {
                    $installerRef = $args['installer'];
                    return true;
                })
            )
        ;

        return $mock;
    }
}
