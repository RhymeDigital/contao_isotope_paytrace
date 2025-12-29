<?php

/**
 *  Copyright (c) 2025 Rhyme Digital, LLC.
 *
 *  @link			https://rhyme.digital
 *  @license		https://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */

declare(strict_types=1);

namespace Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\Card\Batch;

use GuzzleHttp\Exception\GuzzleException;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Client;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\BaseEndpoint;
use Psr\Http\Message\ResponseInterface;

class Refund extends BaseEndpoint
{
    /**
     * @var string
     */
    protected static string $endpoint = 'card/batch/refund';

    /**
     * @param Client $client
     * @param array  $data
     * @param string $authToken
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function sendRequest(Client $client, array $data, string $authToken): ResponseInterface
    {
        return $client->post(static::getEndpoint(), [
            'headers' => [
                'Content-Type'    => 'application/json',
                'Cache-Control'   => 'no-cache',
                'Authorization' => 'Bearer '.$authToken
            ],
            'json' => $data
        ]);
    }
}