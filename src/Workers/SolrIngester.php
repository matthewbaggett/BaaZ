<?php

namespace Baaz\Workers;

use Baaz\Models\Product;
use Baaz\Workers\Traits\SolrWorkerTrait;
use Predis\Client as Predis;
use Predis\Collection\Iterator\Keyspace;
use Solarium\Exception\ExceptionInterface;
use Solarium\Exception\HttpException;
use ⌬\Services\EnvironmentService;

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
                        $this->predis->del($key);
                        printf(
                            'Wrote Product %s to Solr in %s ms, %d left in queue' . PHP_EOL,
                            'http://baaz.local/' . $product->getSlug(),
                            number_format((microtime(true) - $timeStart) * 1000, 0),
                            count($this->predis->keys($match))
                        );
                    } else {
                        printf(
                            'Attempt to write product %s to Solr FAILED in %s ms' . PHP_EOL,
                            'http://baaz.local/' . $product->getSlug(),
                            number_format((microtime(true) - $timeStart) * 1000, 0)
                        );
                    }
                }catch(ExceptionInterface $exception){
                    printf(
                        'Attempt to write product %s to Solr EXCEPTION in %s ms: %s' . PHP_EOL,
                        'http://baaz.local/' . $product->getSlug(),
                        number_format((microtime(true) - $timeStart) * 1000, 0),
                        $exception->getMessage()
                    );
                }

            }
            //Set memory usage statistic in redis.
            $this->predis->rpush(sprintf("memory:ingester:solr:%s", gethostname()), [memory_get_peak_usage()]);
            $this->predis->ltrim(sprintf("memory:ingester:solr:%s", gethostname()),0,99);

            echo "No work to be done, sleeping...\n";
            while (0 == count($this->predis->keys($match))) {
                sleep(5);
            }
        }
    }
}
