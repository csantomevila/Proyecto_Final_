<?php
// Configuración básica
define('APP_NAME', 'TCG Portal Seguro');
define('BASE_URL', 'http://tcg-portal.local'); // Usado para construir URLs internas

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'portal_user');
define('DB_PASS', 'TuPasswordSeguro123!');
define('DB_NAME', 'tcg_portal');

// Configuración de Nextcloud
define('NEXTCLOUD_URL', 'https://tu-nextcloud.local/remote.php/dav/files/sync_user');
define('NEXTCLOUD_USER', 'sync_user');
define('NEXTCLOUD_PASS', 'CloudPassword123!');
define('NEXTCLOUD_REMOTE_DIR', '/tcg_portal');

// Configuración de archivos (subidas)
define('UPLOAD_DIR', __DIR__.'/../uploads'); // Ruta al directorio de subidas (usaremos este nombre en upload.php)
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB en bytes (usaremos este nombre en upload.php)
define('ALLOWED_MIME_TYPES', [ // Renombrado de ALLOWED_TYPES para claridad, esto es para tipos MIME
    'application/pdf',
    'image/jpeg',
    'image/png',
    'text/plain',
    'application/zip',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // Para .docx
]);
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'txt', 'zip', 'docx']); // Lista de extensiones permitidas (sin punto)

// Configuración de sesión
define('SESSION_TIMEOUT', 3600); // 1 hora

// Configuración SMTP
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'csantomevila@danielcastelao.org');
define('SMTP_PASS', 'xtqz csks gscv wotb');
define('SMTP_ENCRYPTION', 'tls'); // PHPMailer lo interpreta como STARTTLS
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'csantomevila@danielcastelao.org');
define('SMTP_FROM_NAME', 'TCG Portal Seguro'); // Ya lo tenías, y es igual a APP_NAME

// Configuración de la aplicación (URLs públicas, tokens, etc.)
define('PUBLIC_APP_URL', 'https://tudominio.com'); // Renombrado de APP_URL para claridad si BASE_URL es local
define('TOKEN_EXPIRY_DURATION', '+1 hour'); // Renombrado de TOKEN_EXPIRY para claridad

// --- NUEVAS CONSTANTES PARA LOGGING DE SUBIDAS ---
define('LOG_DIR_BASE', __DIR__ . '/../logs/'); // Directorio base para todos los logs
define('LOG_FILE_UPLOADS', LOG_DIR_BASE . 'file_uploads.log'); // Log específico para subidas

?>
