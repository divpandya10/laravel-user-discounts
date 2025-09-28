<?php

namespace Hipster\UserDiscounts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'value',
        'max_amount',
        'max_usage_per_user',
        'max_total_usage',
        'current_usage',
        'starts_at',
        'expires_at',
        'is_active',
        'stacking_order',
        'conditions',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'current_usage' => 'integer',
        'max_usage_per_user' => 'integer',
        'max_total_usage' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'stacking_order' => 'integer',
        'conditions' => 'array',
    ];

    /**
     * Get the user discounts for this discount.
     */
    public function userDiscounts(): HasMany
    {
        return $this->hasMany(UserDiscount::class);
    }

    /**
     * Get the audit records for this discount.
     */
    public function audits(): HasMany
    {
        return $this->hasMany(DiscountAudit::class);
    }

    /**
     * Check if the discount is currently valid.
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return false;
        }

        if ($this->max_total_usage && $this->current_usage >= $this->max_total_usage) {
            return false;
        }

        return true;
    }

    /**
     * Check if the discount is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::now()->gt($this->expires_at);
    }

    /**
     * Check if the discount has reached its total usage limit.
     */
    public function hasReachedTotalLimit(): bool
    {
        return $this->max_total_usage && $this->current_usage >= $this->max_total_usage;
    }

    /**
     * Calculate the discount amount for a given original amount.
     */
    public function calculateDiscountAmount(float $originalAmount): float
    {
        if ($this->type === 'percentage') {
            $discountAmount = ($originalAmount * $this->value) / 100;
            
            if ($this->max_amount && $discountAmount > $this->max_amount) {
                $discountAmount = $this->max_amount;
            }
            
            return $discountAmount;
        }

        // Fixed amount discount
        return min($this->value, $originalAmount);
    }

    /**
     * Scope to get only active discounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only valid discounts (active and not expired).
     */
    public function scopeValid($query)
    {
        $now = Carbon::now();
        
        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', $now);
            });
    }

    /**
     * Scope to get discounts ordered by stacking order.
     */
    public function scopeOrderedByStacking($query)
    {
        return $query->orderBy('stacking_order', 'asc');
    }
}

