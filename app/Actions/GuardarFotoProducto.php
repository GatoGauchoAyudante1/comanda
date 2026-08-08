<?php

namespace App\Actions;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda la foto de un producto, achicada y en JPEG.
 *
 * Las fotos las saca el dueño con el celular: vienen de 4000px y 5 MB. Servir
 * eso en la carta pública, con 40 productos y desde datos móviles, es una
 * pantalla que no carga. Se reduce al lado largo y se recomprime al subir,
 * una sola vez, en vez de resolverlo en cada visita.
 *
 * Se usa GD y no una librería de imágenes porque GD ya es requisito de la
 * instalación (la usa negocio:iconos) y esto es lo único que hace falta.
 *
 * El archivo viejo se borra: si no, cada cambio de foto deja basura en disco
 * que nadie va a limpiar nunca.
 */
class GuardarFotoProducto
{
    /** Lado máximo en píxeles. Alcanza de sobra para una tarjeta de carta. */
    private const LADO = 900;

    private const CALIDAD = 82;

    private const CARPETA = 'productos';

    /** Devuelve la ruta relativa dentro del disco `public`. */
    public function __invoke(Product $producto, UploadedFile $archivo): string
    {
        $imagen = $this->abrir($archivo);

        if (! $imagen) {
            throw new \RuntimeException('No se pudo leer la imagen.');
        }

        $imagen = $this->achicar($imagen);

        // Nombre nuevo en cada subida: el navegador y el proxy cachean por URL,
        // y reusar el nombre deja la foto vieja en pantalla.
        $ruta = self::CARPETA . '/' . $producto->id . '-' . Str::lower(Str::random(8)) . '.jpg';

        ob_start();
        imagejpeg($imagen, null, self::CALIDAD);
        $jpeg = (string) ob_get_clean();

        imagedestroy($imagen);

        Storage::disk('public')->put($ruta, $jpeg);

        $this->borrar($producto);

        return $ruta;
    }

    /** Saca la foto actual del disco. Se llama también al desmarcar «quitar foto». */
    public function borrar(Product $producto): void
    {
        if ($producto->image_path) {
            Storage::disk('public')->delete($producto->image_path);
        }
    }

    /** @return \GdImage|false */
    private function abrir(UploadedFile $archivo)
    {
        $contenido = file_get_contents($archivo->getRealPath());

        return $contenido === false ? false : @imagecreatefromstring($contenido);
    }

    /**
     * Reduce al lado largo manteniendo la proporción y aplana sobre blanco:
     * un PNG o un WebP con transparencia guardado como JPEG sale con el fondo
     * negro si no se aplana antes.
     *
     * @param  \GdImage  $original
     * @return \GdImage
     */
    private function achicar($original)
    {
        $ancho = imagesx($original);
        $alto  = imagesy($original);
        $lado  = max($ancho, $alto);

        $escala = $lado > self::LADO ? self::LADO / $lado : 1;

        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto  = max(1, (int) round($alto * $escala));

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));

        imagecopyresampled($destino, $original, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($original);

        return $destino;
    }
}
