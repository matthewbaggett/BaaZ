<?php

namespace Baaz\Workers;

use Baaz\Models\Image;
use Baaz\Workers\Traits\GuzzleWorkerTrait;
use Predis\Client as Predis;
use Predis\Collection\Iterator\Keyspace;
use ⌬\Services\EnvironmentService;
use ⌬\UUID\UUID;

class ImageDownloader extends GenericWorker
{
    use GuzzleWorkerTrait;

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
            $match = 'queue:image-worker:*';
            $pipeline = $this->predis->pipeline();
            $tickCount = 0;
            $timeSinceFlush = time();
            foreach (new Keyspace($this->predis, $match) as $key) {
                ++$tickCount;
                $work = $this->predis->hgetall($key);

                try {
                    $imageData = $this->getGuzzle()->get($work['url']);
                    $this->predis->del($key);
                } catch (\Exception $e) {
                }

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
                $pipeline->set(
                    sprintf('%s:%s:%s', 'queue', 'solr-loader', UUID::v4()),
                    $work['product']
                );

                //Set memory usage statistic in redis.
                $pipeline->setex('memory:ingester:feed:'.gethostname(), 60, memory_get_peak_usage());

                if ($tickCount > 200 || $timeSinceFlush < time() - 60) {
                    $pipeline->flushPipeline();
                    $tickCount = 0;
                    $timeSinceFlush = time();
                }
            }
            $pipeline->flushPipeline();
            echo "No work to be done, sleeping...\n";
            while (0 == count($this->predis->keys($match))) {
                sleep(5);
            }
        }
    }
}
