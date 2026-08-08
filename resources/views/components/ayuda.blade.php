{{--
  El botón de ayuda de la barra superior.

  Va al lado del de tema, en el layout y no en cada pantalla, para que esté
  siempre en el mismo lugar: la gente lo busca donde lo vio la última vez.

  Mismo patrón que <x-tema />: sin Alpine, sólo un atributo-gancho que
  resources/js/app.js escucha por delegación. El panel en sí lo dibuja
  partials/ayuda.blade.php.
--}}
<button type="button" class="ayuda-btn" data-ayuda-abrir
        title="Ayuda: cómo se hace cada cosa"
        aria-label="Abrir la ayuda"
        aria-controls="ayuda-panel"
        aria-expanded="false">
    <x-icono nombre="ayuda" />
</button>
