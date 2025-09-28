<?php

namespace Hipster\UserDiscounts\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Hipster\UserDiscounts\Models\UserDiscount;

class DiscountApplied
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The user discount instance.
     */
    public UserDiscount $userDiscount;

    /**
     * The user ID.
     */
    public int $userId;

    /**
     * The discount ID.
     */
    public int $discountId;

    /**
     * The original amount before discount.
     */
    public float $originalAmount;

    /**
     * The discount amount applied.
     */
    public float $discountAmount;

    /**
     * The final amount after discount.
     */
    public float $finalAmount;

    /**
     * The transaction ID.
     */
    public ?string $transactionId;

    /**
     * Additional metadata.
     */
    public ?array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(
        UserDiscount $userDiscount,
        float $originalAmount,
        float $discountAmount,
        float $finalAmount,
        ?string $transactionId = null,
        ?array $metadata = null
    ) {
        $this->userDiscount = $userDiscount;
        $this->userId = $userDiscount->user_id;
        $this->discountId = $userDiscount->discount_id;
        $this->originalAmount = $originalAmount;
        $this->discountAmount = $discountAmount;
        $this->finalAmount = $finalAmount;
        $this->transactionId = $transactionId;
        $this->metadata = $metadata;
    }
}

