<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/db_connection.php';

function sync_file_to_cloud($filePath, $fileName) {
    $remoteUrl = NEXTCLOUD_URL . NEXTCLOUD_REMOTE_DIR . '/' . $fileName;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remoteUrl);
    curl_setopt($ch, CURLOPT_USERPWD, NEXTCLOUD_USER . ":" . NEXTCLOUD_PASS);
    curl_setopt($ch, CURLOPT_PUT, 1);
    curl_setopt($ch, CURLOPT_INFILE, fopen($filePath, 'r'));
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filePath));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 201;
}
