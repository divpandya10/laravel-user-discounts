<?php

/**
 * Example usage of the Laravel User Discounts Package
 * 
 * This file demonstrates how to use the package in a Laravel application.
 */

use Hipster\UserDiscounts\Services\DiscountService;
use Hipster\UserDiscounts\Models\Discount;
use Hipster\UserDiscounts\Events\DiscountApplied;

class UserDiscountsExample
{
    protected DiscountService $discountService;

    public function __construct()
    {
        $this->discountService = app(DiscountService::class);
    }

    /**
     * Example: Create and assign a discount to a user
     */
    public function createAndAssignDiscount()
    {
        // Create a discount
        $discount = Discount::create([
            'name' => 'Welcome Discount',
            'code' => 'WELCOME10',
            'description' => '10% off for new users',
            'type' => 'percentage',
            'value' => 10,
            'max_amount' => 50, // Maximum $50 discount
            'max_usage_per_user' => 1,
            'max_total_usage' => 100,
            'is_active' => true,
            'stacking_order' => 1,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        // Assign to user
        $userDiscount = $this->discountService->assign(1, $discount->id);
        
        return $userDiscount;
    }

    /**
     * Example: Apply discounts to an order
     */
    public function applyDiscountsToOrder($userId, $orderAmount, $orderId)
    {
        // Get eligible discounts for user
        $eligibleDiscounts = $this->discountService->eligibleFor($userId);
        
        if ($eligibleDiscounts->isEmpty()) {
            return [
                'original_amount' => $orderAmount,
                'discount_amount' => 0,
                'final_amount' => $orderAmount,
                'applied_discounts' => [],
            ];
        }

        // Apply discounts
        $result = $this->discountService->apply($userId, $orderAmount, $orderId);

        // Log the discount application
        \Log::info("Discount applied to order {$orderId}", [
            'user_id' => $userId,
            'original_amount' => $result['original_amount'],
            'discount_amount' => $result['discount_amount'],
            'final_amount' => $result['final_amount'],
            'applied_discounts' => $result['applied_discounts'],
        ]);

        return $result;
    }

    /**
     * Example: Handle discount events
     */
    public function setupEventListeners()
    {
        // Listen for discount application events
        \Event::listen(DiscountApplied::class, function (DiscountApplied $event) {
            // Send notification to user
            \Mail::to($event->userDiscount->user->email)->send(
                new \App\Mail\DiscountAppliedMail($event)
            );

            // Update analytics
            \Analytics::track('discount_applied', [
                'user_id' => $event->userId,
                'discount_id' => $event->discountId,
                'discount_amount' => $event->discountAmount,
                'original_amount' => $event->originalAmount,
            ]);
        });
    }

    /**
     * Example: Get user discount statistics
     */
    public function getUserDiscountSummary($userId)
    {
        $stats = $this->discountService->getUserDiscountStats($userId);
        
        return [
            'total_discounts' => $stats['total_discounts'],
            'active_discounts' => $stats['active_discounts'],
            'valid_discounts' => $stats['valid_discounts'],
            'total_usage' => $stats['total_usage'],
        ];
    }

    /**
     * Example: Create a complex discount scenario
     */
    public function createComplexDiscountScenario()
    {
        // Create multiple discounts with different stacking orders
        $discounts = [
            [
                'name' => 'Loyalty Discount',
                'code' => 'LOYALTY15',
                'type' => 'percentage',
                'value' => 15,
                'stacking_order' => 1,
                'max_usage_per_user' => 1,
            ],
            [
                'name' => 'Free Shipping',
                'code' => 'FREESHIP',
                'type' => 'fixed',
                'value' => 10,
                'stacking_order' => 2,
                'max_usage_per_user' => 1,
            ],
            [
                'name' => 'First Time Buyer',
                'code' => 'FIRSTTIME',
                'type' => 'percentage',
                'value' => 5,
                'stacking_order' => 3,
                'max_usage_per_user' => 1,
            ],
        ];

        $createdDiscounts = [];
        foreach ($discounts as $discountData) {
            $discount = Discount::create(array_merge($discountData, [
                'is_active' => true,
                'starts_at' => now(),
                'expires_at' => now()->addDays(30),
            ]));
            $createdDiscounts[] = $discount;
        }

        return $createdDiscounts;
    }

    /**
     * Example: Apply complex discount scenario
     */
    public function applyComplexDiscounts($userId, $orderAmount)
    {
        // Assign all discounts to user
        $discounts = $this->createComplexDiscountScenario();
        
        foreach ($discounts as $discount) {
            $this->discountService->assign($userId, $discount->id);
        }

        // Apply all discounts
        $result = $this->discountService->apply($userId, $orderAmount, 'COMPLEX_ORDER');

        return $result;
    }

    /**
     * Example: Handle discount expiration
     */
    public function handleExpiredDiscounts()
    {
        // Find expired discounts
        $expiredDiscounts = Discount::where('expires_at', '<', now())
            ->where('is_active', true)
            ->get();

        foreach ($expiredDiscounts as $discount) {
            // Deactivate expired discounts
            $discount->update(['is_active' => false]);
            
            // Log expiration
            \Log::info("Discount expired: {$discount->name}", [
                'discount_id' => $discount->id,
                'expired_at' => $discount->expires_at,
            ]);
        }

        return $expiredDiscounts->count();
    }
}
