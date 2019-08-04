<?php

namespace Baaz\Models;

use Baaz\Baaz;

abstract class File extends MultiMediaModel
{
    public static function Factory()
    {
        return Baaz::Container()->get(__CLASS__);
    }
}
