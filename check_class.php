<?php
require __DIR__ . '/vendor/autoload.php';
if (class_exists('BaconQrCode\Renderer\Image\SvgImageRendererBackEnd')) {
    echo "Class exists\n";
} else {
    echo "Class does not exist\n";
}