<?php

namespace Baaz\Controllers;

use Predis\Client as Predis;
use Predis\Collection\Iterator\Keyspace;
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
        $this->redis->client('SETNAME', get_called_class());
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
        $memoryUsage = [];
        foreach (new Keyspace($this->redis, 'memory:*') as $memoryKey) {
            list($memory, $set, $task, $node) = explode(':', $memoryKey);
            $bytes = $this->redis->lrange($memoryKey, 0, 99);
            $bytes = ceil(array_sum($bytes) / count($bytes));
            $megabytes = $bytes / 1024 / 1024;
            $memoryUsage[ucfirst($set)][ucfirst($task)][strtoupper($node)] = number_format($megabytes, 2).'MB';
        }

        $slowLog = [];
        list($serverTime, $microsecondsPast) = $this->redis->time();
        $slowQueries = $this->redis->slowlog('GET', 100);
        usort($slowQueries, function ($a, $b) { return $a['duration'] <= $b['duration']; });
        foreach ($slowQueries as $slowQuery) {
            $slowLog[] = sprintf(
                '(%s => %s) %s in %sms (%s sec ago)',
                $slowQuery['clientIp'] ?? 'Unknown Client Connections',
                $slowQuery['clientName'] ?? 'Unknown Client Name',
                implode(' ', $slowQuery['command']),
                number_format($slowQuery['duration'] / 1000, 0),
                $serverTime - $slowQuery['timestamp'],
            );
        }

        return $response->withJson([
            'Status' => 'Okay',
            'Products' => $this->redis->get('count:products'),
            'Queues' => [
                'Solr' => $this->redis->get('count:worker-queue-solr'),
                'SolrReject' => $this->redis->get('count:worker-queue-solr-reject'),
                'Image' => $this->redis->get('count:worker-queue-image'),
                'ImageFail' => $this->redis->get('count:worker-queue-image-failed'),
            ],
            'SlowLog' => $slowLog,
            'Memory' => $memoryUsage,
        ]);
    }
}
