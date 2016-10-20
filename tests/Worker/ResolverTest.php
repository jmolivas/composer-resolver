<?php

namespace Toflar\ComposerResolver\Test\Worker;

use Composer\Installer;
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

    /**
     * Does not test the outcome of composer itself as composer has its dedicated
     * tests. We do test if the worker does set the correct settings on the installer
     * and if it behaves correctly itself.
     *
     * @dataProvider runDataProvider
     */
    public function testRun($pollingFrequency, $ttl, $jobData, $runResult, $installerAssertionProperties)
    {
        $installerRef = null;
        $queueKey = 'whatever';

        $resolver = new Resolver(
            $this->getRedis($queueKey, $pollingFrequency, $ttl, $jobData),
            $this->getLogger($installerRef),
            __DIR__,
            $queueKey,
            5
        );

        $resolver->setMockRunResult($runResult);
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

    public function runDataProvider()
    {
        return [
            'Test default settings' => [
                5, 5,
                [
                    'id' => 'foobar.id',
                    'status' => Job::STATUS_PROCESSING,
                    'composerJson' => '{"name":"whatever/whatever","description":"whatever","config":{"platform":{"php":"7.0.11"}}}',
                ],
                0,
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
                5, 5,
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
                0,
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
                5, 5,
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
                0,
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

    private function getRedis($queueKey, $pollingFrequency, $ttl, $jobData)
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
