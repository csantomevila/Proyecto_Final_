<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirigir al login si no está autenticado
if (!isset($_SESSION['user_id'])) {
    $login_url = rtrim(BASE_URL, '/') . '/app/login.php'; // Usa BASE_URL
    header('Location: ' . $login_url);
    exit;
}

$conn_error_message = null;
$files_result = null; // Para almacenar el resultado de la consulta
$files_data = []; // Array para almacenar los datos de los archivos

$conn = get_db_connection();
if (!$conn) {
    $conn_error_message = "Error de conexión con la base de datos. No se pueden mostrar los archivos.";
    error_log("Dashboard: Error de conexión a la BD: " . mysqli_connect_error());
} else {
    // Usar un límite más razonable o implementar paginación en el futuro.
    // Aquí se asume que la columna `synced` existe en tu tabla `uploaded_files`.
    // Si no existe, puedes quitarla del SELECT y de la visualización.
    $sql = "SELECT file_id, original_name, file_size, file_type, uploaded_at, synced, stored_name 
            FROM uploaded_files 
            WHERE user_id = ? 
            ORDER BY uploaded_at DESC 
            LIMIT 50"; // Límite para la carga inicial

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $files_result = $stmt->get_result();
        if ($files_result) {
            while ($row = $files_result->fetch_assoc()) {
                $files_data[] = $row;
            }
        } else {
            $conn_error_message = "Error al obtener la lista de archivos.";
            error_log("Dashboard: Error al ejecutar get_result(): " . $stmt->error);
        }
        $stmt->close();
    } else {
         $conn_error_message = "Error al preparar la consulta para listar archivos.";
         error_log("Dashboard: Error al preparar consulta de archivos: " . $conn->error);
    }
    $conn->close(); // Cerrar la conexión después de usarla
}

// Mensajes de éxito/error pasados por GET desde upload.php (o cualquier otra acción)
$success_message_key = $_GET['success'] ?? null;
$error_message_key = $_GET['error'] ?? null;

// Mapeo de claves a mensajes (puedes expandir esto)
$feedback_messages = [
    'upload_ok' => "✅ Archivo subido correctamente.",
    'delete_ok' => "✅ Archivo eliminado correctamente.",
    'invalid_type' => "❌ Tipo de archivo no permitido.",
    'file_too_large' => "❌ El archivo supera el tamaño permitido.",
    'upload_failed' => "❌ Error al subir el archivo.",
    'db_error' => "❌ Error al procesar la solicitud en la base de datos.",
    'file_not_found' => "❌ Archivo no encontrado para la acción solicitada.",
    'permission_denied' => "❌ No tienes permiso para realizar esta acción.",
    'delete_failed' => "❌ Error al intentar eliminar el archivo."
];

$display_success_message = $success_message_key && isset($feedback_messages[$success_message_key]) ? $feedback_messages[$success_message_key] : null;
$display_error_message = $error_message_key && isset($feedback_messages[$error_message_key]) ? $feedback_messages[$error_message_key] : ($conn_error_message ?? null);


// Función para formatear tamaño de archivo
function format_file_size_display($size_in_bytes) {
    if ($size_in_bytes >= 1073741824) { // GB
        return number_format($size_in_bytes / 1073741824, 2) . ' GB';
    } elseif ($size_in_bytes >= 1048576) { // MB
        return number_format($size_in_bytes / 1048576, 2) . ' MB';
    } elseif ($size_in_bytes >= 1024) { // KB
        return number_format($size_in_bytes / 1024, 2) . ' KB';
    } elseif ($size_in_bytes > 0) {
        return $size_in_bytes . ' bytes';
    } else {
        return '0 bytes';
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars(APP_NAME) ?></title> {/* Usa APP_NAME */}
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet"> {/* FontAwesome actualizado */}
    <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/assets/css/styles.css"> {/* Estilos generales */}
    <style>
        /* Variables y Estilos Generales (copiados de tu dashboard y adaptados) */
        :root {
            --primary-color: #007bff; 
            --primary-hover-color: #0056b3;
            --secondary-color: #6c757d; 
            --success-color: #198754; /* Verde Bootstrap 5 */
            --success-bg-color: #d1e7dd; 
            --error-color: #dc3545; 
            --error-bg-color: #f8d7da; 
            --info-color: #0dcaf0; /* Cian Bootstrap 5 */
            --info-bg-color: #cff4fc; 
            --light-color: #f8f9fa; 
            --dark-color: #212529; 
            --text-color: #495057; 
            --text-muted-color: #6c757d; 
            --body-bg: #eef2f7; 
            --card-bg: #ffffff; 
            --input-border-color: #ced4da;
            --input-focus-border-color: #86b7fe; /* Bootstrap 5 focus */
            --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1); 
            --box-shadow-light: 0 0.125rem 0.25rem rgba(0,0,0,0.075); 
            --border-radius: 0.375rem; 
            --font-family-sans-serif: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        }

        body {
            font-family: var(--font-family-sans-serif);
            color: var(--text-color);
            line-height: 1.6;
            background-color: var(--body-bg);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 1200px;
            width: 90%; /* Para dar un poco de margen en pantallas anchas */
            margin: 2rem auto;
            padding: 0 1rem; /* Padding lateral en vez de 1.5rem */
            flex-grow: 1;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem; /* Más espacio */
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }

        header h1 {
            color: var(--dark-color);
            margin: 0;
            font-size: 2.25rem; /* Un poco más grande */
            font-weight: 600; /* Un poco menos bold que 700 */
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1rem;
        }
        .user-info .fa-user-circle {
            font-size: 1.5rem;
            color: var(--secondary-color);
        }
        .user-info span {
            font-weight: 500;
            color: var(--dark-color);
        }

        .btn, .logout-btn, .btn-download, .upload-form button[type="submit"] { /* Estilo base para botones */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.65rem 1.25rem; /* Ajuste de padding */
            border-radius: var(--border-radius);
            font-size: 1rem; /* Tamaño de fuente base para botones */
            font-weight: 500; /* Peso de fuente */
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent; /* Borde base */
            transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .logout-btn {
            background-color: var(--secondary-color);
            color: var(--light-color);
            border-color: var(--secondary-color);
        }
        .logout-btn:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .alert {
            padding: 1rem 1.25rem; /* Ajuste de padding */
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            font-weight: 500; /* Más ligero que bold */
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-width: 1px;
            border-style: solid;
        }
        .alert.success {
            background-color: var(--success-bg-color);
            color: #0f5132; /* Color de texto más oscuro para mejor contraste */
            border-color: #badbcc;
        }
        .alert.error {
            background-color: var(--error-bg-color);
            color: #842029; /* Color de texto más oscuro */
            border-color: #f5c2c7;
        }
        .alert.info {
            background-color: var(--info-bg-color);
            color: #055160; /* Color de texto más oscuro */
            border-color: #b6effb;
        }
        .alert .fas, .alert .far { /* Iconos en alertas */
             font-size: 1.25rem; /* Un poco más grande */
        }
        .upload-form {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: calc(var(--border-radius) * 1.5);
            box-shadow: var(--box-shadow-light);
            margin-bottom: 2.5rem; /* Más espacio */
            border: 1px solid #dee2e6;
        }
        .upload-form h2 {
             margin-top: 0;
             margin-bottom: 1.5rem;
             color: var(--dark-color);
             font-size: 1.75rem; /* Título más grande */
             font-weight: 600;
        }
        .upload-form input[type="file"] {
            display: block;
            margin-bottom: 1.5rem;
            width: 100%;
            padding: 0.75rem 1rem; /* Padding ajustado */
            border: 1px solid var(--input-border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            color: var(--text-color);
            background-color: var(--light-color);
            box-sizing: border-box;
            line-height: 1.5; /* Mejorar apariencia del texto del input file */
        }
        .upload-form input[type="file"]::-webkit-file-upload-button { /* Estilo del botón interno */
            padding: 0.75rem 1rem;
            margin: -0.75rem -1rem; /* Ajustar para que no afecte el padding general */
            margin-right: 1rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius) 0 0 var(--border-radius); /* Redondear solo esquina izquierda */
            cursor: pointer;
        }
        .upload-form input[type="file"]:hover::-webkit-file-upload-button {
            background-color: var(--primary-hover-color);
        }
        .upload-form input[type="file"]:focus {
             outline: none;
             border-color: var(--input-focus-border-color);
             box-shadow: 0 0 0 0.2rem rgba(var(--primary-color-rgb, 0,123,255),0.25); /* Necesitas definir --primary-color-rgb o usar el color directamente */
        }
        .upload-form button[type="submit"] { /* Botón principal de subida */
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            width: auto; /* Ajustar al contenido */
        }
        .upload-form button[type="submit"]:hover {
            background-color: var(--primary-hover-color);
            border-color: var(--primary-hover-color);
        }
        .btn-download-link { /* Para el enlace "Ver mis descargas" */
             background-color: var(--info-color);
             color: white;
             border-color: var(--info-color);
             margin-bottom: 2.5rem;
        }
        .btn-download-link:hover {
            background-color: #0aa3b8; /* Info hover color */
            border-color: #0aa3b8;
        }
        .section-title { /* Para "Tus Archivos Recientes" */
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color); /* Línea de acento */
            display: inline-block; /* Para que el borde solo ocupe el texto */
        }
        .file-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); /* Mínimo 300px */
            gap: 1.5rem;
            margin-top: 0; /* Ya controlado por section-title */
        }
        .file-item {
            background: var(--card-bg);
            padding: 1.25rem; /* Padding ajustado */
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-light);
            border: 1px solid #e0e0e0;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            display: flex;
            flex-direction: column;
            gap: 0.6rem; /* Espacio entre elementos internos */
        }
        .file-item:hover {
            transform: translateY(-4px);
            box-shadow: var(--box-shadow);
        }
        .file-item .file-name { /* Clase específica para el nombre */
            color: var(--dark-color);
            font-size: 1.1rem;
            font-weight: 600;
            word-break: break-all; /* Para nombres muy largos */
            display: flex; /* Para el icono y el texto */
            align-items: center;
            gap: 0.5rem;
        }
        .file-item .file-meta { /* Contenedor para metadatos */
            font-size: 0.875rem; /* Tamaño unificado */
            color: var(--text-muted-color);
            display: flex;
            flex-direction: column; /* Apilar metadatos */
            gap: 0.3rem;
        }
        .file-item .file-meta span { /* Cada línea de metadato */
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .sync-status {
             font-weight: 500;
             display: flex;
             align-items: center;
             gap: 0.4rem;
             font-size: 0.9rem; /* Mismo tamaño que otros metadatos */
             padding: 0.25rem 0.5rem; /* Pequeño padding para destacar */
             border-radius: calc(var(--border-radius) / 2);
             margin-top: 0.5rem; /* Espacio arriba */
             width: fit-content; /* Ajustar al contenido */
        }
        .sync-status.synced {
            color: var(--success-color);
            background-color: var(--success-bg-color);
        }
        .sync-status.not-synced {
            color: var(--info-color);
            background-color: var(--info-bg-color);
        }
        /* File action buttons (Download/Delete) */
        .file-actions {
            margin-top: auto; /* Empuja los botones al final de la tarjeta */
            padding-top: 1rem;
            border-top: 1px solid #eee;
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end; /* Alinea botones a la derecha */
        }
        .file-actions .btn-action {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .btn-action.download {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-action.download:hover { background-color: var(--primary-hover-color); }

        .btn-action.delete {
            background-color: var(--error-color);
            color: white;
        }
        .btn-action.delete:hover { background-color: #c82333; } /* Error hover */

        footer {
             margin-top: 3rem; /* Más espacio antes del footer */
             padding: 2rem; /* Más padding */
             text-align: center;
             font-size: 0.9rem;
             color: var(--text-muted-color);
             border-top: 1px solid #dee2e6;
        }
        footer p { margin: 0.5rem 0; }
        footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .container { width: 95%; padding: 0 0.5rem; margin-top: 1rem;}
            header { flex-direction: column; gap: 1rem; text-align: center; margin-bottom: 1.5rem; }
            header h1 { font-size: 1.8rem; }
            .user-info { flex-direction: column; gap: 0.5rem; font-size: 0.95rem; }
            .upload-form { padding: 1.5rem; }
            .upload-form h2, .section-title { font-size: 1.5rem; }
            .file-list { grid-template-columns: 1fr; gap: 1rem; }
            .upload-form button[type="submit"], .btn-download-link { width: 100%; }
            .file-item { padding: 1rem; }
            .file-actions { justify-content: center; }
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1><?= htmlspecialchars(APP_NAME) ?></h1>
        <div class="user-info">
            <i class="fas fa-user-circle"></i> <span>Bienvenido, <?= htmlspecialchars($_SESSION['username'] ?? 'Usuario') ?></span>
            <a href="<?= rtrim(BASE_URL, '/') ?>/app/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
        </div>
    </header>

    <?php if ($display_success_message): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($display_success_message) ?>
        </div>
    <?php elseif ($display_error_message): ?>
        <div class="alert error">
            <i class="fas fa-times-circle"></i> <?= htmlspecialchars($display_error_message) ?>
        </div>
    <?php endif; ?>

    <form action="<?= rtrim(BASE_URL, '/') ?>/app/upload.php" method="post" enctype="multipart/form-data" class="upload-form">
        <h2><i class="fas fa-cloud-upload-alt"></i> Subir Nuevo Archivo</h2>
        <input type="file" name="file" id="fileInput" required aria-label="Seleccionar archivo">
        <button type="submit" class="btn primary"><i class="fas fa-upload"></i> Subir Archivo</button>
    </form>

    <a href="<?= rtrim(BASE_URL, '/') ?>/app/downloads.php" class="btn btn-download-link"><i class="fas fa-download"></i> Ver Historial de Descargas</a>

    <h2 class="section-title">Tus Archivos Recientes</h2>
    <div class="file-list">
        <?php if (!empty($files_data)): ?>
            <?php foreach ($files_data as $file): ?>
                <div class="file-item">
                    <div class="file-name">
                        <i class="far fa-file-alt"></i> <!-- Icono genérico de archivo -->
                        <?= htmlspecialchars($file['original_name']) ?>
                    </div>
                    <div class="file-meta">
                        <span><i class="far fa-clock"></i> Subido: <?= date('d/m/Y H:i', strtotime($file['uploaded_at'])) ?></span>
                        <span><i class="fas fa-database"></i> Tamaño: <?= format_file_size_display($file['file_size']) ?></span>
                        <span><i class="fas fa-tag"></i> Tipo: <?= htmlspecialchars($file['file_type']) ?></span>
                    </div>
                    <?php
                        // Asumimos que la columna 'synced' existe y es booleana (0 o 1)
                        $synced_status = isset($file['synced']) && $file['synced'] ? 'synced' : 'not-synced';
                        $synced_text = $synced_status === 'synced' ? 'Sincronizado con Nextcloud' : 'Pendiente de sincronización';
                        $synced_icon = $synced_status === 'synced' ? 'fas fa-check-circle' : 'fas fa-hourglass-half';
                    ?>
                    <div class="sync-status <?= $synced_status ?>">
                        <i class="<?= $synced_icon ?>"></i> <?= $synced_text ?>
                    </div>
                    <div class="file-actions">
                        <a href="<?= rtrim(BASE_URL, '/') ?>/app/downloads.php?file_id=<?= htmlspecialchars($file['file_id']) ?>&stored_name=<?= urlencode($file['stored_name']) ?>&original_name=<?= urlencode($file['original_name']) ?>" 
                           class="btn-action download" 
                           title="Descargar <?= htmlspecialchars($file['original_name']) ?>">
                           <i class="fas fa-download"></i> Descargar
                        </a>
                        <form action="<?= rtrim(BASE_URL, '/') ?>/app/delete_file.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este archivo? Esta acción no se puede deshacer.');">
                            <input type="hidden" name="file_id" value="<?= htmlspecialchars($file['file_id']) ?>">
                            <input type="hidden" name="stored_name" value="<?= htmlspecialchars($file['stored_name']) ?>">
                            <button type="submit" class="btn-action delete" title="Eliminar <?= htmlspecialchars($file['original_name']) ?>">
                                <i class="fas fa-trash-alt"></i> Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php elseif ($conn_error_message): ?>
             <div class="alert error">
                 <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($conn_error_message) ?>
             </div>
        <?php else: ?>
            <div class="alert info">
                <i class="fas fa-info-circle"></i> Aún no has subido ningún archivo. Usa el formulario de arriba para empezar.
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <p>© <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?>. Todos los derechos reservados.</p>
        <p><a href="#">Política de Privacidad</a> | <a href="#">Términos de Servicio</a></p>
    </footer>
</div>
</body>
</html>
