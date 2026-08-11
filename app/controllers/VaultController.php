<?php
/*
 * Archivo: app/controllers/VaultController.php
 * Autor:   C3r0d4y
 *
 * Controlador de la API de bóveda temporal.
 * Delega toda la lógica al modelo Vault y devuelve JSON.
 *
 * Rutas:
 *   POST /api/upload     → sube un blob cifrado y devuelve token + URL
 *   GET  /api/download   → sirve y elimina el blob (descarga única)
 *   GET  /api/status     → verifica validez y tiempo restante del vault
 *   GET  /api/list-usb   → lista dispositivos USB detectados por el SO
 */

declare(strict_types=1);

final class VaultController extends Controller
{
    private Vault $vault;

    public function __construct()
    {
        $this->vault = new Vault();
    }

    // ── POST /api/upload ──────────────────────────────────────────────────────

    public function upload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError(405, 'Método no permitido.');
        }

        try {
            // El modelo lee php://input por bloques; no se carga el cuerpo aquí
            $result = $this->vault->store();
            $this->json($result);
        } catch (\OverflowException $e) {
            $this->jsonError(503, $e->getMessage());
        } catch (\LengthException $e) {
            $this->jsonError(413, $e->getMessage());
        } catch (\InvalidArgumentException | \UnexpectedValueException $e) {
            $this->jsonError(400, $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->jsonError(500, $e->getMessage());
        }
    }

    // ── GET /api/download ─────────────────────────────────────────────────────

    public function download(): void
    {
        $token = (string) ($_GET['t'] ?? '');

        try {
            $this->vault->serve($token);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError(400, $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->jsonError((int) ($e->getCode() ?: 500), $e->getMessage());
        }
    }

    // ── GET /api/status ───────────────────────────────────────────────────────

    public function status(): void
    {
        $token = (string) ($_GET['t'] ?? '');

        try {
            $this->json($this->vault->status($token));
        } catch (\InvalidArgumentException $e) {
            $this->json(['valid' => false, 'reason' => 'invalid_token']);
        }
    }

    // ── GET /api/list-usb ─────────────────────────────────────────────────────

    public function listUsb(): void
    {
        // La lista de discos revela hardware del servidor: por defecto solo
        // se permite desde la propia máquina (ver USB_API_LOCAL_ONLY en config).
        $remota = !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
        if (USB_API_LOCAL_ONLY && $remota) {
            $this->jsonError(403, 'La función de dispositivos USB solo está disponible desde el propio equipo.');
        }

        $this->json($this->vault->listUsb());
    }
}
