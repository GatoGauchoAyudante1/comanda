{{--
  Interruptor de tema. La preferencia se guarda en el navegador, no en el
  usuario: es del dispositivo. La tablet de la cocina puede quedar en oscuro
  y la notebook del dueño en claro, con la misma cuenta.

  El icono muestra A DÓNDE va, no dónde está: en oscuro se ve un sol.
--}}
<button type="button" class="tema-btn" data-tema-btn
        title="Cambiar entre modo claro y oscuro"
        aria-label="Cambiar el tema">

    {{-- Sol: visible en modo oscuro --}}
    <svg class="a-claro" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="4"/>
        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
    </svg>

    {{-- Luna: visible en modo claro --}}
    <svg class="a-oscuro" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>
    </svg>
</button>
