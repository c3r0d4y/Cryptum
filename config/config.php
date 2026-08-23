<?php
/*
 * Archivo: config/config.php
 * Autor:   C3r0d4y
 *
 * Constantes globales de la aplicación.
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    define('APP_NAME',    'Cryptum');
    // Versión de la app: también se usa para invalidar la caché de CSS/JS
    // (los assets se sirven con ?v=APP_VERSION; al cambiarla el navegador recarga).
    define('APP_VERSION', '1.4.4');
    // Para producción en raíz del dominio usar APP_BASE_URL="" en el entorno.
    // Se usa !== false para distinguir entre "no definida" (false) y "definida vacía" ("").
    $envBase = getenv('APP_BASE_URL');
    define('BASE_URL', rtrim($envBase !== false ? (string)$envBase : '/cryptum', '/'));
    define('APP_ROOT',    dirname(__DIR__));
    define('VAULT_PATH',  APP_ROOT . '/storage/vault/');
    define('MAX_FILE_MB', 100);
    define('EXPIRY_SEC',  300);
    define('MAX_VAULTS',  500);
    // Límite de vaults activos por visitante (por hash de IP) para evitar
    // que un solo cliente llene la bóveda y bloquee el servicio a los demás.
    define('MAX_VAULTS_PER_IP', 20);
    // La lista de dispositivos USB expone información del hardware del servidor.
    // Por defecto solo se permite desde la propia máquina (localhost).
    define('USB_API_LOCAL_ONLY', getenv('CRYPTUM_USB_REMOTE') === false);
}
