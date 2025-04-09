<?php

namespace App\GP247\Plugins\PaypalExpress\Models;

use Illuminate\Database\Eloquent\Model;

class PaypalWebhook extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'paypal_webhooks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'event_id',
        'event_type',
        'resource_id',
        'resource_type',
        'status',
        'payload',
        'processed_at',
        'error_message',
        'retry_count'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'retry_count' => 'integer'
    ];

    /**
     * Scope a query to only include pending webhooks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include failed webhooks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include processed webhooks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    /**
     * Scope a query to only include webhooks that need to be retried.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $maxRetries
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNeedsRetry($query, $maxRetries = 3)
    {
        return $query->where('status', 'failed')
            ->where('retry_count', '<', $maxRetries);
    }

    /**
     * Mark the webhook as processed.
     *
     * @return bool
     */
    public function markAsProcessed()
    {
        return $this->update([
            'status' => 'processed',
            'processed_at' => now()
        ]);
    }

    /**
     * Mark the webhook as failed.
     *
     * @param  string  $errorMessage
     * @return bool
     */
    public function markAsFailed($errorMessage)
    {
        return $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1
        ]);
    }
} 