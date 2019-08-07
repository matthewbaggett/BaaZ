<?php

namespace Baaz\Controllers\Traits;

trait RedisClientTrait
{
    protected function getCalledClassStub(): string
    {
        $classFragments = explode('\\', get_called_class());
        return end($classFragments);
    }
}
