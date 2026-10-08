<?php

namespace App\DTOs\Pdf;

readonly class FacilityPdfData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $operator,
        public string $postalCode,
        public string $address,
        public string $phone,
        public ?string $fax,
        public ?string $invoiceRegistrationNumber,
        public array $bank,
        public array $billing,
        public ?string $logoPath,
        public ?string $sealPath,
    ) {}

    public static function fromConfig(?array $facility = null, ?int $facilityId = null): self
    {
        $facility = $facility ?? config('facility');
        $bank = $facility['bank'] ?? config('facility.bank', []);
        $billing = $facility['billing'] ?? config('facility.billing', []);

        return new self(
            id: $facilityId ?? $facility['id'] ?? 1,
            name: $facility['name'] ?? config('facility.name', ''),
            operator: $facility['operator'] ?? config('facility.operator', ''),
            postalCode: $facility['postal_code'] ?? config('facility.postal_code', ''),
            address: $facility['address'] ?? config('facility.address', ''),
            phone: $facility['phone'] ?? config('facility.phone', ''),
            fax: $facility['fax'] ?? config('facility.fax'),
            invoiceRegistrationNumber: $facility['invoice_registration_number'] ?? config('facility.invoice_registration_number'),
            bank: [
                'name' => $bank['name'] ?? '',
                'branch_name' => $bank['branch_name'] ?? '',
                'account_type' => $bank['account_type'] ?? '普通',
                'account_number' => $bank['account_number'] ?? '',
                'account_holder' => $bank['account_holder'] ?? '',
            ],
            billing: [
                'direct_debit_day' => $billing['direct_debit_day'] ?? 27,
            ],
            logoPath: $facility['logo_path'] ?? null,
            sealPath: $facility['seal_path'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'operator' => $this->operator,
            'postal_code' => $this->postalCode,
            'address' => $this->address,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'invoice_registration_number' => $this->invoiceRegistrationNumber,
            'bank' => $this->bank,
            'billing' => $this->billing,
            'logo_path' => $this->logoPath,
            'seal_path' => $this->sealPath,
        ];
    }
}