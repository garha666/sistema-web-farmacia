<?php

declare(strict_types=1);

namespace App\Pos\Value;

use App\Pos\Enum\SaleCategory;
use App\Pos\Money;

/**
 * Copia inmutable del producto con los datos necesarios para vender (RF-2, RF-29).
 */
final readonly class ProductSnapshot
{
    public function __construct(
        public int $id,
        public string $name,
        public Money $unitPrice,
        public SaleCategory $category,
        public int $availableStock,
        public bool $sellable = true,
    ) {
    }

    public function requiresPrescription(): bool
    {
        return $this->category->requiresPrescription();
    }

    public function withAvailableStock(int $availableStock): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            unitPrice: $this->unitPrice,
            category: $this->category,
            availableStock: $availableStock,
            sellable: $this->sellable,
        );
    }
}
