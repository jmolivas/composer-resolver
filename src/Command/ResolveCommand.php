<?php
/**
 * Created by PhpStorm.
 * User: yanickwitschi
 * Date: 13.10.16
 * Time: 14:32
 */

namespace Toflar\ComposerResolver\Command;


use Composer\IO\ConsoleIO;
use Predis\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Toflar\ComposerResolver\Worker\Resolver;

class ResolveCommand extends Command
{
    private $resolver;
    private $predis;

    public function __construct(Resolver $resolver, Client $predis)
    {
        $this->resolver = $resolver;
        $this->predis   = $predis;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('resolve-job')
            ->setDescription('Resolves a given job ID manually.')
            ->addArgument('jobId', InputArgument::REQUIRED, 'The job ID.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $jobId = $input->getArgument('jobId');
        $jobData = $this->predis->get('jobs:' . $jobId);

        if (null === $jobData) {
            $output->writeln('<error>Could not find Job ID ' . $jobId . '</error>');
            return;
        }

        $job = \Toflar\ComposerResolver\Job::createFromArray(json_decode($jobData, true));

        $io = new ConsoleIO($input, $output, $this->getHelperSet());
        $this->resolver->resolve($job, $io);
    }
}
