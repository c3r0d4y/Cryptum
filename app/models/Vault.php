<?php
/*
 * Archivo: app/models/Vault.php
 * Autor:   C3r0d4y
 *
 * Gestiona el almacenamiento temporal de blobs cifrados.
 * El servidor nunca recibe contraseñas ni archivos en claro:
 * solo guarda el blob ya cifrado por el cliente (Web Crypto API).
 *
 * Ciclo de vida de un vault:
 *   1. Cliente cifra el archivo en el navegador.
 *   2. Sube el blob cifrado → se asigna un token de 128 bits.
 *   3. El token genera un enlace de descarga única.
 *   4. Al primer GET del enlace el blob se sirve y se elimina.
 *   5. Cualquier vault que supere EXPIRY_SEC también se elimina.
 */

declare(strict_types=1);

final class Vault
{
    // ── Subida ────────────────────────────────────────────────────────────────

    public function store(): array
    {
        $this->cleanup();

        $activos = count(glob(VAULT_PATH . '*.enc') ?: []);
        if ($activos >= MAX_VAULTS) {
            throw new \OverflowException('Capacidad temporal agotada. Intenta en unos minutos.');
        }

        // Hash parcial de IP para auditoría mínima sin almacenar dato personal
        $origin = substr(hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), 0, 8);

        // Cuota por visitante: evita que un solo cliente llene la bóveda
        // y deje sin servicio a los demás (denegación de servicio trivial).
        $propios = 0;
        foreach (glob(VAULT_PATH . '*.meta') ?: [] as $mf) {
            $m = json_decode((string) file_get_contents($mf), true) ?: [];
            if (($m['origin'] ?? '') === $origin) {
                $propios++;
            }
        }
        if ($propios >= MAX_VAULTS_PER_IP) {
            throw new \OverflowException('Has alcanzado el límite de enlaces activos. Espera a que expiren los anteriores.');
        }

        $maxBytes = MAX_FILE_MB * 1024 * 1024;

        // El tamaño declarado se valida antes de leer un solo byte del cuerpo
        $declarado = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($declarado > $maxBytes) {
            throw new \LengthException('Archivo demasiado grande (máximo ' . MAX_FILE_MB . ' MB).');
        }

        if (!is_dir(VAULT_PATH)) {
            mkdir(VAULT_PATH, 0750, true);
        }

        $token   = bin2hex(random_bytes(16));
        $encFile = VAULT_PATH . $token . '.enc';

        // El cuerpo llega como binario crudo (application/octet-stream) y se
        // copia al disco por bloques: un archivo de 100 MB ya no necesita
        // cargarse completo en memoria ni decodificarse de base64.
        $in  = fopen('php://input', 'rb');
        $out = $in !== false ? fopen($encFile, 'wb') : false;
        if ($in === false || $out === false) {
            throw new \RuntimeException('Error al almacenar el archivo en bóveda.');
        }
        chmod($encFile, 0600);

        $escrito = 0;
        $firma   = '';
        while (!feof($in)) {
            $chunk = fread($in, 1048576);
            if ($chunk === false || $chunk === '') {
                break;
            }
            if (strlen($firma) < 4) {
                $firma .= substr($chunk, 0, 4 - strlen($firma));
            }
            $escrito += strlen($chunk);
            if ($escrito > $maxBytes) {
                fclose($in);
                fclose($out);
                $this->shred($encFile);
                throw new \LengthException('Archivo demasiado grande (máximo ' . MAX_FILE_MB . ' MB).');
            }
            fwrite($out, $chunk);
        }
        fclose($in);
        fclose($out);

        if ($escrito < 50) {
            $this->shred($encFile);
            throw new \InvalidArgumentException('Datos de archivo inválidos o corruptos.');
        }

        // Verificar firma binaria del formato Cryptum
        if ($firma !== 'C3VL') {
            $this->shred($encFile);
            throw new \UnexpectedValueException('El archivo no tiene formato Cryptum válido.');
        }

        $meta = [
            'created'    => time(),
            'downloaded' => false,
            'size'       => $escrito,
            'origin'     => $origin,
        ];
        file_put_contents(VAULT_PATH . $token . '.meta', json_encode($meta));
        chmod(VAULT_PATH . $token . '.meta', 0600);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url    = $scheme . '://' . $host . BASE_URL . '/?d=' . $token;

        return [
            'ok'         => true,
            'token'      => $token,
            'url'        => $url,
            'expires_at' => time() + EXPIRY_SEC,
            'expiry_sec' => EXPIRY_SEC,
            'size'       => $escrito,
        ];
    }

    // ── Descarga (única) ──────────────────────────────────────────────────────

    public function serve(string $token): void
    {
        $this->validateToken($token);

        $encFile  = VAULT_PATH . $token . '.enc';
        $metaFile = VAULT_PATH . $token . '.meta';
        $reclamo  = VAULT_PATH . $token . '.serving';

        // Reclamo atómico: rename() solo puede ganarlo UNA petición.
        // Antes se marcaba "downloaded" en el meta, pero dos descargas
        // simultáneas podían leer el meta a la vez y ambas servir el archivo.
        if (!@rename($encFile, $reclamo)) {
            throw new \RuntimeException('Enlace inválido, ya utilizado o expirado.', 404);
        }

        $meta = json_decode((string) @file_get_contents($metaFile), true);
        if (!is_array($meta)) {
            $this->shred($reclamo);
            @unlink($metaFile);
            throw new \RuntimeException('Metadatos corruptos.', 500);
        }

        if ((time() - ($meta['created'] ?? 0)) > EXPIRY_SEC) {
            $this->shred($reclamo);
            @unlink($metaFile);
            throw new \RuntimeException('El enlace ha expirado (límite: 5 minutos).', 410);
        }

        // El meta se conserva marcado como descargado para que la pantalla de
        // estado pueda decir "enlace ya utilizado"; la limpieza lo borra después.
        $meta['downloaded']    = true;
        $meta['downloaded_at'] = time();
        file_put_contents($metaFile, json_encode($meta));

        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($reclamo));
        header('Content-Disposition: attachment; filename="vault.c3v"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Cryptum-Token: consumed');

        readfile($reclamo);
        $this->shred($reclamo);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        exit;
    }

    // ── Estado del vault ──────────────────────────────────────────────────────

    public function status(string $token): array
    {
        $this->validateToken($token);

        $metaFile = VAULT_PATH . $token . '.meta';
        if (!file_exists($metaFile)) {
            return ['valid' => false, 'reason' => 'not_found'];
        }

        $meta = json_decode((string) file_get_contents($metaFile), true);

        // Cada consulta de estado también limpia vaults vencidos: así la
        // limpieza no depende únicamente de que alguien suba un archivo.
        // (El meta propio ya se leyó arriba, por eso el orden importa.)
        $this->cleanup();
        $elapsed   = time() - ($meta['created'] ?? 0);
        $remaining = EXPIRY_SEC - $elapsed;

        if ($remaining <= 0) {
            $this->delete($token);
            return ['valid' => false, 'reason' => 'expired'];
        }

        if (!empty($meta['downloaded'])) {
            $this->delete($token);
            return ['valid' => false, 'reason' => 'used'];
        }

        return [
            'valid'     => true,
            'remaining' => $remaining,
            'size'      => $meta['size'] ?? 0,
        ];
    }

    // ── Dispositivos USB ──────────────────────────────────────────────────────

    public function listUsb(): array
    {
        $raw = shell_exec('lsblk -J -o NAME,TRAN,SIZE,LABEL,MOUNTPOINT,TYPE,VENDOR,MODEL,FSTYPE,RM,HOTPLUG 2>/dev/null');

        if (!$raw) {
            return ['ok' => true, 'devices' => [], 'extra_mounts' => [], 'note' => 'lsblk no disponible en este sistema'];
        }

        $data    = json_decode($raw, true);
        $devices = [];

        foreach ($data['blockdevices'] ?? [] as $dev) {
            $isUSB = ($dev['tran'] === 'usb');
            $isRM  = ($dev['rm']      === true || $dev['rm']      === '1');
            $isHot = ($dev['hotplug'] === true || $dev['hotplug'] === '1');

            if (!$isUSB && !$isRM && !$isHot) continue;
            if ($dev['type'] !== 'disk') continue;

            $vendor = trim((string) ($dev['vendor'] ?? ''));
            $model  = trim((string) ($dev['model']  ?? ''));

            $entry = [
                'name'   => $dev['name'],
                'path'   => '/dev/' . $dev['name'],
                'size'   => $dev['size'] ?? '?',
                'vendor' => $vendor,
                'model'  => $model ?: ($vendor ?: 'Dispositivo removible'),
                'tran'   => $dev['tran'] ?? 'removible',
                'parts'  => [],
            ];

            foreach ($dev['children'] ?? [] as $part) {
                $mp = trim((string) ($part['mountpoint'] ?? ''));
                $entry['parts'][] = [
                    'name'       => $part['name'],
                    'path'       => '/dev/' . $part['name'],
                    'size'       => $part['size'] ?? '?',
                    'label'      => trim((string) ($part['label']  ?? '')),
                    'fstype'     => trim((string) ($part['fstype'] ?? '')),
                    'mountpoint' => $mp,
                    'mounted'    => $mp !== '',
                ];
            }

            $devices[] = $entry;
        }

        // Puntos de montaje adicionales en /media, /mnt, /run/media
        $extraMounts = [];
        $mntRaw      = shell_exec('cat /proc/mounts 2>/dev/null') ?? '';
        foreach (explode("\n", $mntRaw) as $line) {
            $parts = explode(' ', trim($line));
            if (count($parts) < 2) continue;
            $mp = $parts[1];
            if (!str_starts_with($mp, '/media/') && !str_starts_with($mp, '/run/media/') && !str_starts_with($mp, '/mnt/')) continue;
            $alreadyIn = false;
            foreach ($devices as $d) {
                foreach ($d['parts'] as $p) {
                    if ($p['mountpoint'] === $mp) { $alreadyIn = true; break 2; }
                }
            }
            if (!$alreadyIn) {
                $extraMounts[] = ['device' => $parts[0], 'mountpoint' => $mp, 'fstype' => $parts[2] ?? ''];
            }
        }

        return ['ok' => true, 'devices' => $devices, 'extra_mounts' => $extraMounts];
    }

    // ── Privados ──────────────────────────────────────────────────────────────

    // Elimina vault: sobrescribe el archivo cifrado con ceros antes de borrar
    public function delete(string $token): void
    {
        $this->shred(VAULT_PATH . $token . '.enc');
        // También se limpia un posible reclamo huérfano de una descarga interrumpida
        $this->shred(VAULT_PATH . $token . '.serving');
        @unlink(VAULT_PATH . $token . '.meta');
    }

    /*
     * Sobrescribe un archivo con ceros EN SITIO y luego lo borra.
     * Se abre con 'r+' (no trunca): así los ceros caen sobre los mismos
     * bloques del disco que ocupaban los datos. La versión anterior usaba
     * file_put_contents, que primero trunca el archivo — eso libera los
     * bloques originales sin tocarlos y la sobreescritura no servía de nada.
     *
     * Limitación honesta: en discos SSD (wear leveling) y sistemas de
     * archivos con journaling la sobreescritura no garantiza el borrado
     * físico. Es una medida de mejor esfuerzo, no una garantía forense.
     */
    private function shred(string $path): void
    {
        if (is_file($path)) {
            $size = filesize($path) ?: 0;
            $fh   = @fopen($path, 'r+');
            if ($fh !== false) {
                $bloque = str_repeat("\0", 65536);
                $resta  = $size;
                while ($resta > 0) {
                    fwrite($fh, $resta >= 65536 ? $bloque : str_repeat("\0", $resta));
                    $resta -= 65536;
                }
                fflush($fh);
                fclose($fh);
            }
            @unlink($path);
        }
    }

    // Limpia vaults expirados o ya descargados
    private function cleanup(): void
    {
        foreach (glob(VAULT_PATH . '*.meta') ?: [] as $metaFile) {
            $meta  = json_decode((string) file_get_contents($metaFile), true) ?: [];
            $token = basename($metaFile, '.meta');
            $age   = time() - ($meta['created'] ?? 0);
            if ($age > EXPIRY_SEC || !empty($meta['downloaded'])) {
                $this->delete($token);
            }
        }
    }

    private function validateToken(string $token): void
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
            throw new \InvalidArgumentException('Token inválido.', 400);
        }
    }
}
