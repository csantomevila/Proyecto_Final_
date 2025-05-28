#!/usr/bin/env php
<?php
require_once __DIR__.'/../includes/db_connection.php';
require_once __DIR__.'/../includes/nextcloud_sync.php';
require_once __DIR__.'/../includes/logger.php';

// Configuración de entorno
$upload_dir = getenv('UPLOAD_DIR');
$max_retries = 3;

try {
    $db = new Database();
    $sync = new NextcloudSync();
    $logger = new Logger();
    
    // Consulta archivos pendientes con reintentos < máximo
    $pending_files = $db->query(
        "SELECT * FROM uploaded_files 
        WHERE sync_status = 'pending' AND sync_attempts < ?",
        [$max_retries]
    );

    foreach ($pending_files as $file) {
        $local_path = $upload_dir.'/'.$file['stored_name'];
        $remote_path = '/tcg_uploads/'.$file['user_id'].'/'.$file['stored_name'];
        
        $logger->log("Iniciando sync: ".$file['original_name']);
        
        if ($sync->upload($local_path, $remote_path)) {
            $db->query(
                "UPDATE uploaded_files 
                SET sync_status = 'completed', 
                    sync_time = NOW() 
                WHERE id = ?",
                [$file['id']]
            );
            $logger->log("✓ Sync completado: ".$file['original_name']);
        } else {
            $db->query(
                "UPDATE uploaded_files 
                SET sync_attempts = sync_attempts + 1,
                    last_error = ? 
                WHERE id = ?",
                [$sync->getLastError(), $file['id']]
            );
            $logger->log("✗ Fallo sync (intento ".($file['sync_attempts']+1)."): ".$file['original_name']);
        }
    }
} catch (Exception $e) {
    $logger->log("CRITICAL: ".$e->getMessage(), 'ERROR');
    exit(1);
}
?>
