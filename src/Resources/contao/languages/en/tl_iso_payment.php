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
 * Fields
 */
$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_login'] 		    = array('Paytrace API login ID', 'Please enter your Paytrace API Login ID.');
$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_password'] 	        = array('Paytrace API password', 'Please enter your Paytrace API Password.');
$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_integrator_id']     = array('Paytrace Integrator ID', 'This ID was assigned when you registered your developer account.');
$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_trans_type'] 	    = array('Paytrace API Transaction Type', 'Please select the transaction type (Authorize Only or Authorize and Capture).');
$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_cc_types'] 	        = array('Allowed Credit Card Types', 'Please select the credit cards allowed for this payment method.');

/**
 * Reference
 */
$GLOBALS['TL_LANG']['tl_iso_payment']['AUTH_ONLY']                      = 'Authorize Only';
$GLOBALS['TL_LANG']['tl_iso_payment']['AUTH_CAPTURE']                   = 'Authorize & Capture';

$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_brands']['mc']         = 'Mastercard';
$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_brands']['visa']       = 'Visa';
$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_brands']['amex']       = 'American Express';
$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_brands']['discover']   = 'Discover';
$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_brands']['diners']     = 'Diner\'s Club';