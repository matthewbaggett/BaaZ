<?php

namespace Baaz\Controllers;

use Baaz\Models\Product;
use Predis\Client as Predis;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Solarium\Client as SolrClient;
use ⌬\Configuration\Configuration;
use ⌬\Controllers\Abstracts\Controller;
use ⌬\Log\Logger;

class StatusApiController extends Controller
{
    protected const FIELDS_WE_CARE_ABOUT = ['Brand', 'Name', 'Description'];
    /** @var Configuration */
    private $configuration;
    /** @var Predis */
    private $redis;
    /** @var Logger */
    private $logger;
    /** @var SolrClient */
    private $solr;

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
    }

    /**
     * @route GET v1/status.json
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function status(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $memoryKeys = $this->redis->keys("memory:*");
        $memoryUsage = [];
        foreach($memoryKeys as $memoryKey){
            list($memory, $set, $task, $node) = explode(":", $memoryKey);
            $bytes = (int)$this->redis->get($memoryKey);
            $megabytes = $bytes / 1024 / 1024;
            $memoryUsage[ucfirst($set)][ucfirst($task)][strtoupper($node)] = number_format($megabytes,2) . "MB";
        }
        return $response->withJson([
            'Status' => 'Okay',
            'Products' => $this->redis->get("count:products"),
            'Queues' => [
                'Solr' => $this->redis->get("count:worker-queue-solr"),
                'Image' => $this->redis->get("count:worker-queue-image"),
            ],
            'Memory' => $memoryUsage,
        ]);
    }
}
