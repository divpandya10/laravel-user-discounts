<?php

namespace Hipster\UserDiscounts\Tests\Unit;

use Hipster\UserDiscounts\Tests\TestCase;
use Hipster\UserDiscounts\Models\Discount;
use Hipster\UserDiscounts\Models\UserDiscount;
use Carbon\Carbon;

class DiscountModelTest extends TestCase
{
    /** @test */
    public function it_can_calculate_percentage_discount_amount()
    {
        $discount = new Discount([
            'type' => 'percentage',
            'value' => 20,
        ]);

        $amount = $discount->calculateDiscountAmount(100.00);
        $this->assertEquals(20.00, $amount);
    }

    /** @test */
    public function it_can_calculate_fixed_discount_amount()
    {
        $discount = new Discount([
            'type' => 'fixed',
            'value' => 15,
        ]);

        $amount = $discount->calculateDiscountAmount(100.00);
        $this->assertEquals(15.00, $amount);
    }

    /** @test */
    public function it_respects_max_amount_for_percentage_discounts()
    {
        $discount = new Discount([
            'type' => 'percentage',
            'value' => 50, // 50%
            'max_amount' => 10, // But max $10
        ]);

        $amount = $discount->calculateDiscountAmount(100.00);
        $this->assertEquals(10.00, $amount); // Should be capped at $10
    }

    /** @test */
    public function it_does_not_exceed_original_amount_for_fixed_discounts()
    {
        $discount = new Discount([
            'type' => 'fixed',
            'value' => 150, // $150 fixed
        ]);

        $amount = $discount->calculateDiscountAmount(100.00);
        $this->assertEquals(100.00, $amount); // Should not exceed original amount
    }

    /** @test */
    public function it_can_determine_if_discount_is_valid()
    {
        $discount = Discount::create([
            'name' => 'Valid Discount',
            'code' => 'VALID',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $this->assertTrue($discount->isValid());
    }

    /** @test */
    public function it_can_determine_if_discount_is_invalid_when_inactive()
    {
        $discount = Discount::create([
            'name' => 'Inactive Discount',
            'code' => 'INACTIVE',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => false,
        ]);

        $this->assertFalse($discount->isValid());
    }

    /** @test */
    public function it_can_determine_if_discount_is_invalid_when_expired()
    {
        $discount = Discount::create([
            'name' => 'Expired Discount',
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $this->assertFalse($discount->isValid());
    }

    /** @test */
    public function it_can_determine_if_discount_is_invalid_when_not_started()
    {
        $discount = Discount::create([
            'name' => 'Future Discount',
            'code' => 'FUTURE',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'starts_at' => Carbon::now()->addDay(),
        ]);

        $this->assertFalse($discount->isValid());
    }

    /** @test */
    public function it_can_determine_if_discount_has_reached_total_limit()
    {
        $discount = Discount::create([
            'name' => 'Limited Discount',
            'code' => 'LIMITED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'max_total_usage' => 5,
            'current_usage' => 5,
        ]);

        $this->assertTrue($discount->hasReachedTotalLimit());
    }

    /** @test */
    public function it_can_determine_if_discount_is_expired()
    {
        $discount = Discount::create([
            'name' => 'Expired Discount',
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $this->assertTrue($discount->isExpired());
    }

    /** @test */
    public function it_can_scope_active_discounts()
    {
        Discount::create([
            'name' => 'Active Discount',
            'code' => 'ACTIVE',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        Discount::create([
            'name' => 'Inactive Discount',
            'code' => 'INACTIVE',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => false,
        ]);

        $activeDiscounts = Discount::active()->get();
        $this->assertCount(1, $activeDiscounts);
        $this->assertEquals('ACTIVE', $activeDiscounts->first()->code);
    }

    /** @test */
    public function it_can_scope_valid_discounts()
    {
        Discount::create([
            'name' => 'Valid Discount',
            'code' => 'VALID',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        Discount::create([
            'name' => 'Expired Discount',
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $validDiscounts = Discount::valid()->get();
        $this->assertCount(1, $validDiscounts);
        $this->assertEquals('VALID', $validDiscounts->first()->code);
    }

    /** @test */
    public function it_can_scope_discounts_by_stacking_order()
    {
        Discount::create([
            'name' => 'Second Discount',
            'code' => 'SECOND',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'stacking_order' => 2,
        ]);

        Discount::create([
            'name' => 'First Discount',
            'code' => 'FIRST',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'stacking_order' => 1,
        ]);

        $orderedDiscounts = Discount::orderedByStacking()->get();
        $this->assertEquals('FIRST', $orderedDiscounts->first()->code);
        $this->assertEquals('SECOND', $orderedDiscounts->last()->code);
    }
}

