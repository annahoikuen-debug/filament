<?php
$html = file_get_contents('E:\seikyu\storage\app\current_invoice.html');
echo "2026-10分 請求書: " . (strpos($html, '2026-10分 請求書') !== false ? 'FOUND' : 'NOT FOUND') . "\n";
echo "{{ \$invoice->billing_year_month }}: " . (strpos($html, '{{ $invoice->billing_year_month }}') !== false ? 'STILL BROKEN' : 'FIXED') . "\n";