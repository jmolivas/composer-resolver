<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Tests\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Toflar\ComposerResolver\Controller\JobsController;
use Toflar\ComposerResolver\EventListener\CheckInvalidJsonSubscriber;
use Toflar\ComposerResolver\Job;
use Toflar\ComposerResolver\Queue;

class JobsControllerTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $controller = new JobsController(
            $this->getQueue(),
            $this->getUrlGenerator(),
            $this->getLogger(),
            $this->getEventDispatcher(),
            30,
            1,
            20
        );
        $this->assertInstanceOf('Toflar\ComposerResolver\Controller\JobsController', $controller);
    }

    /**
     * @dataProvider indexActionDataProvider
     */
    public function testIndexAction($atpj, $workers, $queueLength, array $expected)
    {
        $controller = new JobsController(
            $this->getQueue($queueLength),
            $this->getUrlGenerator(),
            $this->getLogger(),
            $this->getEventDispatcher(),
            $atpj,
            $workers,
            20
        );

        $response = $controller->indexAction();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals($expected, $json);
    }

    public function testPostActionWithEventListenerResponse()
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new CheckInvalidJsonSubscriber());

        // Test queue is never called
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())
            ->method('addJob');

        $controller = new JobsController(
            $queue,
            $this->getUrlGenerator(),
            $this->getLogger(),
            $eventDispatcher,
            30,
            1,
            20
        );

        $request = new Request([], [], [], [], [], [], 'invalid-json');

        $controller->postAction($request);
    }

    public function testPostActionWithInvalidExtra()
    {
        $controller = new JobsController(
            $this->getQueue(1),
            $this->getUrlGenerator(),
            $this->getLogger(),
            $this->getEventDispatcher(),
            10,
            1,
            20
        );

        $composerJson = [
            'name' => 'whatever',
            'description' => 'whatever',
            'config' => [
                'platform' => [
                    'php' => '7.0.11'
                ],
            ],
            'extra' => [
                'composer-resolver' => [
                    'installed-repository' => [
                        'i-am-so-wrong' => 'I am wrong'
                    ]
                ]
            ]
        ];

        $request = new Request([], [], [], [], [], [], json_encode($composerJson));
        $response = $controller->postAction($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Your composer.json does not provide a valid configuration for the extras definition for the key "composer-resolver".', $response->getContent());
    }

    public function testPostActionWithValidPayloadButInvalidResolverHeader()
    {
        $controller = new JobsController(
            $this->getQueue(1),
            $this->getUrlGenerator(),
            $this->getLogger(),
            $this->getEventDispatcher(),
            10,
            1,
            20
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
        $composerJson = [
            'name' => 'whatever',
            'description' => 'whatever',
            'config' => [
                'platform' => [
                    'php' => '7.0.11'
                ],
            ],
            'repositories' => [
                [
                    'type' => 'vcs',
                    'url' => 'http://whatever.com'
                ],
            ],
            // Check for extra
            'extra' => [
                'composer-resolver' => [
                    'installed-repository' => [
                        'my/package' => '1.0.0'
                    ]
                ]
            ]
        ];

        $logger = $this->getLogger();
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->equalTo('Created a new job and will push it to the queue now.')
            );

        /** @var Queue|\PHPUnit_Framework_MockObject_MockObject $queue */
        $queue = $this->getQueue();

        /** @var Job $jobRef */
        $jobRef = null;

        $queue->expects($this->once())
            ->method('addJob')
            ->willReturnCallback(function($job) use (&$jobRef, $queue) {
                $jobRef = $job;
                return $queue;
            });

        $routes = new RouteCollection();
        $routes->add('jobs_get', new Route('/jobs/{jobId}'));
        $urlGenerator = new UrlGenerator($routes, new RequestContext());

        $controller = new JobsController(
            $queue,
            $urlGenerator,
            $logger,
            $this->getEventDispatcher(),
            10,
            1,
            20
        );

        $request = new Request([], [], [], [], [], [], json_encode($composerJson));
        $request->headers->set('Composer-Resolver-Command', '--profile -vvv --prefer-stable');

        $response = $controller->postAction($request);

        $this->assertInternalType('string', $jobRef->getId());
        $this->assertJson($jobRef->getComposerJson());
        $this->assertSame(Job::STATUS_QUEUED, $jobRef->getStatus());
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
        ]], $jobRef->getComposerOptions());
        $this->assertTrue($response->headers->has('Location'));
        $this->assertSame('/jobs/' . $jobRef->getId(), $response->headers->get('Location'));
    }

    public function testGetActionWithInvalidJobId()
    {
        /** @var Queue|\PHPUnit_Framework_MockObject_MockObject $queue */
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('getJob')
            ->willReturn(null);

        $controller = new JobsController(
            $queue,
            $this->getUrlGenerator(),
            $this->getLogger(),
            $this->getEventDispatcher(),
            10,
            1,
            20
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
        $job = Job::createFromArray($jobData);

        /** @var Queue|\PHPUnit_Framework_MockObject_MockObject $queue */
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('getJob')
            ->with($job->getId())
            ->willReturn($job);

        $routes = new RouteCollection();
        $routes->add('jobs_get_composer_lock', new Route('/jobs/{jobId}/composerLock'));
        $routes->add('jobs_get_composer_output', new Route('/jobs/{jobId}/composerOutput'));
        $urlGenerator = new UrlGenerator($routes, new RequestContext());

        $controller = new JobsController(
            $queue,
            $urlGenerator,
            $this->getLogger(),
            $this->getEventDispatcher(),
            10,
            1,
            20
        );

        $response = $controller->getAction($jobData['id']);

        $this->assertSame($expectedStatusCode, $response->getStatusCode());
        $this->assertEquals($expectedContent, json_decode($response->getContent(), true));
    }

    public function testDeleteAction()
    {
        $job = new Job('foobar', Job::STATUS_QUEUED, '');

        /** @var Queue|\PHPUnit_Framework_MockObject_MockObject $queue */
        $queue = $this->createMock(Queue::class);

        $queue->expects($this->once())
            ->method('getJob')
            ->with('foobar')
            ->willReturn($job);

        $queue->expects($this->once())
            ->method('deleteJob')
            ->with($job);

        $controller = new JobsController(
            $queue,
            $this->getUrlGenerator(),
            $this->getLogger(),
            $this->getEventDispatcher(),
            10,
            1,
            20
        );

        $response = $controller->deleteAction('foobar');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals('Job stopped and deleted.', $response->getContent());
    }

    public function testGetComposerLockActionWithInvalidJobId()
    {
        /** @var Queue|\PHPUnit_Framework_MockObject_MockObject $queue */
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('getJob')
            ->with('nonsenseId')
            ->willReturn(null);

        $controller = new JobsController(
            $queue,
            $this->getUrlGenerator(),
            $this->getLogger(),
            $this->getEventDispatcher(),
            10,
            1,
            20
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

        $job = Job::createFromArray($jobData);

        /** @var Queue|\PHPUnit_Framework_MockObject_MockObject $queue */
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('getJob')
            ->with('uniq.id')
            ->willReturn($job);

        $controller = new JobsController(
            $queue,
            $this->getUrlGenerator(),
            $this->getLogger(),
            $this->getEventDispatcher(),
            10,
            1,
            20
        );

        $response = $controller->getComposerLockAction($jobData['id']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"_readme":"foobar"}', $response->getContent());
    }

    public function testGetComposerOutputActionWithInvalidJobId()
    {
        /** @var Queue|\PHPUnit_Framework_MockObject_MockObject $queue */
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('getJob')
            ->with('nonsenseId')
            ->willReturn(null);

        $controller = new JobsController(
            $queue,
            $this->getUrlGenerator(),
            $this->getLogger(),
            $this->getEventDispatcher(),
            10,
            1,
            20
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

        $job = Job::createFromArray($jobData);

        /** @var Queue|\PHPUnit_Framework_MockObject_MockObject $queue */
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('getJob')
            ->with('uniq.id')
            ->willReturn($job);

        $controller = new JobsController(
            $queue,
            $this->getUrlGenerator(),
            $this->getLogger(),
            $this->getEventDispatcher(),
            10,
            1,
            20
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

    private function getQueue($queueLength = 1)
    {
        $mock = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept()
            ->getMock();

        $mock->expects($this->any())
            ->method('getLength')
            ->willReturn($queueLength);

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

    private function getEventDispatcher()
    {
        $mock = $this->createMock(EventDispatcherInterface::class);
        return $mock;
    }
}
