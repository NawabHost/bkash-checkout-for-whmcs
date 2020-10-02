<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function bkash_MetaData()
{
    return [
        'DisplayName'                 => 'bKash Merchant',
        'APIVersion'                  => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage'            => false,
    ];
}

function bkash_config()
{
    return [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'bKash Merchant',
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

function bkash_link($params)
{
    $bkashScripts = bkash_scriptsHandle($params);
    $markup       = <<<HTML
    <button class="btn btn-primary" id="bkash_button_real"><i class="fas fa-circle-notch fa-spin hidden" style="margin-right: 5px"></i>Pay using bKash</button>
    <button class="hidden" id="bKash_button"></button>
    $bkashScripts
HTML;
    return $markup;
}

function bkash_scriptsHandle($params)
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
        var bKashBtnReal = $('#bkash_button_real');

        bKashBtnReal.on('click', function(e) {
            e.preventDefault();
            bKashBtnReal.attr('disabled', 'disabled');
            $('i', bKashBtnReal).removeClass('hidden');

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
                        window.location = invUrl + '&paymentfailed=true';
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
                            window.location = invUrl + '&paymentfailed=true';
                        }
                    }).fail(function() {
                        bKash.execute().onError();
                        window.location = invUrl + '&paymentfailed=true';
                    });
                },
                onClose: function () {
                    window.location = invUrl + '&paymentfailed=true';
                },
                onError: function() {
                    window.location = invUrl + '&paymentfailed=true';
                }
            });

            $('#bKash_button').click();
        }
    });
</script>
HTML;

    return $markup;
}