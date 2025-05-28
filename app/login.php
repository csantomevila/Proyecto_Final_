<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';
session_start();

// Redirigir si ya está logueado
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['username'] === 'admin') {
        header("Location: admin_home.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']); // NUNCA USAR CONTRASEÑAS EN TEXTO PLANO EN PRODUCCIÓN

    if ($username === '' || $password === '') {
        $error = 'Todos los campos son obligatorios.';
    } else {
        $conn = get_db_connection();
        if ($conn) {
            $stmt = $conn->prepare("SELECT user_id, username, password FROM users WHERE username = ? AND is_active = 1");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($user = $result->fetch_assoc()) {
                    if ($password === $user['password']) {
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];

                        // Registrar último login
                        $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                        if($updateStmt) {
                            $updateStmt->bind_param("i", $user['user_id']);
                            $updateStmt->execute();
                            $updateStmt->close();
                        }

                        // NUEVO: Registro de acceso
                        $log_dir = __DIR__ . '/../logs';
                        if (!file_exists($log_dir)) {
                            mkdir($log_dir, 0755, true);
                        }
                        $log_file = $log_dir . '/access_log.txt';
                        $timestamp = date('Y-m-d H:i:s');
                        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
                        $hostname = gethostbyaddr($ip);
                        $log_entry = "$timestamp | Usuario: {$user['username']} | IP: $ip | Hostname: $hostname\n";
                        file_put_contents($log_file, $log_entry, FILE_APPEND);

                        if ($user['username'] === 'admin') {
                            header("Location: admin_home.php");
                        } else {
                            header("Location: dashboard.php");
                        }
                        exit;
                    } else {
                        $error = 'Usuario o contraseña incorrecta.';
                    }
                } else {
                    $error = 'Usuario o contraseña incorrecta.';
                }
                $stmt->close();
            } else {
                $error = "Error al preparar la consulta.";
            }
            $conn->close();
        } else {
            $error = "Error de conexión a la base de datos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - TCG Portal Seguro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        /* Estilos Generales (si no los tienes ya) */
        :root {
            --primary-color: #007bff; /* Azul moderno y vibrante */
            --primary-hover-color: #0056b3;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --error-color: #dc3545; /* Rojo para errores */
            --error-bg-color: #f8d7da;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --text-color: #495057;
            --text-muted-color: #6c757d;
            --body-bg: #eef2f7; /* Fondo general suave */
            --card-bg: #ffffff;
            --input-border-color: #ced4da;
            --input-focus-border-color: #80bdff;
            --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            --box-shadow-light: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            --border-radius: 0.375rem; /* 6px */
            --font-family-sans-serif: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        }

        body {
            font-family: var(--font-family-sans-serif);
            color: var(--text-color);
            line-height: 1.6;
            background-color: var(--body-bg);
            margin: 0;
            padding: 0;
        }

        /* Estilos específicos para la Página de Login */
        .login-page-body {
            background-image: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }

        .login-container-wrapper {
            display: flex;
            max-width: 1000px; /* Ancho máximo del contenedor principal */
            width: 100%;
            background-color: var(--card-bg);
            border-radius: calc(var(--border-radius) * 2); /* 12px */
            box-shadow: var(--box-shadow);
            overflow: hidden; /* Para que los bordes redondeados afecten a los hijos */
        }

        .login-branding {
            background-image: linear-gradient(to right top, #051937, #004d7a, #008793, #00bf72, #a8eb12);
            color: var(--light-color);
            padding: 3rem 2.5rem; /* Más padding */
            flex-basis: 45%; /* Porcentaje del ancho para la sección de branding */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center; /* Centrar contenido verticalmente */
            text-align: center;
        }

        .login-branding .login-logo {
            max-width: 150px;
            margin-bottom: 1.5rem;
        }

        .login-branding h1 {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .login-branding .portal-name {
            font-weight: 300; /* Nombre del portal más ligero */
        }

        .login-branding .tagline {
            font-size: 1rem;
            font-weight: 300;
            opacity: 0.9;
        }


        .login-form-container {
            padding: 3rem 2.5rem; /* Más padding */
            flex-basis: 55%; /* Porcentaje del ancho para el formulario */
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-form-container h2 {
            color: var(--dark-color);
            text-align: center;
            margin-bottom: 2rem; /* Más espacio */
            font-size: 1.8rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.25rem; /* Espacio entre grupos de formulario */
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-muted-color);
            font-size: 0.9rem;
        }

        .form-group label .icon {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }

        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 0.8rem 1rem; /* Padding más generoso */
            padding-left: 1rem; /* Ajustado, ya que el icono está en el label */
            border: 1px solid var(--input-border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            color: var(--text-color);
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            box-sizing: border-box; /* Importante para que el padding no aumente el tamaño */
        }
        .form-group input[type="text"]::placeholder,
        .form-group input[type="password"]::placeholder {
            color: #aaa;
            font-style: italic;
        }


        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus {
            outline: none;
            border-color: var(--input-focus-border-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); /* Sombra de foco tipo Bootstrap */
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .form-options .remember-me {
            display: flex;
            align-items: center;
            color: var(--text-muted-color);
            font-weight: 400;
        }
        .form-options .remember-me input[type="checkbox"] {
            margin-right: 0.5rem;
            accent-color: var(--primary-color); /* Colorea el checkbox */
        }

        .form-options .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .form-options .forgot-password:hover {
            text-decoration: underline;
        }


        .btn-login {
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem; /* Espacio entre icono y texto */
        }

        .btn-login:hover {
            background-color: var(--primary-hover-color);
            transform: translateY(-2px);
        }
        .btn-login:active {
            transform: translateY(0);
        }

        .error-msg {
            background-color: var(--error-bg-color);
            color: var(--error-color);
            border: 1px solid var(--error-color);
            border-left: 5px solid var(--error-color); /* Borde izquierdo más grueso */
            padding: 1rem; /* Más padding */
            margin-bottom: 1.5rem; /* Más margen */
            border-radius: var(--border-radius);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem; /* Espacio para el icono */
        }
        .error-msg .fas { /* Estilo para el icono dentro del mensaje de error */
            font-size: 1.2rem;
        }

        .register-link {
            font-size: 0.95rem;
            color: var(--text-muted-color);
            text-align: center;
            margin-top: 1.5rem; /* Más margen superior */
        }

        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600; /* Enlace de registro más destacado */
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.85rem;
            color: var(--text-muted-color);
        }

        /* Media Queries para responsividad */
        @media (max-width: 768px) {
            .login-container-wrapper {
                flex-direction: column; /* Apila las secciones en pantallas pequeñas */
                margin: 20px; /* Margen para que no pegue a los bordes */
            }
            .login-branding {
                flex-basis: auto; /* Ancho automático */
                padding: 2rem 1.5rem; /* Menos padding en móvil */
                min-height: 200px; /* Altura mínima para que no se colapse */
            }
            .login-branding h1 {
                font-size: 1.8rem;
            }
            .login-branding .tagline {
                font-size: 0.9rem;
            }
            .login-form-container {
                flex-basis: auto; /* Ancho automático */
                padding: 2rem 1.5rem; /* Menos padding en móvil */
            }
            .login-form-container h2 {
                font-size: 1.5rem;
                margin-bottom: 1.5rem;
            }
            .form-options {
                flex-direction: column;
                gap: 0.75rem;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body class="login-page-body">
    <div class="login-container-wrapper">
        <div class="login-branding">
            <h1>Bienvenido a <br><span class="portal-name">TCG Portal Seguro</span></h1>
            <p class="tagline">Tu puerta de acceso a la gestión segura de archivos.</p>
        </div>
        <div class="login-form-container">
            <h2>Iniciar Sesión</h2>
            <?php if ($error): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form method="post" action="login.php" id="loginForm">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user icon"></i> Usuario
                    </label>
                    <input type="text" name="username" id="username" required placeholder="Tu nombre de usuario">
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock icon"></i> Contraseña
                    </label>
                    <input type="password" name="password" id="password" required placeholder="Tu contraseña">
                </div>
                
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember"> Recordarme
                    </label>
                    <a href="recover.php" class="forgot-password">¿Olvidaste tu contraseña?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Ingresar
                </button>

                <div class="register-link">
                    ¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a>
                </div>
            </form>
             <footer class="login-footer">
                <p>&copy; <?php echo date("Y"); ?> TCG Corp. Todos los derechos reservados.</p>
            </footer>
        </div>
    </div>
    </body>
</html>
