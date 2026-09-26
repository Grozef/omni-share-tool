<?php

return [
    // Inscription publique fermée par défaut : les comptes se créent à la main
    'allow_registration' => (bool) env('ALLOW_REGISTRATION', false),
];
