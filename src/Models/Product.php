<?php
namespace Baaz\Models;

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
        $k = lcfirst(substr($name,3));

        switch(substr($name,0,3)){
            case 'get':
                if(property_exists($this, $k)){
                    return $this->$k;
                }
                throw new \Exception(sprintf("%s does not contain property %s", __CLASS__, $k));
                break;
            case 'set':
                if(property_exists($this, $k)){
                    if($this->$k != $arguments[0]) {
                        $this->$k = $arguments[0];
                        $this->__isDirty = true;
                    }
                    return $this;
                }
                throw new \Exception(sprintf("%s does not contain property %s", __CLASS__, $k));
                break;
            default:
                throw new \Exception(sprintf("%s does not contain function %s", __CLASS__, $name));
        }
    }

    protected function __map(array $inputData, array $mapping) : self
    {
        foreach($inputData as $k => $v){
            $setter = isset($mapping[$k]) ? "set{$mapping[$k]}" : "set{$k}";
            $this->$setter($v);
        }
        return $this;
    }

    public function ingest($json) : self
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

    public function load($uuid) : self
    {
        $keySpace = sprintf(
            "%s:{%s}:",
            "product",
            $uuid
        );

        // @todo replace this with a scan
        $keysToFetch = $this->__redis->keys($keySpace . "*");
        $mgetResult = $this->__redis->mget($keysToFetch);
        $data = array_combine($keysToFetch, $mgetResult);

        foreach($data as $k => $v){
            $k = str_replace($keySpace,"", $k);
            $setter = "set{$k}";
            $this->$setter($v);
        }
        return $this;
    }

    public function save() : self
    {
        if(!$this->__isDirty){
            return $this;
        }

        $keysCount = 0;
        foreach(get_object_vars($this) as $k => $v){
            if(substr($k, 0,2) == '__'){
                continue;
            }
            if($v) {
                $keysCount++;
                $key = sprintf(
                    "%s:{%s}:%s",
                    "product",
                    $this->uuid->__toString(),
                    $k
                );
                if(is_object($v) || is_array($v)){
                    $v = \GuzzleHttp\json_encode($v);
                }
                $this->__redis->set($key, $v);
            }
        }

        printf(
            "Wrote %s to Redis as %d keys" . PHP_EOL,
            $this->name,
            $keysCount
        );

        return $this;
    }

    public function getCacheableImageUrls(){
        return [
            $this->imageURL,
        ];
    }
}