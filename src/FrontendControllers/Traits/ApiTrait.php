<?php

namespace Baaz\Controllers\Traits;

use GuzzleHttp\Client as GuzzleClient;

trait ApiTrait
{
    /** @var GuzzleClient */
    protected $guzzle;

    protected function getGuzzle()
    {
        $this->guzzle = new GuzzleClient([
            'base_uri' => 'http://backend',
            'timeout' => 2.0,
            'decode_content' => 'gzip',
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        return $this->guzzle;
    }

    protected function apiRequest(string $method = 'GET', string $url): array
    {
        $start = microtime(true);
        $response = $this->getGuzzle()->request($method, $url);
        $timeToGet = microtime(true) - $start;
        $this->logger->critical(sprintf(
            'API Took %sms to load  %s',
            number_format($timeToGet * 1000),
            $url
        ));
        $response->getBody()->rewind();

        $responseObject = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);

        $responseObject['Request'] = [
            'Method' => $method,
            'Url' => $url,
        ];

        return $responseObject;
    }
}
