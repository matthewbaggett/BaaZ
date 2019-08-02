<?php
namespace Baaz\Models;

class Product extends MultiMediaModel
{
    protected $brand;
    protected $categoryPath;
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
    protected $imageURL;
    protected $material;
    protected $name;
    protected $price;
    protected $productURL;
    protected $productId;
    protected $pub;
    protected $shop2MarketIdentifier;
    protected $shop2MarketShopId;
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
                #throw new \Exception(sprintf("%s does not contain property %s", __CLASS__, $k));
                die(sprintf("%s does not contain property %s", __CLASS__, $k) . PHP_EOL);
                break;
            case 'set':
                if(property_exists($this, $k)){
                    if($this->$k != $arguments[0]) {
                        $this->$k = $arguments[0];
                        $this->__isDirty = true;
                    }
                    return $this;
                }
                #throw new \Exception(sprintf("%s does not contain property %s", __CLASS__, $k));
                die(sprintf("%s does not contain property %s", __CLASS__, $k) . PHP_EOL);
                break;
            default:
                #throw new \Exception(sprintf("%s does not contain function %s", __CLASS__, $name));
                die(sprintf("%s does not contain function %s", __CLASS__, $name) . PHP_EOL);
        }
    }

    protected function __map(array $inputData, array $mapping) : self
    {
        foreach($inputData as $k => $v){
            if(isset($mapping[$k])){
                $newK = $mapping[$k];
                $setter = "set{$newK}";
            }else{
                $setter = "set{$k}";
            }
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
            ]
        );
    }

    public function save() : self
    {
        if(!$this->__isDirty){
            return $this;
        }
        $cacheItem = $this->__cache->getItem($this->uuid->__toString());
        foreach(get_object_vars($this) as $k => $v){
            \Kint::dump($k, $v);
        }exit;
        return $this;
    }
}