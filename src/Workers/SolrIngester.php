<?php

namespace Baaz\Workers;

use Baaz\Models\Product;
use Baaz\Workers\Traits\SolrWorkerTrait;
use Predis\Client as Predis;
use Predis\Collection\Iterator\Keyspace;
use Predis\PredisException;
use ⌬\Services\EnvironmentService;
use \Solarium\Exception as SolrException;

class SolrIngester extends GenericWorker
{
    use SolrWorkerTrait;

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
        while (true) {
            $match = 'queue:solr-loader:*';
            try {
                foreach (new Keyspace($this->predis, $match) as $key) {
                    $solr = $this->getSolr();

                    $timeStart = microtime(true);
                    $productUUID = $this->predis->get($key);
                    /** @var Product $product */
                    $product = (new Product())->load($productUUID);
                    $update = $solr->createUpdate();

                    $document = $product->createSolrDocument($update);
                    $update->addDocument($document);
                    $update->addCommit();

                    try {
                        $result = $solr->update($update);
                        if ('OK' == $result->getResponse()->getStatusMessage()) {
                            printf(
                                'Wrote Product %s to Solr in %s ms' . PHP_EOL,
                                'http://baaz.local/' . $product->getSlug(),
                                number_format((microtime(true) - $timeStart) * 1000, 0)
                            );

                            // Remove the key from the queue, we're done with it.
                            $this->predis->del($key);
                        } else {
                            printf(
                                'Attempt to write product %s to Solr FAILED in %s ms and has been moved to queue:solr-reject' . PHP_EOL,
                                'http://baaz.local/' . $product->getSlug(),
                                number_format((microtime(true) - $timeStart) * 1000, 0)
                            );

                            // Move the key to the reject queue.
                            $this->predis->rename($key, str_replace("solr-loader", "solr-reject", $key));
                        }
                    } catch (SolrException\HttpException $exception){
                        printf(
                            'Attempt to write product %s to Solr NON-CRITICAL EXCEPTION (%s) in %s ms: %s' . PHP_EOL,
                            'http://baaz.local/' . $product->getSlug(),
                            get_class($exception),
                            number_format((microtime(true) - $timeStart) * 1000, 0),
                            $exception->getMessage()
                        );
                    } catch (SolrException\ExceptionInterface $exception) {
                        printf(
                            'Attempt to write product %s to Solr CRITICAL EXCEPTION (%s) in %s ms: %s and has been moved to queue:solr-reject' . PHP_EOL,
                            'http://baaz.local/' . $product->getSlug(),
                            get_class($exception),
                            number_format((microtime(true) - $timeStart) * 1000, 0),
                            $exception->getMessage()
                        );

                        // Move the key to the reject queue.
                        $this->predis->rename($key, str_replace("solr-loader", "solr-reject", $key));
                    }finally {
                        unset($solr);
                    }
                }
                //Set memory usage statistic in redis.
                $this->predis->rpush(sprintf('memory:ingester:solr:%s', gethostname()), [memory_get_peak_usage()]);
                $this->predis->ltrim(sprintf('memory:ingester:solr:%s', gethostname()), 0, 99);

                echo "No work to be done, sleeping...\n";
                while (0 == count($this->predis->keys($match))) {
                    sleep(5);
                }
            }catch (PredisException $exception){
                printf(
                    'Something went wrong talking to redis (%s) - Gonna give it a moment and try again: %s' . PHP_EOL,
                    get_class($exception),
                    $exception->getMessage()
                );
                sleep(5);
            }
        }
    }
}
