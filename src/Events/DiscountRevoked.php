<?php

namespace Hipster\UserDiscounts\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Hipster\UserDiscounts\Models\UserDiscount;

class DiscountRevoked
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
     * The reason for revocation.
     */
    public ?string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(UserDiscount $userDiscount, ?string $reason = null)
    {
        $this->userDiscount = $userDiscount;
        $this->userId = $userDiscount->user_id;
        $this->discountId = $userDiscount->discount_id;
        $this->reason = $reason;
    }
}

