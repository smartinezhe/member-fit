<?php

declare(strict_types=1);

return [

    /*
     * Activa la verificación en dos pasos por código enviado al correo (RF-02 a RF-04).
     *
     * Debe permanecer en true en uso normal. Solo se pone en false en demostraciones
     * locales donde no hay correo disponible (DP-02). Se lee al arrancar el panel.
     */
    'two_factor_enabled' => (bool) env('MEMBERFIT_TWO_FACTOR_ENABLED', true),

];
