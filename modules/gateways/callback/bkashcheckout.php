<?php

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
     * @var string
     */
    protected $baseUrl;

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
    public function __construct()
    {
        $this->setGateway();
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

        $this->baseUrl = $this->isSandbox ? 'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/' : 'https://checkout.pay.bka.sh/v1.2.0-beta/checkout/';
    }

    /**
     * Set request.
     */
    private function setRequest()
    {
        $this->request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
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
     * Set currency.
     */
    private function setCurrency()
    {
        $this->gatewayCurrency  = (int) $this->gatewayParams['convertto'];
        $this->customerCurrency = (int) \WHMCS\Database\Capsule::table('tblclients')
            ->where('id', '=', $this->invoice['userid'])
            ->value('currency');

        if (!empty($this->gatewayCurrency) && ($this->customerCurrency !== $this->gatewayCurrency)) {
            $this->convoRate = \WHMCS\Database\Capsule::table('tblcurrencies')
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
     * Set fee.
     */
    private function setFee()
    {
        $this->fee = empty($this->gatewayParams['fee']) ? 0 : (($this->gatewayParams['fee'] / 100) * $this->due);
    }

    /**
     * Set total.
     */
    private function setTotal()
    {
        $this->total = ceil(($this->due + $this->fee) * $this->convoRate);
    }

    /**
     * Grant and get token from API.
     *
     * @return mixed
     */
    private function getToken()
    {
        $fields   = [
            'app_key'    => $this->credential['appKey'],
            'app_secret' => $this->credential['appSecret'],
        ];
        $context  = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n" .
                    "username: {$this->credential['username']}\r\n" .
                    "password: {$this->credential['password']}\r\n",
                'content' => json_encode($fields),
                'timeout' => 30,
            ],
        ];
        $context  = stream_context_create($context);
        $url      = $this->baseUrl . 'token/grant';
        $response = file_get_contents($url, false, $context);
        $token    = json_decode($response, true);

        return (is_array($token) && isset($token['id_token'])) ? $token['id_token'] : null;
    }

    /**
     * Create payment session.
     *
     * @return array
     */
    public function createPayment()
    {
        $fields   = [
            'amount'                => $this->total,
            'currency'              => 'BDT',
            'intent'                => 'sale',
            'merchantInvoiceNumber' => $this->invoice['invoiceid'] . '-' . rand(1000000, 9999999),
        ];
        $context  = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n" .
                    "Authorization: {$this->getToken()}\r\n" .
                    "X-APP-KEY: {$this->credential['appKey']}\r\n",
                'content' => json_encode($fields),
                'timeout' => 30,
            ],
        ];
        $context  = stream_context_create($context);
        $url      = $this->baseUrl . 'payment/create';
        $response = file_get_contents($url, false, $context);
        $data     = json_decode($response, true);

        if (is_array($data)) {
            return $data;
        }

        return [
            'status'    => 'error',
            'message'   => 'Invalid response from bKash API.',
            'errorCode' => 'irs',
        ];
    }

    /**
     * Execute the payment by ID.
     *
     * @return array
     */
    private function executePayment()
    {
        $paymentId = $this->request->get('payId');
        $context   = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n" .
                    "Authorization: {$this->getToken()}\r\n" .
                    "X-APP-KEY: {$this->credential['appKey']}\r\n",
                'timeout' => 30,
            ],
        ];

        $context  = stream_context_create($context);
        $url      = $this->baseUrl . 'payment/execute/' . $paymentId;
        $response = file_get_contents($url, false, $context);
        $data     = json_decode($response, true);

        if (is_array($data)) {
            return $data;
        }

        return [
            'status'    => 'error',
            'message'   => 'Invalid response from bKash API.',
            'errorCode' => 'irs',
        ];
    }

    private function queryPayment()
    {
        $paymentId = $this->request->get('payId');
        $context   = [
            'http' => [
                'method'  => 'GET',
                'header'  => "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n" .
                    "Authorization: {$this->getToken()}\r\n" .
                    "X-APP-KEY: {$this->credential['appKey']}\r\n",
                'timeout' => 30,
            ],
        ];

        $context  = stream_context_create($context);
        $url      = $this->baseUrl . 'payment/query/' . $paymentId;
        $response = file_get_contents($url, false, $context);
        $data     = json_decode($response, true);

        if (is_array($data)) {
            return $data;
        }

        return [
            'status'    => 'error',
            'message'   => 'Invalid response from bKash API.',
            'errorCode' => 'irs',
        ];
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
                $this->gatewayModuleName => $payload,
                'request_data'           => $this->request->request->all(),
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
            'date'      => \Carbon\Carbon::now()->toDateTimeString(),
            'amount'    => $this->due,
            'fees'      => $this->fee,
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
        $executePayment = $this->executePayment();
        
        if (!isset($executePayment['transactionStatus'])) {
            $executePayment = $this->queryPayment();
        }

        if (isset($executePayment['transactionStatus']) && $executePayment['transactionStatus'] === 'Completed') {
            $existing = $this->checkTransaction($executePayment['trxID']);

            if ($existing['totalresults'] > 0) {
                return [
                    'status'    => 'error',
                    'message'   => 'The transaction has been already used.',
                    'errorCode' => 'tau',
                ];
            }

            if ($executePayment['amount'] < $this->total) {
                return [
                    'status'    => 'error',
                    'message'   => 'You\'ve paid less than amount is required.',
                    'errorCode' => 'lpa',
                ];
            }

            $this->logTransaction($executePayment);

            $trxAddResult = $this->addTransaction($executePayment['trxID']);

            if ($trxAddResult['result'] === 'success') {
                return [
                    'status'  => 'success',
                    'message' => 'The payment has been successfully verified.',
                ];
            }
        }

        return $executePayment;
    }
}

if (!$_SERVER['REQUEST_METHOD'] === 'POST') {
    die("Direct access forbidden.");
}

if (!(new \WHMCS\ClientArea())->isLoggedIn()) {
    die("You will need to login first.");
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
die(json_encode($response));
