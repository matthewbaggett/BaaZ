<?php

namespace Baaz\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;
use Predis\Client as Predis;

class MemoryLoggerMiddleware
{
    /** @var Predis */
    private $predis;

    public function __construct(
        Predis $predis
    )
    {
        $this->predis = $predis;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $response = $next($request, $response);
        $this->predis->setex(sprintf("memory:http:%s", gethostname()), 60, memory_get_peak_usage());
        return $response;
    }
}
