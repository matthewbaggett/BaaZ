<?php

namespace Baaz\Workers;

use ⌬\Redis\Redis;
use ⌬\Services\EnvironmentService;

class WriteToDatabase extends GenericWorker
{
    public const CACHE_PATH = __DIR__.'/../../cache/';

    /** @var Redis */
    protected $redis;

    public function __construct(
        Redis $redis,
        EnvironmentService $environmentService
    ) {
        $this->redis = $redis;
        parent::__construct($environmentService);
    }

    public function run()
    {
    }
}
