/*
| Interacciones chicas y transversales.
| Lo que necesita estado del servidor va en componentes Livewire;
| lo que es puramente visual y local va en Alpine (que ya viene con Livewire).
*/

/*
| Reloj de las mesas de pool.
|
| El servidor manda `started_at` y los minutos de pausa una sola vez; el
| navegador cuenta solo desde ahí. No hace falta ni polling ni websockets:
| el importe real siempre lo calcula el servidor al cerrar (R-01, D-18).
*/
function arrancarRelojes() {
    const relojes = [...document.querySelectorAll('[data-reloj]')];

    if (! relojes.length) return;

    const pintar = () => relojes.forEach(el => {
        if (el.dataset.corriendo !== '1') return;

        const desde   = new Date(el.dataset.inicio).getTime();
        const pausa   = Number(el.dataset.pausa || 0);
        const minutos = Math.max(0, Math.floor((Date.now() - desde) / 60000) - pausa);
        const texto   = `${Math.floor(minutos / 60)}:${String(minutos % 60).padStart(2, '0')}`;

        if (el.textContent.trim() !== texto) el.textContent = texto;
    });

    pintar();
    setInterval(pintar, 5000);
}

document.addEventListener('DOMContentLoaded', arrancarRelojes);

/*
| Avisar que la barra lateral tiene más ítems fuera de vista.
|
| La lista scrollea pero le ocultamos la barra de scroll para no ensuciar los
| 94 px de ancho, así que sin esto no hay ninguna señal: el usuario cree que
| los ítems que ve son todos los que hay.
|
| Marca `.mas-arriba` y `.mas-abajo` en el rail; el CSS se encarga del
| degradado y la flecha. Funciona igual en vertical (escritorio) y en
| horizontal (la barra inferior del celular).
*/
function marcarDesbordeDelRail() {
    const nav = document.querySelector('.rail-nav');

    if (! nav) return;

    const rail = nav.closest('.rail');

    const actualizar = () => {
        const horizontal = nav.scrollWidth > nav.clientWidth + 4;

        const [pos, max] = horizontal
            ? [nav.scrollLeft, nav.scrollWidth - nav.clientWidth]
            : [nav.scrollTop,  nav.scrollHeight - nav.clientHeight];

        // 4 px de tolerancia: el redondeo de subpíxeles deja restos.
        rail.classList.toggle('mas-arriba', max > 4 && pos > 4);
        rail.classList.toggle('mas-abajo',  max > 4 && pos < max - 4);
    };

    actualizar();
    nav.addEventListener('scroll', actualizar, { passive: true });
    window.addEventListener('resize', actualizar);

    // Al cambiar de pantalla el menú puede tener otra cantidad de ítems.
    if (window.ResizeObserver) {
        new ResizeObserver(actualizar).observe(nav);
    }
}

document.addEventListener('DOMContentLoaded', marcarDesbordeDelRail);

document.addEventListener('click', e => {

    // Grupos de opción excluyente: sólo una prendida a la vez.
    const exclusiva = e.target.closest('.filter, .seg button, .pay:not(.pay-add), [data-pick] > *');
    if (exclusiva && exclusiva.parentElement) {
        [...exclusiva.parentElement.children].forEach(c => c.classList.remove('is-on'));
        exclusiva.classList.add('is-on');
        return;
    }

    // Chips de opción sueltos: se prenden y apagan.
    const suelta = e.target.closest('.opt');
    if (suelta && !suelta.parentElement.hasAttribute('data-pick')) {
        suelta.classList.toggle('is-on');
    }
});
