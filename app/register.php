<?php
// ====== INICIO: Líneas para mostrar errores (QUITAR EN PRODUCCIÓN) ======
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ====== FIN: Líneas para mostrar errores ======

require_once __DIR__ . '/../includes/config.php';
// Asumiendo que db_connection.php establece la conexión en $conn (mysqli)
// NOTA DE SEGURIDAD: El registro en archivo user_requests.txt es inseguro en producción.
// Lo ideal es manejar las solicitudes completamente en la base de datos.
require_once __DIR__ . '/../includes/db_connection.php';
session_start();

// Redirigir si ya está logueado (no debería registrarse si ya tiene sesión)
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['username'] === 'admin') {
        header("Location: admin_home.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $full_name  = trim($_POST['full_name'] ?? '');

    // Verificar que todos los campos están completos
    if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
        $error = 'Todos los campos son obligatorios.';
    } else {
        // Verificar longitud mínima de la contraseña (7 caracteres)
        if (strlen($password) < 7) {
            $error = 'La contraseña debe tener al menos 7 caracteres.';
        } else {
            // *** Advertencia de Seguridad: Esta sección de manejo de archivo es insegura y propensa a errores ***
            // Debería reemplazarse por almacenamiento en la base de datos.
            // Verificar si el nombre de usuario o email ya existe en el archivo de solicitudes
            $request_file = __DIR__ . '/../storage/user_requests.txt';
            $existing_requests = [];

            if (file_exists($request_file)) {
                 // FILE_IGNORE_NEW_LINES: No incluye el salto de línea final
                 // FILE_SKIP_EMPTY_LINES: Omite líneas vacías
                 // @file: Suprime advertencias si hay problemas de lectura (no ideal, mejor manejar el error explícitamente)
                $file_content = @file($request_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($file_content !== false) {
                    $existing_requests = $file_content;
                    foreach ($existing_requests as $line) {
                        $data = explode('|', $line);
                         // Asegurarse de que los índices existen antes de acceder
                        if (isset($data[1]) && $data[1] === $username) {
                            $error = 'El nombre de usuario ya tiene una solicitud pendiente.';
                            break;
                        }
                        if (isset($data[3]) && $data[3] === $email) {
                            $error = 'El correo electrónico ya tiene una solicitud pendiente.';
                            break;
                        }
                    }
                } else {
                    $error = 'Error interno al leer solicitudes pendientes.';
                    // Opcional: error_log("Error reading user requests file: " . $request_file);
                }
            }


            // Verificar si el usuario o correo ya existe en la base de datos
            if (empty($error)) {
                $conn = get_db_connection(); // Obtener conexión mysqli

                if ($conn) {
                    // ====== CORRECCIÓN: Usar $conn en lugar de $pdo ======
                    $stmt = $conn->prepare("SELECT username, email FROM users WHERE username = ? OR email = ?");
                    // ====== FIN CORRECCIÓN ======
                    if ($stmt) {
                        $stmt->bind_param("ss", $username, $email);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($user = $result->fetch_assoc()) {
                            if ($user['username'] === $username) {
                                $error = 'El nombre de usuario ya está registrado en el sistema.';
                            } else {
                                $error = 'El correo electrónico ya está registrado en el sistema.';
                            }
                        }
                        $stmt->close();
                    } else {
                         // Handle error preparing statement
                         $error = 'Error interno al verificar usuario/email en la base de datos.';
                         // Opcional: loggear $conn->error
                         // error_log("MySQL prepare error in register (user check): " . $conn->error);
                    }
                    $conn->close(); // Cerrar conexión
                } else {
                    $error = 'Error al conectar con la base de datos.';
                    // Opcional: loggear error de conexión
                    // error_log("Database connection error in register.");
                }
            }


            // Si no hay error, proceder a almacenar la solicitud en el archivo
            // *** Advertencia de Seguridad: Esto sigue siendo inseguro para producción ***
            if (empty($error)) {
                // Generar un user_id único para la solicitud (no el ID final de la DB)
                $user_id = uniqid('', true);
                $is_active = 0; // Por defecto inactivo
                $created_at = date('Y-m-d H:i:s');

                // Preparar la línea de datos para guardar
                $linea = implode('|', [
                    $user_id,
                    $username,
                    password_hash($password, PASSWORD_DEFAULT), // Guardar hash de contraseña
                    $email,
                    $full_name,
                    $is_active,
                    $created_at,
                    'pending' // Agregar estado 'pending'
                ]);

                // Guardar la solicitud en el archivo
                // Añadir un salto de línea al final
                // LOCK_EX: Bloquea el archivo para evitar escrituras concurrentes (mejora la seguridad del archivo, pero no resuelve los problemas fundamentales)
                if (@file_put_contents($request_file, $linea . PHP_EOL, FILE_APPEND | LOCK_EX) !== false) {
                    $success = 'Solicitud enviada correctamente. Espera aprobación del administrador.';
                    // Limpiar los campos del formulario después de un registro exitoso
                    $_POST = []; // Limpiar el array POST para que los campos salgan vacíos
                } else {
                    $error = 'Error al guardar la solicitud. Intenta más tarde o contacta al administrador.';
                    // Opcional: loggear error de escritura en archivo
                    // error_log("Error writing to user requests file: " . $request_file);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuario - TCG Portal Seguro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        /* Variables CSS comunes */
        :root {
            --primary-color: #007bff; /* Azul moderno y vibrante */
            --primary-hover-color: #0056b3;
            --secondary-color: #6c757d; /* Usado para texto muted */
            --success-color: #28a745; /* Verde */
            --success-bg-color: #d4edda; /* Fondo para alertas success */
            --error-color: #dc3545; /* Rojo para errores */
            --error-bg-color: #f8d7da; /* Fondo para alertas error */
            --info-color: #17a2b8; /* Cían para información */
            --info-bg-color: #d6efff; /* Fondo para alertas info */
            --light-color: #f8f9fa; /* Usado para texto en fondos oscuros */
            --dark-color: #343a40; /* Usado para títulos y texto principal */
            --text-color: #495057; /* Texto general */
            --text-muted-color: #6c757d; /* Texto secundario o descripciones */
            --body-bg: #eef2f7; /* Fondo general suave */
            --card-bg: #ffffff; /* Fondo para tarjetas/contenedores */
            --input-border-color: #ced4da;
            --input-focus-border-color: #80bdff;
            --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1); /* Sombra más pronunciada */
            --box-shadow-light: 0 0.125rem 0.25rem rgba(0,0,0,0.075); /* Sombra ligera para elementos */
            --border-radius: 0.375rem; /* 6px */
            --font-family-sans-serif: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        }

        body {
            font-family: var(--font-family-sans-serif);
            color: var(--text-color);
            line-height: 1.6;
            background-color: var(--body-bg); /* Fondo general */
            margin: 0;
            padding: 20px; /* Añadido padding para móviles */
            min-height: 100vh; /* Asegura que el body ocupe al menos el alto de la ventana */
            display: flex;
            justify-content: center;
            align-items: center;
            box-sizing: border-box; /* Asegura que el padding no afecte el tamaño */
        }

        /* Contenedor del formulario (estilo de tarjeta similar al login form) */
        .auth-form-container { /* Renombrado para claridad, pero se aplican estilos al div */
            background: var(--card-bg);
            padding: 3rem 2.5rem; /* Más padding similar al login */
            border-radius: calc(var(--border-radius) * 2); /* Bordes más redondeados */
            box-shadow: var(--box-shadow); /* Sombra similar al login */
            max-width: 500px; /* Un poco más ancho que el login form si es necesario */
            width: 100%;
            text-align: center; /* Centra el contenido por defecto */
        }

        .auth-form-container h2 {
            color: var(--dark-color); /* Color oscuro para el título */
            text-align: center;
            margin-bottom: 2rem; /* Más espacio debajo del título */
            font-size: 1.8rem;
            font-weight: 600;
        }

        /* Estilos de formulario (ajustados para coincidir con login) */
        form {
            text-align: left; /* Alinea el contenido del formulario a la izquierda */
        }

        .form-group {
            margin-bottom: 1.25rem; /* Espacio entre grupos de formulario */
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-muted-color); /* Color muted para las labels */
            font-size: 0.9rem;
        }
        .form-group label .icon {
            margin-right: 0.5rem;
            color: var(--primary-color); /* Iconos en color primario */
        }


        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="email"] {
            width: 100%;
            padding: 0.8rem 1rem; /* Padding similar a los inputs del login */
            border: 1px solid var(--input-border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            color: var(--text-color);
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            box-sizing: border-box; /* Importante para que el padding no aumente el tamaño */
            display: block; /* Asegura que ocupen su propia línea */
        }
        .form-group input[type="text"]::placeholder,
        .form-group input[type="password"]::placeholder,
        .form-group input[type="email"]::placeholder {
            color: #aaa;
            font-style: italic;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus,
        .form-group input[type="email"]:focus {
            outline: none;
            border-color: var(--input-focus-border-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); /* Sombra de foco tipo Bootstrap */
        }

        .password-hint {
            font-size: 0.8rem;
            color: var(--text-muted-color); /* Color muted */
            margin-top: -0.75rem; /* Ajuste de margen */
            margin-bottom: 1.25rem; /* Ajuste de margen */
            display: block; /* Asegura que esté en su propia línea */
        }


        /* Botón de Submit (estilo similar al btn-login) */
        .btn-submit { /* Usamos una clase específica para el botón de submit */
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.8rem 1rem; /* Padding del botón */
            width: 100%;
            border-radius: var(--border-radius);
            font-size: 1.1rem; /* Texto del botón más grande */
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out, transform 0.1s ease;
            display: flex; /* Para alinear icono y texto */
            align-items: center;
            justify-content: center;
            gap: 0.5rem; /* Espacio entre icono y texto */
             margin-top: 1.5rem; /* Margen superior */
        }

        .btn-submit:hover {
            background-color: var(--primary-hover-color);
            transform: translateY(-2px);
        }
        .btn-submit:active {
            transform: translateY(0);
        }


        /* Mensajes de estado (estilo copiado del dashboard/login) */
        .alert { /* Clase base para mensajes */
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex; /* Para alinear icono y texto */
            align-items: center;
            gap: 0.75rem; /* Espacio para el icono */
            text-align: left; /* Asegura que el texto se alinee a la izquierda */
        }

        .alert.success {
            background-color: var(--success-bg-color);
            color: var(--success-color);
            border: 1px solid var(--success-color);
            border-left: 5px solid var(--success-color); /* Borde izquierdo más grueso */
        }

        .alert.error {
            background-color: var(--error-bg-color);
            color: var(--error-color);
            border: 1px solid var(--error-color);
            border-left: 5px solid var(--error-color); /* Borde izquierdo más grueso */
        }
         .alert .fas { /* Estilo para el icono dentro de la alerta */
             font-size: 1.2rem;
         }


        /* Enlace de Login (estilo similar al registro-link del login) */
        .auth-link { /* Usamos una clase base para enlaces de autenticación */
            font-size: 0.95rem;
            color: var(--text-muted-color);
            text-align: center;
            margin-top: 1.5rem; /* Más margen superior */
        }

        .auth-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600; /* Enlace más destacado */
        }

        .auth-link a:hover {
            text-decoration: underline;
        }

        /* Footer (estilo copiado del login/dashboard) */
        footer {
             margin-top: 2rem; /* Margen superior */
             padding-top: 1.5rem;
             text-align: center;
             font-size: 0.85rem;
             color: var(--text-muted-color);
             border-top: 1px solid #e0e0e0; /* Borde superior */
        }


        /* Media Queries para responsividad */
        @media (max-width: 768px) {
            body {
                padding: 10px; /* Menos padding en móvil */
            }
            .auth-form-container {
                 padding: 2rem 1.5rem; /* Menos padding en móvil */
                 border-radius: var(--border-radius); /* Bordes menos redondeados */
            }
             .auth-form-container h2 {
                 font-size: 1.5rem;
                 margin-bottom: 1.5rem;
             }
             .form-group {
                 margin-bottom: 1rem;
             }
             .form-group input[type="text"],
             .form-group input[type="password"],
             .form-group input[type="email"] {
                 padding: 0.6rem 0.8rem; /* Ajuste de padding */
             }
             .password-hint {
                 margin-top: -0.6rem;
                 margin-bottom: 1rem;
             }
             .btn-submit {
                 padding: 0.7rem 1rem;
                 font-size: 1rem;
                 margin-top: 1rem;
             }
            .alert {
                padding: 0.75rem;
                gap: 0.5rem;
            }
             .alert .fas {
                 font-size: 1rem;
             }
             .auth-link {
                 margin-top: 1rem;
             }
        }

    </style>
</head>
<body>
    <div class="login-container auth-form-container">
        <h2>Registro de Usuario</h2>

        <?php if ($error): ?>
            <div class="alert error">
                <i class="fas fa-times-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif ($success): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="register.php">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user icon"></i> Usuario
                </label>
                <input type="text" name="username" id="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required placeholder="Tu nombre de usuario">
            </div>

            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock icon"></i> Contraseña
                </label>
                <input type="password" name="password" id="password" required placeholder="Define una contraseña segura">
                <div class="password-hint">Mínimo 7 caracteres</div>
            </div>

            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope icon"></i> Correo electrónico
                </label>
                <input type="email" name="email" id="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required placeholder="Tu correo electrónico">
            </div>

            <div class="form-group">
                <label for="full_name">
                    <i class="fas fa-id-card icon"></i> Nombre completo
                </label>
                <input type="text" name="full_name" id="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required placeholder="Tu nombre y apellido">
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-user-plus"></i> Enviar solicitud
            </button>
        </form>

        <div class="auth-link">
            ¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a>
        </div>

        <footer>
            <p>&copy; <?php echo date("Y"); ?> TCG Corp. Todos los derechos reservados.</p>
        </footer>
    </div>
</body>
</html>
