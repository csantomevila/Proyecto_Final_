$autoloaderPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloaderPath)) {
    require_once $autoloaderPath;
} else {
    die("Error crítico: No se encuentra el archivo autoload de Composer en '{$autoloaderPath}'. ...");
}
