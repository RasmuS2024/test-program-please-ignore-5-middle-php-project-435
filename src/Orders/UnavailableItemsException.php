<?php

declare(strict_types=1);

namespace App\Orders;

use RuntimeException;

final class UnavailableItemsException extends RuntimeException
{
    /**
     * @param list<string> $productIds
     */
    public function __construct(public readonly array $productIds)
    {
        parent::__construct('Some products are unavailable.');
    }
}
