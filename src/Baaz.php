<?php

namespace Baaz;

use Slim\Container;
use ⌬\Services\EnvironmentService;
use ⌬\⌬;
use Solarium\Client as SolrClient;

class Baaz extends ⌬
{
    public function setupDependencies(): void
    {
        parent::setupDependencies();

        $this->container[SolrClient::class] = function (Container $c){
            /** @var EnvironmentService $environmentService */
            $environmentService = $c->get(EnvironmentService::class);
            $solrHost = $environmentService->get('SOLR_HOST');
            $solrHost = parse_url($solrHost);
            $config = array(
                'endpoint' => array(
                    'localhost' => array(
                        'scheme' => 'http', # or https
                        'host' => $solrHost['host'],
                        'port' => $solrHost['port'],
                        'path' => '/',
                        'core' => 'baaz',
                    )
                )
            );
            return new SolrClient($config);
        };
    }
}
