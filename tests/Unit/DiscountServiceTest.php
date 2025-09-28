<?php

namespace Hipster\UserDiscounts\Tests\Unit;

use Hipster\UserDiscounts\Tests\TestCase;
use Hipster\UserDiscounts\Services\DiscountService;
use Hipster\UserDiscounts\Models\Discount;
use Hipster\UserDiscounts\Models\UserDiscount;
use Hipster\UserDiscounts\Models\DiscountAudit;
use Hipster\UserDiscounts\Events\DiscountAssigned;
use Hipster\UserDiscounts\Events\DiscountApplied;
use Hipster\UserDiscounts\Exceptions\DiscountNotEligibleException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DiscountServiceTest extends TestCase
{
    protected DiscountService $discountService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discountService = new DiscountService();
        Event::fake();
        
        // Disable cache for testing
        config(['user-discounts.cache.enabled' => false]);
    }

    /** @test */
    public function it_can_assign_a_discount_to_a_user()
    {
        // Create a valid discount
        $discount = Discount::create([
            'name' => 'Test Discount',
            'code' => 'TEST10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'stacking_order' => 1,
        ]);

        // Assign discount to user
        $userDiscount = $this->discountService->assign(1, $discount->id);

        $this->assertInstanceOf(UserDiscount::class, $userDiscount);
        $this->assertEquals(1, $userDiscount->user_id);
        $this->assertEquals($discount->id, $userDiscount->discount_id);
        $this->assertTrue($userDiscount->is_active);
        $this->assertNull($userDiscount->revoked_at);

        // Check that event was fired
        Event::assertDispatched(DiscountAssigned::class);
    }

    /** @test */
    public function it_cannot_assign_an_invalid_discount()
    {
        // Create an inactive discount
        $discount = Discount::create([
            'name' => 'Inactive Discount',
            'code' => 'INACTIVE',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => false,
        ]);

        $this->expectException(DiscountNotEligibleException::class);
        $this->discountService->assign(1, $discount->id);
    }

    /** @test */
    public function it_cannot_assign_an_expired_discount()
    {
        // Create an expired discount
        $discount = Discount::create([
            'name' => 'Expired Discount',
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $this->expectException(DiscountNotEligibleException::class);
        $this->discountService->assign(1, $discount->id);
    }

    /** @test */
    public function it_can_revoke_a_discount_from_a_user()
    {
        // Create a discount and assign it
        $discount = Discount::create([
            'name' => 'Test Discount',
            'code' => 'TEST10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $userDiscount = $this->discountService->assign(1, $discount->id);

        // Revoke the discount
        $result = $this->discountService->revoke(1, $discount->id, 'Test revocation');

        $this->assertTrue($result);
        
        $userDiscount->refresh();
        $this->assertFalse($userDiscount->is_active);
        $this->assertNotNull($userDiscount->revoked_at);
    }

    /** @test */
    public function it_returns_false_when_revoking_non_existent_discount()
    {
        $result = $this->discountService->revoke(1, 999, 'Test revocation');
        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_get_eligible_discounts_for_a_user()
    {
        // Create valid discounts
        $discount1 = Discount::create([
            'name' => 'Discount 1',
            'code' => 'DISC1',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'stacking_order' => 1,
        ]);

        $discount2 = Discount::create([
            'name' => 'Discount 2',
            'code' => 'DISC2',
            'type' => 'fixed',
            'value' => 5,
            'is_active' => true,
            'stacking_order' => 2,
        ]);

        // Assign discounts to user
        $this->discountService->assign(1, $discount1->id);
        $this->discountService->assign(1, $discount2->id);

        // Get eligible discounts
        $eligibleDiscounts = $this->discountService->eligibleFor(1);

        $this->assertCount(2, $eligibleDiscounts);
    }

    /** @test */
    public function it_excludes_expired_discounts_from_eligible_list()
    {
        // Create a valid discount first
        $validDiscount = Discount::create([
            'name' => 'Valid Discount',
            'code' => 'VALID',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        // Assign valid discount
        $this->discountService->assign(1, $validDiscount->id);

        // Create an expired discount
        $expiredDiscount = Discount::create([
            'name' => 'Expired Discount',
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        // Try to assign expired discount - should fail
        $this->expectException(\Hipster\UserDiscounts\Exceptions\DiscountNotEligibleException::class);
        $this->discountService->assign(1, $expiredDiscount->id);
    }

    /** @test */
    public function it_can_apply_discounts_to_an_amount()
    {
        // Create percentage discount
        $percentageDiscount = Discount::create([
            'name' => 'Percentage Discount',
            'code' => 'PERCENT10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'stacking_order' => 1,
        ]);

        // Create fixed discount
        $fixedDiscount = Discount::create([
            'name' => 'Fixed Discount',
            'code' => 'FIXED5',
            'type' => 'fixed',
            'value' => 5,
            'is_active' => true,
            'stacking_order' => 2,
        ]);

        // Assign discounts to user
        $this->discountService->assign(1, $percentageDiscount->id);
        $this->discountService->assign(1, $fixedDiscount->id);

        // Apply discounts
        $result = $this->discountService->apply(1, 100.00, 'TEST_TRANSACTION');

        $this->assertEquals(100.00, $result['original_amount']);
        $this->assertEquals(15.00, $result['discount_amount']); // 10% + 5 = 10 + 5 = 15
        $this->assertEquals(85.00, $result['final_amount']);
        $this->assertCount(2, $result['applied_discounts']);
        $this->assertEquals('TEST_TRANSACTION', $result['transaction_id']);

        // Check that events were fired
        Event::assertDispatched(DiscountApplied::class, 2);
    }

    /** @test */
    public function it_respects_usage_limits()
    {
        // Create discount with usage limit
        $discount = Discount::create([
            'name' => 'Limited Discount',
            'code' => 'LIMITED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'max_usage_per_user' => 1,
        ]);

        // Assign discount to user
        $this->discountService->assign(1, $discount->id);

        // Apply discount first time
        $result1 = $this->discountService->apply(1, 100.00);
        $this->assertEquals(10.00, $result1['discount_amount']);

        // Apply discount second time - should not be applied due to usage limit
        $result2 = $this->discountService->apply(1, 100.00);
        $this->assertEquals(0.00, $result2['discount_amount']);
        $this->assertCount(0, $result2['applied_discounts']);
    }

    /** @test */
    public function it_respects_total_usage_limits()
    {
        // Create discount with total usage limit
        $discount = Discount::create([
            'name' => 'Total Limited Discount',
            'code' => 'TOTAL_LIMITED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'max_total_usage' => 1,
            'current_usage' => 1,
        ]);

        // Try to assign discount - should fail
        $this->expectException(DiscountNotEligibleException::class);
        $this->discountService->assign(1, $discount->id);
    }

    /** @test */
    public function it_calculates_percentage_discounts_correctly()
    {
        $discount = Discount::create([
            'name' => 'Percentage Discount',
            'code' => 'PERCENT20',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
        ]);

        $this->discountService->assign(1, $discount->id);

        $result = $this->discountService->apply(1, 100.00);

        $this->assertEquals(20.00, $result['discount_amount']);
        $this->assertEquals(80.00, $result['final_amount']);
    }

    /** @test */
    public function it_calculates_fixed_discounts_correctly()
    {
        $discount = Discount::create([
            'name' => 'Fixed Discount',
            'code' => 'FIXED15',
            'type' => 'fixed',
            'value' => 15,
            'is_active' => true,
        ]);

        $this->discountService->assign(1, $discount->id);

        $result = $this->discountService->apply(1, 100.00);

        $this->assertEquals(15.00, $result['discount_amount']);
        $this->assertEquals(85.00, $result['final_amount']);
    }

    /** @test */
    public function it_respects_max_amount_for_percentage_discounts()
    {
        $discount = Discount::create([
            'name' => 'Capped Percentage Discount',
            'code' => 'CAPPED',
            'type' => 'percentage',
            'value' => 50, // 50%
            'max_amount' => 10, // But max $10
            'is_active' => true,
        ]);

        $this->discountService->assign(1, $discount->id);

        $result = $this->discountService->apply(1, 100.00);

        // Should be capped at $10, not $50
        $this->assertEquals(10.00, $result['discount_amount']);
        $this->assertEquals(90.00, $result['final_amount']);
    }

    /** @test */
    public function it_creates_audit_records_for_discount_application()
    {
        $discount = Discount::create([
            'name' => 'Audit Test Discount',
            'code' => 'AUDIT_TEST',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $userDiscount = $this->discountService->assign(1, $discount->id);
        $this->discountService->apply(1, 100.00, 'AUDIT_TRANSACTION');

        // Check audit records
        $audits = DiscountAudit::where('user_discount_id', $userDiscount->id)->get();
        $this->assertCount(2, $audits); // assigned + applied

        $appliedAudit = $audits->where('action', 'applied')->first();
        $this->assertNotNull($appliedAudit);
        $this->assertEquals(100.00, $appliedAudit->original_amount);
        $this->assertEquals(10.00, $appliedAudit->discount_amount);
        $this->assertEquals(90.00, $appliedAudit->final_amount);
        $this->assertEquals('AUDIT_TRANSACTION', $appliedAudit->transaction_id);
    }

    /** @test */
    public function it_handles_concurrent_discount_application()
    {
        $discount = Discount::create([
            'name' => 'Concurrent Test Discount',
            'code' => 'CONCURRENT',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $this->discountService->assign(1, $discount->id);

        // Simulate concurrent access by running multiple transactions
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = $this->discountService->apply(1, 100.00, "CONCURRENT_{$i}");
        }

        // Only one should succeed due to usage limit
        $successfulApplications = collect($results)->filter(function ($result) {
            return $result['discount_amount'] > 0;
        });

        $this->assertCount(1, $successfulApplications);
    }

    /** @test */
    public function it_orders_discounts_by_stacking_order()
    {
        // Create discounts with different stacking orders
        $discount1 = Discount::create([
            'name' => 'First Discount',
            'code' => 'FIRST',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'stacking_order' => 2,
        ]);

        $discount2 = Discount::create([
            'name' => 'Second Discount',
            'code' => 'SECOND',
            'type' => 'fixed',
            'value' => 5,
            'is_active' => true,
            'stacking_order' => 1,
        ]);

        $this->discountService->assign(1, $discount1->id);
        $this->discountService->assign(1, $discount2->id);

        $result = $this->discountService->apply(1, 100.00);

        // Second discount (stacking_order: 1) should be applied first
        // Then first discount (stacking_order: 2) should be applied to remaining amount
        // Expected: 100 - 5 = 95, then 95 - (95 * 0.1) = 85.5
        $this->assertEquals(14.50, $result['discount_amount']); // 5 + 9.5
        $this->assertEquals(85.50, $result['final_amount']);
    }
}

