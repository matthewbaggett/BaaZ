<?php

namespace Baaz\Models;

use Baaz\Baaz;
use Baaz\Filesystem\ImageFilesystem;

class Image extends File
{
    protected $__imageFilesystem;

    protected $productUUID;

    public function __construct(
        ImageFilesystem $imageFilesystem
    ) {
        $this->__imageFilesystem = $imageFilesystem;
        parent::__construct();
    }

    public function __toArray()
    {
        $arr = parent::__toArray();

        $arr['StoragePath'] = $this->getStoragePath();
        $arr['Slug'] = $this->getSlug();

        ksort($arr);

        return $arr;
    }

    /**
     * @return mixed
     */
    public function getProductUUID()
    {
        return $this->productUUID;
    }

    /**
     * @param mixed $productUUID
     *
     * @return Image
     */
    public function setProductUUID($productUUID)
    {
        $this->productUUID = $productUUID;

        $this->__isDirty = true;

        return $this;
    }

    public static function Factory(): Image
    {
        return Baaz::Container()->get(__CLASS__);
    }

    //public function getStorageKey(): string
    //{
    //    return sprintf(
    //        '%s:product(%s):image_id(%s)',
    //        $this->getClassStump(),
    //        $this->getProductUUID() ?? "*",
    //        $this->uuid,
    //    );
    //}

    public function getStoragePath(): string
    {
        return sprintf(
            '%s/%s/%s/%s',
            substr($this->uuid, 0, 2),
            substr($this->uuid, 2, 2),
            substr($this->uuid, 4, 2),
            substr($this->uuid, 6)
        );
    }

    public function setFileData(string $data): self
    {
        $this->__imageFilesystem->put($this->getStoragePath(), $data);

        return $this;
    }

    public function getSlug(): string
    {
        return sprintf(
            'i/%s/%sx%s.%s',
            $this->uuid,
            300,
            300,
            'jpg'
        );
    }
}
