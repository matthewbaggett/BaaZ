<?php

namespace Baaz\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;
use Predis\Client as Predis;
use ⌬\Services\EnvironmentService;

class MemoryLoggerMiddleware
{
    /** @var Predis */
    private $predis;
    /** @var EnvironmentService */
    private $environmentService;

    public function __construct(
        Predis $predis,
        EnvironmentService $environmentService
    )
    {
        $this->predis = $predis;
        $this->environmentService = $environmentService;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $response = $next($request, $response);
        $serviceName = $this->environmentService->get('SERVICE_NAME', 'http');
        $this->predis->rpush(sprintf("memory:http:%s:%s", $serviceName, gethostname()), [memory_get_peak_usage()]);
        $this->predis->ltrim(sprintf("memory:http:%s:%s", $serviceName, gethostname()),0,100);
        return $response;
    }
}
