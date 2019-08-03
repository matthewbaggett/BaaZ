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

class FeedIngester extends GenericWorker
{
    public const CACHE_PATH = __DIR__.'/../../cache/';
    /** @var GuzzleClient */
    protected $guzzle;

    /** @var Redis */
    protected $redis;
    
    public function __construct(
        Redis $redis,
        EnvironmentService $environmentService
    ) {
        parent::__construct($environmentService);

        $this->redis = $redis;

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

    public function run(): void
    {
        $feeds = $this->guzzle->get('https://tt_shops:tt_shops!@api.shop2market.com/api/v1/publishers/1885/feeds.json');

        $feeds = \GuzzleHttp\json_decode($feeds->getBody()->getContents(), true);

        echo "Main feed downloaded\n";

        $feedWorkerPool = $this->getNewWorkerPool();

        $feedWorkerPool->create(new ClosureWorker(
            function ($feed, Semaphore $semaphore, \ArrayObject $storage) {
                extract($feed);
                /*
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

                \Kint::dump($feeds);

                if (strtotime($start_date) < time() && strtotime($end_date) > time() && $active) {
                    $ljsonGzPath = self::CACHE_PATH."{$publisher_id}_{$shop_id}.ljson.gz";
                    if (!file_exists($ljsonGzPath) || filemtime($ljsonGzPath) < time() - 86400) {
                        $channelFeedJsonLinesRequest = $this->guzzle->get($feeds['json.gz']);
                        $channelFeedJsonLinesRequest->getBody()->rewind();

                        $channelFeedJsonLinesCompressed = $channelFeedJsonLinesRequest->getBody()->getContents();
                        file_put_contents($ljsonGzPath, $channelFeedJsonLinesCompressed);
                    }

                    foreach (gzfile($ljsonGzPath) as $jsonLine) {
                        try {
                            $product = new Product($this->redis);
                            $json = \GuzzleHttp\json_decode($jsonLine, true);
                            $product->ingest($json);
                            $product->save();

                            foreach ($product->getCacheableImageUrls() as $imageUrl) {
                                $this->redis->set(sprintf('%s:%s:%s', 'queue', 'image-worker', UUID::v4()), $imageUrl);
                            }

                            $this->redis->set('memory:ingester:feed:' . gethostname(), memory_get_usage());
                        } catch (\Exception $e) {
                            echo $e->getMessage()."\n";
                        }
                    }
                }
            }
        ));

        while ($feed = array_shift($feeds)) {
            printf("Feed length: %d\n", count($feeds));
            $feedWorkerPool->run($feed);
        }
        $feedWorkerPool->waitForAllWorkers();
    }
}
