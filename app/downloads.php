<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = get_db_connection();
$stmt = $conn->prepare("SELECT * FROM uploaded_files WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Descargar Archivos</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<div class="container">
    <header>
        <h1>Archivos Disponibles para Descargar</h1>
        <div class="user-info">
            <span>Usuario: <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="dashboard.php" class="logout-btn">⬅️ Volver al Dashboard</a>
        </div>
    </header>

    <?php if ($result->num_rows === 0): ?>
        <p>No hay archivos disponibles.</p>
    <?php else: ?>
        <ul style="list-style: none; padding-left: 0;">
            <?php while ($row = $result->fetch_assoc()): ?>
                <li style="margin-bottom: 1rem; background: var(--card-bg); padding: 1rem; border-radius: 8px;">
                    <strong><?= htmlspecialchars($row['original_name']) ?></strong><br>
                    <small>Subido el <?= date('d/m/Y H:i', strtotime($row['uploaded_at'])) ?></small><br>
                    <a href="../uploads/<?= urlencode($row['stored_name']) ?>" 
                       download="<?= htmlspecialchars($row['original_name']) ?>" 
                       style="color: var(--primary-color); font-weight: 600;">
                        ⬇️ Descargar
                    </a>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php endif; ?>
</div>
</body>
</html>
