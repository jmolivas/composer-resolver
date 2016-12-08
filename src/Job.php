<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver;

use Composer\Command\UpdateCommand;
use Composer\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Job
 *
 * @package Toflar\ComposerResolver
 * @author  Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class Job implements \JsonSerializable
{
    const STATUS_QUEUED                 = 'queued';
    const STATUS_PROCESSING             = 'processing';
    const STATUS_FINISHED               = 'finished';
    const STATUS_FINISHED_WITH_ERRORS   = 'finished_with_errors';

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $composerJson;

    /**
     * @var string
     */
    private $composerLock;

    /**
     * @var string
     */
    private $composerOutput;

    /**
     * @var array
     */
    private $composerOptions = [];

    /**
     * Valid composer update command arguments
     */
    private static $validUpdateArguments = [
        'packages'
    ];

    /**
     * Valid composer update command options
     */
    private static $validUpdateOptions = [
        'prefer-source',
        'prefer-dist',
        'no-dev',
        'no-suggest',
        'prefer-stable',
        'prefer-lowest',
        'ansi',
        'no-ansi',
        'profile',
        'verbose',
    ];

    /**
     * Job constructor.
     *
     * @param string $id
     * @param string $status
     * @param string $composerJson
     */
    public function __construct(
        string $id,
        string $status,
        string $composerJson
    ) {
        $this->id           = $id;
        $this->status       = $status;
        $this->composerJson = $composerJson;
    }

    /**
     * Gets the job id.
     *
     * @return string
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * Gets the job status.
     *
     * @return string
     */
    public function getStatus() : string
    {
        return $this->status;
    }

    /**
     * Sets the job status.
     *
     * @param string $status
     *
     * @return Job
     */
    public function setStatus(string $status) : self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get the composer.json
     *
     * @return string
     */
    public function getComposerJson() : string
    {
        return $this->composerJson;
    }

    /**
     * Set the composer.json
     *
     * @param string $composerJson
     */
    public function setComposerJson(string $composerJson) : self
    {
        $this->composerJson = $composerJson;

        return $this;
    }

    /**
     * Get the composer.lock
     *
     * @return string
     */
    public function getComposerLock() : string
    {
        return (string) $this->composerLock;
    }


    /**
     * Set the composer.lock
     *
     * @param string $composerLock
     */
    public function setComposerLock(string $composerLock) : self
    {
        $this->composerLock = $composerLock;

        return $this;
    }

    /**
     * Get the composer output
     *
     * @return string
     */
    public function getComposerOutput() : string
    {
        return (string) $this->composerOutput;
    }


    /**
     * Set the composer output
     *
     * @param string $composerOutput
     */
    public function setComposerOutput(string $composerOutput) : self
    {
        $this->composerOutput = $composerOutput;

        return $this;
    }

    /**
     * Get the composer options
     *
     * @return array
     */
    public function getComposerOptions(): array
    {
        return $this->composerOptions;
    }

    /**
     * Set the composer options
     *
     * @param array $composerOptions
     */
    public function setComposerOptions(array $composerOptions)
    {
        $this->composerOptions = $composerOptions;
    }

    /**
     * Get the job data as an array.
     *
     * @return array
     */
    public function getAsArray() : array
    {
        return [
            'id'                => $this->id,
            'status'            => $this->status,
            'composerJson'      => $this->composerJson,
            'composerLock'      => $this->composerLock,
            'composerOutput'    => $this->composerOutput,
            'composerOptions'   => $this->composerOptions,
        ];
    }

    /**
     * Implements the JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize() : array
    {
        return $this->getAsArray();
    }

    /**
     * Create a job from an array.
     *
     * @param array $array
     *
     * @return Job
     */
    public static function createFromArray(array $array) : self
    {
        $job = new static(
            $array['id'],
            $array['status'],
            $array['composerJson']
        );

        if (isset($array['composerLock'])) {
            $job->setComposerLock((string) $array['composerLock']);
        }

        if (isset($array['composerOutput'])) {
            $job->setComposerOutput((string) $array['composerOutput']);
        }


        if (isset($array['composerOptions']) && is_array($array['composerOptions'])) {
            $job->setComposerOptions($array['composerOptions']);
        }

        return $job;
    }

    /**
     * Parses an command line like string into arguments and validates against
     * the UpdateCommand and the allowed arguments and options of the job.
     *
     * @param string $arguments
     *
     * @return array
     *
     * @throws RuntimeException If input is not valid
     */
    public static function createComposerOptionsFromCommandLineArguments(string $arguments)
    {
        $options = [
            'args'    => [],
            'options' => [],
        ];
        $newDefinition = new InputDefinition();

        $updateCmd = new UpdateCommand();
        $composerApp = new Application();

        // Arguments
        static::addValidArgumentsFromDefinition($updateCmd->getDefinition(), $newDefinition);
        static::addValidArgumentsFromDefinition($composerApp->getDefinition(), $newDefinition);

        // Options
        static::addValidOptionsFromDefinition($updateCmd->getDefinition(), $newDefinition);
        static::addValidOptionsFromDefinition($composerApp->getDefinition(), $newDefinition);

        $input = new StringInput($arguments);
        $input->bind($newDefinition);

        $input->validate();

        // Arguments
        $options['args']    = $input->getArguments();
        $options['options'] = $input->getOptions();

        // Handle verbosity
        unset($options['options']['verbose']);
        $options['options']['verbosity'] = static::getVerbosity($input);

        return $options;
    }

    /**
     * @param InputDefinition $old
     * @param InputDefinition $new
     */
    private static function addValidArgumentsFromDefinition(InputDefinition $old, InputDefinition $new)
    {
        foreach ($old->getArguments() as $argument) {
            if (in_array($argument->getName(), self::$validUpdateArguments)) {
                $new->addArgument($argument);
            }
        }
    }

    /**
     * @param InputDefinition $old
     * @param InputDefinition $new
     */
    private static function addValidOptionsFromDefinition(InputDefinition $old, InputDefinition $new)
    {
        foreach ($old->getOptions() as $option) {
            if (in_array($option->getName(), self::$validUpdateOptions)) {
                $new->addOption($option);
            }
        }
    }

    /**
     * @param InputInterface $input
     *
     * @return int
     */
    private static function getVerbosity(InputInterface $input) : int
    {
        if ($input->hasParameterOption('-vvv', true) || $input->hasParameterOption('--verbose=3', true) || $input->getParameterOption('--verbose', false, true) === 3) {
            return OutputInterface::VERBOSITY_DEBUG;
        } elseif ($input->hasParameterOption('-vv', true) || $input->hasParameterOption('--verbose=2', true) || $input->getParameterOption('--verbose', false, true) === 2) {
            return OutputInterface::VERBOSITY_VERY_VERBOSE;
        } elseif ($input->hasParameterOption('-v', true) || $input->hasParameterOption('--verbose=1', true) || $input->hasParameterOption('--verbose', true) || $input->getParameterOption('--verbose', false, true)) {
            return OutputInterface::VERBOSITY_VERBOSE;
        } else {
            return OutputInterface::VERBOSITY_NORMAL;
        }
    }
}
