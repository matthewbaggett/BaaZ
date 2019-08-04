<?php

namespace Baaz\Models;

use Predis\Client as Predis;
use Predis\Pipeline\Pipeline;
use ⌬\UUID\UUID;

class MultiMediaModel
{
    /** @var string */
    protected $uuid;
    /** @var bool */
    protected $__isDirty = false;
    /** @var Predis */
    private $__predis;

    public function __construct(
        Predis $predis
    ) {
        $this->__predis = $predis;
        $this->uuid = UUID::v4();
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getUuidShort() : string
    {
        return substr($this->getUuid(), 0, 7);
    }

    /**
     * @param string $uuid
     * @return MultiMediaModel
     */
    public function setUuid(string $uuid): MultiMediaModel
    {
        $this->uuid = $uuid;
        $this->__isDirty = true;
        return $this;
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

    public function load($uuid): self
    {
        if (strlen($uuid) < UUID::EXPECTED_LENGTH) {
            // @todo Do a partial match here. This requires a search
        }
        $this->setUuid($uuid);
        $key = $this->getStorageKey();

        $hmgetResult = array_combine($this->getValidFields(), $this->getRedis()->hmget($key, $this->getValidFields()));

        foreach ($hmgetResult as $k => $v) {
            $setter = "set{$k}";
            $this->{$setter}($v);
        }

        return $this;
    }

    protected function getStorageKey() : string
    {
        return sprintf(
            '%s:%s',
            $this->getClassStump(),
            $this->uuid,
        );
    }

    public function save(Pipeline $pipeline = null, $savePipeline = true): self
    {
        if (!$this->__isDirty) {
            return $this;
        }

        if (!$pipeline) {
            $pipeline = $this->getRedis()->pipeline();
        }

        $dict = [];
        foreach ($this->getValidFields() as $field) {
            if ($this->{$field}) {
                if (is_object($this->{$field}) || is_array($this->{$field})) {
                    $this->{$field} = \GuzzleHttp\json_encode($this->{$field});
                }
                $dict[$field] = $this->{$field};
            }
        }

        # \Kint::dump($this->getStorageKey(), $dict); sleep(30);

        $pipeline->hmset($this->getStorageKey(), $dict);

        if ($savePipeline) {
            $pipeline->flushPipeline(true);
        }

        printf(
            '%s %s %s to Redis as %d keys %s'.PHP_EOL,
            $savePipeline ? 'Wrote' : 'Queued',
            ucfirst($this->getClassStump()),
            property_exists($this, 'name') ? $this->name : $this->getUuidShort(),
            count($dict),
            method_exists($this, 'getSlug') ? sprintf('( http://baaz.local/%s )', $this->getSlug()) : null
        );

        return $this;
    }

    private function getValidFields(): array
    {
        $valid = [];
        foreach (array_keys(get_object_vars($this)) as $field) {
            if ('__' == substr($field, 0, 2)) {
                continue;
            }
            $valid[] = $field;
        }

        return $valid;
    }

    protected function getClassStump(): string
    {
        $classElem = explode('\\', get_called_class());

        return strtolower(end($classElem));
    }
}
