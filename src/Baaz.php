<?php

namespace Baaz;

use Baaz\Middleware\MemoryLoggerMiddleware;
use Slim\Container;
use Solarium\Client as SolrClient;
use ⌬\Services\EnvironmentService;
use ⌬\⌬;

class Baaz extends ⌬
{
    public function setupDependencies(): void
    {
        parent::setupDependencies();

        $this->container[SolrClient::class] = function (Container $c) {
            /** @var EnvironmentService $environmentService */
            $environmentService = $c->get(EnvironmentService::class);
            $solrHost = $environmentService->get('SOLR_HOST');
            $solrHost = parse_url($solrHost);
            $config = [
                'endpoint' => [
                    'localhost' => [
                        'scheme' => 'http', // or https
                        'host' => $solrHost['host'],
                        'port' => $solrHost['port'],
                        'path' => '/',
                        'core' => 'mycore',
                    ],
                ],
            ];

            return new SolrClient($config);
        };
    }

    public function setupMiddlewares(): void
    {
        parent::setupMiddlewares();
        $this->app->add($this->container->get(MemoryLoggerMiddleware::class));

    }
}
