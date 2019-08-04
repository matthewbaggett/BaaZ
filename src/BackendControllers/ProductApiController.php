<?php

namespace Baaz\Controllers;

use Baaz\Models\Product;
use Predis\Client as Predis;
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
        // @todo not sure how to implement random here yet.
        $random = null !== $request->getQueryParam('random');
        $count = (int) ($request->getQueryParam('count') ?? 5);

        $productUUIDs = $this->scanUntilEnoughFound('product:*', $count);

        $products = [];
        foreach ($productUUIDs as $productUUID) {
            $productUUID = str_replace('product:', '', $productUUID);
            $product = (new Product($this->redis))->load($productUUID);
            $products[] = $product->__toArray();
        }

        //\Kint::dump($count, $productUUIDs, $products);exit;

        return $response->withJson([
            'Status' => 'Okay',
            'Products' => $products,
        ]);
    }

    private function scanUntilEnoughFound($match, $count)
    {
        $found = [];
        $cursor = 0;
        $loopedAround = false;
        while (count($found) < $count && false == $loopedAround) {
            list($cursor, $keys) = $this->redis->scan($cursor, ['match' => $match, 'count' => $count]);
            $found = array_unique(array_merge($found, $keys));
            if (0 == $cursor) {
                $loopedAround = true;
            }
        }

        return array_slice($found, 0, $count);
    }
}
