<?php

namespace Baaz\Workers;

use QXS\WorkerPool\WorkerPool;
use WyriHaximus\CpuCoreDetector\Detector;
use ⌬\Services\EnvironmentService;

class GenericWorker
{
    /** @var EnvironmentService */
    protected $environmentService;

    public function __construct(
        EnvironmentService $environmentService
    ) {
        $this->environmentService = $environmentService;
    }

    public function getNewWorkerPool(): WorkerPool
    {
        $workerPool = new WorkerPool();
        $cpuCoreCount = Detector::detect();
        $threadCount = $cpuCoreCount * $this->environmentService->get('THREAD_MULTIPLE', 1.0);

        $threadCount = clamp(1, floor($threadCount), $cpuCoreCount * 2);

        $workerPool->setWorkerPoolSize($threadCount);

        return $workerPool;
    }
}
