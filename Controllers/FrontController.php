<?php
#App\GP247\Plugins\PaypalExpress\Controllers\FrontController.php
namespace App\GP247\Plugins\PaypalExpress\Controllers;

use App\GP247\Plugins\PaypalExpress\AppConfig;
use App\GP247\Plugins\PaypalExpress\Services\PaypalService;
use GP247\Front\Controllers\RootFrontController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\GP247\Plugins\PaypalExpress\Services\PaypalWebhookService;

class FrontController extends RootFrontController
{
    protected $paypalService;
    public $plugin;

    public function __construct()
    {
        parent::__construct();
        $this->plugin = new AppConfig;
        $this->paypalService = new PaypalService();
    }

    public function index()
    {
        //Nothing
    }

    /**
     * Process order
     *
     * @return void
     */
    public function processOrder()
    {
        $data = session()->all();
        if (empty($data['orderID']) || empty($data['dataOrder']) || empty($data['arrCartDetail'])) {
            gp247_report('PayPal Process Order - Missing required session data: ' . json_encode($data));
            return redirect()->route('front.home')->with('error', 'Missing required order data');
        }
        $orderID = $data['orderID'];
        $dataOrder = $data['dataOrder'];
        $arrCartDetail = $data['arrCartDetail'];

        $order = \GP247\Shop\Models\ShopOrder::find($orderID);
        if (!$order) {
            gp247_report('PayPal Process Order - Order not found: ' . $orderID);
            return redirect()->route('front.home')->with('error', 'Order not found');
        }

        try {
            $orderData = [
                'order_id' => $orderID,
                'description' => "Order ID: ".$orderID,
                'currency' => $dataOrder['currency'],
                'total' => $dataOrder['total'],
                'subtotal' => $dataOrder['subtotal'],
                'tax' => $dataOrder['tax'] ?? 0,
                'shipping' => $dataOrder['shipping'] ?? 0,
                'discount' => $dataOrder['discount'] ?? 0,
                'other_fee' => $dataOrder['other_fee'] ?? 0,
                'items' => $arrCartDetail
            ];

            $result = $this->paypalService->createOrder($orderData);
            if (empty($result['id'])) {
                gp247_report('PayPal Process Order - Failed to get token ID: ' . json_encode($result));
                return redirect()->route('front.home')->with('error', 'Failed to create PayPal order');
            }

            // Update transaction ID in database
            $paypalToken = $result['id'];
            session(['paypalToken' => $paypalToken]);
            
            $approvalUrl = null;
            foreach ($result['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approvalUrl = $link['href'];
                    break;
                }
            }

            if (!$approvalUrl) {
                gp247_report('PayPal Process Order - No approval URL found: ' . json_encode($result));
                return redirect()->route('front.home')->with('error', 'Invalid PayPal response');
            }
            //Dont use header('Location: ' . $approvalUrl);
            //Because session will be lost
            return redirect()->away($approvalUrl);

        } catch (\Exception $e) {
            gp247_report('PayPal Process Order - Error: ' . $e->getMessage());
            
            // Check if error is related to unsupported currency
            $errorMessage = $e->getMessage();
            if (stripos($errorMessage, 'currency') !== false && 
                (stripos($errorMessage, 'not supported') !== false || 
                 stripos($errorMessage, 'invalid') !== false || 
                 stripos($errorMessage, 'unsupported') !== false)) {
                
                // Extract currency code from error message if possible
                $currencyCode = $dataOrder['currency'];
                
                return redirect()->route('front.home')->with('error', 
                    'Currency ' . $currencyCode . ' is not supported by PayPal. Please try with a different currency.');
            }
            
            return redirect()->route('front.home')->with('error', 'PayPal Process for order '.$orderID.' failed. Please contact to administrator.');
        }
    }

    /**
     * Capture payment after approval
     * Security measures:
     * 1. Verify PayPal response
     * 2. Check session data
     * 3. Validate order status
     */
    public function capturePayment()
    {
        try {
            // Get token and PayerID from PayPal approval response
            $token = request()->token;
            $PayerID = request()->PayerID;

            if (!$token || !$PayerID) {
                return response()->json(['error' => 'Missing PayPal approval parameters'], 400);
            }

            // Get order data from session
            $sessionData = session()->all();
            $orderID = $sessionData['orderID'] ?? null;
            
            if (!$orderID) {
                gp247_report('PayPal Capture Payment - Missing order ID: ' . json_encode($sessionData));
                return redirect()->route('front.home')->with('error', 'Invalid session or missing order data');
            }

            // Verify order exists and is in pending state
            $order = \GP247\Shop\Models\ShopOrder::where('id', $orderID)
                ->first();

            if (!$order) {
                gp247_report('PayPal Capture Payment - Order not found: ' . $orderID);
                return redirect()->route('front.home')->with('error', 'Order not found');
            }

            // Verify the token matches the one we stored
            if (session('paypalToken') !== $token) {
                gp247_report('PayPal Capture Payment - Invalid transaction token: ' . $token);
                return redirect()->route('front.home')->with('error', 'Invalid transaction token');
            }

            // Capture the payment
            $result = $this->paypalService->captureOrder($token);
            

            if ($result['status'] === 'COMPLETED') {
                //Destroy session
                session()->forget('paypalToken');

                $transaction = $result['purchase_units'][0]['payments']['captures'][0]['id'] ?? null; 
                // Update order status
                $order->update([
                    'transaction' => $transaction,
                    'status' => gp247_config($this->plugin->configKey.'_order_status_success'),
                    'payment_status' => gp247_config($this->plugin->configKey.'_payment_status_success')
                ]);

                //Add history
                $dataHistory = [
                    'order_id' => $orderID,
                    'content' => 'Transaction ' . $transaction,
                    'customer_id' => $order->customer_id ?? 0,
                    'order_status_id' => gp247_config($this->plugin->configKey.'_order_status_success'),
                ];
                $order->addOrderHistory($dataHistory);
                return (new \GP247\Shop\Controllers\ShopCartController)->completeOrder();
            }
        } catch (\Exception $e) {
            gp247_report('PayPal Capture Payment - Error: ' . $e->getMessage());
            return redirect()->route('front.home')->with('error', 'Capture payment for order failed. Please contact to administrator.');
        }
    }

    /**
     * Cancel payment
     */
    public function cancelPayment()
    {
        return (new \GP247\Shop\Controllers\ShopCartController)->cancelOrder();
    }


    /**
     * Handle PayPal webhook
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function handleWebhook(Request $request)
    {
        try {
            // Get webhook data
            $webhookData = $request->all();
            // gp247_report('PayPal Webhook - Webhook data: ' . json_encode($webhookData));
            
            // Log all headers for debugging
            // $allHeaders = $request->header();
            // gp247_report('PayPal Webhook - All Headers: ' . json_encode($allHeaders));
            
            // Extract PayPal headers
            $transmissionId = $request->header('paypal-transmission-id');
            $transmissionTime = $request->header('paypal-transmission-time');
            $transmissionSig = $request->header('paypal-transmission-sig');
            $certUrl = $request->header('paypal-cert-url');
            
           
            // Verify webhook signature
            $paypalService = new PaypalService();
            $verificationData = [
                'transmission_id' => $transmissionId,
                'transmission_time' => $transmissionTime,
                'event_body' => $request->getContent(),
                'transmission_sig' => $transmissionSig,
                'cert_url' => $certUrl,
                'webhook_id' => config('Plugins/PaypalExpress.webhook_id')
            ];
            
            // Log verification data
            // gp247_report('PayPal Webhook - Verification Data: ' . json_encode($verificationData));
            
            $verificationResult = $paypalService->verifyWebhookSignature($verificationData);
            
            if (!$verificationResult || $verificationResult['verification_status'] !== 'SUCCESS') {
                gp247_report('PayPal Webhook - Invalid signature');
                return response('Invalid signature', 400);
            }
            
            // Process webhook
            $webhookService = new PaypalWebhookService();
            $result = $webhookService->processWebhook($webhookData);
            
            if ($result) {
                return response('Webhook processed successfully', 200);
            } else {
                return response('Error processing webhook', 500);
            }
        } catch (\Exception $e) {
            gp247_report('PayPal Webhook - Error: ' . $e->getMessage());
            return response('Internal server error', 500);
        }
    }
}