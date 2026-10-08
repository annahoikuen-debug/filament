<?php

namespace App\Services\Pdf\Contracts;

use Illuminate\Http\Response;

interface RendererInterface
{
    public function render(string $html, array $options = []): string;
    public function stream(string $html, string $filename): Response;
    public function download(string $html, string $filename): Response;
}