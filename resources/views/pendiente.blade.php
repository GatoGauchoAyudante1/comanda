@extends('layouts.app')

@section('titulo', $pantalla)
@section('subtitulo', 'Todavía no construida')

@section('contenido')
    <div class="card" style="max-width:640px">
        <div class="sec">Pantalla pendiente</div>
        <p class="t-dim">
            El diseño de <b class="t-white">{{ $pantalla }}</b> ya está definido y aprobado.
            Se construye según el orden de <b class="t-white">docs/08-roadmap.md</b>.
        </p>
        <div class="hr"></div>
        <div class="lv">
            <span class="k">Mockup de referencia</span>
            <span class="v fs14">mockups-html/{{ $mockup }}</span>
        </div>
    </div>
@endsection
