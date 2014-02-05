<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <daniel@bugadani.hu>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\CLI;

use InvalidArgumentException;
use Miny\Application\BaseApplication;
use Miny\AutoLoader;
use Miny\Factory\Container;
use Miny\Log\Log;

class WorkerApplication extends BaseApplication
{
    private $jobs = array();
    private $exit_requested = false;

    public function __construct($environment = self::ENV_PROD, AutoLoader $autoloader = null)
    {
        ignore_user_abort(true);
        set_time_limit(0);
        pcntl_signal(
            SIGTERM,
            function () {
                exit;
            }
        );
        parent::__construct($environment, $autoloader);
    }

    protected function registerDefaultServices(Container $container)
    {
        $container->addAlias('\\Miny\\Application\\BaseApplication', '\\Modules\\CLI\\WorkerApplication');
        parent::registerDefaultServices($container);
    }


    /**
     * @param string $name
     * @param mixed  $runnable
     * @param mixed  $workload
     * @param mixed  $condition
     * @param bool   $one_time
     *
     * @return Job
     * @throws InvalidArgumentException
     */
    public function addJob($name, $runnable, $workload = null, $condition = null, $one_time = false)
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('Job name must be a string.');
        }
        if (!$runnable instanceof Job) {
            $runnable = new Job($runnable, $workload, $one_time, $condition);
        }

        $this->getContainer()->get('\\Miny\\Log\\Log')->write(
            Log::INFO,
            'WorkerApplication',
            'Registering new %s "%s"',
            ($one_time ? 'one-time job' : 'job'),
            $name
        );
        $this->jobs[$name] = $runnable;

        return $runnable;
    }

    public function removeJob($name)
    {
        unset($this->jobs[$name]);
    }

    public function requestExit()
    {
        $this->exit_requested = true;
    }

    protected function onRun()
    {
        /** @var $log Log */
        $log = $this->getContainer()->get('\\Miny\\Log\\Log');
        while (!$this->exit_requested && !empty($this->jobs)) {

            foreach ($this->jobs as $name => $job) {
                if ($job->canRun()) {
                    $job->run($this);
                    if ($job->isOneTimeJob()) {
                        $log->write(Log::INFO, 'WorkerApplication', 'Removing one-time job %s', $name);
                        $this->removeJob($name);
                    }
                } else {
                    $log->write(Log::INFO, 'WorkerApplication', 'Skipping job %s', $name);
                }
            }
            $log->flush();
        }
    }
}
