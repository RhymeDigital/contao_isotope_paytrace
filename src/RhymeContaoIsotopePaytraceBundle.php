<?php

declare(strict_types=1);

/**
 * Rhyme Contao Isotope Paytrace Bundle
 *
 * Copyright (c) 2025 Rhyme.Digital
 *
 * @license GPLv3
 */

namespace Rhyme\ContaoIsotopePaytraceBundle;

use Rhyme\ContaoIsotopePaytraceBundle\DependencyInjection\RhymeIsotopePaytraceExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Configures the bundle
 */
final class RhymeContaoIsotopePaytraceBundle extends Bundle
{
    protected function getContainerExtensionClass(): string
    {
        return RhymeIsotopePaytraceExtension::class;
    }
}