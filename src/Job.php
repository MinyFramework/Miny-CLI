<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\CLI;

use InvalidArgumentException;
use UnexpectedValueException;

class Job
{
    private $runnable;
    private $isOneTime;
    private $runCondition;
    private $workload;

    /**
     * @param callable $runnable The job to run
     * @param null     $workload Workload for the job to process
     * @param callable $runCondition Callable that return true if the job can be executed
     * @param bool     $oneTime Indicates whether the job should only be run once
     */
    public function __construct($runnable, $workload = null, $runCondition = null, $oneTime = false)
    {
        if (!is_callable($runnable)) {
            if (is_string($runnable)) {
                $runnable = [$runnable, 'run'];
            } else {
                if (!(is_array($runnable) && count($runnable) == 2)) {
                    throw new InvalidArgumentException('Invalid runnable set.');
                }
            }
        }
        $this->runnable     = $runnable;
        $this->isOneTime    = $oneTime;
        $this->runCondition = $runCondition;
        $this->workload     = $workload;
    }

    public function isOneTimeJob()
    {
        return $this->isOneTime;
    }

    public function setRunCondition($runCondition)
    {
        $this->runCondition = $runCondition;
    }

    public function canRun()
    {
        if (is_callable($this->runCondition)) {
            $function = $this->runCondition;

            return $function();
        }

        return true;
    }

    public function getWorkload()
    {
        return $this->workload;
    }

    public function run(WorkerApplication $app)
    {
        if (is_array($this->runnable)) {
            list($class, $method) = $this->runnable;

            if (is_string($class)) {
                //Lazily instantiate WorkerController
                //Keep it in the same variable for later use
                if (!class_exists($class)) {
                    $class = '\\Application\\Controllers\\' . ucfirst($class) . 'Controller';
                    if (!class_exists($class)) {
                        throw new UnexpectedValueException('Class not found: ' . $class);
                    }
                }
                $class = $app->getContainer()->get($class);
                //Cache our runnable to avoid reinstantiation
                $this->runnable[0] = $class;
            }

            if ($class instanceof WorkerController) {
                $class->$method($this);

                return;
            }
        }
        $runnable = $this->runnable;
        $runnable($app, $this);
    }
}
