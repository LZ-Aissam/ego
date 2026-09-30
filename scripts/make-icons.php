<?php
// Génère public/icons/icon-192.png et icon-512.png (texte « EGO » pixelisé) avec GD.

foreach ([192, 512] as $size) {
    $small = imagecreatetruecolor(33, 21);
    imagefill($small, 0, 0, imagecolorallocate($small, 9, 9, 11));
    imagestring($small, 5, 3, 3, 'EGO', imagecolorallocate($small, 163, 230, 53));

    $icon = imagecreatetruecolor($size, $size);
    imagefill($icon, 0, 0, imagecolorallocate($icon, 9, 9, 11));
    $width = (int) round($size * 0.8);
    $height = (int) round($width * 21 / 33);
    imagecopyresized($icon, $small, (int) (($size - $width) / 2), (int) (($size - $height) / 2), 0, 0, $width, $height, 33, 21);

    imagepng($icon, __DIR__."/../public/icons/icon-{$size}.png");
}

echo "Icônes générées.\n";
