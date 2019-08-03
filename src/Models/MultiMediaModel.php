<?php
namespace Baaz\Models;

use ⌬\Redis\Redis;
use ⌬\UUID\UUIDValue;

class MultiMediaModel
{
    /** @var Redis */
    protected $__redis;

    /** @var UUIDValue */
    protected $uuid;

    public function __construct(
        Redis $redis
    )
    {
        $this->__redis = $redis;
        $this->uuid = new UUIDValue();
    }

    public function __toArray(){
        $array = [];
        foreach(get_object_vars($this) as $k => $v){
            if(substr($k,0,2) != '__'){
                $k = ucfirst($k);
                if(in_array(substr($v, 0,1), ['{', '['])){
                    $v = \GuzzleHttp\json_decode($v);
                }
                $array[$k] = $v;
            }
        }
        return $array;
    }
}