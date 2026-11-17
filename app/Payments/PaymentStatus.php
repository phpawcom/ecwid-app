<?php

namespace App\Payments;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
