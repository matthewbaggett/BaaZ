<?php

namespace Baaz\Workers;

use Predis\Client as Predis;
use Predis\Collection\Iterator\Keyspace;
use ⌬\Services\EnvironmentService;

class StatsGenerator extends GenericWorker
{
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
        $counts = [
            'products' => 'product:*',
            'worker-queue-solr' => 'queue:solr-loader:*',
            'worker-queue-image' => 'queue:image-worker:*',
        ];
        while (true) {
            $totals = [];
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
            }
            $pipeline->flushPipeline();
            echo "Stats generated, sleeping...\n";
            sleep(5*60);
        }
    }
}
