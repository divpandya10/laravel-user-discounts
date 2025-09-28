# Laravel User Discounts Package

A comprehensive Laravel package for managing user-level discounts with deterministic stacking, comprehensive auditing, and concurrency safety.

## Features

- **User-level discount assignments** with usage tracking
- **Deterministic stacking** with configurable order
- **Comprehensive auditing** of all discount operations
- **Concurrency safety** for concurrent discount applications
- **Usage limits** per user and total usage caps
- **Event-driven architecture** with Laravel events
- **Configurable rounding** and percentage caps
- **Full test coverage** with unit and feature tests

## Installation

```bash
composer require hipster/user-discounts
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=user-discounts-config
```

Publish the migrations:

```bash
php artisan vendor:publish --tag=user-discounts-migrations
```

Run the migrations:

```bash
php artisan migrate
```

## Usage

### Basic Usage

```php
use Hipster\UserDiscounts\Services\DiscountService;

$discountService = app(DiscountService::class);

// Assign a discount to a user
$userDiscount = $discountService->assign($userId, $discountId);

// Get eligible discounts for a user
$eligibleDiscounts = $discountService->eligibleFor($userId);

// Apply discounts to an amount
$result = $discountService->apply($userId, $originalAmount, $transactionId);

// Revoke a discount from a user
$discountService->revoke($userId, $discountId, 'Reason for revocation');
```

### Creating Discounts

```php
use Hipster\UserDiscounts\Models\Discount;

$discount = Discount::create([
    'name' => 'Welcome Discount',
    'code' => 'WELCOME10',
    'type' => 'percentage', // or 'fixed'
    'value' => 10, // 10% or $10
    'max_amount' => 50, // Maximum discount amount (for percentage)
    'max_usage_per_user' => 1,
    'max_total_usage' => 100,
    'is_active' => true,
    'stacking_order' => 1,
    'starts_at' => now(),
    'expires_at' => now()->addDays(30),
]);
```

### Applying Discounts

```php
$result = $discountService->apply($userId, 100.00, 'ORDER_123');

// Result structure:
// [
//     'original_amount' => 100.00,
//     'discount_amount' => 15.00,
//     'final_amount' => 85.00,
//     'applied_discounts' => [
//         [
//             'user_discount_id' => 1,
//             'discount_id' => 1,
//             'discount_code' => 'WELCOME10',
//             'discount_amount' => 10.00,
//         ],
//         // ... more discounts
//     ],
//     'transaction_id' => 'ORDER_123',
// ]
```

## Configuration Options

The package provides extensive configuration options in `config/user-discounts.php`:

### Stacking Configuration

```php
'stacking' => [
    'order' => [
        'percentage' => 1,
        'fixed' => 2,
    ],
    'max_percentage_cap' => 100,
    'allow_negative_final_amount' => false,
],
```

### Rounding Configuration

```php
'rounding' => [
    'mode' => 'round', // 'round', 'floor', 'ceil'
    'decimal_places' => 2,
],
```

### Concurrency Configuration

```php
'concurrency' => [
    'lock_timeout' => 30,
    'retry_attempts' => 3,
],
```

## Events

The package fires several events that you can listen to:

```php
use Hipster\UserDiscounts\Events\DiscountAssigned;
use Hipster\UserDiscounts\Events\DiscountRevoked;
use Hipster\UserDiscounts\Events\DiscountApplied;

Event::listen(DiscountAssigned::class, function (DiscountAssigned $event) {
    // Handle discount assignment
});

Event::listen(DiscountApplied::class, function (DiscountApplied $event) {
    // Handle discount application
    Log::info("Discount applied: {$event->discountAmount} off {$event->originalAmount}");
});
```

## Models

### Discount Model

```php
use Hipster\UserDiscounts\Models\Discount;

$discount = Discount::create([...]);

// Check if discount is valid
if ($discount->isValid()) {
    // Discount is active and not expired
}

// Calculate discount amount
$discountAmount = $discount->calculateDiscountAmount(100.00);
```

### UserDiscount Model

```php
use Hipster\UserDiscounts\Models\UserDiscount;

$userDiscount = UserDiscount::where('user_id', $userId)
    ->where('discount_id', $discountId)
    ->first();

// Check if user discount is active
if ($userDiscount->isActive()) {
    // User discount is active
}

// Check usage limit
if ($userDiscount->hasReachedUsageLimit()) {
    // User has reached their usage limit
}
```

## Auditing

All discount operations are automatically audited:

```php
use Hipster\UserDiscounts\Models\DiscountAudit;

// Get all audit records for a user
$audits = DiscountAudit::where('user_id', $userId)->get();

// Get audit records by action
$appliedAudits = DiscountAudit::where('action', 'applied')->get();

// Get audit records for a transaction
$transactionAudits = DiscountAudit::where('transaction_id', 'ORDER_123')->get();
```

## Testing

Run the test suite:

```bash
composer test
```

In GitHub, every push runs the tests via Actions. You can download the latest test output from the Actions run page (Artifacts: phpunit-results-php-8.2 / 8.3) or screenshot the green badge above as evidence.

The package includes comprehensive unit and feature tests covering:

- Discount assignment and revocation
- Discount application with stacking
- Usage limit enforcement
- Concurrency safety
- Audit trail creation
- Event firing
- Edge cases and error handling

## Requirements

- PHP 8.1+
- Laravel 10.0+ (11.0+, 12.0+)
- MySQL/PostgreSQL/SQLite

## License

MIT License. See LICENSE file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## Support

For support and questions, please open an issue on GitHub.
