<?php

namespace App\Http\Controllers;

use App\Common\Helpers;
use App\Integrations\PaperLess\eCouncil;
use App\Integrations\TechPay\HostedCheckOut;
use App\Integrations\WhatsAppService;
use App\Models\MerchantApplications;
use App\Models\Merchants;
use App\Models\PayerKyc;
use App\Models\PaymentProviders;
use App\Models\ProcessedMessages;
use App\Models\Transactions;
use App\Models\TransactionsBreakdown;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use stdClass;
use Symfony\Component\HttpFoundation\ParameterBag;

class OldWhatsAppController extends Controller
{
    private bool $is_new_request;
    private string $msisdn;
    private string $session_id;
    private string $user_input;
    private string $app_name;
    private Model $last_session;
    private string $request_reference;

    private string $contact_name;
    private string $businessPhoneNumberId;
    private string $messageId;

    private string $driver = 'WHATSAPP';

    private WhatsAppService $whatsAppMessageService;


    private array $menu = [
        '1' => [
            'text' => 'Make Payment',
            'function' => 'pay_merchant',
            'action' => 'get_account_number'
        ],
        '2' => [
            'text' => 'Check Transaction History',
            'function' => 'check_transaction_history',
            'action' => ''
        ],
    ];
    private Collection $event;
    /**
     * @var false|resource|string|null
     */
    private $content;
    private ParameterBag $payload;
    private array $buttons;
    /**
     * @var mixed|null
     */
    private mixed $sender;

    public function __construct()
    {
        $this->app_name = config('app.friendly_name');
        $this->whatsAppMessageService = new WhatsAppService();
    }

    public function handleWebhook(Request $request)
    {

        $request_time = date('Y-m-d h:i:s');
        $request_type = 'WHATSAPP_REQUEST';
        $this->request_reference = Helpers::generateUUIDV4();
        Helpers::logApiRequest($request->all(), ['entry'], $request_time, $response_time = date('Y-m-d h:i:s'), '', '', $this->request_reference, $request->reference, 'SUCCESS', $request_type);
        Helpers::createBasicLog('whatsapp', "Request: " . json_encode($request->all()), $this->request_reference);

        $this->payload = new ParameterBag((array)json_decode($request->getContent(), true)['entry'][0]['changes'][0]['value']);
        $this->messages = Collection::make((array)$this->payload->get('messages') ? (array)$this->payload->get('messages')[0] : $this->payload);

        $this->content = json_decode($request->getContent());

        $entry = $request->input('entry', [])[0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;
        $message = $value['messages'][0] ?? null;
        $status = $value['statuses'][0] ?? null;
        //get display_phone_number
        $this->sender = $value['metadata']['display_phone_number'] ?? null;
        $this->session_id = $entry['id'] ?? null;

        if ($message) {

            $businessPhoneNumberId = $value['metadata']['phone_number_id'] ?? null;
            $from = $message['from'] ?? null;

            $messageId = $message['id'] ?? null;
            $text = $this->whatsAppMessageService->getMessageText($message);
            $this->msisdn = $from;
            $this->user_input = trim($text);
            $this->contact_name = $changes['value']['contacts'][0]['profile']['name'] ?? 'Unknown';

            $old_session = Helpers::getLastSession($this->session_id, $this->msisdn);

            $this->is_new_request = ($old_session == null);
            if ($old_session != null)
                $this->last_session = $old_session;


            if ($businessPhoneNumberId && $from && $messageId) {
                $this->businessPhoneNumberId = $businessPhoneNumberId;
                $this->messageId = $messageId;

                if (ProcessedMessages::where('message_id', $messageId)->exists()) {
                    return response(['already_processed' => true], 200);
                } else {
                    $processedMessage = new ProcessedMessages();
                    $processedMessage->message_id = $messageId;
                    $processedMessage->driver = $this->driver;
                    $processedMessage->save();
                }

                $this->whatsAppMessageService->markMessageAsRead($businessPhoneNumberId, $messageId);

                if ($this->is_new_request == 1) {
                    Helpers::logSession($this->session_id, $this->msisdn, 'welcome', 'get_menu', [], $this->request_reference, $this->sender, $this->content);
                    Helpers::logApiRequest($request->all(), ['welcome'], $request_time, $response_time = date('Y-m-d h:i:s'), '', '', $this->request_reference, $request->reference, 'SUCCESS', $request_type);
                    return $this->sendResponse([], false, 'welcome');
                } elseif ($this->user_input === '000') {
                    $new_session = Helpers::logSession($this->session_id, $this->msisdn, 'exit', 'quit', [], $this->request_reference, $this->sender, $this->content);
                    $this->last_session = $new_session;
                    return $this->handleReturningUser();
                } else {
                    $last_session = Helpers::getLastSession($this->session_id, $this->msisdn);

                    if ($last_session == null) {

                        Helpers::logApiRequest($request->all(), ['welcome'], $request_time, $response_time = date('Y-m-d h:i:s'), '', '', $this->request_reference, $request->reference, 'SUCCESS', $request_type);
                        return $this->sendResponse([], false, 'welcome');
                    } else {
                        $this->request_reference = $last_session->request_reference;
                        $this->last_session = $last_session;
                        return $this->handleReturningUser();
                    }
                }
            }
        }
        return response([], 200);
    }

    public function verifyWebhook(Request $request)
    {

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');
        Log::info('Webhook verification request:', $request->all());
        return response($challenge, 200);
//
//        if ($mode === 'subscribe' && $token === env('WEBHOOK_VERIFY_TOKEN')) {
//            Log::info('Webhook verified successfully!');
//            return response($challenge, 200);
//        }
//        Log::error('Webhook verification failed! Local token:' . env('WEBHOOK_VERIFY_TOKEN') . ', Request token: ' . $token);
//        return response()->json(['error' => 'Forbidden'], 403);
    }


    private function handleReturningUser()
    {
        $last_session = $this->last_session;
        $function = $last_session->function;
        if ($function == 'welcome') {
            return $this->welcome();
        } else if ($function == 'pay_merchant') {
            return $this->payMerchant();
        } elseif ($function == 'check_transaction_history') {
            return $this->checkTransactionHistory();
        } elseif ($function == 'exit') {
            return $this->exit();
        } else {
            $response = [
                'msg' => "Apologies for the inconvenience. We're unable to process your request at the moment. Please try again later.",
                'end_session' => 'true',
                'request_reference' => $this->request_reference,
            ];
            return $this->sendResponse($response);
        }
    }

    private function welcome()
    {
        $function = $this->user_input;
        if ($function == 'Make payment' || $function == '1') {
            Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'validate_account_number', [], $this->request_reference, $this->sender, $this->content);
            $user_numbers = Helpers::getBotFud($this->msisdn, $this->driver, 'COUNCIL_PAYMENTS', 'ACCOUNT_NUMBER');
            $this->buttons = [];
            foreach ($user_numbers as $displayValue => $systemValue) {
                $this->buttons[$systemValue] = $systemValue;
            }
            if (count($this->buttons) == 0) {
                $response = [
                    'msg' => "Please enter your Council Customer Account Number.",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'validate_account_number', [], $this->request_reference, $this->sender, $this->content);
                return $this->sendResponse($response);
            } else {
                $response = [
                    'msg' => "Please enter your customer account number or choose from the options below.",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'validate_account_number', [], $this->request_reference, $this->sender, $this->content);
                return $this->sendResponse($response, false, 'buttons');
            }
        } elseif ($function == 'Check statement' || $function == 'Check history' || $function == '2') {
            return $this->checkTransactionHistory();
        } elseif ($function == 'exit') {
            return $this->exit();
        } else {
            $response = [
                'msg' => "Invalid option selected. Kindly select an option from the menu",
            ];
            $this->whatsAppMessageService->sendWelcomeMenu($this->businessPhoneNumberId, $this->msisdn, $this->messageId, $response['msg']);
        }
        return 0;
    }

    private
    function payMerchant()
    {
        $session_log = $this->last_session;

        $action = $session_log->action ?? '';
        if ($action == 'get_account_number') {
            $user_numbers = Helpers::getBotFud($this->msisdn, $this->driver, 'COUNCIL_PAYMENTS', 'ACCOUNT_NUMBER');
            $this->buttons = [];
            foreach ($user_numbers as $displayValue => $systemValue) {
                $this->buttons[$systemValue] = $systemValue;
            }
            if (count($this->buttons) == 0) {
                $response = [
                    'msg' => "Kindly provide your Council Customer Account Number",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'validate_account_number', [], $this->request_reference, $this->sender, $this->content);
                return $this->sendResponse($response);
            } else {
                $response = [
                    'msg' => "Kindly provide your customer account number OR select from the list below",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'validate_account_number', [], $this->request_reference, $this->sender, $this->content);
                return $this->sendResponse($response, false, 'buttons');
            }
        } elseif ($action == 'validate_account_number') {
            if (strlen($this->user_input) < 5) {
                $response = [
                    'msg' => "The account number is too short. Kindly enter a valid account number",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'validate_account_number', [], $this->request_reference, $this->sender, $this->content);
                return $this->sendResponse($response);
            }
            try {
                $ecouncil = new eCouncil();
                $bills = $ecouncil->queryBillsByAccountNumber($this->user_input);
            } catch (\Exception $e) {
                $response = [
                    'msg' => $e->getMessage(),
                    'end_session' => 'true',
                    'request_reference' => $this->request_reference
                ];
                Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'validate_account_number', [], $this->request_reference, $this->sender, $this->content);
                Log::error("Could not fetch bills: " . $e->getMessage());
                return $this->sendResponse($response);
            }

            if (count($bills['cart_items']) == 0) {
                $response = [
                    'msg' => "You currently have no unpaid bills.\n\nPlease provide a different account number or contact your council for assistance.",
                    'end_session' => 'true',
                    'request_reference' => $this->request_reference
                ];
                Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'validate_account_number', [], $this->request_reference, $this->sender, $this->content);
                return $this->sendResponse($response);
            }

            $session_bills = [];
            foreach ($bills['cart_items'] as $bill) {
                if ($bill['description'] == 'Balance B/F')
                    continue;
                $session_bills[] = [
                    "reference" => $bill['reference'],
                    "accountNo" => $bill['accountNo'],
                    "accountName" => $bill['accountName'],
                    "description" => $bill['description'],
                    "altDescription" => $bill['altDescription'],
                    "balanceDue" => $bill['balanceDue'],
                    'raw' => $bill
                ];
            }
            $session_data = new stdClass();
            $session_data->bills = json_encode($session_bills);
            Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'get_bill', $session_data, $this->request_reference, $this->sender, $this->content);

            $bills = '';
            $accountName = '';
            $index = 1;
            $this->buttons = [];
            foreach ($session_bills as $ref => $session_bill) {
                $bill = "$index. " . 'K' . number_format($session_bill['balanceDue'], 2) . ' - ' . $session_bill['description'];
                $bills .= $bill . "\n";
                $accountName = $session_bill['accountName'];
                $this->buttons[$index] = substr($bill, 0, 20);
                $index++;
            }

            Helpers::logBotUserFud($this->msisdn, $this->user_input . ' - ' . $accountName, $this->user_input, $this->driver, 'ACCOUNT_NUMBER', 'COUNCIL_PAYMENTS');

            $message = "Hello $accountName,\nYou currently have " . count($session_bills) . " unpaid bills.\n";
            $message .= "Please choose the bill you would like to pay from the list below:\n\n";
            $message .= $bills;

            $response = [
                'msg' => $message,
                'end_session' => 'false',
                'request_reference' => $this->request_reference
            ];
            return $this->sendResponse($response, false, 'buttons');

        } elseif ($action == 'get_bill') {
            $session_data = json_decode($this->last_session->session_data);
            $bills = json_decode($session_data->bills, true);

            $this->user_input = (((int)$this->user_input) - 1);
            $bill = $bills[$this->user_input] ?? null;
            if ($bill == null) {
                $my_bills = '';
                $accountName = '';
                $index = 1;
                $this->buttons = [];
                foreach ($bills as $session_bill) {
                    $bill = "$index. " . 'K' . number_format($session_bill['balanceDue'], 2) . ' - ' . $session_bill['description'];
                    $my_bills .= $bill . "\n";
                    $accountName = $session_bill['accountName'];
                    $this->buttons[$index] = substr($bill, 0, 20);
                    $index++;
                }

                $message = "Your selection is invalid. Please select a valid bill.\nHello $accountName,\nYou currently have " . count($bills) . " unpaid bills.\n";
                $message .= "Please choose the bill you would like to pay from the list below:\n\n";
                $message .= $my_bills;

                $response = [
                    'msg' => $message,
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];

                Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'get_bill', $session_data, $this->request_reference, $this->sender, $this->content);
                return $this->sendResponse($response, false, 'buttons');
            }

            $session_data->bill = $bill;
            Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'get_amount', $session_data, $this->request_reference, $this->sender, $this->content);
            $string = 'K' . number_format($bill['balanceDue'], 2) . ' - ' . $bill['description'];
            $balance = $bill['balanceDue'];
            $half = number_format(($balance / 2), 2, '.', '');
            $quarter = number_format(($half / 2), 2, '.', '');

            $this->buttons = [];
            $this->buttons["$balance"] = "K" . number_format($balance, 2) . " (Full)";
            if ($half > 1)
                $this->buttons["$half"] = "K" . number_format($half, 2) . " (Half)";
            if ($quarter > 1)
                $this->buttons["$quarter"] = "K" . number_format($quarter, 2) . " (Quarter)";

            $response = [
                'msg' => "You're about to pay {$string}.\n\nPlease enter the payment amount or choose from the options provided below:",
                'end_session' => 'false',
                'request_reference' => $this->request_reference
            ];
            return $this->sendResponse($response, false, 'buttons');
        } elseif ($action == 'get_amount') {
            $amount = $this->user_input;
            if (!is_numeric($amount)) {
                $response = [
                    'msg' => "The amount you entered is invalid. Please enter a valid amount.",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'get_amount', $this->last_session->session_data, $this->request_reference, $this->sender, $this->content);
                return $this->sendResponse($response);
            }
            $session_data = json_decode($this->last_session->session_data);

            $session_data->amount = $amount;
            Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'get_payment_method', $session_data, $this->request_reference, $this->sender, $this->content);
//
            $response = [
                'msg' => "Please select your preferred payment method:\n1. Mobile Money\n2. Debit/Credit Card",
                'end_session' => 'false',
                'request_reference' => $this->request_reference
            ];
            $this->buttons = [];
            $this->buttons['1'] = 'Mobile Money';
            $this->buttons['2'] = 'Debit/Credit Card';
            return $this->sendResponse($response, false, 'buttons');

        } elseif ($action == 'get_payment_method') {
            $session_data = json_decode($this->last_session->session_data);
            $amount = "K" . number_format($session_data->amount, 2);

            if ($this->user_input == '1' || strtolower($this->user_input) == 'mobile money') {
                $session_data->payment_method = 'MOBILE MONEY';
                Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'get_mobile', $session_data, $this->request_reference, $this->sender, $this->content);
                $user_numbers = Helpers::getBotFud($this->msisdn, $this->driver, 'COUNCIL_PAYMENT', 'MOBILE');

                if (Helpers::isValidZambianMobileNumber($this->msisdn) && !array_key_exists($this->msisdn, $user_numbers)) {
                    $user_numbers[$this->msisdn] = $this->msisdn;
                }

                $this->buttons = [];
                foreach ($user_numbers as $displayValue => $systemValue) {
                    $this->buttons[$systemValue] = $systemValue;
                }
                if (count($this->buttons) == 0) {
                    $response = [
                        'msg' => "Please enter your Mobile Money phone number:",
                        'end_session' => 'false',
                        'request_reference' => $this->request_reference
                    ];
                    return $this->sendResponse($response);
                } else {
                    $response = [
                        'msg' => "Please enter your Mobile Money phone number or select one from the list below:",
                        'end_session' => 'false',
                        'request_reference' => $this->request_reference
                    ];
                    return $this->sendResponse($response, false, 'buttons');
                }
            } elseif ($this->user_input == '2' || strtolower($this->user_input) == 'debit/credit card') {
                $session_data->payment_method = 'CARD';
                Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'confirm_payment', $session_data, $this->request_reference, $this->sender, $this->content);
                $this->buttons = [];
                $this->buttons['1'] = 'Confirm';
                $this->buttons['2'] = 'Cancel';
                $response = [
                    'msg' => "You're about to make a payment of {$amount} via Card. Please confirm your action:\n1. Confirm\n2. Cancel",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                return $this->sendResponse($response, false, 'buttons');
            } else {
                $this->buttons = [];
                $this->buttons['1'] = 'Mobile Money';
                $this->buttons['2'] = 'Debit/Credit Card';
                $response = [
                    'msg' => "The payment option you selected is invalid. Please choose a valid option.",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                return $this->sendResponse($response, false, 'buttons');
            }
        } elseif ($action == 'get_mobile') {
            $session_data = json_decode($this->last_session->session_data);
            $mobile = preg_replace('/[^0-9]/', '', $this->user_input);
            $bill = $session_data->bill;

            if (empty($mobile) || !is_numeric($mobile)) {
                $response = [
                    'msg' => "Please provide a valid mobile number. The number you've entered seems to be incorrect.",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                return $this->sendResponse($response);
            } elseif (strlen($mobile) < 10) {
                $response = [
                    'msg' => "Please provide a valid mobile number. The number you've entered appears to be too short.",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                return $this->sendResponse($response);
            } elseif (strlen($mobile) > 12) {
                $response = [
                    'msg' => "Please provide a valid mobile number. The number you've entered appears to be too long.",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                return $this->sendResponse($response);
            }
            if (strlen($mobile) == 10)
                $mobile = '26' . $mobile;

            if (!Helpers::isValidZambianMobileNumber($mobile)) {
                $response = [
                    'msg' => "Please enter a valid Zambian mobile number. It appears that the number you've provided ({$mobile}) is not valid.",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                return $this->sendResponse($response);
            }

            $session_data->mobile = $mobile;
            Helpers::logSession($this->session_id, $this->msisdn, 'pay_merchant', 'confirm_payment', $session_data, $this->request_reference, $this->sender, $this->content);
            Helpers::logBotUserFud($this->msisdn, $mobile, $mobile, $this->driver, 'MOBILE', 'COUNCIL_PAYMENT');
            $response = [
                'msg' => "You're about to make a payment of K{$session_data->amount} for {$bill->description}. Please confirm your action:\n1. Confirm\n2. Cancel",
                'end_session' => 'false',
                'request_reference' => $this->request_reference
            ];
            $this->buttons = [];
            $this->buttons['1'] = 'Confirm';
            $this->buttons['2'] = 'Cancel';
            return $this->sendResponse($response, false, 'buttons');
        } elseif ($action == 'confirm_payment') {

            $session_data = json_decode($this->last_session->session_data);
            $amount = $session_data->amount;
            $payment_method = $session_data->payment_method;
            $bill = $session_data->bill;
            $balance = $bill->balanceDue;
            $reference = $bill->accountNo;
            $description = $bill->description;
            $sender = $this->last_session->sender;
            $app = MerchantApplications::where('class', $sender)->first();
            $merchant = $app->merchant;
            $provider = $app->provider;

            if ($this->user_input == '1') {

                $item_id = $bill->accountNo;
                $mobile = $session_data->mobile ?? '';
                $maid = $app->id;
                $payment_mode = $session_data->payment_method;

                $user_ip = Helpers::getUserIp();
                $user_agent = request()->header('User-Agent');
                Helpers::LogPerformance('TRANSACTIONS', 'TXN_CREATION_ATTEMPT', $maid, $item_id, $this->driver . '_COUNCIL_COLLECTION', $user_ip, $user_agent, '', '', '', request()->all());


                $kyc = [
                    'firstName' => $this->contact_name,
                    'lastName' => '',
                    'email' => '',
                    'mobile' => $mobile,
                    'address' => $this->msisdn,
                    'id' => $this->businessPhoneNumberId,
                    'company' => '',
                    'customerType' => $this->driver,
                    'tpin' => $this->messageId
                ];
                $payments = [
                    [
                        'amountPaid' => $amount,
                        'balanceDue' => $balance,
                        'reference' => $reference,
                        'description' => $description,
                    ]
                ];

                try {

                    $txn_reference = Helpers::generateUUID();
                    $trans_kyc = new PayerKyc();
                    $trans_kyc->reference = $txn_reference;
                    $trans_kyc->first_name = $kyc['firstName'] ?? '';
                    $trans_kyc->surname = $kyc['lastName'] ?? '';
                    $trans_kyc->tpin = $kyc['tpin'] ?? '';
                    $trans_kyc->company = $kyc['company'] ?? '';
                    $trans_kyc->email = $kyc['email'] ?? '';
                    $trans_kyc->mobile = $kyc['mobile'] ?? '';
                    $trans_kyc->address = $kyc['address'] ?? '';
                    $trans_kyc->national_id = $kyc['id'] ?? '';
                    $trans_kyc->customer_type = $kyc['customerType'] ?? '';
                    $trans_kyc->save();

                    $transaction = new Transactions();
                    $transaction->reference = $txn_reference;
                    $transaction->amount = $amount;
                    $transaction->status = 'PENDING';
                    $transaction->settlement_status = 'PENDING';
                    $transaction->payer_kyc_id = $trans_kyc->id;
                    $transaction->merchant_code = $provider->merchant_code;
                    $transaction->merchant_id = $merchant->id;
                    $transaction->payment_provider_id = $provider->id;
                    $transaction->payment_type = 'COUNCIL_COLLECTION';
                    $transaction->payment_channel = $this->driver;
                    $transaction->reference_1 = $this->msisdn;
                    $transaction->reference_2 =  $reference;
                    $transaction->reference_3 = $description;
                    $transaction->save();

                    foreach ($payments as $payment) {
                        $item_id = new TransactionsBreakdown();
                        $item_id->transaction_id = $transaction->id;
                        $item_id->amount = $payment['amountPaid'];
                        $item_id->balance_as_at = $payment['balanceDue'];
                        $item_id->reference = $payment['reference'];
                        $item_id->payment_type = 'COUNCIL_COLLECTION';
                        $item_id->settlement_status = 'PENDING';
                        $item_id->description = $payment['description'];
                        $item_id->details = json_encode($bill);
                        $item_id->merchant_application_id = $app->id;
                        $item_id->merchant_application_name = $app->name;
                        $item_id->qty = 1;
                        $item_id->save();
                    }

                    $return = url("query/$app->id/cb?");
                    $client = new HostedCheckOut($provider);
                    $token = $client->getToken($amount, $txn_reference, $description . ' - ' . $reference, $return);
                    $url = $client->getEndpoint() . '/checkout/' . $token . '?option=1';

                    $transaction->provider_payment_reference = $token;
                    $transaction->save();

                    if ($payment_method == 'MOBILE MONEY') {
                        $response = [
                            'msg' => "Shortly, you will receive a prompt to approve a payment of K{$amount} to {$merchant->name} on the number {$mobile}. Please approve this payment to finalize the transaction. We will notify you once your payment has been successfully processed.",
                            'end_session' => 'true',
                            'request_reference' => $this->request_reference
                        ];
                        $this->sendResponse($response, true);
                        $client->pushMobilePayment($mobile, $token);
                    } else {
//
                        $response = [
                            'msg' => "Please click on the link below to finalize your payment using your Credit/Debit Card.\n\n$url" .
                                "\n\nWe will send you a notification once your payment has been successfully processed.",
                            'end_session' => 'true',
                            'request_reference' => $this->request_reference
                        ];
                        $this->sendResponse($response, true);
                    }
                    $user_ip = Helpers::getUserIp();
                    $user_agent = request()->header('User-Agent');
                    Helpers::LogPerformance('TRANSACTIONS', 'TXN_CREATED', $maid, $item_id, 'CUSTOM_FORM_COLLECTION', $user_ip, $user_agent, $transaction->id, "Payment mode: $payment_mode", "Mobile $mobile", request()->all());


                    $response = [
                        'msg' => "If you have any questions, please don't hesitate to contact {$merchant->name} at {$merchant->mobile} or via email at {$merchant->email}."
                            . "\n\nTo access more services, simply reply with 'Hi'."
                            . "\n\nThank you for using {$this->app_name}.",
                        'end_session' => 'true',
                        'request_reference' => $this->request_reference
                    ];
                    Helpers::endSession($this->session_id, $this->msisdn);
                    return $this->sendResponse($response, true);
                } catch (\Exception $e) {
                    $response = [
                        'msg' => "We're sorry, but an error occurred while processing your payment. Could you please try again later?",
                        'end_session' => 'true',
                        'request_reference' => $this->request_reference
                    ];
                    Log::error("Could not create transaction: " . $e->getMessage());
                    return $this->sendResponse($response);
                }
            } elseif ($this->user_input == '2') {
                $response = [
                    'msg' => "Your payment of K{$amount} to {$merchant->name} has been cancelled.",
                    'end_session' => 'true',
                    'request_reference' => $this->request_reference
                ];
                return $this->sendResponse($response);
            } else {
                $response = [
                    'msg' => "The option you selected is invalid. Please select a valid option.\n\nYou are about to pay K{$session_data->amount} for {$bill->description}.\n\nPlease confirm:\n1. Confirm\n2. Cancel",
                    'end_session' => 'false',
                    'request_reference' => $this->request_reference
                ];
                $this->buttons = [];
                $this->buttons['1'] = 'Confirm';
                $this->buttons['2'] = 'Cancel';
                return $this->sendResponse($response, false, 'buttons');
            }
        }
    }

    private
    function checkTransactionHistory(): \Illuminate\Foundation\Application|\Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $mobile = $this->msisdn;
        $transactions = Transactions::where('reference_1', $mobile)->orderBy('id', 'desc')->limit(10)->get();
        $response = "Your last 10 transactions:\n";
        foreach ($transactions as $transaction) {
            $amount = number_format($transaction->amount, 2);
            $response .= "Description: {$transaction->reference_3}, Amount: K{$amount},\nStatus: {$transaction->status}\nTransaction Date: {$transaction->created_at}\n\n";
        }
        $response = $response . "To access more services, simply reply with 'Hi'.\n\nThank"
            . " you for using {$this->app_name}.";
        $response = [
            'msg' => $response,
            'end_session' => 'true',
            'request_reference' => $this->request_reference
        ];
        Helpers::endSession($this->session_id, $this->msisdn);
        return $this->sendResponse($response, true);
    }

    private
    function exit()
    {
        Helpers::endSession($this->session_id, $this->msisdn);
        $salutation = Helpers::getByeSalutation();
        $response = [
            'msg' => "Thank you for using {$this->app_name}. If you need further assistance, simply reply with 'Hi'.\n\n$salutation 👋!",
            'end_session' => 'true',
            'request_reference' => $this->request_reference
        ];
        return $this->sendResponse($response, true);
    }

    private function sendResponse($response, $is_end = false, $template = 'text'): \Illuminate\Http\Response
    {
        $from = $this->msisdn;
        $messageId = $this->messageId;
        $businessPhoneNumberId = $this->businessPhoneNumberId;
        $text = $response['msg'] ?? '';

        if (!$is_end)
            $text = $text . "\n\n" . "To exit, please reply with 000.";
        if ($template == 'text') {
            $this->whatsAppMessageService->sendMessage($businessPhoneNumberId, $from, $messageId, $text);
        } elseif ($template == 'welcome') {
            $body = "Hello {$this->contact_name}! 😃\nI'm here to assist you with your Council services payments via Mobile Money or Bank Card.\n\nPlease select an option below to get started.";
            $this->whatsAppMessageService->sendWelcomeMenu($businessPhoneNumberId, $from, $messageId, $body);
        } elseif ($template == 'buttons') {
            $this->whatsAppMessageService->sendMessageWithButtons($businessPhoneNumberId, $from, $messageId, $text, $this->buttons);
        }
        return response([], 200);
    }
}
