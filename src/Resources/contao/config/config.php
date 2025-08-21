<?php

/**
 * Rhyme Contao Isotope Paytrace Bundle
 *
 * Copyright (c) 2025 Rhyme.Digital
 *
 * @license GPLv3
 */

declare(strict_types=1);

namespace {

    use Isotope\Model\Payment;
    use Rhyme\ContaoIsotopePaytraceBundle\Model;

    /**
     * Payment methods
     */
    Payment::registerModelType('rhyme_paytrace', Model\Payment\Paytrace::class);

}