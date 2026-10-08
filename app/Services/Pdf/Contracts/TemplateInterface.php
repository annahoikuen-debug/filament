<?php

namespace App\Services\Pdf\Contracts;

interface TemplateInterface
{
    public function render(array $data): string;
    public function getCss(): string;
    public function getRequiredFonts(): array;
}