<?php
/*
 * Archivo: app/models/Descargas.php
 * Autor:   C3r0d4y
 *
 * Sabe qué versiones de Cryptum Portable hay disponibles para descargar
 * y de dónde las va a tomar el usuario.
 *
 * Hay dos orígenes posibles para cada archivo:
 *
 *   1. El propio servidor (carpeta public/descargas/). Es el camino preferido:
 *      funciona aunque la instalación esté en una red aislada, sin salida a
 *      internet. Los ejecutables se colocan ahí con el script
 *      portable/build/descargar_binarios.sh
 *
 *   2. El Release de GitHub. Es el respaldo: si el ejecutable todavía no se
 *      ha copiado al servidor, el botón sigue funcionando y lleva al usuario
 *      al archivo oficial publicado en el repositorio.
 *
 * Los ejecutables pesan decenas de megabytes, así que NO se guardan dentro
 * del repositorio: engordarían el historial de Git para siempre y cada nueva
 * compilación sumaría otro tanto. Solo el código fuente comprimido viaja
 * versionado, porque son apenas unos kilobytes.
 */

declare(strict_types=1);

final class Descargas
{
    // Etiqueta del Release de GitHub que contiene los ejecutables de esta versión.
    public const RELEASE_TAG = 'v1.4.5';

    // Dirección desde la que GitHub entrega los archivos de ese Release.
    public const RELEASE_URL = 'https://github.com/c3r0d4y/Cryptum/releases/download/' . self::RELEASE_TAG . '/';

    // Carpeta física del servidor donde se buscan primero los archivos.
    private const CARPETA = APP_ROOT . '/public/descargas/';

    /*
     * Catálogo de lo que se ofrece al usuario.
     *
     * Cada entrada define:
     *   archivo  nombre exacto del archivo, igual en el servidor y en GitHub
     *   sistema  para qué sistema operativo es
     *   titulo   lo que lee el usuario en el botón
     *   detalle  una línea que explica cuándo usar justamente ese archivo
     *   peso     tamaño aproximado, por si el archivo aún no está en el servidor
     *   destaca  true en la opción que debe usar la mayoría de la gente
     */
    private const CATALOGO = [
        [
            'archivo' => 'cryptum.exe',
            'sistema' => 'Windows',
            'titulo'  => 'Windows — programa con ventana',
            'detalle' => 'Doble clic y se abre la ventana. Es el que usa casi todo el personal.',
            'peso'    => '15 MB',
            'destaca' => true,
        ],
        [
            'archivo' => 'cryptum',
            'sistema' => 'Linux',
            'titulo'  => 'Linux — programa con ventana',
            'detalle' => 'Dale permiso de ejecución y ábrelo. También corre desde la propia USB.',
            'peso'    => '28 MB',
            'destaca' => true,
        ],
        [
            'archivo' => 'cryptum-cli.exe',
            'sistema' => 'Windows',
            'titulo'  => 'Windows — versión de consola',
            'detalle' => 'Solo si trabajas desde la línea de comandos o en equipos sin escritorio.',
            'peso'    => '15 MB',
            'destaca' => false,
        ],
        [
            'archivo' => 'cryptum-portable.zip',
            'sistema' => 'Código',
            'titulo'  => 'Código fuente en Python',
            'detalle' => 'Para revisar cómo funciona o compilarlo tú mismo. Requiere Python 3.',
            'peso'    => '45 KB',
            'destaca' => false,
        ],
    ];

    /*
     * Devuelve el catálogo listo para pintar en la vista, ya resuelto el
     * origen de cada archivo y con el tamaño real cuando se puede medir.
     */
    public static function listar(): array
    {
        $lista = [];

        foreach (self::CATALOGO as $item) {
            $ruta   = self::CARPETA . $item['archivo'];
            $enCasa = is_file($ruta);

            $item['local'] = $enCasa;
            $item['url']   = $enCasa
                ? rtrim(BASE_URL, '/') . '/descargas/' . $item['archivo']
                : self::RELEASE_URL . $item['archivo'];

            // La huella solo se ofrece si está junto al archivo en el servidor.
            $item['sha256'] = is_file($ruta . '.sha256')
                ? rtrim(BASE_URL, '/') . '/descargas/' . $item['archivo'] . '.sha256'
                : self::RELEASE_URL . $item['archivo'] . '.sha256';

            // Si el archivo está aquí, se informa el tamaño exacto en vez del aproximado.
            if ($enCasa) {
                $item['peso'] = self::formatoPeso((int) filesize($ruta));
            }

            $lista[] = $item;
        }

        return $lista;
    }

    /*
     * Indica si algún ejecutable todavía no se ha copiado al servidor.
     * La vista lo usa para avisar que esas descargas salen a internet.
     */
    public static function faltanLocales(): bool
    {
        foreach (self::listar() as $item) {
            if (!$item['local']) {
                return true;
            }
        }
        return false;
    }

    // Convierte un número de bytes en algo legible para una persona.
    private static function formatoPeso(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }
        return $bytes . ' B';
    }
}
