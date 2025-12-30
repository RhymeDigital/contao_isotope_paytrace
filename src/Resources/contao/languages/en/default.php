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

    $lang = &$GLOBALS['TL_LANG'];
    $model = &$lang['MODEL'];

    /**
     * Payment modules
     */
    $model['tl_iso_payment']['rhyme_paytrace'] = ['Paytrace', 'Paytrace Payment Method - This method is a fully PCI compliant payment solution.'];

    /**
     * Paytrace transaction statuses
     */
    $lang['MSC']['PAYTRACE_TRANSACTION_STATUS']['A'] = 'Authorized';
    $lang['MSC']['PAYTRACE_TRANSACTION_STATUS']['D'] = 'Declined';
    $lang['MSC']['PAYTRACE_TRANSACTION_STATUS']['F'] = 'Offline';
    $lang['MSC']['PAYTRACE_TRANSACTION_STATUS']['P'] = 'Pending';
    $lang['MSC']['PAYTRACE_TRANSACTION_STATUS']['I'] = 'Incomplete EMV';
    $lang['MSC']['PAYTRACE_TRANSACTION_STATUS']['R'] = 'Card Reversed';
    $lang['MSC']['PAYTRACE_TRANSACTION_STATUS']['V'] = 'Voided';
    $lang['MSC']['PAYTRACE_TRANSACTION_STATUS']['S'] = 'Settled';
    $lang['MSC']['PAYTRACE_TRANSACTION_STATUS']['L'] = 'Settlement Lock';
    $lang['MSC']['PAYTRACE_TRANSACTION_STATUS']['Z'] = 'Zero Auth';
    $lang['MSC']['PAYTRACE_TRANSACTION_STATUS']['N'] = 'New';

    /**
     * Misc
     */
    $lang['MSC']['transaction_requested_amount']    = $lang['MSC']['transaction_requested_amount'] ?? 'Requested Amount';
    $lang['MSC']['transaction_settle_amount']       = $lang['MSC']['transaction_settle_amount'] ?? 'Settle Amount';
    $lang['MSC']['transaction_card_brand']          = $lang['MSC']['transaction_card_brand'] ?? 'Card Brand';
    $lang['MSC']['transaction_created_at']          = $lang['MSC']['transaction_created_at'] ?? 'Created At';
    $lang['MSC']['transaction_billing_name']        = $lang['MSC']['transaction_billing_name'] ?? 'Billing Name';
    $lang['MSC']['transaction_billing_email']       = $lang['MSC']['transaction_billing_email'] ?? 'Billing Email';
    $lang['MSC']['transaction_billing_phone']       = $lang['MSC']['transaction_billing_phone'] ?? 'Billing Phone';
    $lang['MSC']['transaction_customer_reference']  = $lang['MSC']['transaction_customer_reference'] ?? 'Customer Reference';
    $lang['MSC']['transaction_id']                  = $lang['MSC']['transaction_id'] ?? 'Transaction ID';
    $lang['MSC']['transaction_uuid']                = $lang['MSC']['transaction_uuid'] ?? 'Transaction UUID';
    $lang['MSC']['transaction_processor']           = $lang['MSC']['transaction_processor'] ?? 'Processor';
    $lang['MSC']['transaction_approval_code']       = $lang['MSC']['transaction_approval_code'] ?? 'Approval Code';
    $lang['MSC']['transaction_status']              = $lang['MSC']['transaction_status'] ?? 'Transaction Status';
    $lang['MSC']['transaction_card_masked']         = $lang['MSC']['transaction_card_masked'] ?? 'Card Masked';
    $lang['MSC']['transaction_action']              = $lang['MSC']['transaction_action'] ?? 'Action';
    $lang['MSC']['transaction_sale_success']        = $lang['MSC']['transaction_sale_success'] ?? 'Paytrace E2ee: Successfully created transaction.%s';
    $lang['MSC']['transaction_void_confirm']        = $lang['MSC']['transaction_void_confirm'] ?? 'Are you sure you want to void this transaction?';
    $lang['MSC']['transaction_void_success']        = $lang['MSC']['transaction_void_success'] ?? 'Void request successfully sent.';
    $lang['MSC']['transaction_refund_confirm']      = $lang['MSC']['transaction_refund_confirm'] ?? 'Are you sure you want to refund this transaction?';
    $lang['MSC']['transaction_refund_success']      = $lang['MSC']['transaction_refund_success'] ?? 'Refund request successfully sent.';

    /**
     * Errors
     */
    $lang['ERR']['collection_not_purchasable']             = $lang['ERR']['collection_not_purchasable'] ?? 'Order/product collection is not purchasable';
    $lang['ERR']['paytrace_no_api_response']               = $lang['ERR']['paytrace_no_api_response'] ?? 'Paytrace E2ee: No API response received.';
    $lang['ERR']['access_token_retrieval']                 = $lang['ERR']['access_token_retrieval'] ?? 'Paytrace E2ee: Could not retrieve access token.%s';
    $lang['ERR']['paytrace_transaction_attempt']           = $lang['ERR']['paytrace_transaction_attempt'] ?? 'Paytrace E2ee: Error(s) when attempting transaction: %s';
    $lang['ERR']['paytrace_payment_not_processed1']        = $lang['ERR']['paytrace_payment_not_processed1'] ?? 'Payment could not be processed. Message from processor: %s';
    $lang['ERR']['paytrace_payment_not_processed2']        = $lang['ERR']['paytrace_payment_not_processed2'] ?? 'Payment could not be processed. Please try again or contact an administrator.';
    $lang['ERR']['paytrace_get_transaction']               = $lang['ERR']['paytrace_get_transaction'] ?? 'Paytrace E2ee: Errors when attempting to retrieve transaction information: %s';
    $lang['ERR']['paytrace_void_transaction']              = $lang['ERR']['paytrace_void_transaction'] ?? 'Paytrace E2ee: Errors when attempting to void transaction: %s';
    $lang['ERR']['paytrace_refund_transaction']            = $lang['ERR']['paytrace_refund_transaction'] ?? 'Paytrace E2ee: Errors when attempting to refund transaction: %s';
    $lang['ERR']['paytrace_refund_missing_settlement_amt'] = $lang['ERR']['paytrace_refund_missing_settlement_amt'] ?? 'Cannot refund transaction without a settlement amount.%s';
}