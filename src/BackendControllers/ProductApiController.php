<?php

namespace Baaz\Controllers;

use Baaz\Models\Product;
use Predis\Client as Predis;
use Predis\Collection\Iterator\Keyspace;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use ⌬\Configuration\Configuration;
use ⌬\Controllers\Abstracts\Controller;
use ⌬\Log\Logger;

class ProductApiController extends Controller
{
    /** @var Configuration */
    private $configuration;
    /** @var Predis */
    private $redis;
    /** @var Logger */
    private $logger;

    public function __construct(
        Configuration $configuration,
        Predis $redis,
        Logger $logger
    ) {
        $this->configuration = $configuration;
        $this->redis = $redis;
        $this->logger = $logger;
    }

    /**
     * @route GET v1/api/product/{productUUID}.json
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function product(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $productUUID = $request->getAttribute('productUUID');

        $product = (new Product($this->redis))->load($productUUID);

        return $response->withJson([
            'Status' => 'Okay',
            'Product' => $product->__toArray(),
        ]);
    }

    /**
     * @route GET v1/api/products
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function products(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $match = 'product:*';

        $productUUIDs = $this->redis->scan(0, ['match' => $match, 'count' => 20]);

        $products = [];

        return $response->withJson([
            'Status' => 'Okay',
            'Products' => $products
        ]);
    }
}
