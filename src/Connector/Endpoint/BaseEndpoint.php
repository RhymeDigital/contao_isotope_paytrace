<?php

/**
 *  Copyright (c) 2025 Rhyme Digital, LLC.
 *
 *  @link			https://rhyme.digital
 *  @license		https://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */

declare(strict_types=1);

namespace Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint;

abstract class BaseEndpoint
{
    /**
     * API endpoint
     * @var string
     */
    protected static string $endpoint;

    /**
     * Get the endpoint
     * @return string
     */
    protected static function getEndpoint(): string
    {
        return static::$endpoint;
    }



}