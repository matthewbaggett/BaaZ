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
use ⌬\⌬;

class FeedIngester
{
    /** @var GuzzleClient */
    protected $guzzle;

    /** @var WorkerPool */
    protected $workerPool;

    /** @var ⌬ */
    protected $⌬;

    const CACHE_PATH = __DIR__ . "/../../cache/";

    public function __construct(
        ⌬ $⌬
    )
    {
        $this->⌬ = $⌬;
        $stack = HandlerStack::create();

        $stack->push(
            new CacheMiddleware(
                new GreedyCacheStrategy(
                    new DoctrineCacheStorage(
                        new FilesystemCache(self::CACHE_PATH)
                    ),
                    86400,
                    new KeyValueHttpHeader(['Authorization'])
                )
            ),
            'feed-cache'
        );

        $this->guzzle = new GuzzleClient(['handler' => $stack]);
    }

    public function getNewWorkerPool() : WorkerPool
    {
        $workerPool = new WorkerPool();
        $cpuCoreCount = Detector::detect();
        $threadCount = ($cpuCoreCount + 1);
        $threadCount = 1;
        $workerPool->setWorkerPoolSize($threadCount);
        printf("Starting with {$threadCount} threads.");
        return $workerPool;
    }

    public function run() : void
    {
        $feeds = $this->guzzle->get("https://tt_shops:tt_shops!@api.shop2market.com/api/v1/publishers/1885/feeds.json");

        $feeds = \GuzzleHttp\json_decode($feeds->getBody()->getContents(), true);

        ($feedWorkerPool = $this->getNewWorkerPool())
            ->create(new ClosureWorker(
                function($feed, Semaphore $semaphore, \ArrayObject $storage){
                    /**
                     * @var $start_date
                     * @var $end_date
                     * @var $publisher_id
                     * @var $shop_id
                     * @var $feeds
                     * @var $active
                     * @var $export
                     * @var $shop
                     * @var $meta
                     */
                    extract($feed);
                    if(strtotime($start_date) < time() && strtotime($end_date) > time() && $active){
                        ($channelFeedJsonLinesRequest = $this->guzzle->get($feeds['json.gz']))
                            ->getBody()->rewind();

                        $channelFeedJsonLinesCompressed = $channelFeedJsonLinesRequest->getBody()->getContents();
                        \Kint::dump(
                            $channelFeedJsonLinesRequest,
                            $channelFeedJsonLinesRequest->getStatusCode(),
                            $channelFeedJsonLinesRequest->getBody()->getSize(),
                        );
                        $ljsonGzPath = self::CACHE_PATH . "{$publisher_id}_{$shop_id}.ljson.gz";
                        file_put_contents($ljsonGzPath, $channelFeedJsonLinesCompressed);

                        // Now create another worker to handle each line.
                        ($productWorker = $this->getNewWorkerPool())
                            ->create(new ClosureWorker(
                                function($jsonLine, Semaphore $semaphore, \ArrayObject $storage){
                                    $productJson = \GuzzleHttp\json_decode($jsonLine, true);
                                    \Kint::dump($productJson);
                                    $product = (new Product())
                                        ->ingest($productJson)
                                        ->save();
                                    \Kint::dump($product);
                                    exit;
                                }
                            ));

                        foreach(gzfile($ljsonGzPath) as $jsonLine){
                            #$productWorker->run($jsonLine);
                            $productJson = \GuzzleHttp\json_decode($jsonLine, true);
                            \Kint::dump($productJson);
                            /** @var Product $product */
                            $product = ($this->⌬->getContainer()->get(Product::class))
                                ->ingest($productJson)
                                ->save();
                            \Kint::dump($product);
                            exit;
                        }

                        $productWorker->waitForAllWorkers();
                    }
                }
            ));

        foreach($feeds as $feed){
           $feedWorkerPool->run($feed);
        }

        $feedWorkerPool->waitForAllWorkers();
    }
}
