<?php


namespace Toflar\ComposerResolver\Test\Controller;

use Predis\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Toflar\ComposerResolver\Controller\JobsController;
use Toflar\ComposerResolver\Job;

class JobsControllerTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $controller = new JobsController(
            $this->getRedis(),
            $this->getUrlGenerator(),
            $this->getLogger(),
            'key',
            600,
            30,
            1
        );
        $this->assertInstanceOf('Toflar\ComposerResolver\Controller\JobsController', $controller);
    }

    /**
     * @dataProvider indexActionDataProvider
     */
    public function testIndexAction($atpj, $workers, $queueLength, array $expected)
    {
        $controller = new JobsController(
            $this->getRedis($queueLength),
            $this->getUrlGenerator(),
            $this->getLogger(),
            'key',
            600,
            $atpj,
            $workers
        );

        $response = $controller->indexAction();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals($expected, $json);
    }

    public function testPostActionWithInvalidJson()
    {
        $controller = new JobsController(
            $this->getRedis(1),
            $this->getUrlGenerator(),
            $this->getLogger(),
            'key',
            600,
            10,
            1
        );

        $request = new Request([], [], [], [], [], [], 'I am invalid json.');
        $response = $controller->postAction($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Your composer.json does not contain valid json content.', $response->getContent());
    }

    public function testPostActionWithInvalidComposerSchema()
    {
        $controller = new JobsController(
            $this->getRedis(1),
            $this->getUrlGenerator(),
            $this->getLogger(),
            'key',
            600,
            10,
            1
        );

        $request = new Request([], [], [], [], [], [], '{"I am valid":"json","but I have":"no relation to the composer","schema":{"at":"all"}}');
        $response = $controller->postAction($request);

        $this->assertSame(400, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);

        $this->assertSame('Your provided composer.json does not comply with the composer.json schema!', $json['msg']);
        $this->assertTrue(count($json['errors']) > 0);
    }

    public function testPostActionWithNoPlatformConfigProvided()
    {
        $controller = new JobsController(
            $this->getRedis(1),
            $this->getUrlGenerator(),
            $this->getLogger(),
            'key',
            600,
            10,
            1
        );

        $composerJson = [
            'name' => 'whatever',
            'description' => 'whatever'
        ];

        $request = new Request([], [], [], [], [], [], json_encode($composerJson));
        $response = $controller->postAction($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Your composer.json must provide a platform configuration (see https://getcomposer.org/doc/06-config.md#platform). Otherwise, you will not get the correct dependencies for your specific platform needs.', $response->getContent());
    }

    public function testPostActionWithValidPayloadButInvalidResolverHeader()
    {
        $controller = new JobsController(
            $this->getRedis(1),
            $this->getUrlGenerator(),
            $this->getLogger(),
            'key',
            600,
            10,
            1
        );


        $composerJson = [
            'name' => 'whatever',
            'description' => 'whatever',
            'config' => [
                'platform' => [
                    'php' => '7.0.11'
                ],
            ],
        ];

        $request = new Request([], [], [], [], [], [], json_encode($composerJson));
        $request->headers->set('Composer-Resolver-Command', 'these are complete nonsense --params');
        $response = $controller->postAction($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('You provided Composer-Resolver-Command header. The content of it is not accepted. Check the manual for the correct usage.', $response->getContent());
    }

    public function testPostActionWithValidPayload()
    {
        $processingJob = null;
        $logger = $this->getLogger();
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->equalTo('Created a new job and will push it to the queue now.'),
                $this->callback(function($args) use (&$processingJob) {
                    $processingJob = $args['job'];
                    return $processingJob instanceof Job;
                })
            );

        $redis = $this->getRedis(1);
        $redis->expects($this->exactly(2))
            ->method('__call')
            ->withConsecutive(
                // setex call
                [
                    $this->equalTo('setex'),
                    $this->callback(function($args) {
                        try {
                            $this->assertStringStartsWith('jobs:', $args[0]);
                            $this->assertInternalType('int', $args[1]);
                            $this->assertJson($args[2]);
                            return true;
                        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                            return false;
                        }
                    })
                ],
                // rpush call
                [
                    $this->equalTo('rpush'),
                    $this->callback(function($args) {
                        try {
                            $this->assertInternalType('string', $args[0]);
                            $this->assertInternalType('array', $args[1]);
                            return true;
                        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                            return false;
                        }
                    })
                ]
            );

        $routes = new RouteCollection();
        $routes->add('jobs_get', new Route('/jobs/{jobId}'));
        $urlGenerator = new UrlGenerator($routes, new RequestContext());
        
        $controller = new JobsController(
            $redis,
            $urlGenerator,
            $logger,
            'key',
            600,
            10,
            1
        );

        $composerJson = [
            'name' => 'whatever',
            'description' => 'whatever',
            'config' => [
                'platform' => [
                    'php' => '7.0.11'
                ],
            ],
            // This is needed to check if the composer.json is correctly sanitized
            'repositories' => [
                // valid one
                [
                    'type' => 'vcs',
                    'url' => 'http://whatever.com'
                ],
                // local one - invalid
                [
                    'type' => 'git',
                    'url' => '/usr/foobar/repos/project/bundle'
                ],
                // artifact one - invalid
                [
                    'type' => 'artifact',
                    'url' => './repos/artifact'
                ],
            ]
        ];

        $request = new Request([], [], [], [], [], [], json_encode($composerJson));
        $request->headers->set('Composer-Resolver-Command', '--profile -vvv --prefer-stable');

        $response = $controller->postAction($request);

        /** @var Job $processingJob */
        $this->assertInternalType('string', $processingJob->getId());
        $this->assertJson($processingJob->getComposerJson());

        $json = json_decode($processingJob->getComposerJson(), true);
        // Assert repositories
        $this->assertCount(1, $json['repositories']);
        $this->assertEquals([[
            'type' => 'vcs',
            'url' => 'http://whatever.com'
        ]], $json['repositories']);

        $this->assertSame(Job::STATUS_QUEUED, $processingJob->getStatus());
        $this->assertEquals(['args' => ['packages' => []], 'options' => [
            'prefer-source' => false,
            'prefer-dist' => false,
            'no-dev' => false,
            'no-suggest' => false,
            'prefer-stable' => true,
            'prefer-lowest' => false,
            'ansi' => false,
            'no-ansi' => false,
            'profile' => true,
            'verbosity' => 256
        ]], $processingJob->getComposerOptions());
        $this->assertTrue($response->headers->has('Location'));
        $this->assertSame('/jobs/' . $processingJob->getId(), $response->headers->get('Location'));
    }

    public function testGetActionWithInvalidJobId()
    {
        $redis = $this->getRedis(1);
        $redis->expects($this->once())
            ->method('__call')
            ->with($this->equalTo('get'))
            ->willReturn(null)
        ;

        $controller = new JobsController(
            $redis,
            $this->getUrlGenerator(),
            $this->getLogger(),
            'key',
            600,
            10,
            1
        );

        $response = $controller->getAction('nonsenseId');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Job not found.', $response->getContent());
    }

    /**
     * @dataProvider getActionDataProvider
     */
    public function testGetAction(array $jobData, $expectedStatusCode, array $expectedContent)
    {
        $redis = $this->createMock(Client::class);
        $redis->expects($this->any())
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

        $routes = new RouteCollection();
        $routes->add('jobs_get_composer_lock', new Route('/jobs/{jobId}/composerLock'));
        $routes->add('jobs_get_composer_output', new Route('/jobs/{jobId}/composerOutput'));
        $urlGenerator = new UrlGenerator($routes, new RequestContext());

        $controller = new JobsController(
            $redis,
            $urlGenerator,
            $this->getLogger(),
            'key',
            600,
            10,
            1
        );

        $response = $controller->getAction($jobData['id']);

        $this->assertSame($expectedStatusCode, $response->getStatusCode());
        $this->assertEquals($expectedContent, json_decode($response->getContent(), true));
    }

    public function testGetComposerLockActionWithInvalidJobId()
    {
        $redis = $this->getRedis(1);
        $redis->expects($this->once())
            ->method('__call')
            ->with($this->equalTo('get'))
            ->willReturn(null)
        ;

        $controller = new JobsController(
            $redis,
            $this->getUrlGenerator(),
            $this->getLogger(),
            'key',
            600,
            10,
            1
        );

        $response = $controller->getComposerLockAction('nonsenseId');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Job not found.', $response->getContent());
    }

    public function testGetComposerLockAction()
    {
        $jobData = [
            'id' => 'uniq.id',
            'status' => Job::STATUS_FINISHED,
            'composerJson' => '{"name":"foobar"}',
            'composerLock' => '{"_readme":"foobar"}',
        ];

        $redis = $this->createMock(Client::class);
        $redis->expects($this->any())
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

        $controller = new JobsController(
            $redis,
            $this->getUrlGenerator(),
            $this->getLogger(),
            'key',
            600,
            10,
            1
        );

        $response = $controller->getComposerLockAction($jobData['id']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"_readme":"foobar"}', $response->getContent());
    }

    public function testGetComposerOutputActionWithInvalidJobId()
    {
        $redis = $this->getRedis(1);
        $redis->expects($this->once())
            ->method('__call')
            ->with($this->equalTo('get'))
            ->willReturn(null)
        ;

        $controller = new JobsController(
            $redis,
            $this->getUrlGenerator(),
            $this->getLogger(),
            'key',
            600,
            10,
            1
        );

        $response = $controller->getComposerOutputAction('nonsenseId');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Job not found.', $response->getContent());
    }

    public function testGetComposerOutputAction()
    {
        $output = 'This is a nice' . PHP_EOL . 'command line output.';
        $jobData = [
            'id' => 'uniq.id',
            'status' => Job::STATUS_FINISHED,
            'composerJson' => '{"name":"foobar"}',
            'composerLock' => '{"_readme":"foobar"}',
            'composerOutput' => $output,
        ];

        $redis = $this->createMock(Client::class);
        $redis->expects($this->any())
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

        $controller = new JobsController(
            $redis,
            $this->getUrlGenerator(),
            $this->getLogger(),
            'key',
            600,
            10,
            1
        );

        $response = $controller->getComposerOutputAction($jobData['id']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($output, $response->getContent());
    }

    public function indexActionDataProvider()
    {
        return [
            [
                30,
                1,
                5,
                [
                    'approxWaitingTime' => 150,
                    'approxWaitingTimeHuman' => '2 min 30 s',
                    'numberOfJobsInQueue' => 5,
                    'numberOfWorkers' => 1,
                ]
            ],
            [
                45,
                6,
                3,
                [
                    'approxWaitingTime' => 22,
                    'approxWaitingTimeHuman' => '0 min 22 s',
                    'numberOfJobsInQueue' => 3,
                    'numberOfWorkers' => 6,
                ]
            ],
            [
                32,
                18,
                162,
                [
                    'approxWaitingTime' => 288,
                    'approxWaitingTimeHuman' => '4 min 48 s',
                    'numberOfJobsInQueue' => 162,
                    'numberOfWorkers' => 18,
                ]
            ]
        ];
    }

    public static function getActionDataProvider()
    {
        return [
            'Test processing' => [
                [
                    'id' => 'foobar.uuid',
                    'status' => Job::STATUS_PROCESSING,
                    'composerJson' => 'composerJsonContent'
                ],
                202,
                [
                    'jobId' => 'foobar.uuid',
                    'status' => Job::STATUS_PROCESSING,
                    'links' => [
                        'composerLock' => '/jobs/foobar.uuid/composerLock',
                        'composerOutput' => '/jobs/foobar.uuid/composerOutput'

                    ]
                ]
            ],
            'Test finished' => [
                [
                    'id' => 'foobar.uuid',
                    'status' => Job::STATUS_FINISHED,
                    'composerJson' => 'composerJsonContent'
                ],
                200,
                [
                    'jobId' => 'foobar.uuid',
                    'status' => Job::STATUS_FINISHED,
                    'links' => [
                        'composerLock' => '/jobs/foobar.uuid/composerLock',
                        'composerOutput' => '/jobs/foobar.uuid/composerOutput'

                    ]
                ]
            ],
        ];
    }

    private function getRedis($queueLength = 1)
    {
        $mock = $this->createMock(Client::class);

        $mock->expects($this->any())
            ->method('__call')
            ->willReturnCallback(function($method, $args) use ($queueLength) {
                if ('llen' === $method) {
                    return $queueLength;
                }
            });

        return $mock;
    }

    private function getUrlGenerator()
    {
        $mock = $this->createMock(UrlGeneratorInterface::class);
        return $mock;
    }

    private function getLogger()
    {
        $mock = $this->createMock(LoggerInterface::class);
        return $mock;
    }
}
