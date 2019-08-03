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

class ImageDownloader extends GenericWorker
{
    public const CACHE_PATH = __DIR__.'/../../cache/';

    /** @var Redis */
    protected $redis;

    public function __construct(
        Redis $redis,
        EnvironmentService $environmentService
    ) {
        $this->redis = $redis;
        parent::__construct($environmentService);
    }

    public function run(){
        $this->redis->get
    }
}
