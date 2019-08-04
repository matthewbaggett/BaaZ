<?php

namespace Baaz\Controllers;

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
    use Traits\ApiTrait;

    /** @var Configuration */
    private $configuration;
    /** @var Redis */
    private $redis;
    /** @var Logger */
    private $logger;

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

        $productResponse = $this->apiRequest('GET', "v1/api/product/{$productUUID}.json");

        $this->setTitle($productResponse['Product']['Name']);

        return $this->renderHtml($request, $response, 'Product/Show.twig', (array) $productResponse);
    }

    /**
     * @route GET / weight=-10
     * @route GET l/random
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function homepage(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $productsResponse = $this->apiRequest('GET', 'v1/api/products?random&count=20');

        $this->setTitle('20 Random Products!');

        $this->addCss(__DIR__.'/../../assets/starbursts.css');

        return $this->renderHtml($request, $response, 'Product/List.twig', (array) $productsResponse);
    }
}
