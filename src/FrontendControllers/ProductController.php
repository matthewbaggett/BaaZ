<?php

namespace Baaz\Controllers;

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

class ProductController extends HtmlController
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

        $this->guzzle = new GuzzleClient([
            'base_uri' => 'http://backend',
            'timeout' => 2.0,
        ]);
    }

    /**
     * @route GET p/{productUUID}/{slug}
     * @route GET product/{productUUID}
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function product(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $productUUID = $request->getAttribute('productUUID');

        $productResponse = $this->apiRequest('GET', "api/product/{$productUUID}");

        $this->setTitle($productResponse->Product->Name);

        return $this->renderHtml($request, $response, 'Product/Show.twig', (array) $productResponse);
    }

    protected function apiRequest(string $method = 'GET', string $url)
    {
        $start = microtime(true);
        $response = $this->guzzle->request($method, $url);
        $timeToGet = microtime(true) - $start;
        $this->logger->critical(sprintf(
            'API Took %sms to load  %s',
            number_format($timeToGet * 1000),
            $url
        ));
        $response->getBody()->rewind();

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }
}
