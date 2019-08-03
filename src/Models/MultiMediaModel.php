<?php

namespace Baaz\Models;

use Predis\Client as Predis;
use ⌬\UUID\UUIDValue;

class MultiMediaModel
{
    /** @var UUIDValue */
    protected $uuid;
    /** @var Predis */
    private $__predis;

    public function __construct(
        Predis $predis
    ) {
        $this->__predis = $predis;
        $this->uuid = new UUIDValue();
    }

    public function __toArray()
    {
        $array = [];
        foreach (get_object_vars($this) as $k => $v) {
            if ('__' != substr($k, 0, 2)) {
                $k = ucfirst($k);
                if (in_array(substr($v, 0, 1), ['{', '['], true)) {
                    $v = \GuzzleHttp\json_decode($v);
                }
                $array[$k] = $v;
            }
        }

        return $array;
    }

    /**
     * @return Predis
     */
    public function getRedis(): Predis
    {
        return $this->__predis;
    }
}
