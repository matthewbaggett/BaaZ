<?php

namespace Baaz\Workers;

use Baaz\Lists\ImageList;
use Baaz\Models\Image;
use Baaz\QueuesAndLists;
use Baaz\Workers\Traits\GuzzleWorkerTrait;

class ImageDownloader extends GenericWorker
{
    use GuzzleWorkerTrait;

    public const CACHE_PATH = __DIR__.'/../../cache/';

    public function iter()
    {
        /** @var ImageList $imageList */
        $imageList = ImageList::Factory();
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

                $imageList->addItem($image->__toArray(), ['Uuid' => $image->getUuid()]);

                $images = [];
                $images[] = $image->__toArray();

                $productKey = "lists:data:products:{$work['product']}";
                $encodedImages = json_encode($images);

                $this->predis->hset($productKey, "Images", $encodedImages);

                \Kint::dump(
                    $this->predis->hgetall($productKey),
                    $this->predis->hget($productKey,'Images'),
                    $encodedImages
                );

                $pipeline->flushPipeline();

                // And add the product to a queue for the solr-loader
                $this->queueManager->getQueue(QueuesAndLists::QueueWorkerPushSolr)
                    ->addItem($work)
                ;
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
