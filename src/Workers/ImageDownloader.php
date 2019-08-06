<?php

namespace Baaz\Workers;

use Baaz\Models\Image;
use Baaz\QueuesAndLists;
use Baaz\Workers\Traits\GuzzleWorkerTrait;

class ImageDownloader extends GenericWorker
{
    use GuzzleWorkerTrait;

    public const CACHE_PATH = __DIR__.'/../../cache/';

    public function iter()
    {
        $queue = $this->queueManager->getQueue(QueuesAndLists::QueueWorkerDownloadImages);
        $pipeline = $this->predis->pipeline();
        while ($work = $queue->getNextItem()) {
            try {
                $imageData = $this->getGuzzle()->get($work['url']);

                $image = Image::Factory()
                    ->setFileData($imageData->getBody()->getContents())
                    ->setProductUUID($work['product'])
                    ->save($pipeline)
                ;

                $picturesUUIDs = $this->predis->hget("product:{$work['product']}", 'pictures');
                if (null === ($picturesUUIDs = json_decode($picturesUUIDs))) {
                    $picturesUUIDs = [];
                }

                $picturesUUIDs[] = $image->getUuid();

                $pipeline->hset("product:{$work['product']}", 'pictures', json_encode($picturesUUIDs));

                // And add the product to a queue for the solr-loader
                $this->queueManager->getQueue(QueuesAndLists::QueueWorkerPushSolr)
                    ->addItem($work)
                ;

                $pipeline->flushPipeline();
            } catch (\Exception $e) {
                printf(
                    'Failed to download %s, moved to failure list'.PHP_EOL,
                    $work['url']
                );
                // And add the product to a queue with the other failed image download items
                $this->queueManager->getQueue(QueuesAndLists::QueueWorkerDownloadImagesFailed)
                    ->addItem($work)
                ;
            }
        }

        $this->updateMemoryUsage();

        // Flush the pipeline
        $pipeline->flushPipeline();
        echo "No work to be done, sleeping...\n";
        while (0 == $queue->getLength()) {
            sleep(5);
        }
    }
}
