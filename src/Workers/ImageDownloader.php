<?php

namespace Baaz\Workers;

use Predis\Client as Predis;
use Predis\Collection\Iterator;
use QXS\WorkerPool\ClosureWorker;
use QXS\WorkerPool\Semaphore;
use ⌬\Services\EnvironmentService;

class ImageDownloader extends GenericWorker
{
    public const CACHE_PATH = __DIR__.'/../../cache/';

    /** @var Predis */
    protected $predis;

    public function __construct(
        Predis $predis,
        EnvironmentService $environmentService
    ) {
        $this->predis = $predis;
        parent::__construct($environmentService);
    }

    public function run()
    {
        $imageWorkerPool = $this->getNewWorkerPool();

        $imageWorkerPool->create(new ClosureWorker(
            function ($null, Semaphore $semaphore, \ArrayObject $storage) {
                $pipeline = $this->predis->pipeline();
                $iter = new Iterator\Keyspace($this->predis, 'queue:image-worker', 1);
                foreach ($iter as $index => $value) {
                    \Kint::dump($index, $value);
                }
            }
        ));

        while ($imageWorkerPool->waitForOneFreeWorker()) {
            $imageWorkerPool->run(null);
        }
        $imageWorkerPool->waitForAllWorkers();
    }
}
