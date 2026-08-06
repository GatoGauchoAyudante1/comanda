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
