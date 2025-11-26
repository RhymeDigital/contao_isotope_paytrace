<?php

/**
 *  Copyright (c) 2025 Rhyme Digital, LLC.
 *
 *  @link			https://rhyme.digital
 *  @license		https://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */

declare(strict_types=1);

namespace Rhyme\ContaoIsotopePaytraceBundle\Connector;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class Client
{

    /**
     * Production endpoint
     * @var string
     */
    public const PROD_BASE_URI = 'https://api.paytrace.com/v3/';

    /**
     * Sandbox endpoint
     * @var string
     */
    public const SANDBOX_BASE_URI = 'https://api.sandbox.paytrace.com/v3/';

    /**
     * @var string
     */
    protected string $clientId;

    /**
     * @var string
     */
    protected string $clientSecret;

    /**
     * @var string
     */
    protected string $integratorId;

    /**
     * @var bool
     */
    protected bool $devMode;

    /**
     * Client
     * @var GuzzleClient
     */
    protected GuzzleClient $guzzleClient;

    /**
     * @param string $clientId
     * @param string $clientSecret
     * @param string $integratorId
     * @param bool   $devMode
     * @return self
     */
    public static function create(string $clientId, string $clientSecret, string $integratorId, bool $devMode=false): self
    {
        return (new static())->initialize($clientId, $clientSecret, $integratorId, $devMode);
    }

    /**
     * @param string $clientId
     * @param string $clientSecret
     * @param string $integratorId
     * @param bool   $devMode
     * @return self
     */
    public function initialize(string $clientId, string $clientSecret, string $integratorId, bool $devMode=false): self
    {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->integratorId = $integratorId;
        $this->devMode      = $devMode;

        $this->setGuzzleClient(new GuzzleClient([
            'base_uri' => $this->devMode ? static::SANDBOX_BASE_URI : static::PROD_BASE_URI,
            'headers' => [
                'X-Integrator-Id' => $this->integratorId,
                'X-Permalinks'    => true,
                'Content-Type'    => 'application/x-www-form-urlencoded',
                'Cache-Control'   => 'no-cache',
            ],
        ]));

        return $this;
    }

    /**
     * @param GuzzleClient $client
     * @return $this
     */
    public function setGuzzleClient(GuzzleClient $client): self
    {
        $this->guzzleClient = $client;
        return $this;
    }

    /**
     * @return GuzzleClient
     */
    public function getGuzzleClient(): GuzzleClient
    {
        return $this->guzzleClient;
    }

    /**
     * @param string $endpoint
     * @param array  $options
     * @return ResponseInterface
     * @throws RuntimeException | GuzzleException
     */
    public function get(string $endpoint, array $options=[]): ResponseInterface
    {
        if (!isset($this->guzzleClient)) {
            throw new RuntimeException('API client not initialized. Please call initialize() first.');
        }

        return $this->guzzleClient->get($endpoint, $options);
    }

    /**
     * @param string $endpoint
     * @param array  $options
     * @return ResponseInterface | GuzzleException
     */
    public function post(string $endpoint, array $options=[]): ResponseInterface
    {
        if (!isset($this->guzzleClient)) {
            throw new RuntimeException('API client not initialized. Please call initialize() first.');
        }

        return $this->guzzleClient->post($endpoint, $options);
    }

    /**
     * @param string $endpoint
     * @param array  $options
     * @return ResponseInterface | GuzzleException
     */
    public function put(string $endpoint, array $options=[]): ResponseInterface
    {
        if (!isset($this->guzzleClient)) {
            throw new RuntimeException('API client not initialized. Please call initialize() first.');
        }

        return $this->guzzleClient->put($endpoint, $options);
    }

}