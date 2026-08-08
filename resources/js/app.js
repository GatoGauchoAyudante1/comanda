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

/*
| Interruptor de tema.
|
| La preferencia es del dispositivo, no del usuario: la tablet de la cocina
| puede quedar en oscuro y la notebook del dueño en claro con la misma cuenta.
| Por eso va en localStorage y no en la base.
|
| El tema guardado ya se aplicó en el <head> (partials/tema-script) para que no
| haya destello al cargar; acá sólo se maneja el clic.
*/
document.addEventListener('click', e => {
    if (! e.target.closest('[data-tema-btn]')) return;

    const raiz   = document.documentElement;
    const aClaro = raiz.dataset.tema !== 'claro';

    if (aClaro) {
        raiz.dataset.tema = 'claro';
    } else {
        delete raiz.dataset.tema;
    }

    try {
        localStorage.setItem('tema', aClaro ? 'claro' : 'oscuro');
    } catch (err) {
        // Sin almacenamiento el cambio vale sólo para esta pantalla.
    }

    // La barra del navegador en el celular acompaña al fondo de la app.
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.setAttribute('content', aClaro ? '#F5F7F6' : '#070908');
    }
});

/*
| Panel de ayuda.
|
| Sin Alpine: el `x-data` lo declara cada pantalla (ver layouts/app.blade.php),
| así que un panel atado a Alpine se caería en las pantallas que no lo declaran,
| y la cocina ni siquiera usa ese layout. Con el atributo `hidden` y el CSS que
| ya existe para `.overlay[hidden]` alcanza.
|
| El contenido lo arma App\Support\Ayuda; acá sólo se abre, se cierra y se filtra.
*/
(function ayuda() {
    const panel = () => document.querySelector('[data-ayuda-panel]');

    const abrir = () => {
        const p = panel();

        if (! p) return;

        p.hidden = false;
        document.querySelectorAll('[data-ayuda-abrir]').forEach(b => b.setAttribute('aria-expanded', 'true'));

        // El foco al buscador, pero no en el celular: ahí el teclado tapa
        // media pantalla y lo primero que se quiere es leer, no escribir.
        const buscar = p.querySelector('[data-ayuda-buscar]');
        if (buscar && window.innerWidth > 900) buscar.focus();
    };

    const cerrar = () => {
        const p = panel();

        if (! p) return;

        p.hidden = true;
        document.querySelectorAll('[data-ayuda-abrir]').forEach(b => b.setAttribute('aria-expanded', 'false'));
    };

    document.addEventListener('click', e => {
        if (e.target.closest('[data-ayuda-abrir]')) return abrir();
        if (e.target.closest('[data-ayuda-cerrar]')) return cerrar();

        // Tocar el velo, fuera del diálogo, también cierra.
        const p = panel();
        if (p && ! p.hidden && e.target === p) cerrar();
    });

    document.addEventListener('keydown', e => {
        const p = panel();

        if (e.key === 'Escape' && p && ! p.hidden) cerrar();
    });

    /*
    | Filtro del buscador.
    |
    | Compara contra `data-buscar`, que ya trae título, ubicación y sinónimos
    | en minúscula. Se busca por palabras sueltas y en cualquier orden: quien
    | escribe "cargar mozo" y quien escribe "mozo alta" tienen que llegar.
    |
    | Se le sacan los acentos a los dos lados: nadie escribe "café" en el
    | apuro de un sábado, y "credito" tiene que encontrar "crédito".
    */
    const plano = t => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

    /*
    | Palabras que se tiran antes de comparar.
    |
    | La gente escribe la pregunta entera —«dónde se cargan las mesas»— y si
    | todas las palabras tienen que estar, no encuentra nada. Sacando estas
    | queda «cargan mesas», que es lo que hay que buscar.
    */
    const VACIAS = new Set([
        'donde', 'dónde', 'como', 'cómo', 'que', 'qué', 'cual', 'cuál', 'quien', 'quién',
        'se', 'me', 'le', 'lo', 'la', 'las', 'los', 'el', 'un', 'una', 'unos', 'unas',
        'de', 'del', 'al', 'a', 'en', 'con', 'por', 'para', 'y', 'o', 'es', 'hay',
    ]);

    document.addEventListener('input', e => {
        const campo = e.target.closest('[data-ayuda-buscar]');

        if (! campo) return;

        const p      = panel();
        const dichas = plano(campo.value).split(/\s+/).filter(Boolean);
        const utiles = dichas.filter(w => ! VACIAS.has(w));

        // Si escribió sólo palabras vacías, se busca por lo que puso tal cual:
        // vaciar el filtro entero sería mostrarle todo sin motivo aparente.
        const partes = utiles.length ? utiles : dichas;

        let encontrados = 0;

        p.querySelectorAll('[data-ayuda-tema]').forEach(tema => {
            const heno  = plano(tema.dataset.buscar);
            const entra = partes.every(parte => heno.includes(parte));

            tema.hidden = ! entra;

            // Buscando conviene ver el contenido; sin buscar, la lista corta.
            if (entra) {
                encontrados++;
                tema.open = partes.length > 0;
            }
        });

        // Un grupo sin ningún tema visible es sólo un título suelto.
        p.querySelectorAll('[data-ayuda-grupo]').forEach(grupo => {
            grupo.hidden = ! grupo.querySelector('[data-ayuda-tema]:not([hidden])');
        });

        p.querySelector('[data-ayuda-nada]').hidden = encontrados > 0;
    });
})();

/*
| Ver la clave que se está tipeando.
|
| En el celular, con el teclado tapando media pantalla y los puntitos, el que
| se equivocó una letra no tiene forma de darse cuenta: sólo puede borrar todo
| y volver a empezar.
|
| El botón declara a qué campo apunta con data-ver-clave="<id del input>".
*/
document.addEventListener('click', e => {
    const btn = e.target.closest('[data-ver-clave]');

    if (! btn) return;

    const campo = document.getElementById(btn.dataset.verClave);

    if (! campo) return;

    const ver = campo.type === 'password';

    campo.type = ver ? 'text' : 'password';
    btn.classList.toggle('is-on', ver);
    btn.setAttribute('aria-pressed', ver ? 'true' : 'false');
    btn.setAttribute('aria-label', ver ? 'Ocultar la clave' : 'Mostrar la clave');
    btn.setAttribute('title', ver ? 'Ocultar la clave' : 'Mostrar la clave');

    // El foco vuelve al campo, al final del texto. Sin esto, en el celular se
    // cierra el teclado y hay que volver a tocar el input para seguir.
    campo.focus();

    try {
        campo.setSelectionRange(campo.value.length, campo.value.length);
    } catch (err) {
        // Algunos navegadores no dejan mover el cursor en un campo de clave.
    }
});

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
