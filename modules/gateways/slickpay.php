<?php
/**
 * WHMCS Sample Payment Gateway Module
 *
 * Payment Gateway modules allow you to integrate payment solutions with the
 * WHMCS platform.
 *
 * This sample file demonstrates how a payment gateway module for WHMCS should
 * be structured and all supported functionality it can contain.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "gatewaymodule" and therefore all functions
 * begin "slickpay_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _config
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Billing\Payment\Transaction\Information;
use WHMCS\Carbon;

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function slickpay_MetaData()
{
    return array(
        'DisplayName' => 'CIB/EDAHABIA (Slick-Pay)',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function slickpay_config()
{
    $accounts = array();
    $gatewayModuleName = basename(__FILE__, '.php');

    $hint = 'Select your bank account. (Page must be reloaded to display options)';

    if (function_exists('getGatewayVariables')) {
        $gatewayParams = getGatewayVariables($gatewayModuleName);

        if (!empty($gatewayParams['publicKey'])
            && isset($gatewayParams['testMode'])
        ) {

            try {

                $url = slickpay_ApiUrl($gatewayParams['testMode']) . "/users/accounts";

                $cURL = curl_init();

                curl_setopt($cURL, CURLOPT_URL, $url);
                curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
                    "Accept: application/json",
                    "Content-Type: application/json",
                    "Authorization: Bearer {$gatewayParams['publicKey']}",
                ));
                curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 3);
                curl_setopt($cURL, CURLOPT_TIMEOUT, 20);

                $response = curl_exec($cURL);

                curl_close($cURL);

                $accountsList = json_decode($response, true);

                if (!empty($accountsList['data'])) {

                    foreach ($accountsList['data'] as $key => $value) {
                        $accounts[$value['uuid']] = $value['title'];
                    }
                }

            } catch (\Exception $e) {
                $hint = "Select your bank account. (API Error : {$e->getMessage()}";
            }
        }

    } else {
        $hint = 'getGatewayVariables function does not exists.';
    }

    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'CIB/EDAHABIA (Slick-Pay)',
        ),
        // a text field type allows for single line text input
        'publicKey' => array(
            'FriendlyName' => 'Public Key',
            'Type' => 'text',
            'Size' => '128',
            'Default' => '',
            'Description' => 'Your Slick-pay.com account public key.',
        ),
        // the dropdown field type renders a select menu of options
        'bankAccount' => array(
            'FriendlyName' => 'Bank account',
            'Type' => 'dropdown',
            'Options' => $accounts,
            'Description' => $hint,
        ),
        // the yesno field type displays a single checkbox option
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode.',
        ),
    );
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function slickpay_link($params)
{
    // Gateway Configuration Parameters
    $publicKey = $params['publicKey'];
    $bankAccount = $params['bankAccount'];
    $testMode = $params['testMode'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = floatval($params['amount']);
    $currencyCode = $params['currency'];

    if ($currencyCode != 'DZD') { // 012
        return '<div style="margin: 1rem; padding: 1rem; border: 1px solid #9ec5fe; border-radius: 0.375rem; background: #cfe2ff; color: #052c65;"><strong>Notice:</strong> <i>CIB/EDAHABIA (Slick-Pay)</i> is available only for <i>DZD</i> currency!</div>';
    }

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    $url = slickpay_ApiUrl($testMode) . "/users/invoices";

    try {
        $address = "{$address1} {$address2}, {$city}, {$state} - {$postcode}, {$country}";

        $cURL = curl_init();

        curl_setopt($cURL, CURLOPT_URL, $url);
        curl_setopt($cURL, CURLOPT_POSTFIELDS, json_encode([
            'amount'    => $amount,
            'account'   => $bankAccount,
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'phone'     => $phone,
            'email'     => $email,
            'address'   => $address,
            'url'       => $returnUrl,
            'items'     => [
                [
                    'name'     => $description,
                    'price'    => $amount,
                    'quantity' => 1,
                ]
            ],
            'webhook_url' => "{$systemUrl}/modules/gateways/callback/{$moduleName}.php",
            'webhook_signature' => slickpay_StrRand(),
            'webhook_meta_data' => array(
                'x_invoice_id' => $invoiceId,
                'x_amount' => $amount,
                'x_hash' => md5($invoiceId . $publicKey),
            ),
        ]));
        curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: Bearer {$publicKey}",
        ));
        curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($cURL, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($cURL);

        curl_close($cURL);

        $response = json_decode($response, true);

        if (empty($response['url'])) {
            $message = !empty($response['message']) ? $response['message'] : 'Please contact the website administrator';

            return "<div style=\"margin: 1rem; padding: 1rem; border: 1px solid #f1aeb5; border-radius: 0.375rem; background: #f8d7da; color: #58151c;\"><strong>Payment Gateway Error:</strong> {$message}.</div>";
        }

        $postfields = array();
        // $postfields['username'] = $username;
        // $postfields['invoice_id'] = $invoiceId;
        // $postfields['description'] = $description;
        // $postfields['amount'] = $amount;
        // $postfields['currency'] = $currencyCode;
        // $postfields['first_name'] = $firstname;
        // $postfields['last_name'] = $lastname;
        // $postfields['email'] = $email;
        // $postfields['address1'] = $address1;
        // $postfields['address2'] = $address2;
        // $postfields['city'] = $city;
        // $postfields['state'] = $state;
        // $postfields['postcode'] = $postcode;
        // $postfields['country'] = $country;
        // $postfields['phone'] = $phone;
        // $postfields['callback_url'] = "{$systemUrl}/modules/gateways/callback/{$moduleName}.php";
        // $postfields['return_url'] = $returnUrl;

        $htmlOutput = '<form method="post" action="' . $response['url'] . '">';
        foreach ($postfields as $k => $v) {
            $htmlOutput .= '<input type="hidden" name="' . $k . '" value="' . urlencode($v) . '" />';
        }
        $htmlOutput .= '<input type="submit" value="' . $langPayNow . '" />';
        $htmlOutput .= '</form>';

        return $htmlOutput;

    } catch (\Exception $e) {
        return "<div style=\"margin: 1rem; padding: 1rem; border: 1px solid #f1aeb5; border-radius: 0.375rem; background: #f8d7da; color: #58151c;\"><strong>Payment Gateway Error:</strong> {$e->getMessage()}</div>";
    }
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 * @return array Transaction response status

function slickpay_refund($params)
{
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $secretKey = $params['secretKey'];
    $testMode = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to initiate refund and interpret result

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
        // Unique Transaction ID for the refund transaction
        'transid' => $refundTransactionId,
        // Optional fee amount for the fee value refunded
        'fees' => $feeAmount,
    );
}
*/

/**
 * Cancel subscription.
 *
 * If the payment gateway creates subscriptions and stores the subscription
 * ID in tblhosting.subscriptionid, this function is called upon cancellation
 * or request by an admin user.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/subscription-management/
 *
 * @return array Transaction response status

function slickpay_cancelSubscription($params)
{
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $secretKey = $params['secretKey'];
    $testMode = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Subscription Parameters
    $subscriptionIdToCancel = $params['subscriptionID'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to cancel subscription and interpret result

    return array(
        // 'success' if successful, any other value for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
    );
}
*/

/**
 * @param array @params
 *
 * @return Information
 */
function slickpay_TransactionInformation(array $params = []): Information
{
    // Gateway Configuration Parameters
    $publicKey = $params['publicKey'];
    $testMode = $params['testMode'];

    $information = new Information();

    try {
        $url = slickpay_ApiUrl($testMode) . "/invoices/{$params['transactionId']}";

        /**
         * Connect to gateway to retrieve transaction information.
         */
         $cURL = curl_init();

        curl_setopt($cURL, CURLOPT_URL, $url);
        curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: Bearer {$publicKey}",
        ));
        curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($cURL, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($cURL);

        curl_close($cURL);

        $transactionData = json_decode($response, true);

        if (empty($transactionData['id'])) return $information;

        $information->setTransactionId($transactionData['id'])
            ->setAmount($transactionData['amount'])
            // ->setCurrency($transactionData['currency'])
            // ->setType($transactionData['type'])
            // ->setAvailableOn(Carbon::parse($transactionData['available_on']))
            ->setCreated(Carbon::parse($transactionData['date']))
            // ->setDescription($transactionData['description'])
            // ->setFee($transactionData['fee'])
            ->setStatus($transactionData['status']);

        if (!empty($transactionData['transaction']['serial'])) {
            $information->setAdditionalDatum('serial', $transactionData['transaction']['serial']);
        }

        if (!empty($transactionData['transaction']['log'])) {
            $information->setAdditionalDatum('log', $transactionData['transaction']['log']);
        }

    } catch (\Throwable $th) {
        //throw $th;
    }

    return $information;
}

/**
 * Generate Slick-Pay API url.
 *
 * @param mixed $testMode
 *
 * @return string
 */
function slickpay_ApiUrl($testMode): string
{
    if (!is_null($testMode)
        && (
            $testMode == false
            || $testMode == 'no'
            || $testMode == 0
        )
    ) {
        $url = "https://prodapi.slick-pay.com/api/v2";
    } else {
        $url = "https://devapi.slick-pay.com/api/v2";
    }

    return $url;
}

/**
 * Generate Random String.
 *
 * @param number $length
 *
 * @return string
 */
function slickpay_StrRand($length = 10): string
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $randomString;
}