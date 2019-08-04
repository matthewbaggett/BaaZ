<?php

namespace Baaz\Workers\Traits;

use Doctrine\Common\Cache\FilesystemCache;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\KeyValueHttpHeader;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;

trait GuzzleWorkerTrait
{
    /** @var GuzzleClient */
    protected $guzzle;

    public function getGuzzle(): GuzzleClient
    {
        if (!$this->guzzle) {
            $stack = HandlerStack::create();

            $stack->push(
                new CacheMiddleware(
                    new GreedyCacheStrategy(
                        new DoctrineCacheStorage(
                            new FilesystemCache(self::CACHE_PATH)
                        ),
                        86400,
                        new KeyValueHttpHeader(['Authorization'])
                    )
                ),
                'feed-cache'
            );

            $this->guzzle = new GuzzleClient(['handler' => $stack]);
        }

        return $this->guzzle;
    }
}
