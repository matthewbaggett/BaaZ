<?php

namespace Baaz;

class QueuesAndLists
{
    public const ListProducts = 'products';
    public const QueueWorkerDownloadImages = 'worker-download-images';
    public const QueueWorkerDownloadImagesFailed = 'worker-download-images-failed';
    public const QueueWorkerPushSolr = 'worker-push-solr';
    public const QueueWorkerPushSolrFailed = 'worker-push-solr-failed';
}
