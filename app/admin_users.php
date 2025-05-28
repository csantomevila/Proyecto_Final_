<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';

// Verifica que el usuario esté logueado y sea admin
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$conn = get_db_connection();

// Añadir un nuevo usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    
    if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
        $error_message = "Todos los campos son obligatorios.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
        $stmt->bind_param("ssss", $username, $password, $email, $full_name);
        
        if ($stmt->execute()) {
            $success_message = "Usuario añadido con éxito.";
        } else {
            $error_message = "Error al añadir el usuario.";
        }

        $stmt->close();
    }
}

// Eliminar un usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    if ($user_id != 1) { // No se puede eliminar al admin
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Usuario eliminado con éxito.";
        } else {
            $error_message = "Error al eliminar el usuario.";
        }
        
        $stmt->close();
    } else {
        $error_message = "No puedes eliminar al administrador.";
    }
}

// Obtener lista de usuarios
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id != 1"); // No mostrar al admin
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administración de Usuarios</title>
    <style>
        /* Estilos generales para la página de administración */
        body.admin-users {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #4361ee;
            font-size: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .back-btn-container {
            text-align: right;
            margin-bottom: 1rem;
        }

        .back-btn {
            padding: 0.5rem 1rem;
            background-color: #4361ee;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .back-btn:hover {
            background-color: #3a56d4;
        }

        /* Formulario de añadir usuario */
        .add-user-form {
            margin-bottom: 2rem;
        }

        .add-user-form input {
            padding: 0.75rem;
            margin: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
            width: calc(33% - 1.5rem);
        }

        .add-user-form button {
            padding: 0.75rem 1rem;
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .add-user-form button:hover {
            background-color: #3a56d4;
        }

        /* Tabla de usuarios */
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background-color: #ffffff;
        }

        .user-table th, .user-table td {
            padding: 1rem;
            border: 1px solid #ddd;
            text-align: left;
        }

        .user-table th {
            background-color: #f0f0f0;
            color: #4361ee;
        }

        .user-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .user-table td {
            font-size: 0.9rem;
            color: #333;
        }

        .delete-btn {
            background-color: #e63946;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .delete-btn:hover {
            background-color: #d62828;
        }
    </style>
</head>
<body class="admin-users">
    <div class="container">
        <h1>Administración de Usuarios</h1>

        <!-- Botón para volver al panel de administración -->
        <div class="back-btn-container">
            <a href="admin_home.php" class="back-btn">← Volver al Panel de Admin</a>
        </div>

        <!-- Mensajes de error o éxito -->
        <?php if (isset($error_message)): ?>
            <div class="error-message" style="color: red;"><?= htmlspecialchars($error_message) ?></div>
        <?php elseif (isset($success_message)): ?>
            <div class="success-message" style="color: green;"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <!-- Formulario de añadir nuevo usuario -->
        <div class="add-user-form">
            <h2>Añadir Nuevo Usuario</h2>
            <form method="post" action="admin_users.php">
                <input type="text" name="username" placeholder="Nombre de Usuario" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <input type="email" name="email" placeholder="Correo Electrónico" required>
                <input type="text" name="full_name" placeholder="Nombre Completo" required>
                <button type="submit" name="add_user">Añadir Usuario</button>
            </form>
        </div>

        <!-- Tabla de usuarios -->
        <h2>Lista de Usuarios</h2>
        <table class="user-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Correo Electrónico</th>
                    <th>Nombre Completo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['user_id']) ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                        <td>
                            <form method="post" action="admin_users.php" onsubmit="return confirm('¿Estás seguro de eliminar este usuario?');">
                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                <button type="submit" name="delete_user" class="delete-btn">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
