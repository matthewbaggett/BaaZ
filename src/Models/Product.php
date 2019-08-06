<?php

namespace Baaz\Models;

use Solarium\Core\Query\DocumentInterface;
use ⌬\UUID\UUID;

class Product extends MultiMediaModel
{
    protected $brand;
    protected $category = [];
    protected $categoryPath;
    protected $campaignID;
    protected $channelCategoryId;
    protected $channelCategoryPath;
    protected $channelCategory = [];
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
    protected $id;
    protected $imageURL;
    protected $material;
    protected $name;
    protected $pictures;
    protected $price;
    protected $productURL;
    protected $productId;
    protected $pub;
    protected $shop2MarketIdentifier;
    protected $shop2MarketShopId;
    protected $size;
    protected $sku;
    protected $stock;
    protected $timeImported;
    protected $variantId;

    /** @var Image[] */
    protected $__relatedImages = [];

    public function __construct($query = [], $response = null)
    {
        parent::__construct($query, $response);
        if (!empty($query) && isset($query['Images'])) {
            foreach ($query['Images'] as $imageJson) {
                $imageJson = json_decode($imageJson, true);
                foreach ($imageJson as $image) {
                    $this->__relatedImages[] = Image::Factory()->load($image['Uuid']);
                }
            }
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

    public function __toArray()
    {
        $images = [];

        foreach ($this->__relatedImages as $image) {
            $images[] = $image->__toArray();
        }

        return array_merge(
            parent::__toArray(),
            [
                'TimeImportedAgo' => $this->timeImported ? $this->getTimeAgo()->inWordsFromStrings($this->timeImported) : null,
                'ReferringDomain' => $this->getReferringDomain(),
                'Slug' => $this->getSlug(),
                'Images' => $images,
            ]
        );
    }

    public function setEan($ean): self
    {
        if (strlen($ean) > 13) {
            $ean = substr($ean, 0, 13);
        }
        $this->ean = $ean;

        return $this;
    }

    public function ingest($json): self
    {
        $this->__map(
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

        $this->timeImported = date('Y-m-d H:i:s');
        $this->category = explode('>', $this->categoryPath);
        $this->categoryPath = explode('>', $this->channelCategoryPath);
        $this->uuid = UUID::v4();

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
        $this->pictures = [];
        $return = parent::load($uuid);

        if (is_string($this->pictures)) {
            $this->pictures = json_decode($this->pictures);
        }

        // Squash Nonsense giant arrays of pictures
        if (is_array($this->pictures) && count($this->pictures) > 50) {
            $this->pictures = [];
        }

        if (is_array($this->pictures) && count($this->pictures) > 0) {
            foreach ($this->pictures as $picture) {
                $this->__relatedImages[] = Image::Factory()->load($picture);
            }
        }

        return $return;
    }

    public function createSolrDocument(\Solarium\QueryType\Update\Query\Query $solrQuery): DocumentInterface
    {
        $solrDocument = $solrQuery->createDocument();
        foreach ($this->__toArray() as $k => $v) {
            if ($v) {
                if (!(is_string($v) || is_numeric($v))) {
                    $v = \GuzzleHttp\json_encode($v);
                } else {
                    $v = addslashes($v);
                }
                $solrDocument->{$k} = $v;
            }
        }

        return $solrDocument;
    }

    protected function getReferringDomain()
    {
        $url = parse_url($this->deeplink);

        if (isset($url['host'])) {
            return 'www.' == substr($url['host'], 0, 4) ? substr($url['host'], 4) : $url['host'];
        }

        return 'Somewhere!';
    }
}
