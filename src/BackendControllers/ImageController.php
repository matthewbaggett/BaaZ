<?php

namespace Baaz\Controllers;

use Baaz\Lists\ImageList;
use Baaz\Models\Image;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;
use ⌬\Configuration\Configuration;
use ⌬\Controllers\Abstracts\HtmlController;
use ⌬\Log\Logger;
use ⌬\Redis\Redis;

class ImageController extends HtmlController
{
    use Traits\RedisClientTrait;

    /** @var Configuration */
    private $configuration;
    /** @var Redis */
    private $redis;
    /** @var Logger */
    private $logger;
    /** @var GuzzleClient */
    private $guzzle;

    public function __construct(
        Twig $twig,
        Configuration $configuration,
        Redis $redis,
        Logger $logger
    ) {
        parent::__construct($twig);

        $this->configuration = $configuration;
        $this->redis = $redis;
        $this->logger = $logger;
        #$this->redis->client('SETNAME', $this->getCalledClassStub());
    }

    /**
     * @route GET v1/image/{imageUUID}.json
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function image(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $imageList = ImageList::Factory();
        $imageUUID = $request->getAttribute('imageUUID');

        $image = $imageList->find($imageUUID);

        return $response->withJson([
            'Status' => 'Okay',
            'Image' => $image,
        ]);
    }
}
