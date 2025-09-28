<?php

namespace Hipster\UserDiscounts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'discount_id',
        'user_discount_id',
        'action',
        'original_amount',
        'discount_amount',
        'final_amount',
        'transaction_id',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the user that the audit record belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('user-discounts.user_model', 'App\Models\User'), 'user_id');
    }

    /**
     * Get the discount that the audit record belongs to.
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    /**
     * Get the user discount that the audit record belongs to.
     */
    public function userDiscount(): BelongsTo
    {
        return $this->belongsTo(UserDiscount::class);
    }

    /**
     * Scope to get audit records by action.
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to get audit records for a specific transaction.
     */
    public function scopeForTransaction($query, string $transactionId)
    {
        return $query->where('transaction_id', $transactionId);
    }

    /**
     * Scope to get audit records within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }
}

