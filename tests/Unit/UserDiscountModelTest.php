<?php

namespace Hipster\UserDiscounts\Tests\Unit;

use Hipster\UserDiscounts\Tests\TestCase;
use Hipster\UserDiscounts\Models\Discount;
use Hipster\UserDiscounts\Models\UserDiscount;
use Carbon\Carbon;

class UserDiscountModelTest extends TestCase
{
    /** @test */
    public function it_can_determine_if_user_discount_is_active()
    {
        $discount = Discount::create([
            'name' => 'Test Discount',
            'code' => 'TEST',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $userDiscount = UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($userDiscount->isActive());
    }

    /** @test */
    public function it_can_determine_if_user_discount_is_inactive()
    {
        $discount = Discount::create([
            'name' => 'Test Discount',
            'code' => 'TEST',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $userDiscount = UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount->id,
            'is_active' => false,
            'assigned_at' => now(),
        ]);

        $this->assertFalse($userDiscount->isActive());
    }

    /** @test */
    public function it_can_determine_if_user_discount_is_inactive_when_revoked()
    {
        $discount = Discount::create([
            'name' => 'Test Discount',
            'code' => 'TEST',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $userDiscount = UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount->id,
            'is_active' => true,
            'assigned_at' => now(),
            'revoked_at' => now(),
        ]);

        $this->assertFalse($userDiscount->isActive());
    }

    /** @test */
    public function it_can_determine_if_user_has_reached_usage_limit()
    {
        $discount = Discount::create([
            'name' => 'Limited Discount',
            'code' => 'LIMITED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'max_usage_per_user' => 2,
        ]);

        $userDiscount = UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount->id,
            'usage_count' => 2,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($userDiscount->hasReachedUsageLimit());
    }

    /** @test */
    public function it_can_determine_if_user_has_not_reached_usage_limit()
    {
        $discount = Discount::create([
            'name' => 'Limited Discount',
            'code' => 'LIMITED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'max_usage_per_user' => 2,
        ]);

        $userDiscount = UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount->id,
            'usage_count' => 1,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->assertFalse($userDiscount->hasReachedUsageLimit());
    }

    /** @test */
    public function it_can_determine_if_user_discount_is_valid()
    {
        $discount = Discount::create([
            'name' => 'Valid Discount',
            'code' => 'VALID',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $userDiscount = UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($userDiscount->isValid());
    }

    /** @test */
    public function it_can_determine_if_user_discount_is_invalid_when_discount_expired()
    {
        $discount = Discount::create([
            'name' => 'Expired Discount',
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $userDiscount = UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->assertFalse($userDiscount->isValid());
    }

    /** @test */
    public function it_can_revoke_user_discount()
    {
        $discount = Discount::create([
            'name' => 'Test Discount',
            'code' => 'TEST',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $userDiscount = UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $result = $userDiscount->revoke();

        $this->assertTrue($result);
        $this->assertFalse($userDiscount->is_active);
        $this->assertNotNull($userDiscount->revoked_at);
    }

    /** @test */
    public function it_can_increment_usage_count()
    {
        $discount = Discount::create([
            'name' => 'Test Discount',
            'code' => 'TEST',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'max_usage_per_user' => 2,
        ]);

        $userDiscount = UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount->id,
            'usage_count' => 0,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $result = $userDiscount->incrementUsage();

        $this->assertTrue($result);
        $this->assertEquals(1, $userDiscount->usage_count);
    }

    /** @test */
    public function it_cannot_increment_usage_when_limit_reached()
    {
        $discount = Discount::create([
            'name' => 'Test Discount',
            'code' => 'TEST',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'max_usage_per_user' => 1,
        ]);

        $userDiscount = UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount->id,
            'usage_count' => 1,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $result = $userDiscount->incrementUsage();

        $this->assertFalse($result);
        $this->assertEquals(1, $userDiscount->usage_count); // Should remain unchanged
    }

    /** @test */
    public function it_can_scope_active_user_discounts()
    {
        $discount = Discount::create([
            'name' => 'Test Discount',
            'code' => 'TEST',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        UserDiscount::create([
            'user_id' => 2,
            'discount_id' => $discount->id,
            'is_active' => false,
            'assigned_at' => now(),
        ]);

        $activeUserDiscounts = UserDiscount::active()->get();
        $this->assertCount(1, $activeUserDiscounts);
        $this->assertEquals(1, $activeUserDiscounts->first()->user_id);
    }

    /** @test */
    public function it_can_scope_valid_user_discounts()
    {
        $validDiscount = Discount::create([
            'name' => 'Valid Discount',
            'code' => 'VALID',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $expiredDiscount = Discount::create([
            'name' => 'Expired Discount',
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $validDiscount->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $expiredDiscount->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $validUserDiscounts = UserDiscount::valid()->get();
        $this->assertCount(1, $validUserDiscounts);
        $this->assertEquals($validDiscount->id, $validUserDiscounts->first()->discount_id);
    }
}

