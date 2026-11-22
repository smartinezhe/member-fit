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

    // Días de anticipación para marcar una membresía como "Por vencer" (RN-07).
    'expiry_alert_days' => (int) env('MEMBERSHIP_EXPIRY_ALERT_DAYS', 7),

];
