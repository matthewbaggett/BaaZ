<?php

namespace Baaz\Lists;

use Baaz\Baaz;
use Predis\Client as PredisClient;
use ⌬\Redis\Queue\ItemList;
use ⌬\Redis\Queue\ItemListManager;

class ItemListMetaManager extends ItemList
{
    protected static $metaManager;

    public static function Factory(): ItemList
    {
        $class = get_called_class();
        $listName = $class::LIST_NAME;
        /** @var PredisClient $redis */
        $redis = Baaz::Container()->get(PredisClient::class);
        self::$metaManager = new ItemListManager($redis);

        return new self(self::$metaManager, $listName);
    }

    public function getNextItems($count = 1): ?array
    {
        $iter = 0;
        $items = [];
        while (count($items) < $count && $iter < ($count * 2)) {
            ++$iter;
            $items[] = parent::getNextItem();
        }

        return $items;
    }
}
