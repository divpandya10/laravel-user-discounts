<?php

namespace Hipster\UserDiscounts\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Hipster\UserDiscounts\Models\Discount;
use Hipster\UserDiscounts\Models\UserDiscount;
use Hipster\UserDiscounts\Models\DiscountAudit;
use Hipster\UserDiscounts\Events\DiscountAssigned;
use Hipster\UserDiscounts\Events\DiscountRevoked;
use Hipster\UserDiscounts\Events\DiscountApplied;
use Hipster\UserDiscounts\Exceptions\DiscountNotFoundException;
use Hipster\UserDiscounts\Exceptions\DiscountNotEligibleException;
use Hipster\UserDiscounts\Exceptions\DiscountUsageLimitExceededException;
use Hipster\UserDiscounts\Exceptions\ConcurrentDiscountApplicationException;

class DiscountService
{
    /**
     * Assign a discount to a user.
     */
    public function assign(int $userId, int $discountId): UserDiscount
    {
        $discount = Discount::findOrFail($discountId);

        if (!$discount->isValid()) {
            throw new DiscountNotEligibleException("Discount is not valid or has expired.");
        }

        // Check if user already has this discount
        $existingUserDiscount = UserDiscount::where('user_id', $userId)
            ->where('discount_id', $discountId)
            ->first();

        if ($existingUserDiscount && $existingUserDiscount->isActive()) {
            return $existingUserDiscount;
        }

        // Create or reactivate user discount
        if ($existingUserDiscount) {
            $existingUserDiscount->is_active = true;
            $existingUserDiscount->revoked_at = null;
            $existingUserDiscount->save();
            $userDiscount = $existingUserDiscount;
        } else {
            $userDiscount = UserDiscount::create([
                'user_id' => $userId,
                'discount_id' => $discountId,
                'assigned_at' => now(),
                'is_active' => true,
            ]);
        }

        // Create audit record
        $this->createAuditRecord($userDiscount, 'assigned');

        // Fire event
        Event::dispatch(new DiscountAssigned($userDiscount));

        return $userDiscount;
    }

    /**
     * Revoke a discount from a user.
     */
    public function revoke(int $userId, int $discountId, ?string $reason = null): bool
    {
        $userDiscount = UserDiscount::where('user_id', $userId)
            ->where('discount_id', $discountId)
            ->where('is_active', true)
            ->first();

        if (!$userDiscount) {
            return false;
        }

        $result = $userDiscount->revoke();
        
        if (!$result) {
            return false;
        }

        // Create audit record
        $this->createAuditRecord($userDiscount, 'revoked', null, null, null, null, ['reason' => $reason]);

        // Fire event
        Event::dispatch(new DiscountRevoked($userDiscount, $reason));

        return true;
    }

    /**
     * Get eligible discounts for a user.
     */
    public function eligibleFor(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "user_discounts_eligible_{$userId}";
        
        if (config('user-discounts.cache.enabled')) {
            return Cache::remember($cacheKey, config('user-discounts.cache.ttl', 60), function () use ($userId) {
                return $this->getEligibleDiscounts($userId);
            });
        }

        return $this->getEligibleDiscounts($userId);
    }

    /**
     * Apply eligible discounts to an amount.
     */
    public function apply(int $userId, float $originalAmount, ?string $transactionId = null, ?array $metadata = null): array
    {
        $lockKey = "discount_apply_{$userId}";
        $lockTimeout = config('user-discounts.concurrency.lock_timeout', 30);
        $retryAttempts = config('user-discounts.concurrency.retry_attempts', 3);

        for ($attempt = 0; $attempt < $retryAttempts; $attempt++) {
            try {
                return DB::transaction(function () use ($userId, $originalAmount, $transactionId, $metadata) {
                    return $this->applyDiscountsInTransaction($userId, $originalAmount, $transactionId, $metadata);
                });
            } catch (\Exception $e) {
                if ($attempt === $retryAttempts - 1) {
                    throw new ConcurrentDiscountApplicationException("Failed to apply discounts after {$retryAttempts} attempts: " . $e->getMessage());
                }
                
                // Wait before retry
                usleep(100000 * ($attempt + 1)); // 100ms, 200ms, 300ms
            }
        }

        throw new ConcurrentDiscountApplicationException("Failed to apply discounts after {$retryAttempts} attempts.");
    }

    /**
     * Get eligible discounts for a user (internal method).
     */
    private function getEligibleDiscounts(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return UserDiscount::with('discount')
            ->where('user_id', $userId)
            ->valid()
            ->get()
            ->filter(function ($userDiscount) {
                return !$userDiscount->hasReachedUsageLimit();
            });
    }

    /**
     * Apply discounts within a database transaction.
     */
    private function applyDiscountsInTransaction(int $userId, float $originalAmount, ?string $transactionId, ?array $metadata): array
    {
        $eligibleDiscounts = $this->getEligibleDiscounts($userId);
        
        if ($eligibleDiscounts->isEmpty()) {
            return [
                'original_amount' => $originalAmount,
                'discount_amount' => 0,
                'final_amount' => $originalAmount,
                'applied_discounts' => [],
                'transaction_id' => $transactionId,
            ];
        }

        // Sort discounts by stacking order
        $sortedDiscounts = $eligibleDiscounts->sortBy(function ($userDiscount) {
            return $userDiscount->discount->stacking_order;
        });

        $currentAmount = $originalAmount;
        $appliedDiscounts = [];
        $totalDiscountAmount = 0;

        foreach ($sortedDiscounts as $userDiscount) {
            if ($currentAmount <= 0) {
                break;
            }

            $discountAmount = $userDiscount->discount->calculateDiscountAmount($currentAmount);
            
            if ($discountAmount > 0) {
                // Check if this would exceed the maximum percentage cap
                $totalPercentage = (($originalAmount - $currentAmount + $discountAmount) / $originalAmount) * 100;
                $maxPercentageCap = config('user-discounts.stacking.max_percentage_cap', 100);
                
                if ($totalPercentage > $maxPercentageCap) {
                    $maxAllowedDiscount = ($maxPercentageCap / 100) * $originalAmount;
                    $alreadyApplied = $originalAmount - $currentAmount;
                    $discountAmount = max(0, $maxAllowedDiscount - $alreadyApplied);
                }

                if ($discountAmount > 0) {
                    // Apply the discount
                    $currentAmount -= $discountAmount;
                    $totalDiscountAmount += $discountAmount;

                    // Increment usage count
                    $userDiscount->incrementUsage();

                    // Create audit record
                    $this->createAuditRecord(
                        $userDiscount,
                        'applied',
                        $originalAmount,
                        $discountAmount,
                        $currentAmount,
                        $transactionId,
                        $metadata
                    );

                    // Fire event
                    Event::dispatch(new DiscountApplied(
                        $userDiscount,
                        $originalAmount,
                        $discountAmount,
                        $currentAmount,
                        $transactionId,
                        $metadata
                    ));

                    $appliedDiscounts[] = [
                        'user_discount_id' => $userDiscount->id,
                        'discount_id' => $userDiscount->discount_id,
                        'discount_code' => $userDiscount->discount->code,
                        'discount_amount' => $discountAmount,
                    ];
                }
            }
        }

        // Ensure final amount is not negative if not allowed
        if (!config('user-discounts.stacking.allow_negative_final_amount', false) && $currentAmount < 0) {
            $currentAmount = 0;
            $totalDiscountAmount = $originalAmount;
        }

        // Round the final amount
        $currentAmount = $this->roundAmount($currentAmount);
        $totalDiscountAmount = $this->roundAmount($totalDiscountAmount);

        return [
            'original_amount' => $originalAmount,
            'discount_amount' => $totalDiscountAmount,
            'final_amount' => $currentAmount,
            'applied_discounts' => $appliedDiscounts,
            'transaction_id' => $transactionId,
        ];
    }

    /**
     * Create an audit record.
     */
    private function createAuditRecord(
        UserDiscount $userDiscount,
        string $action,
        ?float $originalAmount = null,
        ?float $discountAmount = null,
        ?float $finalAmount = null,
        ?string $transactionId = null,
        ?array $metadata = null
    ): DiscountAudit {
        return DiscountAudit::create([
            'user_id' => $userDiscount->user_id,
            'discount_id' => $userDiscount->discount_id,
            'user_discount_id' => $userDiscount->id,
            'action' => $action,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'transaction_id' => $transactionId,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Round an amount according to configuration.
     */
    private function roundAmount(float $amount): float
    {
        $mode = config('user-discounts.rounding.mode', 'round');
        $decimalPlaces = config('user-discounts.rounding.decimal_places', 2);

        switch ($mode) {
            case 'floor':
                return floor($amount * pow(10, $decimalPlaces)) / pow(10, $decimalPlaces);
            case 'ceil':
                return ceil($amount * pow(10, $decimalPlaces)) / pow(10, $decimalPlaces);
            case 'round':
            default:
                return round($amount, $decimalPlaces);
        }
    }

    /**
     * Clear cache for a user's eligible discounts.
     */
    public function clearUserCache(int $userId): void
    {
        if (config('user-discounts.cache.enabled')) {
            Cache::forget("user_discounts_eligible_{$userId}");
        }
    }

    /**
     * Get discount statistics for a user.
     */
    public function getUserDiscountStats(int $userId): array
    {
        $userDiscounts = UserDiscount::with('discount')
            ->where('user_id', $userId)
            ->get();

        $activeCount = $userDiscounts->where('is_active', true)->count();
        $totalUsage = $userDiscounts->sum('usage_count');
        $validCount = $userDiscounts->filter(function ($userDiscount) {
            return $userDiscount->isValid();
        })->count();

        return [
            'total_discounts' => $userDiscounts->count(),
            'active_discounts' => $activeCount,
            'valid_discounts' => $validCount,
            'total_usage' => $totalUsage,
        ];
    }
}

