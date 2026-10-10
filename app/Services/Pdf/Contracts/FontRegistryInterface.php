<?php

namespace App\Services\Pdf\Contracts;

use Dompdf\Dompdf;

interface FontRegistryInterface
{
    public function register(Dompdf $pdf): void;

    public function getFontFamilies(): array;
}
