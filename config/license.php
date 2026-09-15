<?php

return [
    // Protege /dev-panel. Vive sólo en el .env: ni un admin con acceso a la
    // base de datos puede cambiarla. Si queda vacía, el panel se deshabilita.
    'dev_key' => env('DEV_PANEL_KEY', ''),
];
