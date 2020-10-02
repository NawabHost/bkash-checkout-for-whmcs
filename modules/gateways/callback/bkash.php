<?php

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\Request;
use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

class bKashCheckout
{
    /**
     * @var self
     */
    private static $instance;

    /**
     * @var string
     */
    protected $gatewayModuleName;

    /**
     * @var array
     */
    protected $gatewayParams;

    /**
     * @var boolean
     */
    public $isSandbox;

    /**
     * @var boolean
     */
    public $isActive;

    /**
     * @var integer
     */
    protected $customerCurrency;

    /**
     * @var object
     */
    protected $gatewayCurrency;

    /**
     * @var integer
     */
    protected $clientCurrency;

    /**
     * @var float
     */
    protected $convoRate;

    /**
     * @var array
     */
    protected $invoice;

    /**
     * @var float
     */
    protected $due;

    /**
     * @var float
     */
    protected $fee;

    /**
     * @var float
     */
    public $total;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    public $request;

    /**
     * @var array
     */
    private $credential;

    /**
     * bKashCheckout constructor.
     */
    function __construct()
    {
        $this->setGateway();
        $this->setHttpClient();
        $this->setRequest();
        $this->setInvoice();
    }

    /**
     * The instance.
     *
     * @return self
     */
    public static function init()
    {
        if (self::$instance == null) {
            self::$instance = new bKashCheckout;
        }

        return self::$instance;
    }

    /**
     * Set the payment gateway.
     */
    private function setGateway()
    {
        $this->gatewayModuleName = basename(__FILE__, '.php');
        $this->gatewayParams     = getGatewayVariables($this->gatewayModuleName);
        $this->isSandbox         = !empty($this->gatewayParams['sandbox']);
        $this->isActive          = !empty($this->gatewayParams['type']);

        $this->credential = [
            'username'  => $this->gatewayParams['username'],
            'password'  => $this->gatewayParams['password'],
            'appKey'    => $this->gatewayParams['appKey'],
            'appSecret' => $this->gatewayParams['appSecret'],
        ];
    }

    /**
     * Get and set request
     */
    private function setRequest()
    {
        $this->request = Request::createFromGlobals();
    }

    /**
     * Set guzzle as HTTP client.
     */
    private function setHttpClient()
    {
        $bkashUrl = $this->isSandbox ? 'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/' : 'https://checkout.pay.bka.sh/v1.2.0-beta/checkout/';

        $this->httpClient = new Client(
            [
                'base_uri'    => $bkashUrl,
                'http_errors' => false,
                'timeout'     => 30,
                'headers'     => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
            ]
        );
    }

    /**
     * Set the invoice.
     */
    private function setInvoice()
    {
        $this->invoice = localAPI('GetInvoice', [
            'invoiceid' => $this->request->get('id'),
        ]);

        $this->setCurrency();
        $this->setDue();
        $this->setFee();
        $this->setTotal();
    }

    /**
     * Set currency
     */
    private function setCurrency()
    {
        $this->gatewayCurrency  = (int)$this->gatewayParams['convertto'];
        $this->customerCurrency = Capsule::table('tblclients')
                                         ->where('id', '=', $this->invoice['userid'])
                                         ->value('currency');

        if (!empty($this->gatewayCurrency) && ($this->customerCurrency !== $this->gatewayCurrency)) {
            $this->convoRate = Capsule::table('tblcurrencies')
                                      ->where('id', '=', $this->gatewayCurrency)
                                      ->value('rate');
        } else {
            $this->convoRate = 1;
        }
    }

    /**
     * Set due.
     */
    private function setDue()
    {
        $this->due = $this->invoice['balance'];
    }

    /**
     * Set Fee.
     */
    private function setFee()
    {
        $this->fee = empty($this->gatewayParams['fee']) ? 0 : (($this->gatewayParams['fee'] / 100) * $this->due);
    }

    /**
     * Set Total.
     */
    private function setTotal()
    {
        $this->total = ceil(($this->due + $this->fee) * $this->convoRate);
    }

    /**
     * Grant token from bKash.
     *
     * @return array
     */
    private function grantToken()
    {
        try {
            $response = $this->httpClient->post('token/grant', [
                'headers' => [
                    'username' => $this->credential['username'],
                    'password' => $this->credential['password'],
                ],
                'json'    => [
                    'app_key'    => $this->credential['appKey'],
                    'app_secret' => $this->credential['appSecret'],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (is_array($data)) {
                return $data;
            }

            return [
                'status'  => 'error',
                'message' => 'Invalid response from bKash API.',
            ];
        } catch (GuzzleException $exception) {
            return [
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ];
        }

    }

    /**
     * Get token from memory.
     *
     * @return mixed
     */
    private function getToken()
    {
        $token = $this->grantToken();

        return (is_array($token) && isset($token['id_token'])) ? $token['id_token'] : null;
    }

    /**
     * Get refresh token from the memory.
     *
     * @return mixed
     */
    private function getRefreshToken()
    {
        $token = $this->grantToken();

        return (is_array($token) && isset($token['refresh_token'])) ? $token['refresh_token'] : null;
    }

    /**
     * Store token in the memory.
     *
     * @return boolean
     */
    private function storeToken()
    {
        // TODO: Implement this
    }

    /**
     * Refresh the token.
     *
     * @return array
     */
    private function refreshToken()
    {
        $refreshToken = $this->refreshToken();

        try {
            $response = $this->httpClient->post('token/refresh', [
                'headers' => [
                    'username' => $this->credential['username'],
                    'password' => $this->credential['password'],
                ],
                'json'    => [
                    'app_key'       => $this->credential['appKey'],
                    'app_secret'    => $this->credential['appSecret'],
                    'refresh_token' => $refreshToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (is_array($data)) {
                return $data;
            }

            return [
                'status'  => 'error',
                'message' => 'Invalid response from bKash API.',
            ];
        } catch (GuzzleException $exception) {
            return [
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Create payment session.
     *
     * @return array
     */
    public function createPayment()
    {
        try {
            $response = $this->httpClient->post('payment/create', [
                'headers' => [
                    'Authorization' => $this->getToken(),
                    'X-APP-KEY'     => $this->credential['appKey'],
                ],
                'json'    => [
                    'amount'                => $this->total,
                    'currency'              => 'BDT',  // TODO: Make dynamic
                    'intent'                => 'sale', // TODO: Make dynamic
                    'merchantInvoiceNumber' => $this->invoice['invoiceid'],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (is_array($data)) {
                return $data;
            }

            return [
                'status'  => 'error',
                'message' => 'Invalid response from bKash API.',
            ];
        } catch (GuzzleException $exception) {
            return [
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Execute the payment by ID.
     *
     * @return array
     */
    private function executePayment()
    {
        $paymentId = $this->request->get('payId');
        try {
            $response = $this->httpClient->post('payment/execute/' . $paymentId, [
                'headers' => [
                    'authorization' => $this->getToken(),
                    'X-APP-KEY'     => $this->credential['appKey'],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (is_array($data)) {
                return $data;
            }

            return [
                'status'  => 'error',
                'message' => 'Invalid response from bKash API.',
            ];

        } catch (GuzzleException $exception) {
            return [
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Query the payment by ID.
     *
     * @return array
     */
    private function queryPayment()
    {
        $paymentId = $this->request->get('payId');
        try {
            $response = $this->httpClient->get('payment/query/' . $paymentId, [
                'headers' => [
                    'Authorization' => $this->getToken(),
                    'X-APP-KEY'     => $this->credential['appKey'],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (is_array($data)) {
                return $data;
            }

            return [
                'status'  => 'error',
                'message' => 'Invalid response from bKash API.',
            ];

        } catch (GuzzleException $exception) {
            return [
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Void payment by the ID.
     *
     * @return array
     */
    private function voidPayment()
    {
        $paymentId = $this->request->get('payId');
        try {
            $response = $this->httpClient->post('payment/void/' . $paymentId, [
                'headers' => [
                    'Authorization' => $this->getToken(),
                    'X-APP-KEY'     => $this->credential['appKey'],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (is_array($data)) {
                return $data;
            }

            return [
                'status'  => 'error',
                'message' => 'Invalid response from bKash API.',
            ];

        } catch (GuzzleException $exception) {
            return [
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Check if transaction if exists.
     *
     * @param string $trxId
     *
     * @return mixed
     */
    private function checkTransaction($trxId)
    {
        return localAPI(
            'GetTransactions',
            ['transid' => $trxId]
        );
    }

    /**
     * Log the transaction.
     *
     * @param array $payload
     *
     * @return mixed
     */
    private function logTransaction($payload)
    {
        return logTransaction(
            $this->gatewayParams['name'],
            [
                'bkash' => $payload,
                'post'  => $this->request->request->all(),
            ],
            $payload['transactionStatus']
        );
    }

    /**
     * Add transaction to the invoice.
     *
     * @param string $trxId
     *
     * @return array
     */
    private function addTransaction($trxId)
    {
        $fields = [
            'invoiceid' => $this->invoice['invoiceid'],
            'transid'   => $trxId,
            'gateway'   => $this->gatewayModuleName,
            'date'      => Carbon::now()->toDateTimeString(),
        ];
        $add    = localAPI('AddInvoicePayment', $fields);

        return array_merge($add, $fields);
    }

    /**
     * Make the transaction.
     *
     * @return array
     */
    public function makeTransaction()
    {
        $execute = $this->executePayment();

        if (isset($execute['transactionStatus']) && $execute['transactionStatus'] === 'Completed') {
            $existing = $this->checkTransaction($execute['trxID']);

            if ($existing['totalresults'] > 0) {
                return [
                    'status'  => 'error',
                    'message' => 'The transaction has been already used.',
                ];
            }

            if ($execute['amount'] < $this->total) {
                return [
                    'status'  => 'error',
                    'message' => 'You\'ve paid less than amount is required.',
                ];
            }

            $this->logTransaction($execute);

            $trxAddResult = $this->addTransaction($execute['trxID']);

            if ($trxAddResult['result'] === 'success') {
                return [
                    'status'  => 'success',
                    'message' => 'The payment has been successfully verified.',
                    'data'    => $trxAddResult,
                ];
            }

            return [
                'status'  => 'error',
                'message' => 'Unable to create transaction.',
                'data'    => $trxAddResult,
            ];
        }

        return [
            'status'  => 'error',
            'message' => 'Payment validation error.',
        ];
    }
}

if (!$_SERVER['REQUEST_METHOD'] === 'POST') {
    die("Direct access forbidden.");
}

$bKashCheckout = bKashCheckout::init();

if (!$bKashCheckout->isActive) {
    die("The gateway is unavailable.");
}

$response = [
    'status'  => 'error',
    'message' => 'Invalid action.',
];

switch ($bKashCheckout->request->get('action')) {
    case 'init':
        $response = $bKashCheckout->createPayment();
        break;
    case 'verify':
        $response = $bKashCheckout->makeTransaction();
        break;
}

header('Content-Type: application/json');

echo json_encode($response);