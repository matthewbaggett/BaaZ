<?php

namespace Baaz\Filesystem;

use League\Flysystem\Adapter\Local;

abstract class Filesystem extends \League\Flysystem\Filesystem
{
    public function __construct()
    {
        $calledClass = get_called_class();
        parent::__construct(
            new Local(
                $calledClass::STORAGE_PATH
            )
        );
    }
}
