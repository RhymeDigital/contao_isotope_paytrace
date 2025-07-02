<?php

declare(strict_types=1);

/**
 * Rhyme Contao Isotope Paytrace Bundle
 *
 * Copyright (c) 2025 Rhyme.Digital
 *
 * @license GPLv3
 */

namespace Rhyme\ContaoIsotopePaytraceBundle\Model\Payment;

use Contao\DataContainer;
use Contao\Input;
use Contao\StringUtil;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Interfaces\IsotopePurchasableCollection;
use Isotope\Model\Address;
use Isotope\Model\Payment;
use Isotope\Model\ProductCollection\Order;
use Isotope\Module\Checkout;
use Isotope\Template;
use stdClass;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
use net\authorize\api\constants\ANetEnvironment;

class Paytrace extends Payment {

    /**
     * @inheritdoc
     */
    public function checkoutForm(IsotopeProductCollection $objOrder, \Module $objModule)
    {
        if (!$objOrder instanceof IsotopePurchasableCollection) {
            \System::log('Product collection ID "' . $objOrder->getId() . '" is not purchasable', __METHOD__, TL_ERROR);
            return false;
        }

        //Check for whether we are on the process step or not
        $isOnProcessStep = \Haste\Input\Input::getAutoItem('step') === Checkout::STEP_PROCESS;

        //Check for previous Authorization so we never double-charge cards
        $paymentData = StringUtil::deserialize($objOrder->payment_data, true);
        if(isset($paymentData['AUTHORIZENET']) && \is_array($paymentData['AUTHORIZENET'])) {
            foreach ($paymentData['AUTHORIZENET'] as $attempt => $authData) {
                if ($authData && $authData['responseCode'] === '1') {
                    return false;
                }
            }
            //If we reach here, run a hook to determine whether to continue to PROCESS step or redirect, etc
            // !HOOK: Handle Authorize.net failure
            if (isset($GLOBALS['ISO_HOOKS']['authNetFailure']) && \is_array($GLOBALS['ISO_HOOKS']['authNetFailure'])) {
                foreach ($GLOBALS['ISO_HOOKS']['authNetFailure'] as $callback) {
                    \System::importStatic($callback[0])->{$callback[1]}($objOrder, $objModule, $this, $paymentData, $isOnProcessStep);
                }
            }
            //Otherwise we redirect back to last checkout step?

        }

        $strErrors = '';

        if (\Input::post('FORM_SUBMIT') == $objModule->getFormId().'_process') {

            //Check the data and authorize the payment
            $response = $this->authPayment($objOrder, $objModule);

            if($response['hasError'] === true || !strlen(trim(strval($response['transId'])))) {
                $strErrors = $response['errorMessage'];
                \System::log('Authorize.net: Errors when attempting transaction:  [' . $response['errorCode'] . '] "'.$response['errorMessage'].'"', __METHOD__, TL_ERROR);

            } else {

                //Pack it up, people. We're done here
                \System::log('Authorize.net: Successfully created transaction with Transaction ID:  "' . $response['transId'] . '"', __METHOD__, TL_GENERAL);
                return false;
            }
        }

        //Add handler JS
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/rhymecontaoisotopeauthorizenet/js/acceptjs_handler.js';

        global $objPage;
        $objAddress = $objOrder->getBillingAddress();

        //Set necessary Authorize.net data for Accept.js tokenization
        $arrData                            = array();
        $arrData['authorize_login']         = $this->authorize_login;
        $arrData['authorize_public_key']    = $this->authorize_public_key;
        $arrData['fullName']                = substr($objAddress->firstname . ' ' . $objAddress->lastname, 0, 40);
        $arrData['zip']                     = substr($objAddress->postal, 0, 12);

        /** @var Template|stdClass $objTemplate */
        $objTemplate = new Template('iso_payment_authorizenetacceptjs');

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
    public function processPayment(IsotopeProductCollection $objOrder, \Module $objModule)
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
        if (null === ($objOrder = Order::findByPk($orderId))) {

            return parent::backendInterface($orderId);
        }

        $paymentData = StringUtil::deserialize($objOrder->payment_data, true);

        if (0 === \count($paymentData['AUTHORIZENET'])) {

            return parent::backendInterface($orderId);
        }

        $buffer = '
<div id="tl_buttons">
<a href="' . ampersand(str_replace('&key=payment', '', \Environment::get('request'))) . '" class="header_back" title="' . specialchars($GLOBALS['TL_LANG']['MSC']['backBT']) . '">' . $GLOBALS['TL_LANG']['MSC']['backBT'] . '</a>
</div>

<h2 class="sub_headline">' . $this->name . ' (' . $GLOBALS['TL_LANG']['MODEL']['tl_iso_payment'][$this->type][0] . ')' . '</h2>

<table class="tl_show">
  <tbody>';

        DataContainer::loadLanguageFile('default');

        foreach ($paymentData['AUTHORIZENET'] as $transactionAttempt => $arrData) {

            $buffer .= '
            <tr><td style="padding-top:15px;" colspan="2"><h4>'.sprintf($GLOBALS['TL_LANG']['MISC']['authorize_backend']['transaction_attempt'], (int) $transactionAttempt).'</h4></td></tr>';

            $i = 0;
            foreach ($arrData as $k => $v) {
                if (\is_array($v)) {
                    continue;
                }

                $buffer .= '
  <tr>
    <td' . (($i % 2) ? '' : ' class="tl_bg"') . '><span class="tl_label">' . $k . ': </span></td>
    <td' . (($i % 2) ? '' : ' class="tl_bg"') . '>' . $v . '</td>
  </tr>';

                ++$i;
            }
        }

        $buffer .= '
</tbody></table>
</div>';

        return $buffer;
    }


    /**
     * @param IsotopeProductCollection $objOrder
     * @param \Module $objModule
     * @return array
     */
    protected function authPayment(IsotopeProductCollection $objOrder, \Module $objModule) {

        // Create a merchantAuthenticationType object with authentication details
        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($this->authorize_login);
        $merchantAuthentication->setTransactionKey($this->authorize_transaction_key);

        // Create the payment object for a payment nonce
        $opaqueData = new AnetAPI\OpaqueDataType();
        $opaqueData->setDataDescriptor(Input::post('dataDescriptor'));
        $opaqueData->setDataValue(Input::post('dataValue'));

        // Set the transaction's refId
        $refId = 'ref' . (string) $objOrder->getId() . time();

        // Add the payment data to a paymentType object
        $paymentOne = new AnetAPI\PaymentType();
        $paymentOne->setOpaqueData($opaqueData);

        //add the values for transaction settings
        $duplicateWindowSetting = new AnetAPI\SettingType();
        $duplicateWindowSetting->setSettingName("duplicateWindow");
        $duplicateWindowSetting->setSettingValue("600");

        // Create order information
        $order = new AnetAPI\OrderType();
        $order->setInvoiceNumber(substr( (string) ($objOrder->getDocumentNumber() ?: $objOrder->getId()), 0, 20));
        $order->setDescription(substr($this->getOrderDescription($objOrder, $objModule), 0, 255));

        // Set the customer's Bill To address
        /** @var Address $objAddress */
        $objAddress = $objOrder->getBillingAddress();
        $customerAddress = new AnetAPI\CustomerAddressType();
        $customerAddress->setFirstName(substr($objAddress->firstname, 0 , 50));
        $customerAddress->setLastName(substr($objAddress->lastname, 0, 50));
        $customerAddress->setCompany(substr($objAddress->company, 0, 50));
        $customerAddress->setAddress(substr(trim($objAddress->street_1 . ' ' . $objAddress->street_2 . ' ' . $objAddress->street_3), 0, 60));
        $customerAddress->setCity(substr($objAddress->city, 0, 40));
        $customerAddress->setState(substr($objAddress->subdivision, -2));
        $customerAddress->setZip($objAddress->postal);
        $customerAddress->setCountry(strtoupper($objAddress->country));

        // Set the customer's identifying information
        $customerData = new AnetAPI\CustomerDataType();
        $customerData->setType("individual");
        $customerData->setId($objOrder->getMember() ? $objOrder->getMember()->id : $objAddress->id);
        $customerData->setEmail($objAddress->email);

        // Add some merchant defined fields. These fields won't be stored with the transaction,
        // but will be echoed back in the response.
        $merchantDefinedField1 = new AnetAPI\UserFieldType();
        $merchantDefinedField1->setName("cartId");
        $merchantDefinedField1->setValue($objOrder->source_collection_id);

        // Create a TransactionRequestType object and add the previous objects to it
        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType($this->authorize_trans_type == 'AUTH_ONLY' ? 'authOnlyTransaction' : 'authCaptureTransaction');
        $transactionRequestType->setAmount(number_format($objOrder->getTotal(), 2, '.', '') . '');
        $transactionRequestType->setOrder($order);
        $transactionRequestType->setPayment($paymentOne);
        $transactionRequestType->setBillTo($customerAddress);
        $transactionRequestType->setCustomer($customerData);
        $transactionRequestType->addToTransactionSettings($duplicateWindowSetting);
        $transactionRequestType->addToUserFields($merchantDefinedField1);

        // Assemble the complete transaction request
        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequestType);

        // Create the controller and get the response
        $controller = new AnetController\CreateTransactionController($request);
        $endPoint = $this->debug ? ANetEnvironment::SANDBOX : ANetEnvironment::PRODUCTION;
        $response = $controller->executeWithApiResponse($endPoint);

        //Eval response
        $return = array();
        $arrPayment = StringUtil::deserialize($objOrder->payment_data, true);
        if ($response != null) {
            // Check to see if the API request was successfully received and acted upon
            if ($response->getMessages()->getResultCode() == "Ok") {

                // Since the API request was successful, look for a transaction response
                // and parse it to display the results of authorizing the card
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {

                    $return = array(
                        'transId'       => $tresponse->getTransId(),
                        'responseCode'  => $tresponse->getResponseCode(),
                        'messageCode'   => $tresponse->getMessages()[0]->getCode(),
                        'authCode'      => $tresponse->getAuthCode(),
                        'description'   => $tresponse->getMessages()[0]->getDescription()
                    );

                    //Tell the form it is all good with no errors
                    $return['hasError']     =  false;
                    $return['wasOk']        =  true;

                } else {
                    $return['hasError']     =  true;
                    $return['wasOk']        =  true;
                    if ($tresponse->getErrors() != null) {
                        $return['errorCode']    = $tresponse->getErrors()[0]->getErrorCode();
                        $return['errorMessage'] = $tresponse->getErrors()[0]->getErrorText();
                    }
                }
                // Or, send back errors if the API request wasn't successful
            } else {

                $tresponse = $response->getTransactionResponse();
                $return['hasError']     =  true;
                $return['wasOk']        =  false;

                if ($tresponse != null && $tresponse->getErrors() != null) {
                    $return['errorCode']    = $tresponse->getErrors()[0]->getErrorCode();
                    $return['errorMessage'] = $tresponse->getErrors()[0]->getErrorText();

                } else {
                    $return['errorCode']    = $response->getMessages()->getMessage()[0]->getCode();
                    $return['errorMessage'] = $response->getMessages()->getMessage()[0]->getText();
                }
            }
        } else {
            //Some bullshit be happening. Ghosts 'n shit
            $return['hasError']     =  true;
            $return['wasOk']        =  false;
            $return['errorCode']    = 'UNKNOWN|NULL_RESPONSE';
            $return['errorMessage'] = $GLOBALS['TL_LANG']['MISC']['authorize_errors']['default'];
        }

        //Save transaction data for future reference
        $arrPayment['AUTHORIZENET'][] = $return;
        $objOrder->payment_data = $arrPayment;
        $objOrder->save();

        return $return;
    }


    /**
     * @param IsotopeProductCollection $objOrder
     * @param \Module $objModule
     * @return string
     */
    protected function getOrderDescription(IsotopeProductCollection $objOrder, \Module $objModule) : string
    {
        return 'Cart ID: ' . $objOrder->source_collection_id;
    }

}