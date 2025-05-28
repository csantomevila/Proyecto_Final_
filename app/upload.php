<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';

// Definir constantes de log para subidas si no están en config.php
// (Es mejor definirlas en config.php para centralizar)
if (!defined('LOG_DIR_BASE')) {
    define('LOG_DIR_BASE', __DIR__ . '/../logs/');
}
if (!defined('LOG_FILE_UPLOADS')) {
    define('LOG_FILE_UPLOADS', LOG_DIR_BASE . 'file_uploads.log');
}

// Adaptar a los nombres de constantes de tu config.php
$upload_directory_path = UPLOAD_DIR; // Usa tu constante UPLOAD_DIR
$max_allowed_file_size = MAX_FILE_SIZE; // Usa tu constante MAX_FILE_SIZE
// ALLOWED_TYPES en tu config.php contiene tipos MIME.
// Para permitir cualquier archivo, no usaremos esta constante para validación de tipo.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $login_url = rtrim(BASE_URL, '/') . '/app/login.php';
    header('Location: ' . $login_url);
    exit;
}

// --- INICIO: Función de Logging para Subida de Archivos ---
function log_file_upload($status, $user_id, $original_name, $message_detail, $stored_name = null, $file_size = null) {
    if (!is_dir(LOG_DIR_BASE)) {
        if (!mkdir(LOG_DIR_BASE, 0755, true)) {
            error_log("Error crítico: No se pudo crear el directorio de logs de subida en " . LOG_DIR_BASE);
            return;
        }
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';

    $log_entry = "[{$timestamp}] [{$ip_address}] [UserID: {$user_id}] [{$status}]";
    $log_entry .= " OriginalName: \"" . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8') . "\"";
    if ($stored_name) {
        $log_entry .= " StoredName: \"" . htmlspecialchars($stored_name, ENT_QUOTES, 'UTF-8') . "\"";
    }
    if ($file_size !== null) {
        $log_entry .= " Size: " . round($file_size / 1024, 2) . "KB";
    }
    $log_entry .= " - Details: " . htmlspecialchars($message_detail, ENT_QUOTES, 'UTF-8');
    $log_entry .= PHP_EOL;

    if (file_put_contents(LOG_FILE_UPLOADS, $log_entry, FILE_APPEND | LOCK_EX) === false) {
        error_log("Error crítico: No se pudo escribir en el archivo de log de subidas: " . LOG_FILE_UPLOADS);
    }
}
// --- FIN: Función de Logging ---

$error = '';
$success = false;
$uploaded_file_display_info = null;

$current_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file_upload_data = $_FILES['file'];
        $original_name = basename($file_upload_data['name']); // basename() para seguridad
        $file_type_reported_by_browser = $file_upload_data['type'];
        $file_size_from_upload = $file_upload_data['size'];
        $tmp_file_path = $file_upload_data['tmp_name'];

        $sanitized_original_name = preg_replace('/[^A-Za-z0-9._-]/', '_', $original_name);
        $sanitized_original_name = trim($sanitized_original_name, '._-');
        if (empty($sanitized_original_name)) {
            $sanitized_original_name = "archivo_subido_" . time(); // Nombre por defecto si queda vacío
        }
        
        log_file_upload('ATTEMPT', $current_user_id, $original_name, "Intento de subida. Tipo reportado: {$file_type_reported_by_browser}, Tamaño: {$file_size_from_upload} bytes.");

        $file_extension = strtolower(pathinfo($sanitized_original_name, PATHINFO_EXTENSION));
        
        // Generar nombre único para almacenar
        $stored_name_base = hash('sha256', $tmp_file_path . microtime());
        $stored_name = $file_extension ? $stored_name_base . '.' . $file_extension : $stored_name_base;
        
        $target_file_full_path = rtrim($upload_directory_path, '/') . '/' . $stored_name;

        // --- Validaciones ---
        // 1. Tamaño del archivo (usando $max_allowed_file_size que toma de MAX_FILE_SIZE)
        if ($file_size_from_upload > $max_allowed_file_size) {
            $max_mb = round($max_allowed_file_size / 1024 / 1024, 2);
            $error = "El archivo es demasiado grande. Máximo permitido: {$max_mb} MB.";
            log_file_upload('FAILURE', $current_user_id, $original_name, $error, null, $file_size_from_upload);
        }
        // 2. Directorio de subida
        elseif (!is_dir($upload_directory_path)) {
            if (!mkdir($upload_directory_path, 0755, true)) { // 0755 para permisos
                $error = 'Error del servidor: No se pudo crear el directorio de subida.';
                error_log("Fallo al crear directorio de subida: " . $upload_directory_path);
                log_file_upload('FAILURE', $current_user_id, $original_name, $error);
            }
        } elseif (!is_writable($upload_directory_path)) {
             $error = 'Error del servidor: El directorio de subida no tiene permisos de escritura.';
             error_log("Directorio de subida no escribible: " . $upload_directory_path);
             log_file_upload('FAILURE', $current_user_id, $original_name, $error);
        }
        // 3. Mover archivo
        // (La validación de tipo/extensión se ha omitido para permitir cualquier archivo)
        elseif (empty($error) && move_uploaded_file($tmp_file_path, $target_file_full_path)) {
            $conn = get_db_connection();
            if (!$conn) {
                unlink($target_file_full_path); // Eliminar archivo si la BD falla
                $error = 'Error de conexión con la base de datos al registrar el archivo.';
                log_file_upload('FAILURE', $current_user_id, $original_name, $error, $stored_name, $file_size_from_upload);
            } else {
                // Usar el tipo reportado por el navegador, o 'application/octet-stream' si está vacío.
                $final_mime_type_to_store = !empty($file_type_reported_by_browser) ? $file_type_reported_by_browser : 'application/octet-stream';
                $client_ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';

                $stmt = $conn->prepare("
                    INSERT INTO uploaded_files (
                        user_id, original_name, stored_name, file_path, 
                        file_size, file_type, upload_ip
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                // Asegúrate que la columna upload_ip exista en tu tabla uploaded_files.

                $stmt->bind_param(
                    "isssiss",
                    $current_user_id,
                    $sanitized_original_name,
                    $stored_name,
                    $target_file_full_path,
                    $file_size_from_upload,
                    $final_mime_type_to_store,
                    $client_ip_address
                );

                if ($stmt->execute()) {
                    $success = true;
                    $uploaded_file_display_info = [
                        'original_name' => $sanitized_original_name,
                        'file_size' => $file_size_from_upload,
                        'file_type' => $final_mime_type_to_store,
                        'uploaded_at' => date('Y-m-d H:i:s')
                    ];
                    // Pasar parámetros correctos a log_file_upload
                    log_file_upload('SUCCESS', $current_user_id, $sanitized_original_name, "Archivo subido y registrado en BD.", $stored_name, $file_size_from_upload);
                } else {
                    unlink($target_file_full_path);
                    $db_error_msg = $stmt->error ?: "Error desconocido al ejecutar inserción en BD";
                    $error = 'Error al guardar la información del archivo en la base de datos: ' . $db_error_msg;
                    log_file_upload('FAILURE', $current_user_id, $sanitized_original_name, $error, $stored_name, $file_size_from_upload);
                }
                $stmt->close();
                $conn->close();
            }
        } else {
            // Si move_uploaded_file falló y no hay otro error seteado
            if (empty($error)) {
                $error = 'Error desconocido al subir el archivo. Código de error PHP: ' . $file_upload_data['error'];
            }
            log_file_upload('FAILURE', $current_user_id, $original_name, $error . " (Código PHP: {$file_upload_data['error']})");
        }
    } elseif (isset($_FILES['file'])) { // Si $_FILES['file'] está seteado pero hubo un error de subida
        $upload_error_code = $_FILES['file']['error'];
        $php_upload_errors = [
            UPLOAD_ERR_INI_SIZE   => "El archivo excede la directiva upload_max_filesize en php.ini.",
            UPLOAD_ERR_FORM_SIZE  => "El archivo excede la directiva MAX_FILE_SIZE especificada en el formulario HTML.",
            UPLOAD_ERR_PARTIAL    => "El archivo fue solo parcialmente subido.",
            UPLOAD_ERR_NO_FILE    => "Ningún archivo fue subido.",
            UPLOAD_ERR_NO_TMP_DIR => "Falta una carpeta temporal en el servidor.",
            UPLOAD_ERR_CANT_WRITE => "No se pudo escribir el archivo en el disco.",
            UPLOAD_ERR_EXTENSION  => "Una extensión de PHP detuvo la subida del archivo.",
        ];
        $error = $php_upload_errors[$upload_error_code] ?? "Error desconocido durante la subida del archivo (Código: {$upload_error_code}).";
        $original_name_on_error = isset($_FILES['file']['name']) ? basename($_FILES['file']['name']) : "No especificado";
        log_file_upload('FAILURE', $current_user_id, $original_name_on_error, $error);
    } else {
        // Si no se envió ningún archivo
        $error = "No se seleccionó ningún archivo para subir.";
        // log_file_upload('FAILURE', $current_user_id, "Ninguno", $error); // Opcional si se redirige
    }
} else { // Si no es POST o no se envió `file` (acceso directo al script)
    $dashboard_url = rtrim(BASE_URL, '/') . '/app/dashboard.php';
    header('Location: ' . $dashboard_url);
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultado de Subida - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/assets/css/styles.css">
    <style>
        /* Tus estilos CSS (sin cambios respecto a la versión anterior que me diste) */
        :root {
            --primary: #4361ee;
            --success: #40c057; 
            --error: #f03e3e;   
            --text: #212529;    
            --light-text: #6c757d; 
            --bg: #f1f3f5;      
            --card-bg: #ffffff;
            --border: #dee2e6;  
            --border-radius-lg: 0.5rem; 
            --box-shadow-lg: 0 1rem 3rem rgba(0,0,0,0.175); 
            --font-family-sans-serif: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }
        body {
            font-family: var(--font-family-sans-serif);
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .container {
            width: 100%;
            max-width: 700px;
        }
        .result-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-lg);
            overflow: hidden;
        }
        .result-header {
            background: var(--primary);
            color: white;
            padding: 1.5rem 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .result-header h1 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 600;
        }
        .result-content {
            padding: 2rem;
        }
        .message-box {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem;
            border-radius: var(--border-radius-lg);
            margin-bottom: 2rem;
            border: 1px solid transparent;
        }
        .message-box.success {
            background-color: #e6f7f0;
            border-color: var(--success);
            color: #0a3d1a;
        }
        .message-box.error {
            background-color: #fff0f0;
            border-color: var(--error);
            color: #730c0c;
        }
        .message-icon {
            font-size: 1.8rem;
            margin-top: 0.25rem;
        }
        .message-box.success .message-icon { color: var(--success); }
        .message-box.error .message-icon { color: var(--error); }

        .message-text h2 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        .message-text p {
            margin: 0;
            line-height: 1.6;
        }
        .file-details {
            border-top: 1px solid var(--border);
            padding-top: 1.5rem;
            margin-top: 1.5rem;
        }
        .file-details h3 {
            color: var(--primary);
            font-size: 1.1rem;
            margin-bottom: 1rem;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.5rem;
        }
        .detail-row {
            display: flex;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }
        .detail-label {
            width: 160px;
            font-weight: 500;
            color: var(--light-text);
            flex-shrink: 0;
        }
        .detail-value {
            flex-grow: 1;
            word-break: break-all;
        }
        .action-button {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary);
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: var(--border-radius-lg);
            font-weight: 500;
            transition: background-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border: none;
            cursor: pointer;
        }
        .action-button:hover {
            background-color: #3a56d4; /* Define --primary-hover-color en tus CSS globales o aquí */
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .text-center { text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <div class="result-card">
        <div class="result-header">
            <h1>Resultado de la Subida</h1>
        </div>
        <div class="result-content">
            <?php if ($success && $uploaded_file_display_info): ?>
                <div class="message-box success">
                    <div class="message-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    </div>
                    <div class="message-text">
                        <h2>¡Archivo Subido Correctamente!</h2>
                        <p>Tu archivo ha sido procesado y almacenado de forma segura.</p>
                    </div>
                </div>
                <div class="file-details">
                    <h3>Detalles del Archivo Subido</h3>
                    <div class="detail-row">
                        <div class="detail-label">Nombre Original:</div>
                        <div class="detail-value"><?= htmlspecialchars($uploaded_file_display_info['original_name']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Tipo Reportado:</div>
                        <div class="detail-value"><?= htmlspecialchars($uploaded_file_display_info['file_type']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Tamaño:</div>
                        <div class="detail-value">
                            <?= round($uploaded_file_display_info['file_size'] / 1024, 2) ?> KB 
                            (<?= round($uploaded_file_display_info['file_size'] / (1024 * 1024), 3) ?> MB)
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Fecha de Subida:</div>
                        <div class="detail-value"><?= htmlspecialchars($uploaded_file_display_info['uploaded_at']) ?></div>
                    </div>
                </div>
            <?php elseif ($error): ?>
                <div class="message-box error">
                     <div class="message-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    </div>
                    <div class="message-text">
                        <h2>Error al Subir el Archivo</h2>
                        <p><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            <?php endif; ?>
            <div class="text-center">
                 <a href="<?= rtrim(BASE_URL, '/') ?>/app/dashboard.php" class="action-button">Volver al Panel Principal</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
