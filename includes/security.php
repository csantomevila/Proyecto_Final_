<?php
// Protección contra ataques XSS
header('X-XSS-Protection: 1; mode=block');

// Deshabilitar MIME sniffing
header('X-Content-Type-Options: nosniff');

// Política de seguridad de contenido
header("Content-Security-Policy: default-src 'self'");

// Configuración de sesiones seguras
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);  # Habilitar solo con HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Deshabilitar información sensible
header_remove('X-Powered-By');
ini_set('expose_php', 'Off');
?>
