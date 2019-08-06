<?php

namespace Baaz\Redis;

use Predis\Command\ServerSlowlog;

class PatchedServerSlowlog extends ServerSlowlog
{
    /**
     * {@inheritdoc}
     */
    public function parseResponse($data)
    {
        if (is_array($data)) {
            $log = [];

            foreach ($data as $index => $entry) {
                $log[$index] = [
                    'id' => $entry[0],
                    'timestamp' => $entry[1],
                    'duration' => $entry[2],
                    'command' => $entry[3],
                    'clientIp' => $entry[4] ?? null,
                    'clientName' => $entry[5] ?? null,
                ];
            }

            return $log;
        }

        return $data;
    }
}
