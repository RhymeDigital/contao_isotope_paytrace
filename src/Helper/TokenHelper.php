<?php

/**
 *  Copyright (c) 2025 Rhyme Digital, LLC.
 *
 * @link            https://rhyme.digital
 * @license         https://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */

declare(strict_types=1);

namespace Rhyme\ContaoIsotopePaytraceBundle\Helper;

use JsonException;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Client as PaytraceClient;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\Token;

class TokenHelper
{
    /**
     * @param PaytraceClient $client
     * @param string $username
     * @param string $password
     * @return string
     * @throws JsonException
     */
    public function getAccessToken(PaytraceClient $client, string $username, string $password): string
    {
        $tokenEndpoint = new Token();

        // Get a token (don't bother doing the refresh)
        $tokenResponse = $tokenEndpoint->getToken(
            $client,
            $username,
            $password
        );

        $tokenData = \json_decode((string)$tokenResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if (($tokenData['status'] ?? null) !== 'success'
            || empty($accessToken = $tokenData['data']['access_token'])
        ) {
            throw new \Exception($GLOBALS['TL_LANG']['ERR']['access_token_retrieval']);
        }

        return $accessToken;
    }
}