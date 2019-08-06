<?php

namespace Baaz\Workers;

use Predis\Client as Predis;
use QXS\WorkerPool\WorkerPool;
use WyriHaximus\CpuCoreDetector\Detector;
use ⌬\Services\EnvironmentService;

abstract class GenericWorker
{
    /** @var EnvironmentService */
    protected $environmentService;

    protected $startTime;

    /** @var Predis */
    protected $predis;

    public function __construct(
        Predis $predis,
        EnvironmentService $environmentService
    ) {
        $this->predis = $predis;
        $this->predis->client('SETNAME', get_called_class());
        $this->environmentService = $environmentService;
        $this->resetStopwatch();
    }

    public function getNewWorkerPool(): WorkerPool
    {
        $workerPool = new WorkerPool();
        $cpuCoreCount = Detector::detect();
        $threadCount = $cpuCoreCount * $this->environmentService->get('THREAD_MULTIPLE', 1.0);

        $threadCount = clamp(1, floor($threadCount), $cpuCoreCount * 2);

        $workerPool->setWorkerPoolSize($threadCount);

        echo "Starting new worker pool (threads={$threadCount})\n";

        return $workerPool;
    }

    public function resetStopwatch(): void
    {
        $this->startTime = microtime(true);
    }

    public function waypoint(string $label, $thresholdMs = 100)
    {
        $timeToPoint = microtime(true) - $this->startTime;
        $this->resetStopwatch();
        if ($timeToPoint * 1000 > $thresholdMs) {
            printf(
                'WayPoint: %sms %s'.PHP_EOL,
                number_format($timeToPoint * 1000, 0),
                $label,
            );
        }
    }

    public function run()
    {
        printf("Starting %s...\n", get_called_class());
        $this->iterator();
    }

    public function iterator()
    {
        while (true) {
            $this->resetStopwatch();
            if (method_exists($this, 'iter')) {
                $this->iter();
            }
        }
    }
}
