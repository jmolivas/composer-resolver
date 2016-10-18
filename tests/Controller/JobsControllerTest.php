<?php


namespace Toflar\ComposerResolver\Test\Controller;

use Predis\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Toflar\ComposerResolver\Controller\JobsController;

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
     * @dataProvider indexAction
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

    public function indexAction()
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
