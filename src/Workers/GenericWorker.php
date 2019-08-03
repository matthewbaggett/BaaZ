<?php

namespace Baaz\Workers;

use Baaz\Models\Product;
use Doctrine\Common\Cache\FilesystemCache;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\KeyValueHttpHeader;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use QXS\WorkerPool\ClosureWorker;
use QXS\WorkerPool\Semaphore;
use QXS\WorkerPool\WorkerPool;
use WyriHaximus\CpuCoreDetector\Detector;
use ⌬\Redis\Redis;
use ⌬\Services\EnvironmentService;
use ⌬\UUID\UUID;

class GenericWorker
{
    /** @var EnvironmentService  */
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
