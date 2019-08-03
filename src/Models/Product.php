<?php

namespace Baaz\Models;

use Predis\Pipeline\Pipeline;

class Product extends MultiMediaModel
{
    protected $brand;
    protected $categoryPath;
    protected $campaignID;
    protected $channelCategoryId;
    protected $channelCategoryPath;
    protected $colours;
    protected $currency;
    protected $deeplink;
    protected $deliveryCosts;
    protected $deliveryTime;
    protected $description;
    protected $ean;
    protected $enabled;
    protected $familyCode;
    protected $feedID;
    protected $gender;
    protected $imageURL;
    protected $material;
    protected $name;
    protected $price;
    protected $productURL;
    protected $productId;
    protected $pub;
    protected $shop2MarketIdentifier;
    protected $shop2MarketShopId;
    protected $size;
    protected $sku;
    protected $stock;
    protected $variantId;

    protected $__isDirty = false;

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

    protected function __map(array $inputData, array $mapping): self
    {
        foreach ($inputData as $k => $v) {
            $setter = isset($mapping[$k]) ? "set{$mapping[$k]}" : "set{$k}";
            $this->{$setter}($v);
        }

        return $this;
    }

    public function ingest($json): self
    {
        return $this->__map(
            $json,
            [
                'channel_cat_id' => 'channelCategoryId',
                'channel_cat_path' => 'channelCategoryPath',
                'color' => 'colours',
                'product_id' => 'productId',
                'Pub' => 'pub',
                's2m_identifier' => 'shop2MarketIdentifier',
                's2m_shop_id' => 'shop2MarketShopId',
                'variant_id' => 'variantId',
                'family_code' => 'familyCode',
            ]
        );
    }

    public function load($uuid): self
    {
        $key = sprintf('%s:%s', 'product', $uuid);

        $hmgetResult = array_combine($this->getValidFields(), $this->getRedis()->hmget($key, $this->getValidFields()));

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

        $pipeline->hmset(
            sprintf(
                '%s:%s',
                'product',
                $this->uuid->__toString(),
            ),
            $dict
        );

        if ($savePipeline) {
            $pipeline->flushPipeline(true);
        }

        printf(
            '%s %s to Redis as %d keys ( %s )'.PHP_EOL,
            $savePipeline ? 'Wrote' : 'Queued',
            $this->name,
            count($dict),
            sprintf('http://baaz.local/%s', $this->getSlug())
        );

        return $this;
    }

    public function getCacheableImageUrls()
    {
        return [
            $this->imageURL,
        ];
    }

    public function getSlug(): string
    {
        return sprintf(
            'p/%s/%s',
            $this->getUuid(), //substr((string) $this->getUuid(), 0, 7),
            str_replace(
                ' ',
                '-',
                substr(
                    preg_replace('/[^A-Za-z0-9 ]/', '', $this->getName()),
                    0,
                    40
                )
            )
        );
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
