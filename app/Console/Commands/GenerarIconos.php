<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Genera los íconos de la PWA a partir del nombre del negocio.
 *
 * Se hace por comando y no a mano porque cada instalación tiene su propio
 * nombre y su propio color: el ícono es configuración, no un archivo fijo.
 *
 *   php artisan negocio:iconos
 */
class GenerarIconos extends Command
{
    protected $signature = 'negocio:iconos {--color=#28BE7E : color de fondo}';

    protected $description = 'Genera los íconos de la PWA con la inicial del negocio';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('Falta la extensión GD de PHP.');

            return self::FAILURE;
        }

        $destino = public_path('icons');

        if (! is_dir($destino)) {
            mkdir($destino, 0755, true);
        }

        $inicial = mb_strtoupper(mb_substr(config('negocio.nombre'), 0, 1));
        [$r, $g, $b] = $this->rgb($this->option('color'));

        foreach ([192, 512] as $lado) {
            $this->dibujar($lado, $inicial, $r, $g, $b, "{$destino}/icon-{$lado}.png");
            $this->line("  icons/icon-{$lado}.png");
        }

        $this->info("Íconos generados con la inicial «{$inicial}».");

        return self::SUCCESS;
    }

    private function dibujar(int $lado, string $inicial, int $r, int $g, int $b, string $ruta): void
    {
        $img = imagecreatetruecolor($lado, $lado);
        imagesavealpha($img, true);

        $fondo = imagecolorallocate($img, $r, $g, $b);
        $tinta = imagecolorallocate($img, 4, 21, 12);

        imagefill($img, 0, 0, $fondo);

        // La inicial, centrada, con la fuente interna escalada al máximo.
        $escala = (int) round($lado / 22);
        $ancho  = imagefontwidth(5) * $escala;
        $alto   = imagefontheight(5) * $escala;

        $capa = imagecreatetruecolor(imagefontwidth(5), imagefontheight(5));
        imagefill($capa, 0, 0, imagecolorallocate($capa, $r, $g, $b));
        imagestring($capa, 5, 0, 0, $inicial, imagecolorallocate($capa, 4, 21, 12));

        imagecopyresampled(
            $img, $capa,
            (int) (($lado - $ancho) / 2), (int) (($lado - $alto) / 2),
            0, 0, $ancho, $alto, imagefontwidth(5), imagefontheight(5),
        );

        imagepng($img, $ruta);
        imagedestroy($capa);
        imagedestroy($img);
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
