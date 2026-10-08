<?php

namespace App\DTOs\Pdf;

use App\Models\DailyCharge;

readonly class DailyChargePdfData
{
    public function __construct(
        public int $id,
        public string $date,
        public string $itemName,
        public int $unitPrice,
        public int $quantity,
        public int $subtotal,
        public ?string $note,
        public bool $isTaxable,
    ) {}

    public static function fromModel(DailyCharge $charge): self
    {
        $taxType = $charge->chargeItem?->tax_type ?? 'standard';
        $isTaxable = $taxType !== 'non_taxable';

        return new self(
            id: $charge->id,
            date: $charge->date->format('m/d'),
            itemName: $charge->chargeItem?->name ?? '自費サービス',
            unitPrice: (int) $charge->unit_price,
            quantity: (int) $charge->quantity,
            subtotal: (int) $charge->subtotal,
            note: $charge->note,
            isTaxable: $isTaxable,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'item_name' => $this->itemName,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'subtotal' => $this->subtotal,
            'note' => $this->note,
            'is_taxable' => $this->isTaxable,
        ];
    }
}