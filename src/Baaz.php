<?php

namespace Baaz;

use Baaz\Middleware\MemoryLoggerMiddleware;
use Baaz\Redis\PatchedServerSlowlog;
use Predis\Client as Predis;
use Slim;
use Solarium\Client as SolrClient;
use ⌬\Services\EnvironmentService;
use ⌬\⌬;

class Baaz extends ⌬
{
    public function setupDependencies(): void
    {
        parent::setupDependencies();

        $this->container[SolrClient::class] = function (Slim\Container $c) {
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

        $this->container[Predis::class] = function (Slim\Container $c) {
            /** @var EnvironmentService $environmentService */
            $environmentService = $c->get(EnvironmentService::class);
            if ($environmentService->isSet('REDIS_HOST')) {
                $redisMasterHosts = explode(',', $environmentService->get('REDIS_HOST'));
            }
            if ($environmentService->isSet('REDIS_HOST_MASTER')) {
                $redisMasterHosts = explode(',', $environmentService->get('REDIS_HOST_MASTER'));
            }
            if ($environmentService->isSet('REDIS_HOST_SLAVE')) {
                $redisSlaveHosts = explode(',', $environmentService->get('REDIS_HOST_SLAVE'));
            }

            $predis = new Predis($redisMasterHosts[0]);
            $predis->getProfile()->defineCommand('SLOWLOG', new PatchedServerSlowlog());

            return $predis;
        };
    }

    public function setupMiddlewares(): void
    {
        parent::setupMiddlewares();
        $this->app->add($this->container->get(MemoryLoggerMiddleware::class));
    }
}
