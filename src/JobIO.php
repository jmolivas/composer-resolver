<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver;

use Composer\IO\ConsoleIO;

/**
 * Class JobIO. Never supports overwriting.
 *
 * @author  Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class JobIO extends ConsoleIO
{
    /**
     * @var float
     */
    private $startTime;

    /**
     * @return float|null
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @param float $startTime
     */
    public function enableDebugging($startTime)
    {
        $this->startTime = $startTime;
        parent::enableDebugging($startTime);
    }


    /**
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * {@inheritDoc}
     */
    public function overwrite($messages, $newline = true, $size = null, $verbosity = self::NORMAL)
    {
        $this->write($messages, $newline, $verbosity);
    }

    /**
     * {@inheritDoc}
     */
    public function overwriteError($messages, $newline = true, $size = null, $verbosity = self::NORMAL)
    {
        $this->writeError($messages, $newline, $verbosity);
    }
}
