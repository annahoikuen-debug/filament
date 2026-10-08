<?php

namespace App\DTOs\Pdf;

use App\Models\Resident;

readonly class ResidentPdfData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $nameKana,
        public string $roomNumber,
        public ?string $status,
    ) {}

    public static function fromModel(Resident $resident): self
    {
        return new self(
            id: $resident->id,
            name: $resident->name,
            nameKana: $resident->name_kana,
            roomNumber: $resident->room_number,
            status: $resident->status?->value,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_kana' => $this->nameKana,
            'room_number' => $this->roomNumber,
            'status' => $this->status,
        ];
    }
}