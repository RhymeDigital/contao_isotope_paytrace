<?php

declare(strict_types=1);

/**
 * Rhyme Contao Isotope Paytrace Bundle
 *
 * Copyright (c) 2025 Rhyme.Digital
 *
 * @license GPLv3
 */

namespace Rhyme\ContaoIsotopePaytraceBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Rhyme\ContaoIsotopePaytraceBundle\RhymeContaoIsotopePaytraceBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(RhymeContaoIsotopePaytraceBundle::class)
                ->setLoadAfter(
                    [
                        ContaoCoreBundle::class, 'isotope', 'isotope_rules', 'isotope_reports'
                    ]
                ),
        ];
    }
}