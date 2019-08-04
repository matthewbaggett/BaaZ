<?php

namespace Baaz\Controllers;

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
    }

    /**
     * @route GET image/{imageUUID}.json
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function image(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $imageUUID = $request->getAttribute('imageUUID');

        $image = Image::Factory()->load($imageUUID);

        return $response->withJson([
            'Status' => 'Okay',
            'Image' => $image->__toArray(),
        ]);
    }

}
