<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Código QR en SVG.
 *
 * SVG y no PNG porque el QR se imprime: pegado en la mesa se lo mira de cerca
 * y a cualquier tamaño, y un vectorial no se pixela. Además se puede meter
 * dentro del HTML sin escribir ningún archivo en disco.
 *
 * Siempre en negro sobre blanco: los lectores de los celulares dependen del
 * contraste y un QR "de marca" en verde sobre fondo oscuro no lo lee la mitad
 * de los clientes.
 */
class Qr
{
    public static function svg(string $texto, int $lado = 320): string
    {
        $writer = new Writer(
            new ImageRenderer(
                // Margen 1 módulo: el "quiet zone" del estándar lo agrega el
                // propio renderer; de más sólo achica el dibujo.
                new RendererStyle($lado, 1),
                new SvgImageBackEnd(),
            ),
        );

        $svg = $writer->writeString($texto);

        // El writer devuelve un documento con su cabecera XML. Adentro de una
        // página HTML eso sobra y algunos navegadores lo muestran como texto.
        return (string) preg_replace('/^<\?xml.*?\?>\s*/s', '', $svg);
    }
}
