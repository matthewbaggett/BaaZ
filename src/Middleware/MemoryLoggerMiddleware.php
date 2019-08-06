<?php

namespace Baaz\Middleware;

use Baaz\Redis\MemoryUsage;
use Predis\Client as Predis;
use Slim\Http\Request;
use Slim\Http\Response;
use ⌬\Services\EnvironmentService;

class MemoryLoggerMiddleware
{
    /** @var Predis */
    private $predis;
    /** @var EnvironmentService */
    private $environmentService;
    /** @var MemoryUsage */
    private $memoryUsage;

    public function __construct(
        Predis $predis,
        EnvironmentService $environmentService,
        MemoryUsage $memoryUsage
    ) {
        $this->predis = $predis;
        $this->environmentService = $environmentService;
        $this->memoryUsage = $memoryUsage;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $response = $next($request, $response);
        $serviceName = $this->environmentService->get('SERVICE_NAME', 'http');

        $this->memoryUsage->updateMemory(sprintf(
            'memory:http:%s:%s',
            $serviceName,
            gethostname()
        ));

        return $response;
    }
}
