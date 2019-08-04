<?php

namespace Baaz\Models;

use Predis\Client as Predis;
use Predis\Pipeline\Pipeline;
use Westsworld\TimeAgo;
use ⌬\UUID\UUID;

class MultiMediaModel
{
    /** @var string */
    protected $uuid;
    /** @var bool */
    protected $__isDirty = false;
    /** @var Predis */
    private $__predis;

    /** @var TimeAgo */
    private $__timeAgo;

    public function __construct(
        Predis $predis
    ) {
        $this->__predis = $predis;
        $this->uuid = UUID::v4();
        $this->__timeAgo = new TimeAgo();
    }

    public function __call($name, $arguments)
    {
        $k = lcfirst(substr($name, 3));

        switch (substr($name, 0, 3)) {
            case 'get':
                if (property_exists($this, $k)) {
                    return $this->{$k};
                }

                throw new \Exception(sprintf(
                    '%s does not contain property %s in %s',
                    __CLASS__,
                    $k,
                    '['.implode(', ', array_keys(get_object_vars($this))).']'
                ));

                break;
            case 'set':
                if (property_exists($this, $k)) {
                    if ($this->{$k} != $arguments[0]) {
                        $this->{$k} = $arguments[0];
                        $this->__isDirty = true;
                    }

                    return $this;
                }

                throw new \Exception(sprintf(
                    '%s does not contain property %s in %s',
                    __CLASS__,
                    $k,
                    '['.implode(', ', array_keys(get_object_vars($this))).']'
                ));

                break;
            default:
                throw new \Exception(sprintf('%s does not contain function %s', __CLASS__, $name));
        }
    }

    public function __toArray()
    {
        $array = [];
        foreach ($this->getValidFields() as $field) {
            $array[ucfirst($field)] = $this->{$field};
        }

        return $array;
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getUuidShort(): string
    {
        return substr($this->getUuid(), 0, 7);
    }

    /**
     * @param string $uuid
     *
     * @return MultiMediaModel
     */
    public function setUuid(string $uuid): MultiMediaModel
    {
        $this->uuid = $uuid;
        $this->__isDirty = true;

        return $this;
    }

    /**
     * @return Predis
     */
    public function getRedis(): Predis
    {
        return $this->__predis;
    }

    /**
     * @return TimeAgo
     */
    public function getTimeAgo(): TimeAgo
    {
        return $this->__timeAgo;
    }

    public function load($uuid): self
    {
        if (strlen($uuid) < UUID::EXPECTED_LENGTH) {
            // @todo Do a partial match here. This requires a search
        }
        $this->setUuid($uuid);
        $key = $this->getStorageKey();

        $fields = $this->getValidFields();
        $values = $this->getRedis()->hmget($key, $fields);
        $hmgetResult = array_combine($fields, $values);
        //\Kint::dump($key, $fields, $values, $hmgetResult);

        foreach ($hmgetResult as $k => $v) {
            $setter = "set{$k}";
            $this->{$setter}($v);
        }

        return $this;
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

        // \Kint::dump($this->getStorageKey(), $dict); sleep(30);

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

    protected function getStorageKey(): string
    {
        return sprintf(
            '%s:%s',
            $this->getClassStump(),
            $this->uuid,
        );
    }

    protected function getClassStump(): string
    {
        $classElem = explode('\\', get_called_class());

        return strtolower(end($classElem));
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
}
