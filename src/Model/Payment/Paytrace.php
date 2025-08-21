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

use Contao\Module;
use Contao\System;
use Contao\Input;
use Contao\StringUtil;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Interfaces\IsotopePurchasableCollection;
use Isotope\Model\Payment;
use Isotope\Module\Checkout;
use Isotope\Template;
use Haste\Input\Input as HasteInput;

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
            // Add in the E2ee library js
            $GLOBALS['TL_HEAD'][] = '<script src="https://api'.($this->debug ? '.sandbox' : '').'.paytrace.com/v3/assets/js/e2ee/paytrace-e2ee.js"></script>';

            // Add handler JS
            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/rhymecontaoisotopepaytrace/js/paytrace_e2ee_handler.js';
        }

        //Check for previous Authorization so we never double-charge cards
        $paymentData = StringUtil::deserialize($objOrder->payment_data, true);
        if(isset($paymentData['PAYTRACE_E2EE']) && \is_array($paymentData['PAYTRACE_E2EE'])) {
            foreach ($paymentData['PAYTRACE_E2EE'] as $attempt => $authData) {
                if ($authData && $authData['responseCode'] === '1') {
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

        if (Input::post('FORM_SUBMIT') === $objModule->getFormId().'_process') {

            //Check the data and authorize the payment
            $response = $this->authPayment($objOrder, $objModule);

            if($response['hasError'] === true || !strlen(trim(strval($response['transId'])))) {
                $strErrors = $response['errorMessage'];
                System::log('Paytrace E2ee: Errors when attempting transaction:  [' . $response['errorCode'] . '] "'.$response['errorMessage'].'"', __METHOD__, TL_ERROR);

            } else {

                //Pack it up, people. We're done here
                System::log('Paytrace E2ee: Successfully created transaction with Transaction ID:  "' . $response['transId'] . '"', __METHOD__, TL_GENERAL);
                return false;
            }
        }

        global $objPage;
        $objAddress = $objOrder->getBillingAddress();

        //Set necessary data for E2ee tokenization
        $arrData                            = array();
        $arrData['authorize_login']         = $this->authorize_login;
        $arrData['authorize_public_key']    = $this->authorize_public_key;
        $arrData['fullName']                = substr($objAddress->firstname . ' ' . $objAddress->lastname, 0, 40);
        $arrData['zip']                     = substr($objAddress->postal, 0, 12);

        $objTemplate = new Template(($this->customTpl ?? null) ? $this->customTpl : 'iso_payment_paytrace');

        $objTemplate->setData($arrData);
        $objTemplate->id            = $this->id;
        $objTemplate->pageId        = $objPage->id;
        $objTemplate->debug         = $this->debug;
        $objTemplate->formSubmit    = $objModule->getFormId().'_process';
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
    protected function authPayment(IsotopeProductCollection $objOrder, Module $objModule) {

        $return = '';

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