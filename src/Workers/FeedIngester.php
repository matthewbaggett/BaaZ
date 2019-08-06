<?php

namespace Baaz\Workers;

use Baaz\Models\Product;
use Baaz\QueuesAndLists;
use Baaz\Workers\Traits\GuzzleWorkerTrait;
use QXS\WorkerPool\ClosureWorker;
use QXS\WorkerPool\Semaphore;

class FeedIngester extends GenericWorker
{
    use GuzzleWorkerTrait;

    public const CACHE_PATH = __DIR__.'/../../cache/';

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
                    $queue = $this->queueManager->getQueue(QueuesAndLists::QueueWorkerDownloadImages);

                    foreach (gzfile($ljsonGzPath) as $jsonLine) {
                        try {
                            $productsList = $this->listManager->getList(QueuesAndLists::ListProducts);

                            $product = new Product();
                            $json = \GuzzleHttp\json_decode($jsonLine, true);
                            $product->ingest($json);
                            $product->setId($productsList->getLength() + 1);
                            //$product->save($pipeline);
                            $productsList->addItem($product->__toArray(), ['uuid' => $product->getUuid()]);
                            printf(
                                'Wrote Product %s to Redis as %d keys %s'.PHP_EOL,
                                $product->getName(),
                                count($product->__toArray()),
                                method_exists($product, 'getSlug') ? sprintf('( http://baaz.local/%s )', $product->getSlug()) : null
                            );

                            // Add the product images to a queue for the image-worker
                            foreach ($product->getCacheableImageUrls() as $imageUrl) {
                                $queue->addItem([
                                    'url' => $imageUrl,
                                    'product' => $product->getUuid(),
                                ]);
                            }

                            $this->updateMemoryUsage();
                        } catch (\Exception $e) {
                            echo $e->getMessage()."\n";
                        }

                        $pipeline->flushPipeline(true);
                        $sleep = $this->environmentService->get('DELAY_PER_ITEM_MS', 0) * 1000;
                        printf("Sleeping for %s seconds...\n", number_format($sleep / 1000000, 3));
                        usleep($sleep);
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
