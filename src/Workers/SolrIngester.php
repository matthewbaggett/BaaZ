<?php

namespace Baaz\Workers;

use Baaz\Models\Product;
use Baaz\Workers\Traits\SolrWorkerTrait;
use Predis\Collection\Iterator\Keyspace;
use Predis\PredisException;
use Solarium\Exception as SolrException;

class SolrIngester extends GenericWorker
{
    use SolrWorkerTrait;

    public const CACHE_PATH = __DIR__.'/../../cache/';

    public function iterator()
    {
        $match = 'queue:solr-loader:*';

        try {
            foreach (new Keyspace($this->predis, $match) as $key) {
                $this->iter($key);
            }
            //Set memory usage statistic in redis.
            $this->predis->rpush(sprintf('memory:ingester:solr:%s', gethostname()), [memory_get_peak_usage()]);
            $this->predis->ltrim(sprintf('memory:ingester:solr:%s', gethostname()), 0, 99);

            echo "No work to be done, sleeping...\n";
            while (0 == count($this->predis->keys($match))) {
                sleep(5);
            }
        } catch (PredisException $exception) {
            printf(
                'Something went wrong talking to redis (%s) - Gonna give it a moment and try again: %s'.PHP_EOL,
                get_class($exception),
                $exception->getMessage()
            );
            sleep(5);
        }
    }

    public function iter($key): void
    {
        $this->resetStopwatch();
        $solr = $this->getSolr();
        $this->waypoint('GetSolr');

        $timeStart = microtime(true);
        $productUUID = $this->predis->get($key);
        $this->waypoint('Get ProductUUID');
        /** @var Product $product */
        $product = (new Product())->load($productUUID);
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

                // Remove the key from the queue, we're done with it.
                $this->predis->del($key);
            } else {
                $this->waypoint('Get Status Message NOT OK');

                printf(
                    'Attempt to write product %s to Solr FAILED in %s ms and has been moved to queue:solr-reject'.PHP_EOL,
                    'http://baaz.local/'.$product->getSlug(),
                    number_format((microtime(true) - $timeStart) * 1000, 0)
                );

                // Move the key to the reject queue.
                $this->predis->rename($key, str_replace('solr-loader', 'solr-reject', $key));
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
            $this->predis->rename($key, str_replace('solr-loader', 'solr-reject', $key));
        } finally {
            $this->waypoint('Unset Solr');

            unset($solr);
        }
    }
}
