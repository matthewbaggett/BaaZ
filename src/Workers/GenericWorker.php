<?php

namespace Baaz\Workers;

use Baaz\Baaz;
use Baaz\Redis\MemoryUsage;
use Predis\Client as Predis;
use QXS\WorkerPool\WorkerPool;
use WyriHaximus\CpuCoreDetector\Detector;
use ⌬\Redis\Queue\ItemListManager;
use ⌬\Redis\Queue\ItemQueueManager;
use ⌬\Services\EnvironmentService;

abstract class GenericWorker
{
    /** @var EnvironmentService */
    protected $environmentService;
    /** @var float */
    protected $startTime;
    /** @var Predis */
    protected $predis;
    /** @var ItemQueueManager */
    protected $queueManager;
    /** @var ItemListManager */
    protected $listManager;
    /** @var MemoryUsage */
    protected $memoryUsage;

    public function __construct(
        Predis $predis,
        EnvironmentService $environmentService,
        ItemQueueManager $queueManager,
        ItemListManager $listManager,
        MemoryUsage $memoryUsage
    ) {
        $this->predis = $predis;
        $this->predis->client('SETNAME', $this->getCalledClassStub());
        $this->queueManager = $queueManager;
        $this->listManager = $listManager;
        $this->environmentService = $environmentService;
        $this->memoryUsage = $memoryUsage;
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
            $this->updateMemoryUsage();
        }
    }

    public function updateMemoryUsage()
    {
        //Send memory usage statistic in redis.
        $this->memoryUsage->updateMemory(sprintf(
            'memory:worker:%s:%s',
            $this->getCalledClassStub(),
            gethostname()
        ));
    }

    private function getCalledClassStub(): string
    {
        $classFragments = explode('\\', get_called_class());

        return end($classFragments);
    }
}
