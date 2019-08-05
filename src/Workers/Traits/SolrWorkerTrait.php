<?php

namespace Baaz\Workers\Traits;

use Baaz\Baaz;
use Solarium\Client as SolrClient;
use Solarium\Exception\ExceptionInterface as SolrException;

trait SolrWorkerTrait
{
    /** @var SolrClient */
    protected $solr;

    public function getSolr(): SolrClient
    {
        if (!$this->solr) {
            $this->solr = Baaz::Container()->get(SolrClient::class);
        }

        return $this->solr;
    }

    public function doSolrPing(): bool
    {
        $ping = $this->solr->createPing();

        try {
            $this->solr->ping($ping);

            return true;
        } catch (SolrException $e) {
            return false;
        }
    }
}
