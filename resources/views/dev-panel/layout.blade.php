{{--
  Layout propio y mínimo del panel del desarrollador.

  No extiende layouts/app: el panel vive fuera del sistema del cliente y no
  tiene que arrastrar su barra lateral, su sesión ni su PWA. La CSS va acá
  embebida y no en Tailwind por una razón práctica: así el panel se ve bien
  aunque en el servidor no se haya corrido `npm run build`.
--}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    {{-- Es una URL privada: que no la indexe nadie. --}}
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('titulo', 'Panel del desarrollador')</title>

    <style>
        :root{
            --bg:#0b0e0d; --card:#121716; --card-2:#161d1b; --line:#232c29;
            --txt:#e9efec; --txt-2:#98a5a0; --txt-3:#6b7772;
            --green:#37d07f; --green-dim:rgba(55,208,127,.10); --green-line:rgba(55,208,127,.35);
            --amber:#f0b429; --amber-dim:rgba(240,180,41,.10); --amber-line:rgba(240,180,41,.35);
            --red:#f2555a;   --red-dim:rgba(242,85,90,.10);    --red-line:rgba(242,85,90,.35);
        }
        *{box-sizing:border-box}
        body{
            margin:0; background:var(--bg); color:var(--txt);
            font-family:'Outfit',ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;
            font-size:14.5px; line-height:1.45;
        }
        a{color:inherit}
        .wrap{max-width:1040px; margin:0 auto; padding:28px 20px 64px}

        /* cabecera */
        .head{display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:24px}
        .head h1{margin:0; font-size:21px; font-weight:600; letter-spacing:-.2px}
        .head .sub{color:var(--txt-2); font-size:13.5px; margin-top:2px}

        /* bloques */
        .card{background:var(--card); border:1px solid var(--line); border-radius:14px; padding:18px}
        .grid{display:grid; gap:14px}
        .grid-3{grid-template-columns:repeat(3,1fr)}
        .sec{font-size:12px; text-transform:uppercase; letter-spacing:.10em; color:var(--txt-3); margin:26px 0 12px}
        .sec:first-child{margin-top:0}
        .hint{color:var(--txt-3); font-size:12.5px; margin-top:-4px; margin-bottom:14px}

        /* tarjetas de resumen */
        .kpi .lbl{color:var(--txt-2); font-size:12.5px}
        .kpi .val{font-size:26px; font-weight:600; letter-spacing:-.5px; margin-top:4px; font-variant-numeric:tabular-nums}
        .kpi .foot{color:var(--txt-3); font-size:12.5px; margin-top:4px}
        .t-green{color:var(--green)} .t-red{color:var(--red)} .t-amber{color:var(--amber)}

        /* formularios */
        label{display:block; color:var(--txt-2); font-size:12.5px; margin-bottom:5px}
        input[type=text],input[type=number],input[type=password],input[type=date]{
            width:100%; background:var(--card-2); border:1px solid var(--line); color:var(--txt);
            border-radius:9px; padding:9px 11px; font:inherit; font-size:14px;
        }
        input:focus{outline:none; border-color:var(--green-line)}
        .fields{display:grid; gap:12px; grid-template-columns:repeat(4,1fr)}
        .check{display:flex; align-items:center; gap:8px; color:var(--txt); font-size:14px; padding-top:22px}
        .check input{width:16px; height:16px; accent-color:var(--green)}
        .actions{display:flex; gap:10px; justify-content:flex-end; margin-top:16px; flex-wrap:wrap}

        .btn{
            background:var(--card-2); border:1px solid var(--line); color:var(--txt);
            border-radius:9px; padding:9px 15px; font:inherit; font-size:13.5px; cursor:pointer;
            text-decoration:none; display:inline-block;
        }
        .btn:hover{border-color:var(--txt-3)}
        .btn-primary{background:var(--green); border-color:var(--green); color:#06120c; font-weight:600}
        .btn-primary:hover{filter:brightness(1.06)}
        .btn-sm{padding:5px 10px; font-size:12.5px}

        /* avisos */
        .notice{border:1px solid var(--line); border-radius:11px; padding:12px 14px; margin-bottom:16px; font-size:13.5px}
        .notice-green{border-color:var(--green-line); background:var(--green-dim)}
        .notice-red{border-color:var(--red-line); background:var(--red-dim)}
        .notice ul{margin:6px 0 0; padding-left:18px}

        /* tabla */
        table{width:100%; border-collapse:collapse}
        th{
            text-align:left; font-size:11.5px; text-transform:uppercase; letter-spacing:.08em;
            color:var(--txt-3); font-weight:500; padding:0 10px 10px; border-bottom:1px solid var(--line);
        }
        td{padding:12px 10px; border-bottom:1px solid var(--line); font-size:14px; vertical-align:middle}
        tr:last-child td{border-bottom:none}
        .money{font-variant-numeric:tabular-nums}
        .badge{display:inline-block; border-radius:999px; padding:3px 10px; font-size:12px; font-weight:600; border:1px solid}
        .badge-green{color:var(--green); border-color:var(--green-line); background:var(--green-dim)}
        .badge-amber{color:var(--amber); border-color:var(--amber-line); background:var(--amber-dim)}
        .badge-red{color:var(--red); border-color:var(--red-line); background:var(--red-dim)}
        .small{color:var(--txt-3); font-size:12px; margin-top:3px}
        .row-actions{display:flex; gap:8px; justify-content:flex-end}
        .empty{color:var(--txt-3); text-align:center; padding:26px 0; font-size:13.5px}

        @media (max-width:760px){
            .grid-3{grid-template-columns:1fr}
            .fields{grid-template-columns:1fr 1fr}
            .row-actions{justify-content:flex-start}
        }
    </style>
</head>
<body>
    @yield('contenido')
</body>
</html>
