<?php
/*
 * Archivo: app/controllers/HomeController.php
 * Autor:   C3r0d4y
 *
 * Sirve la interfaz principal de Cryptum.
 * También entrega a la vista la lista de descargas de la aplicación
 * portátil, para que el modal "App portátil" sepa qué ofrecer.
 * Si la URL contiene ?d=<token>, lo pasa a la vista para que
 * el JS lo lea desde el atributo data-token del body y abra
 * automáticamente el flujo de descifrado por enlace.
 */

declare(strict_types=1);

final class HomeController extends Controller
{
    public function index(): void
    {
        $token = '';
        if (isset($_GET['d']) && preg_match('/^[0-9a-f]{32}$/', $_GET['d'])) {
            $token = $_GET['d'];
        }

        $this->view('home/index', [
            'token'     => $token,
            // Qué versiones de la app portátil se pueden bajar ahora mismo
            'descargas' => Descargas::listar(),
        ]);
    }
}
