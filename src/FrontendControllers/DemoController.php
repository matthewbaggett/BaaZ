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
use Predis\Client as PredisClient;

class DemoController extends HtmlController
{
    use Traits\RedisClientTrait;

    /** @var Configuration */
    private $configuration;
    /** @var PredisClient */
    private $redis;
    /** @var Logger */
    private $logger;
    /** @var GuzzleClient */
    private $guzzle;

    public function __construct(
        Twig $twig,
        Configuration $configuration,
        PredisClient $redis,
        Logger $logger
    ) {
        parent::__construct($twig);

        $this->configuration = $configuration;
        $this->redis = $redis;
        $this->logger = $logger;

        $this->guzzle = new GuzzleClient([
            'base_uri' => 'http://'.gethostname(),
            'timeout' => 2.0,
        ]);

        $this->redis->client('SETNAME',$this->getCalledClassStub());
    }

    /**
     * @route GET demo
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    public function product(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->setTitle('Demo of Widgits');

        return $this->renderHtml($request, $response, 'Template/Demo.twig');
    }
}
