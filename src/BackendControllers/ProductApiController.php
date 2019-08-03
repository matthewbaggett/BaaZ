<?php

namespace Baaz\Controllers;

use Baaz\Models\Product;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use ⌬\Configuration\Configuration;
use ⌬\Controllers\Abstracts\Controller;
use ⌬\Log\Logger;
use ⌬\Redis\Redis;

class ProductApiController extends Controller
{
    /** @var Configuration */
    private $configuration;
    /** @var Redis */
    private $redis;
    /** @var Logger */
    private $logger;

    public function __construct(
        Configuration $configuration,
        Redis $redis,
        Logger $logger
    ) {
        $this->configuration = $configuration;
        $this->redis = $redis;
        $this->logger = $logger;
    }

    /**
     * @route GET api/product/{productUUID}
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
}
