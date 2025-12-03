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

        $formId = $objModule->getFormId().'_'.Checkout::STEP_PROCESS;

        // Check for whether we are on the process step or not
        if ($isOnProcessStep = (HasteInput::getAutoItem('step') === Checkout::STEP_PROCESS))
        {
            // Add in the E2ee library js
            $GLOBALS['TL_HEAD'][] = '<script src="https://api'.($this->debug ? '.sandbox' : '').'.paytrace.com/v3/assets/js/e2ee/paytrace-e2ee.js"></script>';

            // Add handler JS
            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/rhymecontaoisotopepaytrace/js/paytrace_e2ee_handler.js';

            $sandbox = $this->debug ? 'true' : 'false';

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
            Rhyme.PaytraceE2EEHandler.create(form, {sandbox: {$sandbox}});
        }
    });
</script>

HTML;
        }

        //Check for previous Authorization so we never double-charge cards
        $paymentData = StringUtil::deserialize($objOrder->payment_data, true);
        if(isset($paymentData['PAYTRACE_E2EE']) && \is_array($paymentData['PAYTRACE_E2EE'])) {
            foreach ($paymentData['PAYTRACE_E2EE'] as $attempt => $authData) {
                if ($authData && $authData['status'] === 'success') {
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

        if (Input::post('FORM_SUBMIT') === $formId) {

            //Check the data and authorize the payment
            $response = $this->authPayment($objOrder, $objModule);

            if (($response['hasError'] ?? null) === true
                || ($response['response']['data']['response_code'] ?? null) !== 101
                || \trim($response['response']['data']['transaction_uuid'] ?? '') === ''
                || \trim($response['response']['data']['transaction_id'] ?? '') === ''
            ) {
                $strErrors = $response['errorMessage'];
                System::log('Paytrace E2ee: Errors when attempting transaction:  [' . $response['errorCode'] . '] "'.$strErrors.'"', __METHOD__, TL_ERROR);
            }
            else {
                $objOrder->setDatePaid(\time());
                $objOrder->save();

                // Pack it up, people. We're done here
                System::log('Paytrace E2ee: Successfully created transaction with Transaction ID:  "' . $response['response']['data']['transaction_id']  . '"', __METHOD__, TL_GENERAL);
                return false;
            }
        }

        global $objPage;

        $objTemplate = new Template(($this->customTpl ?? null) ? $this->customTpl : 'iso_payment_paytrace');
        $objTemplate->id            = $this->id;
        $objTemplate->pageId        = $objPage->id;
        $objTemplate->debug         = $this->debug;
        $objTemplate->formSubmit    = $formId;
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
        // Do not charge multiple times
        if ($GLOBALS['PAYTRACE_AUTH_ATTEMPTED'] ?? null) {
            return [];
        }
        $GLOBALS['PAYTRACE_AUTH_ATTEMPTED'] = true;

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
                System::log('Paytrace E2ee: Could not retrieve access token.', __METHOD__, TL_ERROR);
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
                [
                    'amount' => $objOrder->getTotal(),
                    'billing_name' => \trim((string)($billingAddress['firstname'] ?: FrontendUser::getInstance()->firstname) . ' ' . (string)($billingAddress['lastname'] ?: FrontendUser::getInstance()->lastname)),
                    'billing_address' => [
                        'street' => $billingAddress['street_1'] ?? '',
                        'street2' => $billingAddress['street_2'] ?? '',
                        'city' => $billingAddress['city'] ?? '',
                        'state' => $billingState,
                        'country' => \strtoupper($billingAddress['country'] ?? ''),
                        'postal_code' => $billingAddress['postal'] ?? '',
                    ],
                    'card' => [
                        'number' => \html_entity_decode((string)Input::post('cardNumber'), ENT_QUOTES, 'UTF-8'),
                        'expiration_month' => (string)Input::post('expMonth'),
                        'expiration_year' => (string)Input::post('expYear'),
                    ],
                    'csc' => \html_entity_decode((string)Input::post('cardCode'), ENT_QUOTES, 'UTF-8'),
                    'billing_email' => (string)($billingAddress['email'] ?: FrontendUser::getInstance()->email),
                    'billing_phone' => (string)($billingAddress['phone'] ?: FrontendUser::getInstance()->phone),
                    'customer_reference_id' => 'Cart ID: ' . $objOrder->source_collection_id,
                    'merchant_id' => $this->paytrace_merchant_id,
                    'shipping_address' => [
                        'street' => $shippingAddress['street_1'] ?? '',
                        'street2' => $shippingAddress['street_2'] ?? '',
                        'city' => $shippingAddress['city'] ?? '',
                        'state' => $shippingState,
                        'country' => $shippingAddress['country'] ?? '',
                        'postal_code' => $shippingAddress['postal'] ?? '',
                    ],
                    'shipping_name' => \trim((string)($shippingAddress['firstname'] ?: FrontendUser::getInstance()->firstname) . ' ' . (string)($shippingAddress['lastname'] ?: FrontendUser::getInstance()->lastname)),
                ],
                $tokenData['data']['access_token']
            );

            if (empty($response = \json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR))) {
                System::log('Paytrace E2ee: Errors when attempting transaction: No response from the API.', __METHOD__, TL_ERROR);
                return [
                    'hasError' => true,
                    'errorMessage' => 'Payment could not be processed. Please try again or contact an administrator.'
                ];
            }

            // Store response data in order for future references
            $arrPayment = StringUtil::deserialize($objOrder->payment_data, true);
            $arrPayment['PAYTRACE_E2EE'] = $arrPayment['PAYTRACE_E2EE'] ?? [];
            $arrPayment['PAYTRACE_E2EE'][] = $response;

            $objOrder->payment_data = $arrPayment;
            $objOrder->save();

            if (($response['data']['response_code'] ?? null) !== 101) {
                System::log('Paytrace E2ee: Errors when attempting transaction: [' . ($response['data']['response_code'] ?? '') . '] "'.($response['data']['response_message'] ?? '').'"', __METHOD__, TL_ERROR);
                return [
                    'hasError' => true,
                    'errorMessage' => 'Payment could not be processed. Message from processor: '.($response['data']['response_message'] ?? '')
                ];
            }

            return [
                'hasError' => false,
                'response' => $response
            ];
        }
        catch (ClientException $e) {
            System::log('Paytrace E2ee: Errors when attempting transaction: '.$e->getResponse()->getBody()->getContents(), __METHOD__, TL_ERROR);
            return [
                'hasError' => true,
                'errorMessage' => 'Payment could not be processed. Please try again or contact an administrator.'
            ];
        }
        catch (\Exception $e) {
            System::log('Paytrace E2ee: Errors when attempting transaction: [' . $e->getCode() . '] "'.$e->getMessage().'"', __METHOD__, TL_ERROR);
            return [
                'hasError' => true,
                'errorMessage' => 'Payment could not be processed. Please try again or contact an administrator.'
            ];
        }
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