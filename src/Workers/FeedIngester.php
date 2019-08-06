<?php

namespace Baaz\Workers;

use Baaz\Models\Product;
use Baaz\Workers\Traits\GuzzleWorkerTrait;
use QXS\WorkerPool\ClosureWorker;
use QXS\WorkerPool\Semaphore;
use ⌬\UUID\UUID;

class FeedIngester extends GenericWorker
{
    use GuzzleWorkerTrait;

    public const CACHE_PATH = __DIR__.'/../../cache/';

    protected $slowMode = true;

    public function run(): void
    {
        $feeds = $this->getGuzzle()->get('https://tt_shops:tt_shops!@api.shop2market.com/api/v1/publishers/1885/feeds.json');

        $feeds = \GuzzleHttp\json_decode($feeds->getBody()->getContents(), true);

        echo "Main feed downloaded\n";

        $feedWorkerPool = $this->getNewWorkerPool();

        $feedWorkerPool->create(new ClosureWorker(
            function ($feed, Semaphore $semaphore, \ArrayObject $storage) {
                if (strtotime($feed['start_date']) < time() && strtotime($feed['end_date']) > time() && $feed['active']) {
                    $ljsonGzPath = self::CACHE_PATH."{$feed['publisher_id']}_{$feed['shop_id']}.ljson.gz";
                    if (!file_exists($ljsonGzPath) || filemtime($ljsonGzPath) < time() - 86400) {
                        $channelFeedJsonLinesRequest = $this->getGuzzle()->get($feed['feeds']['json.gz']);
                        $channelFeedJsonLinesRequest->getBody()->rewind();

                        $channelFeedJsonLinesCompressed = $channelFeedJsonLinesRequest->getBody()->getContents();
                        file_put_contents($ljsonGzPath, $channelFeedJsonLinesCompressed);
                    }

                    $pipeline = $this->predis->pipeline();

                    $queuedRecords = 0;
                    foreach (gzfile($ljsonGzPath) as $jsonLine) {
                        ++$queuedRecords;

                        try {
                            $product = new Product();
                            $json = \GuzzleHttp\json_decode($jsonLine, true);
                            $product->ingest($json);
                            $product->save($pipeline);

                            // Add the product images to a queue for the image-worker
                            foreach ($product->getCacheableImageUrls() as $imageUrl) {
                                $pipeline->hmset(
                                    sprintf('%s:%s:%s', 'queue', 'image-worker', UUID::v4()),
                                    [
                                        'url' => $imageUrl,
                                        'product' => $product->getUuid(),
                                    ]
                                );
                            }

                            //Set memory usage statistic in redis.
                            $this->predis->rpush(sprintf('memory:ingester:feed:%s', gethostname()), [memory_get_peak_usage()]);
                            $this->predis->ltrim(sprintf('memory:ingester:feed:%s', gethostname()), 0, 99);
                        } catch (\Exception $e) {
                            echo $e->getMessage()."\n";
                        }
                        if ($queuedRecords > 200 || $this->slowMode) {
                            $pipeline->flushPipeline(true);
                            $queuedRecords = 0;
                        }
                        if ($this->slowMode) {
                            $sleep = $this->environmentService->get('DELAY_PER_ITEM_MS', 0) * 1000;
                            printf("Sleeping for %s seconds...\n", number_format($sleep / 1000000, 3));
                            usleep($sleep);
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
