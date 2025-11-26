<?php

/**
 *  Copyright (c) 2025 Rhyme Digital, LLC.
 *
 *  @link			https://rhyme.digital
 *  @license		https://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */

declare(strict_types=1);

namespace Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint;

use Rhyme\ContaoIsotopePaytraceBundle\Connector\Client;
use Psr\Http\Message\ResponseInterface;

class Token extends BaseEndpoint
{
    /**
     * API endpoint
     * @var string
     */
    protected static string $endpoint = 'token';

    /**
     * @param Client $client
     * @param string $clientId
     * @param string $clientSecret
     * @return ResponseInterface
     */
    public function getToken(Client $client, string $clientId, string $clientSecret): ResponseInterface
    {
        return $client->post(static::getEndpoint(), [
            'form_params' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]
        ]);
    }
}