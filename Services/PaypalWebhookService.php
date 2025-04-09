<?php

namespace App\GP247\Plugins\PaypalExpress\Services;

use App\GP247\Plugins\PaypalExpress\Models\PaypalWebhook;
use Illuminate\Support\Facades\Log;

class PaypalWebhookService
{
    /**
     * Process a webhook from PayPal
     *
     * @param array $webhookData
     * @return bool
     */
    public function processWebhook($webhookData)
    {
        try {
            // Extract webhook ID and event type
            $eventId = $webhookData['id'] ?? null;
            $eventType = $webhookData['event_type'] ?? null;
            
            if (!$eventType) {
                gp247_report('PayPal Webhook - Missing event type.'.json_encode($webhookData));
                return false;
            }
            
            // Check if webhook already exists
            if ($eventId) {
                $existingWebhook = PaypalWebhook::where('event_id', $eventId)->first();
                if ($existingWebhook) {
                    gp247_report('PayPal Webhook - Duplicate webhook received.'.json_encode($webhookData));
                    return true; // Return true to acknowledge receipt
                }
            }
            
            // Extract resource ID and type
            $resourceId = $webhookData['resource']['id'] ?? null;
            $resourceType = $webhookData['resource_type'] ?? null;
            
            // Create webhook record
            $webhook = PaypalWebhook::create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'resource_id' => $resourceId,
                'resource_type' => $resourceType,
                'status' => 'processed',
                'payload' => $webhookData
            ]);
            
            // Dispatch job to process webhook
            dispatch(function() use ($webhook) {
                $this->processWebhookJob($webhook);
            })->afterResponse();
            
            return true;
        } catch (\Exception $e) {
            gp247_report('PayPal Webhook - Error processing webhook: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process a webhook job
     *
     * @param PaypalWebhook $webhook
     * @return void
     */
    public function processWebhookJob(PaypalWebhook $webhook)
    {
        try {
            // Process webhook based on event type
            switch ($webhook->event_type) {
                case 'PAYMENT.CAPTURE.REFUNDED':
                    $this->handlePaymentCaptureRefunded($webhook);
                    break;
                default:
                    $webhook->markAsProcessed();
                    break;
            }
        } catch (\Exception $e) {
            gp247_report('PayPal Webhook - Error processing webhook job: ' . $e->getMessage());
            $webhook->markAsFailed($e->getMessage());
        }
    }
    
    /**
     * Handle PAYMENT.CAPTURE.REFUNDED event
     *
     * @param PaypalWebhook $webhook
     * @return void
     */
    private function handlePaymentCaptureRefunded(PaypalWebhook $webhook)
    {
        try {
            $payload = $webhook->payload;
            
            // Lấy capture_id thay vì resource id
            $captureId = $payload['resource']['capture_id'] ?? '';
            if (empty($captureId)) {
                // Backup: Thử lấy từ links nếu không có capture_id trực tiếp
                foreach ($payload['resource']['links'] ?? [] as $link) {
                    if ($link['rel'] === 'up' && strpos($link['href'], '/captures/') !== false) {
                        $parts = explode('/captures/', $link['href']);
                        $captureId = end($parts);
                        break;
                    }
                }
            }

            if (empty($captureId)) {
                gp247_report('PayPal Webhook - Cannot find capture ID in refund payload: ' . json_encode($payload));
                return;
            }

            $amount = $payload['resource']['amount']['value'] ?? 0;
            $currency = $payload['resource']['amount']['currency_code'] ?? '';
            
            // Tìm order bằng capture ID
            $order = \GP247\Shop\Models\ShopOrder::where('transaction', $captureId)->first();
            
            if ($order) {
                // Update order status
                $order->update([
                    'status' => gp247_config('Paypal_order_status_refunded'),
                    'payment_status' => gp247_config('Paypal_payment_status_refunded')
                ]);
                
                // Add order history
                $dataHistory = [
                    'order_id' => $order->id,
                    'content' => 'Payment refunded via PayPal. Amount: ' . $amount . ' ' . $currency,
                    'customer_id' => $order->customer_id ?? 0,
                    'order_status_id' => gp247_config('Paypal_order_status_refunded'),
                ];
                $order->addOrderHistory($dataHistory);

                gp247_report('PayPal Webhook - Order refunded: ' . $order->id);
            } else {
                gp247_report('PayPal Webhook - Order not found for capture ID: ' . $captureId);
            }
            
            $webhook->markAsProcessed();
        } catch (\Exception $e) {
            throw $e;
        }
    }
} 