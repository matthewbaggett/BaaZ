<?php

namespace Baaz\Controllers;

use Baaz\Filesystem\ImageFilesystem;
use Baaz\Models\Image;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Body;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;
use ⌬\Configuration\Configuration;
use ⌬\Controllers\Abstracts\HtmlController;
use ⌬\Log\Logger;
use ⌬\Redis\Redis;

class ImageController extends HtmlController
{
    use Traits\ApiTrait;
    use Traits\RedisClientTrait;

    /** @var Configuration */
    private $configuration;
    /** @var Redis */
    private $redis;
    /** @var Logger */
    private $logger;
    /** @var ImageFilesystem */
    private $imageFilesystem;

    public function __construct(
        Twig $twig,
        Configuration $configuration,
        Redis $redis,
        Logger $logger,
        ImageFilesystem $imageFilesystem
    ) {
        parent::__construct($twig);

        $this->configuration = $configuration;
        $this->redis = $redis;
        $this->logger = $logger;
        $this->imageFilesystem = $imageFilesystem;

        #$this->redis->client('SETNAME', $this->getCalledClassStub());
    }

    /**
     * @route GET i/{productUUID}/{imageUUID}/{dimensions}.jpg
     * @route GET image/{imageUUID}/{dimensions}.jpg
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function image(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $productUUID = $request->getAttribute('productUUID');

        $productResponse = $this->apiRequest('GET', "v1/api/product/{$productUUID}.json");

        foreach($productResponse['Product']['Images'] as $image){
            $file = $this->imageFilesystem->get($image['StoragePath']);

            $response = $response->withBody(new Body(fopen('php://temp', 'r+')));
            $response->getBody()->write($file->read());

            return $response->withHeader('Content-Type', 'image/jpeg');
        }

        return $response->withStatus(404,"Not found");
    }
}
