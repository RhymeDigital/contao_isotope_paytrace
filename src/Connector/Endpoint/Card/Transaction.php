<?php

/**
 *  Copyright (c) 2025 Rhyme Digital, LLC.
 *
 * @link            https://rhyme.digital
 * @license         https://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */

declare(strict_types=1);

namespace Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\Card;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Client;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\BaseEndpoint;

class Transaction extends BaseEndpoint
{
    /**
     * @var string
     */
    protected static string $endpoint = 'card/transaction';

    /**
     * @param Client $client
     * @param string $transactionId
     * @param string $merchantId
     * @param string $authToken
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function sendRequest(Client $client, string $transactionId, string $merchantId, string $authToken): ResponseInterface
    {
        return $client->get(static::getEndpoint().'/'.$transactionId, [
            'headers' => [
                'Content-Type'    => 'application/json',
                'Cache-Control'   => 'no-cache',
                'Authorization' => 'Bearer '.$authToken
            ],
            'query' => ['merchant_id'=>$merchantId]
        ]);
    }

}