<?php
namespace Baaz\Models;

use ⌬\Cache\Cache;
use ⌬\UUID\UUIDValue;

class MultiMediaModel
{
    /** @var Cache */
    protected $__cache;

    /** @var UUIDValue */
    protected $uuid;

    public function __construct(
        Cache $cache
    )
    {
        $this->__cache = $cache;
        $this->uuid = new UUIDValue();
    }
}