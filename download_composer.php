<?php
$installDir = 'C:\ProgramData\ComposerSetup\bin';
if (!is_dir($installDir)) {
    mkdir($installDir, 0755, true);
}
copy('https://getcomposer.org/composer.phar', $installDir . '\composer.phar');
echo "Downloaded composer.phar to $installDir\n";