<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\CLI;

abstract class WorkerController
{
    /**
     * @var WorkerApplication
     */
    private $application;

    public function __construct(WorkerApplication $application)
    {
        $this->application = $application;
    }

    /**
     * @return WorkerApplication
     */
    public function getApplication()
    {
        return $this->application;
    }

    abstract public function run(Job $job);
}
