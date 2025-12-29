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

use Contao\BackendTemplate;
use Contao\Controller;
use Contao\CoreBundle\Exception\AjaxRedirectResponseException;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\FrontendUser;
use Contao\Message;
use Contao\Messages;
use Contao\Module;
use Contao\System;
use Contao\Input;
use Contao\StringUtil;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Interfaces\IsotopePurchasableCollection;
use Isotope\Interfaces\IsotopeBackendInterface;
use Isotope\Model\Payment;
use Isotope\Model\ProductCollection\Order;
use Isotope\Module\Checkout;
use Isotope\Template;
use Haste\Input\Input as HasteInput;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Client as PaytraceClient;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\Card\Batch\Refund;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\Card\Batch\VoidTransaction;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\Card\Sale\Keyed;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\Card\Transaction;
use Rhyme\ContaoIsotopePaytraceBundle\Connector\Endpoint\Token;
use Rhyme\ContaoIsotopePaytraceBundle\Helper\TokenHelper;

class Paytrace extends Payment implements IsotopeBackendInterface
{
    /**
     * @inheritdoc
     */
    public function checkoutForm(IsotopeProductCollection $objOrder, Module $objModule)
    {
        if (!$objOrder instanceof IsotopePurchasableCollection) {
            System::log($GLOBALS['TL_LANG']['ERR']['collection_not_purchasable'], __METHOD__, TL_ERROR);
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
    function documentReadyPaytrace{$this->id}(fn) {
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

    window.documentReadyPaytrace{$this->id}(function() {
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
                if (!empty($authData) && ($authData['data']['response_code'] ?? null) === 101) {
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
                System::log(\sprintf($GLOBALS['TL_LANG']['ERR']['paytrace_transaction_attempt'], $response['errorCode'] . '] "'.$strErrors.'"'), __METHOD__, TL_ERROR);
            }
            else {
                $objOrder->setDatePaid(\time());
                $objOrder->save();

                // Pack it up, people. We're done here
                System::log(\sprintf($GLOBALS['TL_LANG']['MSC']['transaction_sale_success'], ' Transaction ID: "' . $response['response']['data']['transaction_id']  . '"'), __METHOD__, TL_GENERAL);
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

        try
        {
            if (empty($accessToken = System::getContainer()->get(TokenHelper::class)->getAccessToken($client, $this->paytrace_username, $this->paytrace_password))) {
                return [
                    'hasError' => true,
                    'errorMessage' => $GLOBALS['TL_LANG']['ERR']['access_token_retrieval']
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

            // Todo: Use Connector\Model\* classes to build the request body to limit potential errors
            $response = $keyedSale->sendRequest(
                $client,
                [
                    'amount' => \round($objOrder->getTotal(), 2),
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
                $accessToken
            );

            if (empty($response = \json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR))) {
                System::log($GLOBALS['TL_LANG']['ERR']['paytrace_no_api_response'], __METHOD__, TL_ERROR);
                return [
                    'hasError' => true,
                    'errorMessage' => $GLOBALS['TL_LANG']['ERR']['paytrace_payment_not_processed2']
                ];
            }

            // Store response data in order for future references
            $arrPayment = StringUtil::deserialize($objOrder->payment_data, true);
            $arrPayment['PAYTRACE_E2EE'] = $arrPayment['PAYTRACE_E2EE'] ?? [];
            $arrPayment['PAYTRACE_E2EE'][] = $response;

            $objOrder->payment_data = $arrPayment;
            $objOrder->save();

            if (($response['data']['response_code'] ?? null) !== 101) {
                System::log(\sprintf($GLOBALS['TL_LANG']['ERR']['paytrace_transaction_attempt'], '[' . ($response['data']['response_code'] ?? '') . '] "'.($response['data']['response_message'] ?? '').'"'), __METHOD__, TL_ERROR);
                return [
                    'hasError' => true,
                    'errorMessage' => \sprintf($GLOBALS['TL_LANG']['ERR']['paytrace_payment_not_processed1'], ($response['data']['response_message'] ?? ''))
                ];
            }

            return [
                'hasError' => false,
                'response' => $response
            ];
        }
        catch (ClientException $e) {
            System::log(\sprintf($GLOBALS['TL_LANG']['ERR']['paytrace_transaction_attempt'], (string)$e->getResponse()->getBody()->getContents()), __METHOD__, TL_ERROR);
            return [
                'hasError' => true,
                'errorMessage' => $GLOBALS['TL_LANG']['ERR']['paytrace_payment_not_processed2']
            ];
        }
        catch (\Exception $e) {
            System::log(\sprintf($GLOBALS['TL_LANG']['ERR']['paytrace_transaction_attempt'], '[' . $e->getCode() . '] "'.$e->getMessage().'"'), __METHOD__, TL_ERROR);
            return [
                'hasError' => true,
                'errorMessage' => $GLOBALS['TL_LANG']['ERR']['paytrace_payment_not_processed2']
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

    /**
     * @param int $collectionId
     * @return bool
     */
    public function hasBackendInterface(int $collectionId): bool
    {
        return true;
    }


    /**
     * Return information and/or advanced features in the backend.
     * //TODO - ADD METHOD TO CAPTURE PAYMENT FOR AUTH-ONLY TRANSACTIONS
     *
     * @param int $collectionId
     *
     * @return string
     */
    public function renderBackendInterface(int $collectionId): string
    {
        $template = new BackendTemplate('be_iso_payment_rhyme_paytrace');
        $template->setData($this->row());

        $template->collectionId  = $collectionId;
        $template->formSubmit    = 'form_submit_paytrace_'.$collectionId;
        $template->order         = Order::findByPk($collectionId);
        $template->paymentData   = $template->order !== null ? StringUtil::deserialize($template->order->payment_data, true) : [];
        $template->requestToken  = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
        $template->transactionId = '';

        // See if we have a successful transaction for capture, void, refund, etc. buttons
        foreach ((array)($template->paymentData['PAYTRACE_E2EE'] ?? []) as $attempt) {
            if (!empty($attempt['data']['response_code'])
                && (int)$attempt['data']['response_code'] === 101
                && !empty($attempt['data']['transaction_id'])
            ) {
                $template->transactionId = (string)$attempt['data']['transaction_id'];
                break;
            }
        }

        if ($template->transactionId)
        {
            try {
                $template->transactionData = $this->getTransactionDataFromAPI($template->transactionId);
            }
            catch (RedirectResponseException | AjaxRedirectResponseException $e) {
            }
            catch (ClientException $e) {
                Message::addError(\sprintf($GLOBALS['TL_LANG']['MSC']['paytrace_err_get_transaction'], $e->getResponse()->getBody()->getContents()));
            }
            catch (GuzzleException | \Exception $e) {
                Message::addError(\sprintf($GLOBALS['TL_LANG']['MSC']['paytrace_err_get_transaction'], ' [' . $e->getCode() . '] "'.$e->getMessage().'"'));
            }

            // Handle form submissions
            if (Input::post('FORM_SUBMIT') === $template->formSubmit) {

                // Void payment
                if (isset($_POST['void'])) {

                    try {
                        $this->sendVoidRequest($template->transactionId);

                        $template->order->paytrace_void_requested = 1;
                        $template->order->save();

                        Message::addConfirmation($GLOBALS['TL_LANG']['MSC']['transaction_void_success']);
                        Controller::reload();
                    }
                    catch (RedirectResponseException | AjaxRedirectResponseException $e) {
                    }
                    catch (ClientException $e) {
                        Message::addError(\sprintf($GLOBALS['TL_LANG']['MSC']['paytrace_err_void_transaction'], $e->getResponse()->getBody()->getContents()));
                    }
                    catch (GuzzleException | \Exception $e) {
                        Message::addError(\sprintf($GLOBALS['TL_LANG']['MSC']['paytrace_err_void_transaction'], ' [' . $e->getCode() . '] "'.$e->getMessage().'"'));
                    }
                }

                // Refund payment
                if (isset($_POST['refund'])) {

                    // Todo: allow backend user to specify the amount to refund
                    if ($amount = (float)($template->transactionData['settle_amount'] ?? 0)) {

                        try {
                            $this->sendRefundRequest($template->transactionId, $amount);

                            $template->order->paytrace_refund_amt_requested = $amount;
                            $template->order->save();

                            Message::addConfirmation($GLOBALS['TL_LANG']['MSC']['transaction_refund_success']);
                            Controller::reload();
                        }
                        catch (RedirectResponseException | AjaxRedirectResponseException $e) {
                        }
                        catch (ClientException $e) {
                            Message::addError(\sprintf($GLOBALS['TL_LANG']['MSC']['paytrace_err_refund_transaction'], $e->getResponse()->getBody()->getContents()));
                        }
                        catch (GuzzleException | \Exception $e) {
                            Message::addError(\sprintf($GLOBALS['TL_LANG']['MSC']['paytrace_err_refund_transaction'], ' [' . $e->getCode() . '] "'.$e->getMessage().'"'));
                        }
                    }
                    else {
                        Message::addError(\sprintf($GLOBALS['TL_LANG']['MSC']['paytrace_refund_missing_settlement_amt'], ''));
                    }
                }

                // Todo: add other actions like capture, etc.
            }
        }

        return $template->parse();
    }


    /**
     * @param string $transactionId
     * @return array
     * @throws \Exception|\GuzzleHttp\Exception\GuzzleException
     */
    protected function getTransactionDataFromAPI(string $transactionId): array
    {
        // Create a client
        $client = PaytraceClient::create(
            $this->paytrace_username,
            $this->paytrace_password,
            $this->paytrace_integrator_id,
            (bool)$this->debug
        );

        if (empty($accessToken = System::getContainer()->get(TokenHelper::class)->getAccessToken($client, $this->paytrace_username, $this->paytrace_password))) {
            throw new \Exception($GLOBALS['TL_LANG']['ERR']['access_token_retrieval']);
        }

        $transactionData = new Transaction();

        $response = $transactionData->sendRequest(
            $client,
            $transactionId,
            $this->paytrace_merchant_id,
            $accessToken
        );

        if (empty($response = \json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR))) {
            throw new \Exception($GLOBALS['TL_LANG']['ERR']['paytrace_no_api_response']);
        }

        if (($response['status'] ?? null) !== 'success') {
            $message = isset($response['data']) && \is_array($response['data']) ? \json_encode($response['data'], JSON_THROW_ON_ERROR) : 'General API error.';
            throw new \Exception(
                $message,
                $response['status_code'] ?? null
            );
        }

        return $response['data'];
    }


    /**
     * @param string $transactionId
     * @param float $amount
     * @return bool
     * @throws \Exception|\GuzzleHttp\Exception\GuzzleException
     */
    protected function sendRefundRequest(string $transactionId, float $amount): bool
    {
        // Create a client
        $client = PaytraceClient::create(
            $this->paytrace_username,
            $this->paytrace_password,
            $this->paytrace_integrator_id,
            (bool)$this->debug
        );

        if (empty($accessToken = System::getContainer()->get(TokenHelper::class)->getAccessToken($client, $this->paytrace_username, $this->paytrace_password))) {
            throw new \Exception($GLOBALS['TL_LANG']['ERR']['access_token_retrieval']);
        }

        $refund = new Refund();

        $response = $refund->sendRequest(
            $client,
            [
                'merchant_id' => $this->paytrace_merchant_id,
                'batch_items' => [
                    [
                        'transaction_id' => $transactionId,
                        'amount' => $amount
                    ]
                ],
            ],
            $accessToken
        );

        if (empty($response = \json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR))) {
            throw new \Exception($GLOBALS['TL_LANG']['ERR']['paytrace_no_api_response']);
        }

        if (($response['status'] ?? null) !== 'success'
            || empty($response['data'])
            || !\is_array($response['data'])
            || ($response['data'][0]['status'] ?? null) === 'error'
        ) {
            $message = isset($response['data']) && \is_array($response['data']) ? \json_encode($response['data'], JSON_THROW_ON_ERROR) : 'General API error.';

            throw new \Exception(
                $message,
                $response['status_code'] ?? null
            );
        }

        return true;
    }


    /**
     * @param string $transactionId
     * @return bool
     * @throws \Exception|\GuzzleHttp\Exception\GuzzleException
     */
    protected function sendVoidRequest(string $transactionId): bool
    {
        // Create a client
        $client = PaytraceClient::create(
            $this->paytrace_username,
            $this->paytrace_password,
            $this->paytrace_integrator_id,
            (bool)$this->debug
        );

        if (empty($accessToken = System::getContainer()->get(TokenHelper::class)->getAccessToken($client, $this->paytrace_username, $this->paytrace_password))) {
            throw new \Exception($GLOBALS['TL_LANG']['ERR']['access_token_retrieval']);
        }

        $voidTransaction = new VoidTransaction();

        $response = $voidTransaction->sendRequest(
            $client,
            [
                'merchant_id' => $this->paytrace_merchant_id,
                'batch_items' => [
                    [
                        'transaction_id' => $transactionId,
                    ]
                ],
            ],
            $accessToken
        );

        if (empty($response = \json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR))) {
            throw new \Exception($GLOBALS['TL_LANG']['ERR']['paytrace_no_api_response']);
        }

        if (($response['status'] ?? null) !== 'success'
            || empty($response['data'])
            || !\is_array($response['data'])
            || ($response['data'][0]['status'] ?? null) === 'error'
        ) {
            $message = isset($response['data']) && \is_array($response['data']) ? \json_encode($response['data'], JSON_THROW_ON_ERROR) : 'General API error.';

            throw new \Exception(
                $message,
                $response['status_code'] ?? null
            );
        }

        return true;
    }
}