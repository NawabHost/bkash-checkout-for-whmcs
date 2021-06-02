<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function bkashcheckout_MetaData()
{
    return [
        'DisplayName'                 => 'bKash merchant (Checkout)',
        'APIVersion'                  => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage'            => false,
    ];
}

function bkashcheckout_config()
{
    return [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'bKash Merchant (Checkout)',
        ],
        'username'     => [
            'FriendlyName' => 'Username',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => 'Enter your bKash merchant username',
        ],
        'password'     => [
            'FriendlyName' => 'Password',
            'Type'         => 'password',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => 'Enter your bKash merchant password',
        ],
        'appKey'       => [
            'FriendlyName' => 'App Key',
            'Type'         => 'password',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => 'Enter the bKash app key',
        ],
        'appSecret'    => [
            'FriendlyName' => 'App Secret',
            'Type'         => 'password',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => 'Enter the bKash app secret',
        ],
        'fee'          => [
            'FriendlyName' => 'Fee',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => 1.85,
            'Description'  => 'Gateway fee if you want to add',
        ],
        'sandbox'      => [
            'FriendlyName' => 'Sandbox',
            'Type'         => 'yesno',
            'Description'  => 'Tick to enable sandbox mode',
        ],
    ];
}

function bkashcheckout_errors($vars)
{
    $errors = [
        "2001" => "Invalid App Key.",
        "2002" => "Invalid Payment ID.",
        "2003" => "Process failed.",
        "2004" => "Invalid firstPaymentDate.",
        "2005" => "Invalid frequency.",
        "2006" => "Invalid amount.",
        "2007" => "Invalid currency.",
        "2008" => "Invalid intent.",
        "2009" => "Invalid Wallet.",
        "2010" => "Invalid OTP.",
        "2011" => "Invalid PIN.",
        "2012" => "Invalid Receiver MSISDN.",
        "2013" => "Resend Limit Exceeded.",
        "2014" => "Wrong PIN.",
        "2015" => "Wrong PIN count exceeded.",
        "2016" => "Wrong verification code.",
        "2017" => "Wrong verification limit exceeded.",
        "2018" => "OTP verification time expired.",
        "2019" => "PIN verification time expired.",
        "2020" => "Exception Occurred.",
        "2021" => "Invalid Mandate ID.",
        "2022" => "The mandate does not exist.",
        "2023" => "Insufficient Balance.",
        "2024" => "Exception occurred.",
        "2025" => "Invalid request body.",
        "2026" => "The reversal amount cannot be greater than the original transaction amount.",
        "2027" => "The mandate corresponding to the payer reference number already exists and cannot be created again.",
        "2028" => "Reverse failed because the transaction serial number does not exist.",
        "2029" => "Duplicate for all transactions.",
        "2030" => "Invalid mandate request type.",
        "2031" => "Invalid merchant invoice number.",
        "2032" => "Invalid transfer type.",
        "2033" => "Transaction not found.",
        "2034" => "The transaction cannot be reversed because the original transaction has been reversed.",
        "2035" => "Reverse failed because the initiator has no permission to reverse the transaction.",
        "2036" => "The direct debit mandate is not in Active state.",
        "2037" => "The account of the debit party is in a state which prohibits execution of this transaction.",
        "2038" => "Debit party identity tag prohibits execution of this transaction.",
        "2039" => "The account of the credit party is in a state which prohibits execution of this transaction.",
        "2040" => "Credit party identity tag prohibits execution of this transaction.",
        "2041" => "Credit party identity is in a state which does not support the current service.",
        "2042" => "Reverse failed because the initiator has no permission to reverse the transaction.",
        "2043" => "The security credential of the subscriber is incorrect.",
        "2044" => "Identity has not subscribed to a product that contains the expected service or the identity is not in Active status.",
        "2045" => "The MSISDN of the customer does not exist.",
        "2046" => "Identity has not subscribed to a product that contains requested service.",
        "2047" => "TLV Data Format Error.",
        "2048" => "Invalid Payer Reference.",
        "2049" => "Invalid Merchant Callback URL.",
        "2050" => "Agreement already exists between payer and merchant.",
        "2051" => "Invalid Agreement ID.",
        "2052" => "Agreement is in incomplete state.",
        "2053" => "Agreement has already been cancelled.",
        "2054" => "Agreement execution pre-requisite hasn't been met.",
        "2055" => "Invalid Agreement State.",
        "2056" => "Invalid Payment State.",
        "2057" => "Not a bKash Account.",
        "2058" => "Not a Customer Wallet.",
        "2059" => "Multiple OTP request for a single session denied.",
        "2060" => "Payment execution pre-requisite hasn't been met.",
        "2061" => "This action can only be performed by the agreement or payment initiator party.",
        "2062" => "The payment has already been completed.",
        "2063" => "Mode is not valid as per request data.",
        "2064" => "This product mode currently unavailable.",
        "2065" => "Mendatory field missing.",
        "2066" => "Agreement is not shared with other merchant.",
        "2067" => "Invalid permission.",
        "2068" => "Transaction has already been completed.",
        "2069" => "Transaction has already been cancelled.",
        "503"  => "System is undergoing maintenance. Please try again later.",
        "lpa"  => "You paid less amount than required.",
        "tau"  => "The transaction already has been used.",
        "irs"  => "Invalid response from the bKash Server.",
        "ucnl" => "You didn't completed the payment process.",
    ];

    $message = null;

    if (!empty($_REQUEST['bkashErrorCode'])) {
        $error   = isset($errors[$_REQUEST['bkashErrorCode']]) ? $errors[$_REQUEST['bkashErrorCode']] : 'Unknown error!';
        $message = '<div class="alert alert-danger" style="margin-top: 10px;" role="alert">' . $error . '</div>';
    }

    return $message;
}

function bkashcheckout_link($params)
{
    $bkashLogo = 'https://scripts.pay.bka.sh/resources/img/bkash_payment.png';
    $bkashScripts = bkashcheckout_scriptsHandle($params);
    $errorMessage = bkashcheckout_errors($params);
    $markup       = <<<HTML
    <img id="bkashcheckout_button_real" src="$bkashLogo">
    <button id="bKash_button"></button>
    <style type="text/css">
        #bkashcheckout_button_real { max-width: 175px; height: auto;}
        #bkashcheckout_button_real:hover { cursor: pointer; }
        #bkashcheckout_button_real.loading { opacity: 0.5; pointer-events: none;}
        #bKash_button { display: none; }
    </style>
    $bkashScripts
    $errorMessage
HTML;
    return $markup;
}

function bkashcheckout_scriptsHandle($params)
{
    $script = 'https://scripts.pay.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout.js';
    if (!empty($params['sandbox'])) {
        $script = 'https://scripts.sandbox.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout-sandbox.js';
    }

    $apiUrl = $params['systemurl'] . 'modules/gateways/callback/' . $params['paymentmethod'] . '.php';
    $invUrl = $params['returnurl'];
    $invId  = $params['invoiceid'];

    $markup = <<<HTML
<script type="text/javascript" src="$script"></script>
<script>
    window.addEventListener('load', function() {
        var apiUrl = "$apiUrl";
        var invUrl = "$invUrl";
        var invId = "$invId";
        var bKashBtnReal = $('#bkashcheckout_button_real');

        bKashBtnReal.on('click', function(e) {
            e.preventDefault();
            bKashBtnReal.addClass('loading');

            $.ajax({
                method: "POST",
                url: apiUrl,
                data: {
                    action: 'init',
                    id: invId
                }
            }).done(function(response) {
                bkashWhmcsHandle(response);
            });
        })

        function bkashWhmcsHandle(params) {
            bKash.init({
                paymentMode: 'checkout',
                paymentRequest: {
                    amount: params.amount,
                    intent: params.intent,
                },

                createRequest: function (request) {
                    if ((typeof params === 'object' && params !== null) && params.hasOwnProperty('paymentID')) {
                        bKash.create().onSuccess(params);
                    } else {
                        bKash.create().onError();
                        window.location = invUrl + '&paymentfailed=true&bkashErrorCode=500';
                    }
                },
                executeRequestOnAuthorization: function () {
                    $.ajax({
                        method: "POST",
                        url: apiUrl,
                        data: {
                            action: 'verify',
                            id: invId,
                            payId: params.paymentID,
                        }
                    }).done(function(vres) {
                        if (vres.status === 'success') {
                            window.location = invUrl + '&paymentsuccess=true';
                        } else {
                            window.location = invUrl + '&paymentfailed=true&bkashErrorCode=' + vres.errorCode;
                        }
                    }).fail(function() {
                        bKash.execute().onError();
                        window.location = invUrl + '&paymentfailed=true&bkashErrorCode=500';
                    });
                },
                onClose: function () {
                    window.location = invUrl + '&paymentfailed=true&bkashErrorCode=ucnl';
                },
                onError: function() {
                    window.location = invUrl + '&paymentfailed=true&bkashErrorCode=unkr';
                }
            });

            $('#bKash_button').click();
        }
    });
</script>
HTML;

    return $markup;
}
