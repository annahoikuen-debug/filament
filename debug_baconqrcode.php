<?php
require __DIR__ . '/vendor/autoload.php';

echo "Testing BaconQrCode library directly...\n";

try {
    $renderer = new \BaconQrCode\Renderer\Image\SvgImageRendererBackEnd();
    $renderer = new \BaconQrCode\Renderer\ImageRenderer($renderer, 200, 200);
    $writer = new \BaconQrCode\Writer($renderer);
    $svg = $writer->writeString("HELLO WORLD");
    
    $dataUri = 'data:image/svg+xml;base64,'.base64_encode($svg);
    
    if (!empty($dataUri) && strpos($dataUri, 'data:image/svg+xml;base64,') === 0) {
        echo "✓ BaconQrCode library is working correctly\n";
        echo "  Generated data URI length: " . strlen($dataUri) . "\n";
    } else {
        echo "✗ BaconQrCode library failed\n";
        echo "  Result: " . var_export($dataUri, true) . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error testing BaconQrCode library: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>