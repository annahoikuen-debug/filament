<?php

namespace App\DTOs\Pdf;

use App\Services\FacilityConfigService;

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
        // FacilityConfigService を使用して統一的に取得
        $configService = app(FacilityConfigService::class);
        $config = $facility ?? $configService->getConfig($facilityId);
        $bank = $config['bank'] ?? [];
        $billing = $config['billing'] ?? [];

        return new self(
            id: $facilityId ?? $config['id'] ?? 1,
            name: $config['name'] ?? '',
            operator: $config['operator'] ?? '',
            postalCode: $config['postal_code'] ?? '',
            address: $config['address'] ?? '',
            phone: $config['phone'] ?? '',
            fax: $config['fax'] ?? null,
            invoiceRegistrationNumber: $config['invoice_registration_number'] ?? null,
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
            logoPath: $config['logo_path'] ?? null,
            sealPath: $config['seal_path'] ?? null,
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