<?php

namespace App\Services\Pdf\Renderers;

use App\Services\Pdf\Contracts\RendererInterface;
use Illuminate\Http\Response;

class HtmlRenderer implements RendererInterface
{
    public function render(string $html, array $options = []): string
    {
        return $html;
    }

    public function stream(string $html, string $filename): Response
    {
        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', "inline; filename=\"{$filename}.html\"");
    }

    public function download(string $html, string $filename): Response
    {
        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}.html\"");
    }
}