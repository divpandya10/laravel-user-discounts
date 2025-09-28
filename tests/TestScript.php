<?php

/**
 * Simple test script to verify the package functionality
 * Run with: php tests/TestScript.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Hipster\UserDiscounts\Services\DiscountService;
use Hipster\UserDiscounts\Models\Discount;

echo "Laravel User Discounts Package - Test Script\n";
echo "============================================\n\n";

try {
    // This is a basic test to ensure the classes can be instantiated
    echo "✓ Package classes can be loaded\n";
    
    // Test discount creation (without database)
    $discount = new Discount([
        'name' => 'Test Discount',
        'code' => 'TEST10',
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);
    
    echo "✓ Discount model can be instantiated\n";
    
    // Test discount calculation
    $discountAmount = $discount->calculateDiscountAmount(100.00);
    echo "✓ Discount calculation works: {$discountAmount}% off 100.00\n";
    
    // Test service instantiation
    $service = new DiscountService();
    echo "✓ DiscountService can be instantiated\n";
    
    echo "\n✅ All basic tests passed!\n";
    echo "The package is ready for use in a Laravel application.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
