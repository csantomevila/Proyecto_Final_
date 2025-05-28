<?php
// --- INICIO: Habilitar visualización de errores para depuración ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN: Habilitar visualización de errores ---

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$phpmailer_path = __DIR__ . '/../includes/PHPMailer/';
$phpmailerFiles = [
    $phpmailer_path . 'Exception.php',
    $phpmailer_path . 'PHPMailer.php',
    $phpmailer_path . 'SMTP.php'
];

foreach ($phpmailerFiles as $file) {
    if (!file_exists($file)) {
        $errorMsg = "Error crítico: Archivo PHPMailer no encontrado: " . basename($file) . " en la ruta: " . $file;
        error_log($errorMsg);
        die("Se ha producido un error interno al intentar procesar su solicitud. Por favor, contacte al administrador del sitio.");
    }
    require_once $file;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// --- INICIO: Función de Logging Simplificada para Intentos de Recuperación ---
if (!defined('LOG_DIR_RECOVERY')) {
    define('LOG_DIR_RECOVERY', __DIR__ . '/../logs/');
}
if (!defined('LOG_FILE_RECOVERY')) {
    define('LOG_FILE_RECOVERY', LOG_DIR_RECOVERY . 'password_recovery_attempts.log');
}

/**
 * Escribe un mensaje simplificado en el log de intentos de recuperación de contraseña.
 * Formato: [Fecha y Hora] [IP] [Estado] [UserID: opcional] Usuario: "identificador" - Detalles: detalles del evento
 *
 * @param string $status         'SUCCESS', 'FAILURE', 'ATTEMPT', 'ERROR'
 * @param string $identifier     El nombre de usuario o email ingresado.
 * @param string $message_detail Detalles adicionales del evento.
 * @param int|null $user_id      ID del usuario si se encontró (opcional).
 */
function log_recovery_attempt($status, $identifier, $message_detail, $user_id = null) {
    if (!is_dir(LOG_DIR_RECOVERY)) {
        if (!mkdir(LOG_DIR_RECOVERY, 0755, true)) {
            error_log("ALERTA DE SEGURIDAD: No se pudo crear el directorio de logs para recuperación de contraseña en " . LOG_DIR_RECOVERY . ". Verifique los permisos.");
            return; 
        }
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';

    $log_entry = "[{$timestamp}]";
    $log_entry .= " [{$ip_address}]";
    $log_entry .= " [{$status}]";

    if ($user_id !== null) {
        $log_entry .= " [UserID: {$user_id}]";
    }

    $log_entry .= " Usuario: \"" . htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') . "\"";
    $log_entry .= " - Detalles: " . htmlspecialchars($message_detail, ENT_QUOTES, 'UTF-8');
    $log_entry .= PHP_EOL;

    if (file_put_contents(LOG_FILE_RECOVERY, $log_entry, FILE_APPEND | LOCK_EX) === false) {
        error_log("ALERTA DE SEGURIDAD: No se pudo escribir en el archivo de log de recuperación de contraseña: " . LOG_FILE_RECOVERY . ". Verifique los permisos del archivo y del directorio.");
    }
}
// --- FIN: Función de Logging Simplificada ---


function generateToken($length = 32) {
    try {
        return bin2hex(random_bytes($length));
    } catch (Exception $e) {
        log_recovery_attempt('ERROR', 'SYSTEM', 'Error generando token seguro: ' . $e->getMessage());
        error_log("Error generando token seguro: " . $e->getMessage());
        die("Error interno al generar un identificador de seguridad. Por favor, intente más tarde.");
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_or_email = trim($_POST['username_or_email'] ?? '');

    log_recovery_attempt('ATTEMPT', $username_or_email, 'Inicio de solicitud de recuperación.');

    if (empty($username_or_email)) {
        $error = 'Por favor, ingresa tu nombre de usuario o correo electrónico.';
        log_recovery_attempt('FAILURE', $username_or_email, 'Identificador vacío.');
    } else {
        $conn = get_db_connection();

        if (!$conn) {
            $db_error_msg = mysqli_connect_error() ?: "Error desconocido de conexión a BD";
            error_log("Fallo de conexión a la BD en recover.php: " . $db_error_msg);
            log_recovery_attempt('ERROR', $username_or_email, 'Fallo de conexión a la BD: ' . $db_error_msg);
            $error = "Error de conexión con el servicio. Por favor, intente más tarde.";
        } else {
            $stmt = $conn->prepare("SELECT user_id, username, email FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
            
            if (!$stmt) {
                $db_prepare_error = $conn->error ?: "Error desconocido al preparar consulta";
                error_log("Error preparando la consulta de búsqueda de usuario en recover.php: " . $db_prepare_error);
                log_recovery_attempt('ERROR', $username_or_email, 'Error preparando consulta de usuario: ' . $db_prepare_error);
                $error = "Error en el servicio (P1). Por favor, intente más tarde.";
            } else {
                $stmt->bind_param("ss", $username_or_email, $username_or_email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($user = $result->fetch_assoc()) {
                    $user_id = $user['user_id'];
                    $user_email = $user['email'];
                    $user_name = $user['username'];

                    if (empty($user_email)) {
                        $error = "No hay una dirección de correo electrónico asociada a esta cuenta para la recuperación. Por favor, contacta al administrador.";
                        error_log("Intento de recuperación para usuario ID {$user_id} ({$user_name}) sin email registrado.");
                        log_recovery_attempt('FAILURE', $username_or_email, 'Usuario encontrado pero sin email asociado.', $user_id);
                    } else {
                        $token = generateToken(32);
                        $expiry_interval = defined('TOKEN_EXPIRY') ? TOKEN_EXPIRY : '+1 hour';
                        $expiry = date('Y-m-d H:i:s', strtotime($expiry_interval));

                        $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW() OR used = 1");
                        if ($stmt_delete) {
                            $stmt_delete->bind_param("i", $user_id);
                            if (!$stmt_delete->execute()) {
                                error_log("Error ejecutando borrado de tokens antiguos/usados para user_id {$user_id} en recover.php: " . $stmt_delete->error);
                            }
                            $stmt_delete->close();
                        } else {
                             error_log("Error preparando borrado de tokens antiguos/usados para user_id {$user_id} en recover.php: " . $conn->error);
                        }

                        $stmt_insert_token = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                        if (!$stmt_insert_token) {
                            $db_error_msg_insert = $conn->error ?: "Error desconocido al preparar inserción de token";
                            error_log("Error preparando la inserción de nuevo token para user_id {$user_id} en recover.php: " . $db_error_msg_insert);
                            log_recovery_attempt('ERROR', $username_or_email, 'Error preparando inserción de token: ' . $db_error_msg_insert, $user_id);
                            $error = "Error en el servicio (P2). Por favor, intente más tarde.";
                        } else {
                            $stmt_insert_token->bind_param("iss", $user_id, $token, $expiry);

                            if ($stmt_insert_token->execute()) {
                                $base_url = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                                $reset_link = $base_url . "/app/reset_password.php?token=" . urlencode($token);
                                
                                $mail = new PHPMailer(true);
                                try {
                                    // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                                    // $mail->Debugoutput = 'html';

                                    $mail->isSMTP();
                                    $mail->Host       = SMTP_HOST;
                                    $mail->SMTPAuth   = true;
                                    $mail->Username   = SMTP_USER;
                                    $mail->Password   = SMTP_PASS;
                                    
                                    if (defined('SMTP_ENCRYPTION') && strtolower(SMTP_ENCRYPTION) === 'tls') {
                                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                    } elseif (defined('SMTP_ENCRYPTION') && strtolower(SMTP_ENCRYPTION) === 'ssl') {
                                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                                    } else {
                                        $mail->SMTPSecure = false; 
                                    }
                                    
                                    $mail->Port       = SMTP_PORT;
                                    $mail->CharSet    = PHPMailer::CHARSET_UTF8;

                                    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                                    $mail->addAddress($user_email, htmlspecialchars($user_name));

                                    $mail->isHTML(true);
                                    $mail->Subject = 'Restablecer tu contraseña - ' . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Portal Seguro');
                                    $mail->Body    = "<p>Hola " . htmlspecialchars($user_name) . ",</p>" .
                                                    "<p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'nuestro portal') . ".</p>" .
                                                    "<p>Para restablecer tu contraseña, por favor haz clic en el siguiente enlace (o cópialo y pégalo en tu navegador):<br>" .
                                                    "<a href=\"" . htmlspecialchars($reset_link) . "\">" . htmlspecialchars($reset_link) . "</a></p>" .
                                                    "<p>Este enlace de restablecimiento es válido por un tiempo limitado (usualmente 1 hora).</p>" .
                                                    "<p>Si no solicitaste un restablecimiento de contraseña, por favor ignora este correo electrónico o contacta con nosotros si tienes alguna preocupación.</p>" .
                                                    "<br><p>Saludos,<br>El equipo de " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Portal Seguro') . "</p>";
                                    
                                    $mail->AltBody = "Hola " . htmlspecialchars($user_name) . ",\n\n" .
                                                    "Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'nuestro portal') . ".\n\n" .
                                                    "Para restablecer tu contraseña, por favor visita el siguiente enlace:\n" .
                                                    htmlspecialchars($reset_link) . "\n\n" .
                                                    "Este enlace de restablecimiento es válido por un tiempo limitado (usualmente 1 hora).\n\n" .
                                                    "Si no solicitaste un restablecimiento de contraseña, por favor ignora este correo electrónico.\n\n" .
                                                    "Saludos,\nEl equipo de " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Portal Seguro');

                                    $mail->send();
                                    $message = 'Se ha enviado un enlace de recuperación a tu dirección de correo electrónico. Por favor, revisa tu bandeja de entrada (y la carpeta de spam).';
                                    log_recovery_attempt('SUCCESS', $username_or_email, 'Correo de recuperación enviado a ' . $user_email, $user_id);
                                
                                } catch (PHPMailerException $e) {
                                    $phpmailer_error_msg = $mail->ErrorInfo . " (Excepción: " . $e->getMessage() . ")";
                                    error_log("Error PHPMailer al enviar correo de recuperación para {$user_email} (recover.php): " . $phpmailer_error_msg);
                                    log_recovery_attempt('FAILURE', $username_or_email, 'Error PHPMailer: ' . $phpmailer_error_msg, $user_id);
                                    $error = "No se pudo enviar el correo de recuperación en este momento. Por favor, inténtalo de nuevo más tarde o contacta al soporte técnico.";
                                }
                            } else {
                                $db_execute_error = $stmt_insert_token->error ?: "Error desconocido al ejecutar inserción de token";
                                error_log("Error al ejecutar la inserción del token para user_id {$user_id} en recover.php: " . $db_execute_error);
                                log_recovery_attempt('ERROR', $username_or_email, 'Error al ejecutar inserción de token: ' . $db_execute_error, $user_id);
                                $error = "Error al procesar tu solicitud (S1). Intenta nuevamente.";
                            }
                            $stmt_insert_token->close();
                        }
                    }
                } else {
                    $message = 'Si la información proporcionada corresponde a una cuenta activa en nuestros registros, se habrá enviado un correo electrónico con instrucciones para restablecer tu contraseña. Por favor, revisa también tu carpeta de spam.';
                    log_recovery_attempt('FAILURE', $username_or_email, 'Usuario no encontrado o inactivo.');
                }
                $stmt->close();
            }
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - TCG Portal Seguro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet"> 
    <style>
        :root {
            --primary-color: #007bff; 
            --primary-hover-color: #0056b3; 
            --error-color: #dc3545; 
            --error-bg-color: #f8d7da; 
            --success-color: #198754; 
            --success-bg-color: #d1e7dd; 
            --light-color: #f8f9fa;
            --dark-color: #212529; 
            --text-color: #495057;
            --text-muted-color: #6c757d; 
            --body-bg: #eef2f7; 
            --card-bg: #ffffff; 
            --input-border-color: #ced4da; 
            --input-focus-border-color: #86b7fe; 
            --input-focus-box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); 
            --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            --border-radius: 0.375rem; 
            --font-family-sans-serif: 'Poppins', sans-serif;
        }
        body {
            font-family: var(--font-family-sans-serif);
            background-color: var(--body-bg);
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column; 
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-color);
            box-sizing: border-box;
        }
        .recover-container {
            max-width: 480px; 
            width: 100%;
            background: var(--card-bg);
            border-radius: calc(var(--border-radius) * 2);
            box-shadow: var(--box-shadow);
            overflow: hidden; 
        }
        .recover-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        .recover-header h2 {
            margin: 0;
            font-size: 1.5rem; 
            font-weight: 600;
            color: white; 
        }
        .recover-header i {
            margin-right: 0.5rem;
        }
        .recover-form-content {
            padding: 2rem;
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem; 
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            border: 1px solid transparent;
        }
        .alert-error {
            background-color: var(--error-bg-color);
            color: var(--error-color);
            border-color: var(--error-color); 
        }
        .alert-success {
            background-color: var(--success-bg-color);
            color: var(--success-color);
            border-color: var(--success-color); 
        }
        .alert i {
            font-size: 1.2rem; 
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .form-group label i {
            margin-right: 0.3em;
        }
        .form-group input[type="text"],
        .form-group input[type="email"] { 
            width: 100%;
            padding: 0.75rem 1rem; 
            border: 1px solid var(--input-border-color);
            border-radius: var(--border-radius);
            box-sizing: border-box; 
            font-size: 1rem;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus {
            border-color: var(--input-focus-border-color);
            outline: 0;
            box-shadow: var(--input-focus-box-shadow);
        }
        .btn {
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            text-align: center;
            transition: background-color 0.15s ease-in-out;
        }
        .btn:hover {
            background-color: var(--primary-hover-color);
        }
        .btn i {
            margin-right: 0.5em;
        }
        .form-footer-links {
            text-align: center;
            margin-top: 1.5rem;
        }
        .form-footer-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .form-footer-links a:hover {
            text-decoration: underline;
        }
        .form-footer-links a i {
            margin-right: 0.3em;
        }
        .explanatory-text {
            font-size: 0.9rem;
            color: var(--text-muted-color);
            margin-bottom: 1.5rem;
            text-align: center;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="recover-container">
        <div class="recover-header">
            <h2><i class="fas fa-lock-open"></i> Recuperar Contraseña</h2>
        </div>
        <div class="recover-form-content">

            <?php if (!empty($error)): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php
            if (!(!empty($message) && empty($error))):
            ?>
                <p class="explanatory-text">
                    Ingresa tu nombre de usuario o la dirección de correo electrónico asociada a tu cuenta. Te enviaremos un enlace para restablecer tu contraseña.
                </p>
                <form method="post" action="recover.php"> 
                    <div class="form-group">
                        <label for="username_or_email">
                            <i class="fas fa-user-circle"></i> Usuario o Correo Electrónico
                        </label>
                        <input type="text" name="username_or_email" id="username_or_email" required
                               value="<?= htmlspecialchars($_POST['username_or_email'] ?? '') ?>"
                               placeholder="ej: tu_usuario o tu_correo@ejemplo.com"
                               aria-describedby="usernameOrEmailHelp">
                        <small id="usernameOrEmailHelp" class="form-text text-muted" style="display:none;">Ingresa tu identificador de cuenta.</small>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Enviar Enlace de Recuperación
                    </button>
                </form>
            <?php endif; ?>

            <div class="form-footer-links">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Volver a Iniciar Sesión</a>
            </div>
        </div>
    </div>
</body>
</html>
