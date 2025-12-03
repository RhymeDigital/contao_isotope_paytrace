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

    $t      = Payment::getTable();
    $lang   = &$GLOBALS['TL_LANG'][$t];
    
    /**
     * Fields
     */
    $lang['paytrace_username']      = ['Paytrace API login ID', 'Please enter your Paytrace API user ID. Note: this is not your normal PayTrace user ID, it needs to be an API user.'];
    $lang['paytrace_password']      = ['Paytrace API password', 'Please enter your Paytrace API password.'];
    $lang['paytrace_integrator_id'] = ['Paytrace Integrator ID', 'This ID was assigned when you registered your developer account.'];
    $lang['paytrace_merchant_id']   = ['Paytrace Merchant ID', 'You can find the PayTrace ID directly in the Virtual Terminal. Navigate to Virtual Terminal → View Batch Reports. Click the Search button. Your PayTrace ID will appear as a 5-digit number following your Merchant ID in the format: Account - sandbox / 8XXXXXXXXXX / 8XXXX. Note: This ID is specific to each environment (sandbox, production, etc.). When transitioning to production, you will need to repeat this process and update your code with the production PayTrace ID.'];
    $lang['paytrace_trans_type']    = ['Paytrace API Transaction Type', 'Please select the transaction type (Authorize Only or Authorize and Capture).'];
    $lang['paytrace_cc_types']      = ['Allowed Credit Card Types', 'Please select the credit cards allowed for this payment method.'];
    $lang['customTpl']              = $lang['customTpl'] ?? ['Custom template', 'Select a custom template for the payment method here.'];

    /**
     * Reference
     */
    $lang['AUTH_ONLY']                      = 'Authorize Only';
    $lang['AUTH_CAPTURE']                   = 'Authorize & Capture';

    $lang['paytrace_brands']['mc']         = 'Mastercard';
    $lang['paytrace_brands']['visa']       = 'Visa';
    $lang['paytrace_brands']['amex']       = 'American Express';
    $lang['paytrace_brands']['discover']   = 'Discover';
    $lang['paytrace_brands']['diners']     = 'Diner\'s Club';

}