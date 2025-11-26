<?php

/**
 * Rhyme Contao Isotope Paytrace Bundle
 *
 * Copyright (c) 2025 Rhyme.Digital
 *
 * @license GPLv3
 */

declare(strict_types=1);

namespace Rhyme\ContaoIsotopePaytraceBundle\Model\Payment;

use Contao\FrontendUser;
use Contao\Module;
use Contao\System;
use Contao\Input;
use Contao\StringUtil;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Interfaces\IsotopePurchasableCollection;
use Isotope\Model\Payment;
use Isotope\Module\Checkout;
use Isotope\Template;
use Haste\Input\Input as HasteInput;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Client as PaytraceClient;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\Card\Sale\Keyed;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\Token;

class Paytrace extends Payment {

    /**
     * @inheritdoc
     */
    public function checkoutForm(IsotopeProductCollection $objOrder, Module $objModule)
    {
        if (!$objOrder instanceof IsotopePurchasableCollection) {
            System::log('Product collection ID "' . $objOrder->getId() . '" is not purchasable', __METHOD__, TL_ERROR);
            return false;
        }

        // Check for whether we are on the process step or not
        if ($isOnProcessStep = (HasteInput::getAutoItem('step') === Checkout::STEP_PROCESS))
        {

            $this->authPayment($objOrder, $objModule);
            // Add in the E2ee library js
            $GLOBALS['TL_HEAD'][] = '<script src="https://api'.($this->debug ? '.sandbox' : '').'.paytrace.com/v3/assets/js/e2ee/paytrace-e2ee.js"></script>';

            // Add handler JS
            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/rhymecontaoisotopepaytrace/js/paytrace_e2ee_handler.js';

            $formId = $objModule->getFormId().'_'.Checkout::STEP_PROCESS;

            // Set up the handler
            $GLOBALS['TL_BODY']['paytrace_'.$this->id] = <<<HTML
<script>
    function documentReady(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else if (document.addEventListener) {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            document.attachEvent('onreadystatechange', function() {
                if (document.readyState !== 'loading')
                    fn();
            });
        }
    }

    window.documentReady(function() {
        const form = document.getElementById('{$formId}');
        if (form) {
            Rhyme.PaytraceE2EEHandler.create(form, {}); // Todo: add options
        }
    });
</script>

HTML;
        }

        //Check for previous Authorization so we never double-charge cards
        $paymentData = StringUtil::deserialize($objOrder->payment_data, true);
        if(isset($paymentData['PAYTRACE_E2EE']) && \is_array($paymentData['PAYTRACE_E2EE'])) {
            foreach ($paymentData['PAYTRACE_E2EE'] as $attempt => $authData) {
                if ($authData && $authData['responseCode'] === '') {
                    return false;
                }
            }
            //If we reach here, run a hook to determine whether to continue to PROCESS step or redirect, etc
            // !HOOK: Handle Authorize.net failure
            if (isset($GLOBALS['ISO_HOOKS']['paytraceE2eeFailure']) && \is_array($GLOBALS['ISO_HOOKS']['paytraceE2eeFailure'])) {
                foreach ($GLOBALS['ISO_HOOKS']['paytraceE2eeFailure'] as $callback) {
                    System::importStatic($callback[0])->{$callback[1]}($objOrder, $objModule, $this, $paymentData, $isOnProcessStep);
                }
            }
            //Otherwise we redirect back to last checkout step?

        }

        $strErrors = '';

        if (Input::post('FORM_SUBMIT') === $objModule->getFormId().'_'.Checkout::STEP_PROCESS) {

            //Check the data and authorize the payment
            $response = $this->authPayment($objOrder, $objModule);

            if (($response['hasError'] ?? null) === true || \trim($response['transId'] ?? '') === '') {
                $strErrors = $response['errorMessage'];
                System::log('Paytrace E2ee: Errors when attempting transaction:  [' . $response['errorCode'] . '] "'.$strErrors.'"', __METHOD__, TL_ERROR);
            }
            else {
                // Pack it up, people. We're done here
                System::log('Paytrace E2ee: Successfully created transaction with Transaction ID:  "' . $response['transId'] . '"', __METHOD__, TL_GENERAL);
                return false;
            }
        }

        global $objPage;
//        $objAddress = $objOrder->getBillingAddress();
//
//        //Set necessary data for E2ee tokenization
//        $arrData                            = array();
//        $arrData['authorize_login']         = $this->authorize_login;
//        $arrData['authorize_public_key']    = $this->authorize_public_key;
//        $arrData['fullName']                = substr($objAddress->firstname . ' ' . $objAddress->lastname, 0, 40);
//        $arrData['zip']                     = substr($objAddress->postal, 0, 12);

        $objTemplate = new Template(($this->customTpl ?? null) ? $this->customTpl : 'iso_payment_paytrace');

//        $objTemplate->setData($arrData);
        $objTemplate->id            = $this->id;
        $objTemplate->pageId        = $objPage->id;
        $objTemplate->debug         = $this->debug;
        $objTemplate->formSubmit    = $objModule->getFormId().'_'.Checkout::STEP_PROCESS;
        $objTemplate->showForm      = $isOnProcessStep;
        $objTemplate->hasErrors     = !empty($strErrors);
        $objTemplate->errorMsg      = $strErrors;

        return $objTemplate->parse();
    }

    /**
     * @inheritdoc
     */
    public function processPayment(IsotopeProductCollection $objOrder, Module $objModule)
    {
        if ($objOrder->isLocked()) {
            return true;
        }

        return false;
    }

    /**
     * Return information or advanced features in the backend.
     * //TODO - ADD METHOD TO CAPTURE PAYMENT FOR AUTH-ONLY TRANSACTIONS
     * //TODO - ADD METHOD TO REFUND CAPTURED TRANSACTIONS
     *
     * @param int $orderId
     *
     * @return string
     */
    public function backendInterface($orderId)
    {
        $buffer = '';

        return $buffer;
    }


    /**
     * @param IsotopeProductCollection $objOrder
     * @param Module $objModule
     * @return array
     */
    protected function authPayment(IsotopeProductCollection $objOrder, Module $objModule): array
    {
        $return = [];

        // Create a client
        $client = PaytraceClient::create(
            $this->paytrace_username,
            $this->paytrace_password,
            $this->paytrace_integrator_id,
            (bool)$this->debug
        );

        $tokenEndpoint = new Token();

        try
        {
            // Get a token (don't bother doing the refresh)
            $tokenResponse = $tokenEndpoint->getToken(
                $client,
                $this->paytrace_username,
                $this->paytrace_password
            );

            $tokenData = \json_decode((string)$tokenResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

            if (($tokenData['status'] ?? null) !== 'success') {
                return [
                    'hasError' => true,
                    'errorMessage' => 'Could not retrieve access token.'
                ];
            }

            $billingAddress = $objOrder->getBillingAddress();
            $billingAddress = $billingAddress !== null ? $billingAddress->row() : [];

            $shippingAddress = $objOrder->getShippingAddress();
            $shippingAddress = $shippingAddress !== null ? $shippingAddress->row() : [];

            $billingState = \explode('-', $billingAddress['subdivision'] ?? '');
            $billingState = \array_key_exists(1, $billingState) ? $billingState[1] : '';

            $shippingState = \explode('-', $shippingAddress['subdivision'] ?? '');
            $shippingState = \array_key_exists(1, $shippingState) ? $shippingState[1] : '';

            $keyedSale = new Keyed();

            $response = $keyedSale->sendRequest(
                $client,
//                [
//                    'amount' => $objOrder->getTotal(),
//                    'billing_address' => [
//                        'street' => $billingAddress['street_1'] ?? '',
//                        'street2' => $billingAddress['street_2'] ?? '',
//                        'city' => $billingAddress['city'] ?? '',
//                        'state' => $billingState,
//                        'country' => \strtoupper($billingAddress['country'] ?? ''),
//                        'postal_code' => $billingAddress['postal'] ?? '',
//                    ],
//                    //'billing_name' => 'string',
//                    'card' => [
//                        'number' => (string)Input::post('cardNumber'),
//                        'expiration_month' => (string)Input::post('expMonth'),
//                        'expiration_year' => (string)Input::post('expYear'),
//                    ],
//                    'csc' => (string)Input::post('cardCode'),
//                    'billing_email' => (string)($billingAddress['email'] ?: FrontendUser::getInstance()->email),
//                    'billing_phone' => (string)($billingAddress['phone'] ?: FrontendUser::getInstance()->phone),
//                    'customer_reference_id' => 'Cart ID: ' . $objOrder->source_collection_id,
//                    //'description' => 'business services',
//                    //'force_partial_auth_capability' => true,
//                    //'invoice_number' => '17',
//                    'merchant_id' => 888000002887,
//                    //'custom_dba' => 'Doing Business As Test Company',
//                    //'return_clr' => false,
//                    //'customer_id' => 0,
//                    'shipping_address' => [
//                        'street' => $shippingAddress['street_1'] ?? '',
//                        'street2' => $shippingAddress['street_2'] ?? '',
//                        'city' => $shippingAddress['city'] ?? '',
//                        'state' => $shippingState,
//                        'country' => $shippingAddress['country'] ?? '',
//                        'postal_code' => $shippingAddress['postal'] ?? '',
//                    ],
//                    'shipping_name' => \trim((string)($billingAddress['firstname'] ?: FrontendUser::getInstance()->firstname) . ' ' . (string)($billingAddress['lastname'] ?: FrontendUser::getInstance()->lastname)),
//                    //'tax_amount' => '2.16',
//                ],
                [
                    'amount' => $objOrder->getTotal(),
                    'billing_address' => [
                        'street' => '123 Test St.',
                        'street2' => '',
                        'city' => 'Easthampton',
                        'state' => 'MA',
                        'country' => 'US',
                        'postal_code' => '01027',
                    ],
                    'card' => [
                        'number' => (string)Input::post('cardNumber'),
                        'expiration_month' => '06',
                        'expiration_year' => '2026',
                    ],
                    'csc' => (string)Input::post('cardCode'),
                    'billing_email' => 'adam@rhyme.digital',
                    'billing_phone' => '4133030353',
                    'customer_reference_id' => 'Cart ID: 1234',
                    'merchant_id' => 888000002887,
                    'shipping_address' => [
                        'street' => '123 Test St.',
                        'street2' => '',
                        'city' => 'Easthampton',
                        'state' => 'MA',
                        'country' => 'US',
                        'postal_code' => '01027',
                    ],
                    'shipping_name' => 'Adam Fisher',
                ],
                $tokenData['data']['access_token']
            );

            \dd((string)$response->getBody());
        }
        catch (ClientException $e) {
            System::log('Paytrace E2ee: Errors when attempting transaction: '.$e->getResponse()->getBody()->getContents(), __METHOD__, TL_ERROR);
            return [
                'hasError' => true,
                'errorMessage' => 'Paytrace E2ee: Errors when attempting transaction: '.$e->getResponse()->getBody()->getContents()
            ];
        }
        catch (\Exception $e) {
            System::log('Paytrace E2ee: Errors when attempting transaction: [' . $e->getCode() . '] "'.$e->getMessage().'"', __METHOD__, TL_ERROR);
            return [
                'hasError' => true,
                'errorMessage' => 'Paytrace E2ee: Errors when attempting transaction: [' . $e->getCode() . '] "'.$e->getMessage().'"'
            ];
        }

        return $return;
    }


    /**
     * @param IsotopeProductCollection $objOrder
     * @param Module $objModule
     * @return string
     */
    protected function getOrderDescription(IsotopeProductCollection $objOrder, Module $objModule) : string
    {
        return 'Cart ID: ' . $objOrder->source_collection_id;
    }

}