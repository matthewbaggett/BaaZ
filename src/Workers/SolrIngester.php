<?php

namespace Baaz\Workers;

use Baaz\Models\Product;
use Baaz\QueuesAndLists;
use Baaz\Workers\Traits\SolrWorkerTrait;
use Solarium\Exception as SolrException;

class SolrIngester extends GenericWorker
{
    use SolrWorkerTrait;

    public const CACHE_PATH = __DIR__.'/../../cache/';

    public function iterator()
    {
        while (true) {
            $this->resetStopwatch();
            if (null !== ($queuedProduct = $this->queueManager->getQueue(QueuesAndLists::QueueWorkerPushSolr)->getNextItem())) {
                $this->iter($queuedProduct);
            } else {
                sleep(5);
            }
        }
    }

    public function iter($queuedProduct): void
    {
        $this->resetStopwatch();
        $solr = $this->getSolr();
        $this->waypoint('GetSolr');

        $timeStart = microtime(true);

        /** @var Product $product */
        $listItem = $this->listManager
            ->getList(QueuesAndLists::ListProducts)
            ->find($queuedProduct['product'])
        ;
        $product = new Product($listItem);

        $this->waypoint('Load Product');
        $update = $solr->createUpdate();

        $document = $product->createSolrDocument($update);
        $update->addDocument($document);
        $update->addCommit();
        $this->waypoint('Create Solr Document', 500);

        try {
            $result = $solr->update($update);
            $this->waypoint('Send to Solr', 500);

            if ('OK' == $result->getResponse()->getStatusMessage()) {
                $this->waypoint('Get Status Message OK');
                printf(
                    'Wrote Product %s to Solr in %s ms'.PHP_EOL,
                    'http://baaz.local/'.$product->getSlug(),
                    number_format((microtime(true) - $timeStart) * 1000, 0)
                );
            } else {
                $this->waypoint('Get Status Message NOT OK');

                printf(
                    'Attempt to write product %s to Solr FAILED in %s ms and has been moved to queue:solr-reject'.PHP_EOL,
                    'http://baaz.local/'.$product->getSlug(),
                    number_format((microtime(true) - $timeStart) * 1000, 0)
                );

                // Put the product back in the queue.
                $this->queueManager->getQueue(QueuesAndLists::QueueWorkerPushSolrFailed)->addItem($queuedProduct);
            }
        } catch (SolrException\HttpException $exception) {
            $this->waypoint('Solr HttpException');

            printf(
                'Attempt to write product %s to Solr NON-CRITICAL EXCEPTION (%s) in %s ms: %s'.PHP_EOL,
                'http://baaz.local/'.$product->getSlug(),
                get_class($exception),
                number_format((microtime(true) - $timeStart) * 1000, 0),
                $exception->getMessage()
            );
            $this->queueManager->getQueue(QueuesAndLists::QueueWorkerPushSolr)->addItem($queuedProduct);

            // Sleep so we don't hammer solr.
            sleep(5);
        } catch (SolrException\ExceptionInterface $exception) {
            $this->waypoint('Solr General Exception');

            printf(
                'Attempt to write product %s to Solr CRITICAL EXCEPTION (%s) in %s ms: %s and has been moved to queue:solr-reject'.PHP_EOL,
                'http://baaz.local/'.$product->getSlug(),
                get_class($exception),
                number_format((microtime(true) - $timeStart) * 1000, 0),
                $exception->getMessage()
            );

            // Move the key to the reject queue.
            $this->queueManager->getQueue(QueuesAndLists::QueueWorkerPushSolrFailed)->addItem($queuedProduct);

            // Sleep so we don't hammer solr.
            sleep(5);
        } finally {
            $this->waypoint('Unset Solr');

            unset($solr);
        }
        $this->updateMemoryUsage();
    }
}
