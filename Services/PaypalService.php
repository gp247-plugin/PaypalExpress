<?php

namespace App\GP247\Plugins\PaypalExpress\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class PaypalService
{
    private $client;
    private $clientId;
    private $clientSecret; 
    private $baseUrl;
    private $accessToken;

    public function __construct()
    {
        if(config('Plugins/PaypalExpress.sandbox')){
            $this->clientId = config('Plugins/PaypalExpress.client_id_sandbox');
            $this->clientSecret = config('Plugins/PaypalExpress.client_secret_sandbox');
            $this->baseUrl = 'https://api-m.sandbox.paypal.com';
        }else{
            $this->clientId = config('Plugins/PaypalExpress.client_id_live');
            $this->clientSecret = config('Plugins/PaypalExpress.client_secret_live');
            $this->baseUrl = 'https://api-m.paypal.com';
        }
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);
    }

    /**
     * Get access token from PayPal
     */
    private function getAccessToken()
    {
        try {
            $response = $this->client->post('/v1/oauth2/token', [
                'auth' => [$this->clientId, $this->clientSecret],
                'form_params' => [
                    'grant_type' => 'client_credentials'
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $this->accessToken = $data['access_token'];
            return $this->accessToken;
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to get PayPal access token: ' . $e->getMessage());
        }
    }

    /**
     * Create a direct payment order
     */
    public function createOrder($orderData)
    {
        if (!$this->accessToken) {
            $this->getAccessToken();
        }

        try {
            $response = $this->client->post('/v2/checkout/orders', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken
                ],
                'json' => [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => $orderData['order_id'],
                            'description' => $orderData['description'],
                            'amount' => [
                                'currency_code' => $orderData['currency'],
                                'value' => $orderData['total'],
                                'breakdown' => [
                                    'item_total' => [
                                        'currency_code' => $orderData['currency'],
                                        'value' => $orderData['subtotal']
                                    ],
                                    'tax_total' => [
                                        'currency_code' => $orderData['currency'],
                                        'value' => $orderData['tax'] ?? '0.00'
                                    ],
                                    'discount' => [
                                        'currency_code' => $orderData['currency'],
                                        'value' => $orderData['discount'] ?? '0.00'
                                    ],
                                    'other_fee' => [
                                        'currency_code' => $orderData['currency'],
                                        'value' => $orderData['other_fee'] ?? '0.00'
                                    ],
                                    'shipping' => [
                                        'currency_code' => $orderData['currency'],
                                        'value' => $orderData['shipping'] ?? '0.00'
                                    ]
                                ]
                            ],
                            'items' => $this->formatLineItems($orderData['items'], $orderData['currency'])
                        ]
                    ],
                    'application_context' => [
                        'return_url' => config('Plugins/PaypalExpress.return_url'),
                        'cancel_url' => config('Plugins/PaypalExpress.cancel_url')
                    ]
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to create PayPal order: ' . $e->getMessage());
        }
    }


    /**
     * Capture an order payment
     */
    public function captureOrder($orderId)
    {
        if (!$this->accessToken) {
            $this->getAccessToken();
        }

        try {
            $response = $this->client->post("/v2/checkout/orders/{$orderId}/capture", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to capture PayPal order: ' . $e->getMessage());
        }
    }

    /**
     * Format line items for PayPal API
     */
    private function formatLineItems($items, $currency)
    {
        return array_map(function($item) use ($currency) {
            return [
                'name' => $item['name'],
                'description' => $item['product_id'].':'.json_encode($item['attribute'] ?? []),
                'quantity' => $item['qty'],
                'unit_amount' => [
                    'currency_code' => $currency,
                    'value' => $item['price']
                ]
            ];
        }, $items);
    }

    /**
     * Verify webhook signature from PayPal
     * 
     * @param array $webhookData Webhook data with the following parameters:
     * - transmission_id: PayPal transmission ID
     * - transmission_time: PayPal transmission time
     * - webhook_id: PayPal webhook ID (must be stored from webhook registration)
     * - event_body: Webhook event body
     * - transmission_sig: PayPal transmission signature
     * - cert_url: PayPal certificate URL
     * 
     * @return array Response from PayPal API containing verification status
     * @throws \Exception If verification fails
     */
    public function verifyWebhookSignature($webhookData)
    {
        if (!$this->accessToken) {
            $this->getAccessToken();
        }

        // Validate required parameters
        $requiredParams = ['transmission_id', 'webhook_id', 'transmission_time', 'event_body', 'transmission_sig', 'cert_url'];
        foreach ($requiredParams as $param) {
            if (empty($webhookData[$param])) {
                gp247_report('PayPal Webhook - (#'.$webhookData['webhook_id'].') Missing required parameter: ' . $param);
                throw new \Exception("Missing required parameter: {$param}");
            }
        }
        // Format data according to PayPal requirements
        $requestData = [
            'transmission_id' => $webhookData['transmission_id'],
            'transmission_time' => $webhookData['transmission_time'],
            'webhook_id' => $webhookData['webhook_id'],
            'webhook_event' => json_decode($webhookData['event_body'], true),
            'transmission_sig' => $webhookData['transmission_sig'],
            'cert_url' => $webhookData['cert_url'],
            'auth_algo' => 'SHA256withRSA' // Required by PayPal
        ];

        try {
            $response = $this->client->post('/v1/notifications/verify-webhook-signature', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => $requestData
            ]);

            $result = json_decode($response->getBody(), true);
            
            // Log the response for debugging
            // gp247_report('PayPal Webhook Verification Response: ' . json_encode($result));
            
            // Check verification status
            if (!isset($result['verification_status']) || $result['verification_status'] !== 'SUCCESS') {
                throw new \Exception('Webhook signature verification failed: ' . ($result['verification_status'] ?? 'Unknown status')."\n".json_encode($webhookData['event_body']));
            }
            
            return $result;
        } catch (GuzzleException $e) {
            $errorMessage = $e->getMessage();
            
            // Log the error for debugging
            gp247_report('PayPal Webhook Verification Error: ' . $errorMessage);
            return false;
            
        }
    }
} 