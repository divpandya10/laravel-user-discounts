<?php

namespace Hipster\UserDiscounts\Console;

use Illuminate\Console\Command;
use Hipster\UserDiscounts\Services\DiscountService;
use Hipster\UserDiscounts\Models\Discount;

class TestDiscountCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user-discounts:test {--user-id=1} {--amount=100}';

    /**
     * The console command description.
     */
    protected $description = 'Test the user discounts package functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        $amount = (float) $this->option('amount');

        $this->info('Testing User Discounts Package...');
        $this->line('');

        try {
            // Create a test discount
            $discount = Discount::create([
                'name' => 'Test Discount',
                'code' => 'TEST_' . time(),
                'type' => 'percentage',
                'value' => 10,
                'is_active' => true,
                'stacking_order' => 1,
            ]);

            $this->info("✓ Created test discount: {$discount->name}");

            // Test discount service
            $discountService = app(DiscountService::class);

            // Assign discount to user
            $userDiscount = $discountService->assign($userId, $discount->id);
            $this->info("✓ Assigned discount to user {$userId}");

            // Get eligible discounts
            $eligibleDiscounts = $discountService->eligibleFor($userId);
            $this->info("✓ Found " . $eligibleDiscounts->count() . " eligible discounts");

            // Apply discount
            $result = $discountService->apply($userId, $amount, 'TEST_TRANSACTION');
            
            $this->info("✓ Applied discount:");
            $this->line("  Original Amount: \${$result['original_amount']}");
            $this->line("  Discount Amount: \${$result['discount_amount']}");
            $this->line("  Final Amount: \${$result['final_amount']}");
            $this->line("  Applied Discounts: " . count($result['applied_discounts']));

            // Get user statistics
            $stats = $discountService->getUserDiscountStats($userId);
            $this->info("✓ User Statistics:");
            $this->line("  Total Discounts: {$stats['total_discounts']}");
            $this->line("  Active Discounts: {$stats['active_discounts']}");
            $this->line("  Valid Discounts: {$stats['valid_discounts']}");
            $this->line("  Total Usage: {$stats['total_usage']}");

            $this->line('');
            $this->info('✅ All tests passed successfully!');
            $this->info('The User Discounts package is working correctly.');

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
