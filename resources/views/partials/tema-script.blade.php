{{--
  Aplica el tema guardado ANTES de que se pinte la página.

  Va inline y no en app.js a propósito: si esperara a que cargue el bundle, el
  usuario vería un destello oscuro antes de que se aplique el claro. Con esto
  el atributo ya está puesto cuando el navegador empieza a dibujar.
--}}
<script>
    (function () {
        try {
            if (localStorage.getItem('tema') === 'claro') {
                document.documentElement.dataset.tema = 'claro';
            }
        } catch (e) {
            // Navegador sin almacenamiento (modo privado estricto): queda en oscuro.
        }
    })();
</script>
