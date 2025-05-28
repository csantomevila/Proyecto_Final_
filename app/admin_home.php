<?php
// ====== INICIO: Líneas para mostrar errores (QUITAR EN PRODUCCIÓN) ======
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ====== FIN: Líneas para mostrar errores ======

require_once __DIR__ . '/../includes/config.php';
// No se requiere db_connection.php directamente aquí, pero si config.php lo incluye, está bien.
session_start();

// Redirigir si no está logueado o no es admin
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Puedes incluir db_connection.php si necesitas acceder a la base de datos en esta página
// require_once __DIR__ . '/../includes/db_connection.php';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio Administrador - TCG Portal Seguro</title>
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

        /* Panel (estilo de tarjeta) */
        .admin-panel { /* Renombramos la clase para mayor claridad */
            background: var(--card-bg); /* Fondo de tarjeta */
            padding: 3rem; /* Padding generoso */
            border-radius: calc(var(--border-radius) * 2); /* Bordes más redondeados */
            box-shadow: var(--box-shadow); /* Sombra */
            text-align: center; /* Centra el contenido */
            max-width: 450px; /* Ancho máximo */
            width: 100%;
        }

        .admin-panel h2 {
            margin-bottom: 2rem;
            color: var(--dark-color); /* Color oscuro para el título */
            font-size: 2rem;
            font-weight: 700;
        }

        /* Estilos para los botones/enlaces de administración */
         .btn { /* Clase base para botones */
            display: inline-flex; /* Permite poner iconos */
            align-items: center;
            justify-content: center;
            gap: 0.75rem; /* Espacio entre icono y texto */
            width: 100%; /* Ancho completo */
            padding: 1rem; /* Padding generoso */
            margin-bottom: 1rem; /* Espacio debajo */
            background-color: var(--primary-color); /* Color primario por defecto */
            color: white;
            text-decoration: none;
            font-weight: 600; /* Peso de fuente consistente */
            border-radius: var(--border-radius); /* Bordes redondeados */
            transition: background-color 0.2s ease-in-out, transform 0.1s ease; /* Transiciones */
            border: none; /* Asegura que no haya borde */
            cursor: pointer; /* Indica que es clickable */
            font-size: 1.1rem; /* Tamaño de fuente */
        }

         .btn:hover {
             background-color: var(--primary-hover-color);
             transform: translateY(-2px);
         }
         .btn:active {
             transform: translateY(0);
         }

        /* Botón/Enlace de Cerrar Sesión (estilo consistente con dashboard) */
        .logout-btn { /* Usamos la misma clase del dashboard */
             display: inline-flex; /* Para icono */
             align-items: center; /* Para icono */
             gap: 0.5rem; /* Para icono */
            background-color: var(--secondary-color); /* Color secundario */
            color: var(--light-color); /* Texto claro */
            padding: 0.6rem 1.2rem; /* Ajustamos padding */
            border-radius: var(--border-radius); /* Bordes redondeados */
            text-decoration: none;
            font-size: 0.95rem; /* Tamaño de fuente */
            font-weight: 500;
            transition: background-color 0.2s ease-in-out;
            margin-top: 1.5rem; /* Espacio superior */
        }

        .logout-btn:hover {
             background-color: #5a6268; /* Un tono más oscuro del secundario */
        }


        /* Footer (estilo consistente) */
        footer {
             margin-top: 3rem; /* Margen superior */
             padding-top: 1.5rem;
             text-align: center;
             font-size: 0.85rem;
             color: var(--text-muted-color); /* Texto muted */
             border-top: 1px solid #e0e0e0; /* Borde superior */
        }
         .admin-panel footer { /* Ajuste para el footer dentro del panel */
             margin-top: 2rem; /* Menos margen si está dentro del panel */
             padding-top: 1rem;
         }

        /* Media Queries para responsividad */
        @media (max-width: 768px) {
             body {
                 padding: 10px; /* Menos padding en móvil */
             }
            .admin-panel {
                 padding: 2rem; /* Menos padding en móvil */
                 border-radius: var(--border-radius); /* Bordes menos redondeados */
            }
            .admin-panel h2 {
                 font-size: 1.8rem;
                 margin-bottom: 1.5rem;
            }
            .btn {
                 padding: 0.8rem; /* Ajuste de padding en móvil */
                 font-size: 1rem;
                 gap: 0.5rem;
            }
            .logout-btn {
                 margin-top: 1rem;
                 padding: 0.5rem 1rem;
                 font-size: 0.9rem;
            }
            .admin-panel footer {
                 margin-top: 1.5rem;
                 padding-top: 0.75rem;
            }
        }
    </style>
</head>
<body>
        <div class="panel admin-panel">
        <h2>Bienvenido, Administrador</h2>
                <a href="admin_dashboard.php" class="btn"><i class="fas fa-file-alt"></i> Administración de Ficheros</a>
        <a href="admin_users.php" class="btn"><i class="fas fa-users-cog"></i> Administración de Usuarios</a>
        <a href="dashboard.php" class="btn"><i class="fas fa-tachometer-alt"></i> Dashboard Normal</a>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>

        <footer>
            <p>&copy; <?php echo date("Y"); ?> TCG Corp. Todos los derechos reservados.</p>
        </footer>
    </div>
</body>
</html>
