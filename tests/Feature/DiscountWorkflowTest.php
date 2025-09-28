<?php

namespace Hipster\UserDiscounts\Tests\Feature;

use Hipster\UserDiscounts\Tests\TestCase;
use Hipster\UserDiscounts\Services\DiscountService;
use Hipster\UserDiscounts\Models\Discount;
use Hipster\UserDiscounts\Models\UserDiscount;
use Hipster\UserDiscounts\Models\DiscountAudit;
use Hipster\UserDiscounts\Events\DiscountAssigned;
use Hipster\UserDiscounts\Events\DiscountRevoked;
use Hipster\UserDiscounts\Events\DiscountApplied;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;

class DiscountWorkflowTest extends TestCase
{
    protected DiscountService $discountService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discountService = new DiscountService();
        
        // Disable cache for testing
        config(['user-discounts.cache.enabled' => false]);
    }

    /** @test */
    public function it_can_complete_the_full_discount_workflow()
    {
        Event::fake();

        // Step 1: Create a discount
        $discount = Discount::create([
            'name' => 'Welcome Discount',
            'code' => 'WELCOME10',
            'description' => '10% off for new users',
            'type' => 'percentage',
            'value' => 10,
            'max_amount' => 50,
            'max_usage_per_user' => 1,
            'is_active' => true,
            'stacking_order' => 1,
        ]);

        // Step 2: Assign discount to user
        $userDiscount = $this->discountService->assign(1, $discount->id);

        $this->assertInstanceOf(UserDiscount::class, $userDiscount);
        $this->assertEquals(1, $userDiscount->user_id);
        $this->assertEquals($discount->id, $userDiscount->discount_id);
        $this->assertTrue($userDiscount->is_active);

        // Verify event was fired
        Event::assertDispatched(DiscountAssigned::class);

        // Step 3: Check eligible discounts
        $eligibleDiscounts = $this->discountService->eligibleFor(1);
        $this->assertCount(1, $eligibleDiscounts);
        $this->assertEquals($discount->id, $eligibleDiscounts->first()->discount_id);

        // Step 4: Apply discount
        $result = $this->discountService->apply(1, 100.00, 'ORDER_123');

        $this->assertEquals(100.00, $result['original_amount']);
        $this->assertEquals(10.00, $result['discount_amount']); // 10% of 100
        $this->assertEquals(90.00, $result['final_amount']);
        $this->assertCount(1, $result['applied_discounts']);
        $this->assertEquals('ORDER_123', $result['transaction_id']);

        // Verify event was fired
        Event::assertDispatched(DiscountApplied::class);

        // Step 5: Verify usage count was incremented
        $userDiscount->refresh();
        $this->assertEquals(1, $userDiscount->usage_count);

        // Step 6: Verify audit records
        $audits = DiscountAudit::where('user_discount_id', $userDiscount->id)->get();
        $this->assertCount(2, $audits); // assigned + applied

        $assignedAudit = $audits->where('action', 'assigned')->first();
        $this->assertNotNull($assignedAudit);

        $appliedAudit = $audits->where('action', 'applied')->first();
        $this->assertNotNull($appliedAudit);
        $this->assertEquals(100.00, $appliedAudit->original_amount);
        $this->assertEquals(10.00, $appliedAudit->discount_amount);
        $this->assertEquals(90.00, $appliedAudit->final_amount);
        $this->assertEquals('ORDER_123', $appliedAudit->transaction_id);

        // Step 7: Try to apply discount again (should fail due to usage limit)
        $result2 = $this->discountService->apply(1, 100.00, 'ORDER_124');
        $this->assertEquals(0.00, $result2['discount_amount']);
        $this->assertCount(0, $result2['applied_discounts']);

        // Step 8: Revoke discount
        $revokeResult = $this->discountService->revoke(1, $discount->id, 'User requested removal');

        $this->assertTrue($revokeResult);

        // Verify event was fired
        Event::assertDispatched(DiscountRevoked::class);

        // Step 9: Check database state directly
        $userDiscountFromDb = UserDiscount::where('user_id', 1)
            ->where('discount_id', $discount->id)
            ->first();
        
        $this->assertNotNull($userDiscountFromDb);
        $this->assertFalse($userDiscountFromDb->is_active);
        $this->assertNotNull($userDiscountFromDb->revoked_at);

        // Step 10: Verify discount is no longer eligible
        $eligibleDiscountsAfterRevoke = $this->discountService->eligibleFor(1);
        $this->assertCount(0, $eligibleDiscountsAfterRevoke);

        // Step 10: Verify revocation audit record
        $revokeAudit = DiscountAudit::where('action', 'revoked')->first();
        $this->assertNotNull($revokeAudit);
        $this->assertEquals('User requested removal', $revokeAudit->metadata['reason']);
    }

    /** @test */
    public function it_can_handle_multiple_discounts_with_stacking()
    {
        Event::fake();

        // Create multiple discounts with different stacking orders
        $percentageDiscount = Discount::create([
            'name' => 'Percentage Discount',
            'code' => 'PERCENT20',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
            'stacking_order' => 1,
        ]);

        $fixedDiscount = Discount::create([
            'name' => 'Fixed Discount',
            'code' => 'FIXED10',
            'type' => 'fixed',
            'value' => 10,
            'is_active' => true,
            'stacking_order' => 2,
        ]);

        // Assign both discounts
        $this->discountService->assign(1, $percentageDiscount->id);
        $this->discountService->assign(1, $fixedDiscount->id);

        // Apply discounts
        $result = $this->discountService->apply(1, 100.00, 'STACKING_TEST');

        // Expected: 100 - 20% = 80, then 80 - 10 = 70
        $this->assertEquals(100.00, $result['original_amount']);
        $this->assertEquals(30.00, $result['discount_amount']); // 20 + 10
        $this->assertEquals(70.00, $result['final_amount']);
        $this->assertCount(2, $result['applied_discounts']);

        // Verify both events were fired
        Event::assertDispatched(DiscountApplied::class, 2);
    }

    /** @test */
    public function it_respects_max_percentage_cap()
    {
        // Set max percentage cap to 30%
        config(['user-discounts.stacking.max_percentage_cap' => 30]);

        // Create multiple percentage discounts
        $discount1 = Discount::create([
            'name' => 'Discount 1',
            'code' => 'DISC1',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
            'stacking_order' => 1,
        ]);

        $discount2 = Discount::create([
            'name' => 'Discount 2',
            'code' => 'DISC2',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
            'stacking_order' => 2,
        ]);

        $this->discountService->assign(1, $discount1->id);
        $this->discountService->assign(1, $discount2->id);

        $result = $this->discountService->apply(1, 100.00);

        // Should be capped at 30% (30.00), not 40% (40.00)
        $this->assertEquals(30.00, $result['discount_amount']);
        $this->assertEquals(70.00, $result['final_amount']);
    }

    /** @test */
    public function it_handles_expired_discounts_correctly()
    {
        // Create a valid discount first
        $validDiscount = Discount::create([
            'name' => 'Valid Discount',
            'code' => 'VALID',
            'type' => 'percentage',
            'value' => 15,
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
    public function it_handles_concurrent_discount_application()
    {
        $discount = Discount::create([
            'name' => 'Concurrent Test Discount',
            'code' => 'CONCURRENT',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'max_usage_per_user' => 1,
        ]);

        $this->discountService->assign(1, $discount->id);

        // Simulate concurrent applications
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->discountService->apply(1, 100.00, "CONCURRENT_{$i}");
        }

        // Only one should succeed
        $successfulApplications = collect($results)->filter(function ($result) {
            return $result['discount_amount'] > 0;
        });

        $this->assertCount(1, $successfulApplications);
    }

    /** @test */
    public function it_creates_comprehensive_audit_trail()
    {
        $discount = Discount::create([
            'name' => 'Audit Test Discount',
            'code' => 'AUDIT_TEST',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        // Assign discount
        $userDiscount = $this->discountService->assign(1, $discount->id);

        // Apply discount
        $this->discountService->apply(1, 100.00, 'AUDIT_TRANSACTION', ['order_id' => 123]);

        // Revoke discount
        $this->discountService->revoke(1, $discount->id, 'Test revocation');

        // Check all audit records
        $audits = DiscountAudit::where('user_discount_id', $userDiscount->id)->get();
        $this->assertCount(3, $audits);

        // Check assigned audit
        $assignedAudit = $audits->where('action', 'assigned')->first();
        $this->assertNotNull($assignedAudit);
        $this->assertEquals(1, $assignedAudit->user_id);
        $this->assertEquals($discount->id, $assignedAudit->discount_id);

        // Check applied audit
        $appliedAudit = $audits->where('action', 'applied')->first();
        $this->assertNotNull($appliedAudit);
        $this->assertEquals(100.00, $appliedAudit->original_amount);
        $this->assertEquals(10.00, $appliedAudit->discount_amount);
        $this->assertEquals(90.00, $appliedAudit->final_amount);
        $this->assertEquals('AUDIT_TRANSACTION', $appliedAudit->transaction_id);
        $this->assertEquals(['order_id' => 123], $appliedAudit->metadata);

        // Check revoked audit
        $revokedAudit = $audits->where('action', 'revoked')->first();
        $this->assertNotNull($revokedAudit);
        $this->assertEquals('Test revocation', $revokedAudit->metadata['reason']);
    }

    /** @test */
    public function it_handles_edge_cases_correctly()
    {
        // Test with zero amount
        $discount = Discount::create([
            'name' => 'Test Discount',
            'code' => 'TEST',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $this->discountService->assign(1, $discount->id);

        $result = $this->discountService->apply(1, 0.00);
        $this->assertEquals(0.00, $result['original_amount']);
        $this->assertEquals(0.00, $result['discount_amount']);
        $this->assertEquals(0.00, $result['final_amount']);

        // Test with very small amount
        $result = $this->discountService->apply(1, 0.01);
        $this->assertEquals(0.01, $result['original_amount']);
        $this->assertEquals(0.00, $result['discount_amount']); // Should be rounded to 0
        $this->assertEquals(0.01, $result['final_amount']);
    }

    /** @test */
    public function it_provides_user_discount_statistics()
    {
        // Create multiple discounts
        $discount1 = Discount::create([
            'name' => 'Discount 1',
            'code' => 'DISC1',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $discount2 = Discount::create([
            'name' => 'Discount 2',
            'code' => 'DISC2',
            'type' => 'fixed',
            'value' => 5,
            'is_active' => true,
        ]);

        // Assign valid discounts
        $this->discountService->assign(1, $discount1->id);
        $this->discountService->assign(1, $discount2->id);

        // Create expired discount but don't assign it (since it would fail)
        $expiredDiscount = Discount::create([
            'name' => 'Expired Discount',
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        // Apply some discounts
        $this->discountService->apply(1, 100.00);
        $this->discountService->apply(1, 50.00);

        // Get statistics
        $stats = $this->discountService->getUserDiscountStats(1);

        $this->assertEquals(2, $stats['total_discounts']);
        $this->assertEquals(2, $stats['active_discounts']);
        $this->assertEquals(2, $stats['valid_discounts']); // Both are valid
        $this->assertEquals(2, $stats['total_usage']); // Two applications
    }
}

