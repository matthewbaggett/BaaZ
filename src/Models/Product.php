<?php

namespace Baaz\Models;

use Solarium\Client as SolrClient;
use Solarium\Core\Query\DocumentInterface;

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

    /** @var Image[] */
    protected $__relatedImages = [];

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
            $this->uuid,
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

    public function load($uuid): MultiMediaModel
    {
        $return = parent::load($uuid);

        $pictures = $this->getRedis()->lrange("product:{$uuid}:pictures", 0, 5);
        foreach($pictures as &$picture){
            $this->__relatedImages[] = Image::Factory()->load($picture);
        }

        return $return;
    }

    public function __toArray()
    {
        $images = [];
        foreach($this->__relatedImages as $image){
            $images[] = $image->__toArray();
        }
        return array_merge(
            parent::__toArray(),
            [
                'Images' => $images,
            ]
        );
    }

    public function createSolrDocument(\Solarium\QueryType\Update\Query\Query $solrQuery) : DocumentInterface
    {
        $solrDocument = $solrQuery->createDocument();
        foreach($this->__toArray() as $k => $v){
            if($v) {
                if (!(is_string($v) || is_numeric($v))) {
                    $v = \GuzzleHttp\json_encode($v);
                }
                $solrDocument->$k = $v;
            }
        }
        return $solrDocument;
    }
}
