<?php
// ====== INICIO: Líneas para mostrar errores (QUITAR EN PRODUCCIÓN) ======
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ====== FIN: Líneas para mostrar errores (QUITAR EN PRODUCCIÓN) ======

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';

// Verificar que el usuario esté logueado y sea admin
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Inicializar mensajes de estado (limpiar mensajes de sesión anteriores)
unset($_SESSION['delete_message']);
unset($_SESSION['delete_error']);

// Procesar solo si es POST y se recibió file_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_id'])) {
    // Usar filter_input para obtener y validar el ID del archivo de forma segura
    $file_id = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);

    if ($file_id === false || $file_id <= 0) {
        $_SESSION['delete_error'] = 'ID de archivo inválido recibido.';
    } else {
        $conn = get_db_connection();

        if (!$conn) { // Verificar si la conexión falló
            $_SESSION['delete_error'] = 'Error de conexión a la base de datos.';
        } else {
            try {
                // Preparar la consulta para eliminar el registro por file_id
                $delete_stmt = $conn->prepare("DELETE FROM uploaded_files WHERE file_id = ?");

                if (!$delete_stmt) {
                    // Error en la preparación de la consulta
                    throw new Exception("Error al preparar la consulta de eliminación: " . $conn->error);
                }

                // Vincular el parámetro y ejecutar la consulta
                $delete_stmt->bind_param("i", $file_id);

                if (!$delete_stmt->execute()) {
                    // Error al ejecutar la consulta
                     throw new Exception("Error al eliminar el registro de la base de datos: " . $delete_stmt->error);
                }

                // Verificar cuántas filas fueron afectadas
                $affected_rows = $conn->affected_rows;
                $delete_stmt->close(); // Cerrar el statement

                if ($affected_rows > 0) {
                    // Éxito: se eliminó al menos una fila
                    $_SESSION['delete_message'] = "Registro de archivo con ID " . $file_id . " eliminado de la base de datos.";
                } else {
                    // No se afectó ninguna fila: el ID no existía
                    $_SESSION['delete_error'] = "No se encontró el registro de archivo con ID " . $file_id . " en la base de datos.";
                }

            } catch (Exception $e) {
                // Capturar cualquier excepción durante la preparación o ejecución
                $_SESSION['delete_error'] = "Ocurrió un error al intentar eliminar el registro: " . $e->getMessage();
                 // Opcional: Registrar el error en el log del servidor
                 // error_log("Error DB al eliminar registro (ID " . $file_id . "): " . $e->getMessage());
            } finally {
                // Cerrar la conexión a la base de datos si está abierta
                if ($conn) {
                    $conn->close();
                }
            }
        }
    } // Fin if $file_id valido
} else { // Fin if $_SERVER['REQUEST_METHOD'] === 'POST'
    $_SESSION['delete_error'] = 'Solicitud de eliminación inválida.';
}

// Redirigir de vuelta al dashboard de administración
header("Location: admin_dashboard.php");
exit; // Asegurar que el script termina después de la redirección
?>
