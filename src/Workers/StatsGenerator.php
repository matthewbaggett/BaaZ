<?php

namespace Baaz\Workers;

use Predis\Collection\Iterator\Keyspace;
use Predis\Response\ServerException;

class StatsGenerator extends GenericWorker
{
    public const CACHE_PATH = __DIR__.'/../../cache/';

    public function run()
    {
        printf("Starting to generate stats...\n");
        $counts = [
            'products' => 'product:*',
            'worker-queue-solr' => 'queue:solr-loader:*',
            'worker-queue-solr-reject' => 'queue:solr-reject:*',
            'worker-queue-image' => 'queue:image-worker:*',
            'worker-queue-image-failed' => 'queue:image-failed:*',
        ];
        $totals = [];

        try {
            $pipeline = $this->predis->pipeline();
            foreach ($counts as $countName => $match) {
                $totals[$countName] = [];
                foreach (new Keyspace($this->predis, $match) as $key) {
                    $totals[$countName][] = $key;
                    $pipeline->sadd('set:'.$countName, $key);
                }
                $totals[$countName] = count(array_unique($totals[$countName]));
                $pipeline->set('count:'.$countName, $totals[$countName]);
                printf(
                    "Stats: \"count:%s\" has %d items\n",
                    $countName,
                    $totals[$countName]
                );
                $pipeline->flushPipeline();
            }

            //Set memory usage statistic in redis.
            $pipeline->rpush(sprintf('memory:stats:stats:%s', gethostname()), [memory_get_peak_usage()]);
            $pipeline->ltrim(sprintf('memory:stats:stats:%s', gethostname()), 0, 99);

            printf(
                "Used %s MB of RAM\nStats generated, sleeping...\n",
                number_format(memory_get_peak_usage() / 1024 / 1024, 2)
            );
            sleep(5 * 60);
        } catch (ServerException $exception) {
            printf(
                "Exception %s connecting to REDIS %s, sleeping and trying again...\n",
                get_class($exception),
                $exception->getMessage()
            );
            sleep(15);
        }
    }
}
