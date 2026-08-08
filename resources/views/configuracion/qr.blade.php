<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>QR de la carta · {{ $negocio }}</title>

{{--
  Hoja autocontenida, igual que el ticket: no usa @vite ni el CSS de la
  aplicación. Esto se imprime y se pega en la mesa; un cambio de Tailwind no
  tiene por qué romper un cartel que ya está plastificado.

  Todo en blanco y negro a propósito: el QR se lee por contraste, y la
  impresora del local casi siempre es monocromática.
--}}
<style>
    @page { size: A5; margin: 0; }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        background: #2a2f2d;
        font-family: 'Outfit', system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: #000;
    }

    .hoja {
        width: 148mm;
        min-height: 210mm;
        margin: 24px auto;
        padding: 22mm 16mm;
        background: #fff;
        box-shadow: 0 8px 40px rgba(0,0,0,.5);
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .negocio  { font-size: 26px; font-weight: 700; letter-spacing: -.4px; }
    .invita   { font-size: 15px; margin-top: 6px; color: #444; }

    .marco {
        margin: 26px auto 0;
        padding: 14px;
        border: 2px solid #000;
        border-radius: 10px;
        width: max-content;
        line-height: 0;                 /* el SVG no arrastra hueco de línea */
    }
    .marco svg { display: block; width: 62mm; height: 62mm; }

    .comollega { font-size: 14px; font-weight: 600; margin-top: 22px; }
    .url {
        font-family: 'Courier New', Courier, monospace;
        font-size: 12.5px;
        margin-top: 8px;
        color: #333;
        word-break: break-all;
    }

    /* Barra de acciones: sólo en pantalla, nunca en el papel. */
    .acciones {
        width: 148mm; margin: 0 auto 40px;
        display: flex; gap: 8px; justify-content: center;
    }
    .acciones a, .acciones button {
        padding: 9px 16px;
        border: 1px solid #4a5350;
        border-radius: 8px;
        background: #1a1f1e;
        color: #f1f4f2;
        font: inherit;
        font-size: 14px;
        cursor: pointer;
        text-decoration: none;
    }
    .acciones button { background: #28BE7E; color: #04150C; border-color: #28BE7E; font-weight: 600; }

    @media print {
        body { background: #fff; }
        .hoja { margin: 0; box-shadow: none; width: auto; min-height: auto; }
        .acciones { display: none; }
    }
</style>
</head>
<body>

<div class="hoja">
    <div class="negocio">{{ $negocio }}</div>
    <div class="invita">Nuestra carta, siempre actualizada</div>

    <div class="marco">{!! $svg !!}</div>

    <div class="comollega">Escaneá el código con la cámara del celular</div>
    <div class="url">{{ $url }}</div>
</div>

<div class="acciones">
    <a href="{{ route('configuracion') }}#carta">Volver a Ajustes</a>
    <button type="button" onclick="window.print()">Imprimir</button>
</div>

</body>
</html>
