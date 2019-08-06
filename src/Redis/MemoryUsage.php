<?php

namespace Baaz\Redis;

use Predis\Client;

class MemoryUsage
{
    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function updateMemory($key)
    {
        $this->client->multi();
        $this->client->lpush($key, [memory_get_peak_usage()]);
        $this->client->ltrim($key, 0, 99);
        $this->client->exec();
    }
}
