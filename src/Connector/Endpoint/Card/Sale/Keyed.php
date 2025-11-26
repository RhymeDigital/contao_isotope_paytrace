<?php

/**
 *  Copyright (c) 2025 Rhyme Digital, LLC.
 *
 *  @link			https://rhyme.digital
 *  @license		https://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */

declare(strict_types=1);

namespace Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\Card\Sale;

use Rhyme\ContaoIsotopePaytraceBundle\Connector\Client;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\BaseEndpoint;
use Psr\Http\Message\ResponseInterface;

class Keyed extends BaseEndpoint
{
    /**
     * @var string
     */
    protected static string $endpoint = 'card/sale/keyed';

    /**
     * @param Client $client
     * @param array  $data
     * @param string $authToken
     * @return ResponseInterface
     */
    public function sendRequest(Client $client, array $data, string $authToken): ResponseInterface
    {
        return $client->post(static::getEndpoint(), [
            'headers' => [
                'X-Integrator-Id' => '9987RhymeDig',
                'X-Permalinks'    => true,
                'Content-Type'    => 'application/json',
                'Cache-Control'   => 'no-cache',
                'Authorization' => 'Bearer '.$authToken
            ],
            'json' => $data
        ]);
    }
}