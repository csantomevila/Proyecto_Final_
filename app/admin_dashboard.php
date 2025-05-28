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

// Manejar búsqueda
$search = '';
$where = '';
$params = [];
$types = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $where = " WHERE (f.original_name LIKE ? OR u.username LIKE ?)";
    $params = ["%$search%", "%$search%"];
    $types = 'ss';
}

$query = "
    SELECT f.file_id, f.user_id, u.username, f.original_name, f.file_size, f.file_type, f.uploaded_at
    FROM uploaded_files f
    JOIN users u ON f.user_id = u.user_id
    $where
    ORDER BY f.uploaded_at DESC
";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Ficheros</title>
    <style>
        body.admin-dashboard {
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

        .header-actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .search-box {
            display: flex;
            gap: 0.5rem;
        }

        .search-input {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            min-width: 250px;
        }

        .search-btn {
            padding: 0.5rem 1rem;
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .search-btn:hover {
            background-color: #3a56d4;
        }

        .back-btn {
            padding: 0.5rem 1rem;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .back-btn:hover {
            background-color: #5a6268;
        }

        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background-color: #ffffff;
        }

        .file-table th, .file-table td {
            padding: 1rem;
            border: 1px solid #ddd;
            text-align: left;
        }

        .file-table th {
            background-color: #f0f0f0;
            color: #4361ee;
        }

        .file-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .file-table td {
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

        .no-results {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .header-actions {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
            
            .search-input {
                flex-grow: 1;
            }
        }
    </style>
</head>
<body class="admin-dashboard">
    <div class="container">
        <h1>Administración de Ficheros</h1>

        <div class="header-actions">
            <form method="get" class="search-box">
                <input type="text" name="search" class="search-input" placeholder="Buscar por nombre o usuario..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn">Buscar</button>
            </form>
            <a href="admin_home.php" class="back-btn">← Volver al Panel de Admin</a>
        </div>

        <table class="file-table">
            <thead>
                <tr>
                    <th>ID_Fichero</th>
                    <th>Usuario</th>
                    <th>Nombre Original</th>
                    <th>Tamaño</th>
                    <th>Tipo</th>
                    <th>Fecha de Subida</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($file = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($file['file_id']) ?></td>
                            <td><?= htmlspecialchars($file['username']) ?></td>
                            <td><?= htmlspecialchars($file['original_name']) ?></td>
                            <td><?= round($file['file_size'] / 1024, 2) ?> KB</td>
                            <td><?= htmlspecialchars($file['file_type']) ?></td>
                            <td><?= htmlspecialchars($file['uploaded_at']) ?></td>
                            <td>
                                <form method="post" action="delete_file.php" onsubmit="return confirm('¿Estás seguro de eliminar este fichero?');">
                                    <input type="hidden" name="file_id" value="<?= htmlspecialchars($file['file_id']) ?>">
                                    <button type="submit" class="delete-btn">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-results">No se encontraron ficheros</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
