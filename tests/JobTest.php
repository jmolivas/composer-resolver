<?php

namespace Toflar\ComposerResolver\Tests;


use Symfony\Component\Console\Output\OutputInterface;
use Toflar\ComposerResolver\Job;

class JobTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $job = new Job('foobar', Job::STATUS_PROCESSING, 'foobar');
        $this->assertInstanceOf('Toflar\ComposerResolver\Job', $job);
    }

    public function testSettersAndGetters()
    {
        $job = new Job('foobar', Job::STATUS_PROCESSING, 'composerJson');

        $this->assertSame('foobar', $job->getId());
        $this->assertSame(Job::STATUS_PROCESSING, $job->getStatus());
        $this->assertSame('composerJson', $job->getComposerJson());

        $job->setComposerOptions(['options']);
        $this->assertSame(['options'], $job->getComposerOptions());

        $job->setStatus(Job::STATUS_FINISHED_WITH_ERRORS);
        $this->assertSame(Job::STATUS_FINISHED_WITH_ERRORS, $job->getStatus());

        $job->setComposerJson('newComposerJson');
        $this->assertSame('newComposerJson', $job->getComposerJson());
    }

    public function testGetAsArray()
    {
        $job = new Job('foobar', Job::STATUS_PROCESSING, 'composerJson');
        $job->setComposerLock('test');

        $this->assertSame([
            'id'                => 'foobar',
            'status'            => Job::STATUS_PROCESSING,
            'composerJson'      => 'composerJson',
            'composerLock'      => 'test',
            'composerOutput'    => null,
            'composerOptions'   => [],
        ], $job->getAsArray());
    }

    public function testCreateFromArray()
    {
        $array = [
            'id'                => 'foobar',
            'status'            => Job::STATUS_PROCESSING,
            'composerJson'      => 'composerJson',
            'composerLock'      => 'test',
            'composerOutput'    => 'composer-output',
            'composerOptions'   => ['options'],
        ];

        $job = Job::createFromArray($array);

        $this->assertSame('foobar', $job->getId());
        $this->assertSame('test', $job->getComposerLock());
        $this->assertSame(['options'], $job->getComposerOptions());
        $this->assertSame($array, $job->getAsArray());
    }

    public function testJsonSerialize()
    {
        $array = [
            'id'                => 'foobar',
            'status'            => Job::STATUS_PROCESSING,
            'composerJson'      => 'composerJson',
            'composerLock'      => 'test',
            'composerOutput'    => 'composer-output',
            'composerOptions'   => ['options'],
        ];

        $job = Job::createFromArray($array);

        $this->assertSame('{"id":"foobar","status":"processing","composerJson":"composerJson","composerLock":"test","composerOutput":"composer-output","composerOptions":["options"]}', json_encode($job));
    }

    /**
     * @dataProvider createComposerOptionsFromCommandLineArguments
     */
    public function testCreateComposerOptionsFromCommandLineArguments($arguments, $expected)
    {
        $options = Job::createComposerOptionsFromCommandLineArguments($arguments);

        $this->assertEquals($expected, $options);
    }

    public function createComposerOptionsFromCommandLineArguments()
    {
        return [
                [
                    'my-vendor/my-package -vvv --profile --no-suggest',
                    [
                        'args' => [
                            'packages' => ['my-vendor/my-package']
                        ],
                        'options' => [
                            'prefer-source' => false,
                            'prefer-dist'   => false,
                            'no-dev'        => false,
                            'no-suggest'    => true,
                            'verbosity'     => OutputInterface::VERBOSITY_DEBUG,
                            'prefer-stable' => false,
                            'prefer-lowest' => false,
                            'ansi'          => false,
                            'no-ansi'       => false,
                            'profile'       => true,
                        ],
                    ],
                ],
                [
                    '-vv --no-dev --prefer-stable',
                    [
                        'args' => [
                            'packages' => []
                        ],
                        'options' => [
                            'prefer-source' => false,
                            'prefer-dist'   => false,
                            'no-dev'        => true,
                            'no-suggest'    => false,
                            'verbosity'     => OutputInterface::VERBOSITY_VERY_VERBOSE,
                            'prefer-stable' => true,
                            'prefer-lowest' => false,
                            'ansi'          => false,
                            'no-ansi'       => false,
                            'profile'       => false,
                        ],
                    ],
                ],
                [
                    '-v --ansi',
                    [
                        'args' => [
                            'packages' => []
                        ],
                        'options' => [
                            'prefer-source' => false,
                            'prefer-dist'   => false,
                            'no-dev'        => false,
                            'no-suggest'    => false,
                            'verbosity'     => OutputInterface::VERBOSITY_VERBOSE,
                            'prefer-stable' => false,
                            'prefer-lowest' => false,
                            'ansi'          => true,
                            'no-ansi'       => false,
                            'profile'       => false,
                        ],
                    ],
                ],
                [
                    '',
                    [
                        'args' => [
                            'packages' => []
                        ],
                        'options' => [
                            'prefer-source' => false,
                            'prefer-dist'   => false,
                            'no-dev'        => false,
                            'no-suggest'    => false,
                            'verbosity'     => OutputInterface::VERBOSITY_NORMAL,
                            'prefer-stable' => false,
                            'prefer-lowest' => false,
                            'ansi'          => false,
                            'no-ansi'       => false,
                            'profile'       => false,
                        ],
                    ],
                ],
        ];
    }
}
