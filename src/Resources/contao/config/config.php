<?php

declare(strict_types=1);

/**
 * Rhyme Contao Isotope Paytrace Bundle
 *
 * Copyright (c) 2025 Rhyme.Digital
 *
 * @license GPLv3
 */

/**
 * Payment methods
 */
\Isotope\Model\Payment::registerModelType('rhyme_paytrace', 'Rhyme\ContaoIsotopePaytraceBundle\Model\Payment\Paytrace');
