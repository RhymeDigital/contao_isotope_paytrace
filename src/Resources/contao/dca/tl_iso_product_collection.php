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

    use Isotope\Model\ProductCollection;

    $t = ProductCollection::getTable();
    $dca = &$GLOBALS['TL_DCA'][$t];

    /**
     * Fields
     */
    $dca['fields']['paytrace_refund_amt_requested'] = array
    (
        'sql'                     => "DOUBLE unsigned NOT NULL default 0"
    );
    $dca['fields']['paytrace_void_requested'] = array
    (
        'eval'                    => array('isBoolean'=>true, 'doNotCopy'=>true),
        'sql'                     => "char(1) NOT NULL default ''"
    );
}