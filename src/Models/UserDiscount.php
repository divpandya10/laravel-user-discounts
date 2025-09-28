<?php

namespace Hipster\UserDiscounts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'discount_id',
        'usage_count',
        'assigned_at',
        'revoked_at',
        'is_active',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the discount.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('user-discounts.user_model', 'App\Models\User'), 'user_id');
    }

    /**
     * Get the discount that belongs to the user.
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    /**
     * Get the audit records for this user discount.
     */
    public function audits(): HasMany
    {
        return $this->hasMany(DiscountAudit::class);
    }

    /**
     * Check if the user discount is currently active.
     */
    public function isActive(): bool
    {
        return $this->is_active && is_null($this->revoked_at);
    }

    /**
     * Check if the user has reached their usage limit for this discount.
     */
    public function hasReachedUsageLimit(): bool
    {
        return $this->discount && $this->usage_count >= $this->discount->max_usage_per_user;
    }

    /**
     * Check if the user discount is still valid (active and not expired).
     */
    public function isValid(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return $this->discount && $this->discount->isValid();
    }

    /**
     * Revoke the user discount.
     */
    public function revoke(): bool
    {
        $this->is_active = false;
        $this->revoked_at = now();
        
        $result = $this->save();
        
        // Force refresh from database
        $this->refresh();
        
        return $result;
    }

    /**
     * Increment the usage count for this user discount.
     */
    public function incrementUsage(): bool
    {
        if ($this->hasReachedUsageLimit()) {
            return false;
        }

        $this->usage_count++;
        return $this->save();
    }

    /**
     * Scope to get only active user discounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereNull('revoked_at');
    }

    /**
     * Scope to get only valid user discounts (active and not expired).
     */
    public function scopeValid($query)
    {
        return $query->active()
            ->whereHas('discount', function ($q) {
                $q->valid();
            });
    }
}

