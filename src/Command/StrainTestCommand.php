<?php

namespace Toflar\ComposerResolver\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Toflar\ComposerResolver\Job;

/**
 * Class StrainTestCommand
 *
 * @package Toflar\ComposerResolver\Command
 * @codeCoverageIgnore
 */
class StrainTestCommand extends Command
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var string
     */
    private $baseUri;

    /**
     * @var Client
     */
    private $client;

    /**
     * Start time
     * @var int
     */
    private $startTime = 0;

    /**
     * Poll frequency in seconds
     * @var int
     */
    private $pollFrequency = 5;

    /**
     * @var array
     */
    private $jobData= [];

    /**
     * @var int
     */
    private $lastOutputLineCount = 0;

    public function configure()
    {
        $this->setName('strain-test')
            ->setDescription('Creates a given number of jobs from a given composer.json and monitors the results.')
            ->addArgument('endpoint', InputArgument::REQUIRED, 'The endpoint without /jobs.')
            ->addArgument('composerJsonPath', InputArgument::REQUIRED, 'The path to the composer.json.')
            ->addArgument('numberOfJobs', InputArgument::REQUIRED, 'The number of jobs to create and monitor.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->startTime = time();
        $this->output = $output;
        $this->baseUri = $input->getArgument('endpoint');
        $numberOfJobs = $input->getArgument('numberOfJobs');

        if (!file_exists($input->getArgument('composerJsonPath'))) {
            throw new \InvalidArgumentException('Path to composer.json is invalid because it does not seem to exist.');
        }

        $body = file_get_contents($input->getArgument('composerJsonPath'));

        $baseUri =  rtrim($input->getArgument('endpoint'), '/');
        $this->client = new Client([
            'base_uri' => $baseUri,
        ]);

        $requests = function ($total) use ($body, $baseUri) {
            for ($i = 0; $i < $total; $i++) {
                yield new Request('POST', $baseUri . '/jobs', [], $body);
            }
        };

        $pool = new Pool($this->client, $requests($numberOfJobs), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $index) {
                $this->updateJobData($response);
            },
            'rejected' => function ($reason, $index) {

                $msg = '';

                if ($reason instanceof \Exception) {
                    $msg = $reason->getMessage();
                }

                throw new \RuntimeException('A POST request to create a job failed, strain testing only supports testing valid requests.' . $msg);
            },
        ]);

        $promise = $pool->promise();

        $promise->wait();
    }

    private function updateJobData(Response $response)
    {
        $data = json_decode($response->getBody()->getContents(), true);

        $this->jobData[$data['jobId']] = $data;
        $this->jobData[$data['jobId']]['statusCode'] = $response->getStatusCode();

        if ($data['status'] !== 'finished'
            || $data['status'] !== 'finished_with_errors'
        ) {
            $this->fetchJobDetails($data['jobId']);
        }

        $this->display();
    }

    private function fetchJobDetails(string $jobId)
    {
        $promise = $this->client->getAsync('/jobs/' . $jobId);
        $promise->then(
            function($response) {
                sleep($this->pollFrequency); // this is stupid because it blocks the whole process but I don't feel like pthreading right now
                $this->updateJobData($response);
            },
            function() {
                throw new \RuntimeException('A GET request to fetch job details failed, strain testing only supports testing valid requests.');
            }
        );
    }

    private function display()
    {
        $tmpOutput = new BufferedOutput();
        $tmpOutput->setFormatter($this->output->getFormatter());

        // Time info
        $secs = time() - $this->startTime;

        $tmpOutput->writeln('Process is running for ' . Helper::formatTime($secs) . '.');
        $tmpOutput->writeln('');

        $table = new Table($tmpOutput);
        $table->setHeaders(array('Job-ID', 'HTTP status code', 'Result'));

        foreach ($this->jobData as $jobId => $data) {
            $result = '<fg=black;bg=red>NO RESULT YET</>';

            switch ($data['status']) {
                case Job::STATUS_FINISHED:
                    $result = '<fg=black;bg=green>OK</>';
                    break;
                case Job::STATUS_FINISHED_WITH_ERRORS:
                    $result = '<fg=yellow>RESOLVED BUT WITH ERRORS</>';
            }

            $table->addRow([$data['jobId'], $data['statusCode'], $result]);
        }

        $table->render();

        $output = $tmpOutput->fetch();

        // Erase previous lines
        if ($this->lastOutputLineCount > 0) {
            $this->output->write(str_repeat("\x1B[1A\x1B[2K", $this->lastOutputLineCount));
        }

        $this->lastOutputLineCount = substr_count($output, "\n") + 1;
        $this->output->writeln($output);
    }
}
