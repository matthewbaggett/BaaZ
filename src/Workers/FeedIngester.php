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
use Predis\Client as Predis;
use QXS\WorkerPool\ClosureWorker;
use QXS\WorkerPool\Semaphore;
use ⌬\Services\EnvironmentService;
use ⌬\UUID\UUID;

class FeedIngester extends GenericWorker
{
    public const CACHE_PATH = __DIR__.'/../../cache/';
    /** @var GuzzleClient */
    protected $guzzle;

    /** @var Predis */
    protected $predis;

    public function __construct(
        Predis $predis,
        EnvironmentService $environmentService
    ) {
        parent::__construct($environmentService);

        $this->predis = $predis;

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
                if (strtotime($feed['start_date']) < time() && strtotime($feed['end_date']) > time() && $feed['active']) {
                    $ljsonGzPath = self::CACHE_PATH."{$feed['publisher_id']}_{$feed['shop_id']}.ljson.gz";
                    if (!file_exists($ljsonGzPath) || filemtime($ljsonGzPath) < time() - 86400) {
                        $channelFeedJsonLinesRequest = $this->guzzle->get($feed['feeds']['json.gz']);
                        $channelFeedJsonLinesRequest->getBody()->rewind();

                        $channelFeedJsonLinesCompressed = $channelFeedJsonLinesRequest->getBody()->getContents();
                        file_put_contents($ljsonGzPath, $channelFeedJsonLinesCompressed);
                    }

                    $pipeline = $this->predis->pipeline();

                    $queuedRecords = 0;
                    foreach (gzfile($ljsonGzPath) as $jsonLine) {
                        ++$queuedRecords;

                        try {
                            $product = new Product($this->predis);
                            $json = \GuzzleHttp\json_decode($jsonLine, true);
                            $product->ingest($json);
                            $product->save($pipeline);

                            foreach ($product->getCacheableImageUrls() as $imageUrl) {
                                $pipeline->set(
                                    sprintf('%s:%s:%s', 'queue', 'image-worker', UUID::v4()),
                                    $imageUrl
                                );
                            }

                            $pipeline->set('memory:ingester:feed:'.gethostname(), memory_get_usage());

                            if ($queuedRecords > 200) {
                                $pipeline->flushPipeline(true);
                                $queuedRecords = 0;
                            }
                        } catch (\Exception $e) {
                            echo $e->getMessage()."\n";
                        }
                    }
                    $pipeline->flushPipeline(true);
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
