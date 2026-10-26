<?php

declare(strict_types=1);

namespace App\Orders;

final class ShippingDetails
{
    public function __construct(
        public readonly string $method,
        public readonly string $recipientName,
        public readonly string $phone,
        public readonly ?string $address,
    ) {
    }
}
