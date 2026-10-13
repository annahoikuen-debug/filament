<?php

namespace Database\Factories;

use App\Enums\ServiceInvoiceStatus;
use App\Enums\ServiceType;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceInvoiceFactory extends Factory
{
    protected $model = ServiceInvoice::class;

    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'resident_id' => Resident::factory(),
            'billing_year_month' => now()->format('Y-m'),
            'service_type' => ServiceType::VisitingCare->value,
            'service_type_label' => ServiceType::VisitingCare->getLabel(),
            'external_system_name' => 'Test System',
            'external_invoice_number' => fake()->optional()->numerify('INV-####'),
            'amount' => fake()->numberBetween(10000, 100000),
            'tax_amount' => fake()->numberBetween(1000, 10000),
            'tax_rate' => 10.00,
            'pdf_path' => null,
            'pdf_original_name' => null,
            'status' => ServiceInvoiceStatus::Draft,
            'sent_at' => null,
            'sent_via' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}