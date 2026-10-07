<?php
copy('https://getcomposer.org/installer', 'composer-setup.php');
$installDir = 'C:\ProgramData\ComposerSetup\bin';
if (!is_dir($installDir)) {
    mkdir($installDir, 0755, true);
}
$phar = new Phar('composer.phar', 0, 'composer.phar');
$phar->buildFromIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator('composer-setup.php')), 'composer-setup.php');
copy('composer-setup.php', $installDir . '\composer-setup.php');
echo "Run: php composer-setup.php --install-dir=$installDir --filename=composer.bat\n";