<?php

namespace Baaz\Controllers;

use Baaz\Controllers\Traits;
use Baaz\Lists\ProductList;
use Baaz\Models\Product;
use Predis\Client as Predis;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Solarium\Client as SolrClient;
use Solarium\Core\Query\Result\ResultInterface;
use Solarium\QueryType\Select\Query\Query;
use ⌬\Configuration\Configuration;
use ⌬\Controllers\Abstracts\Controller;
use ⌬\Log\Logger;

class ProductApiController extends Controller
{
    use Traits\RedisClientTrait;

    protected const FIELDS_WE_CARE_ABOUT = ['Brand', 'Name', 'Description'];
    /** @var Configuration */
    private $configuration;
    /** @var Predis */
    private $redis;
    /** @var Logger */
    private $logger;
    /** @var SolrClient */
    private $solr;
    /** @var ProductList */
    private $productList;

    public function __construct(
        Configuration $configuration,
        Predis $redis,
        Logger $logger,
        SolrClient $solr
    ) {
        $this->configuration = $configuration;
        $this->redis = $redis;
        $this->logger = $logger;
        $this->solr = $solr;
        $this->redis->client('SETNAME', $this->getCalledClassStub());
        $this->productList = ProductList::Factory();
    }

    /**
     * @route GET v1/api/product/random.json
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function product_randomSingle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $product = $this->productList->getNextItem();

        //!\Kint::dump($product);exit;

        return $response->withJson([
            'Status' => 'Okay',
            'Products' => $product,
        ]);
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

        $product = $this->productList->find($productUUID);

        //!\Kint::dump($product);exit;

        return $response->withJson([
            'Status' => 'Okay',
            'Product' => $product,
        ]);
    }

    /**
     * @route GET v1/api/search/{searchTerm}.json
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function search(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $products = [];
        $search = trim($request->getAttribute('searchTerm'));

        /** @var Query $query */
        $query = $this->solr->createSelect();

        // Pagination bits
        $resultsPerPage = (!empty($request->getQueryParam('perPage'))) ? (int) $request->getQueryParam('perPage') : 15;
        $currentPage = (!empty($request->getQueryParam('currentPage'))) ? (int) $request->getQueryParam('currentPage') : 1;
        $query->setRows($resultsPerPage);
        $query->setStart(($currentPage - 1) * $resultsPerPage);

        $query->setDocumentClass(Product::class);

        foreach (self::FIELDS_WE_CARE_ABOUT as $field) {
            $query->setQuery($field.':'.$search);
        }

        //$hl = $query->getHighlighting();
        //$hl->setFields($fieldsWeCareAbout);
        //$hl->setSimplePrefix('<b>');
        //$hl->setSimplePostfix('</b>');

        // add debug settings
        $debug = $query->getDebug();
        $debug->setExplainOther('id:MA*');

        // this executes the query and returns the result
        $resultset = $this->solr->execute($query);
        //$this->debugSolr($resultset);

        foreach ($resultset as $product) {
            // @var $product Product
            $products[] = $product->__toArray();
        }

        return $response->withJson([
            'Status' => 'Okay',
            'Search' => $search,
            'Products' => $products,
        ]);
    }

    /**
     * @route GET v1/api/random.json
     *
     * Accepts parameters perPage, currentPage
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function randomProducts(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $products = [];

        /** @var Query $query */
        $query = $this->solr->createSelect();
        // Pagination bits
        $resultsPerPage = (!empty($request->getQueryParam('perPage'))) ? (int) $request->getQueryParam('perPage') : 15;
        $currentPage = (!empty($request->getQueryParam('currentPage'))) ? (int) $request->getQueryParam('currentPage') : 1;
        $query->setRows($resultsPerPage);
        $query->setStart(($currentPage - 1) * $resultsPerPage);

        $query->setDocumentClass(Product::class);

        // this executes the query and returns the result
        $resultset = $this->solr->execute($query);

        foreach ($resultset as $product) {
            // @var $product Product
            $products[] = $product->__toArray();
        }
        //!\Kint::dump($products); exit;
        return $response->withJson([
            'Status' => 'Okay',
            'Products' => $products,
        ]);
    }

    /**
     * @route GET v1/api/products.json
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function products(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // @todo not sure how to implement not-random here yet. Its always random.
        $random = null !== $request->getQueryParam('random');
        $count = (int) ($request->getQueryParam('count') ?? 5);

        $products = $this->productList->getNextItems($count);

        return $response->withJson([
            'Status' => 'Okay',
            'Products' => $products,
        ]);
    }

    private function debugSolr(ResultInterface $resultset)
    {
        $debugResult = $resultset->getDebug();
        // display the debug results
        echo '<h1>Debug data</h1>';
        echo 'Querystring: '.$debugResult->getQueryString().'<br/>';
        echo 'Parsed query: '.$debugResult->getParsedQuery().'<br/>';
        echo 'Query parser: '.$debugResult->getQueryParser().'<br/>';
        echo 'Other query: '.$debugResult->getOtherQuery().'<br/>';

        echo '<h2>Explain data</h2>';
        foreach ($debugResult->getExplain() as $key => $explanation) {
            echo '<h3>Document key: '.$key.'</h3>';
            echo 'Value: '.$explanation->getValue().'<br/>';
            echo 'Match: '.((true == $explanation->getMatch()) ? 'true' : 'false').'<br/>';
            echo 'Description: '.$explanation->getDescription().'<br/>';
            echo '<h4>Details</h4>';
            foreach ($explanation as $detail) {
                echo 'Value: '.$detail->getValue().'<br/>';
                echo 'Match: '.((true == $detail->getMatch()) ? 'true' : 'false').'<br/>';
                echo 'Description: '.$detail->getDescription().'<br/>';
                echo '<hr/>';
            }
        }

        echo '<h2>ExplainOther data</h2>';
        foreach ($debugResult->getExplainOther() as $key => $explanation) {
            echo '<h3>Document key: '.$key.'</h3>';
            echo 'Value: '.$explanation->getValue().'<br/>';
            echo 'Match: '.((true == $explanation->getMatch()) ? 'true' : 'false').'<br/>';
            echo 'Description: '.$explanation->getDescription().'<br/>';
            echo '<h4>Details</h4>';
            foreach ($explanation as $detail) {
                echo 'Value: '.$detail->getValue().'<br/>';
                echo 'Match: '.((true == $detail->getMatch()) ? 'true' : 'false').'<br/>';
                echo 'Description: '.$detail->getDescription().'<br/>';
                echo '<hr/>';
            }
        }

        echo '<h2>Timings (in ms)</h2>';
        echo 'Total time: '.$debugResult->getTiming()->getTime().'<br/>';
        echo '<h3>Phases</h3>';
        foreach ($debugResult->getTiming()->getPhases() as $phaseName => $phaseData) {
            echo '<h4>'.$phaseName.'</h4>';
            foreach ($phaseData as $class => $time) {
                echo $class.': '.$time.'<br/>';
            }
            echo '<hr/>';
        }
        exit;
    }
}
