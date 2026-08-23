# Cryptum — v1.4.4

**Autor: C3r0d4y**

Bóveda de cifrado de archivos donde **todo el cifrado ocurre en el navegador**
(Web Crypto API). El servidor nunca recibe contraseñas ni archivos en claro:
solo almacena, de forma temporal y opcional, el blob ya cifrado para la función
de enlace de descarga única.

El repositorio contiene **dos aplicaciones que hablan el mismo formato**:

| Aplicación | Dónde vive | Para qué sirve |
|---|---|---|
| **Cryptum** (web) | raíz del repositorio | Cifrar y descifrar desde el navegador, en el servidor de la unidad |
| **Cryptum Portable** | [`portable/`](portable/) | Abrir ese mismo material en el destino, sin internet y sin instalar nada |

El caso de uso es el despliegue: el material se cifra en el servidor antes de
salir, viaja en una memoria USB, y en destino se abre con la aplicación portátil
en cualquier equipo Linux o Windows, sin necesidad de alcanzar el servidor.

> ### 🔗 Versión demo en línea
> **https://cryptum.ciberdefensa.com.mx/**
>
> Instancia pública de demostración. Funciona igual que la instalación local,
> con dos diferencias: la detección automática de dispositivos USB
> (`/api/list-usb`) está deshabilitada porque solo opera desde `localhost`, y
> los enlaces seguros de esa instancia expiran igual a los 5 minutos. No debe
> usarse para material sensible real: es un entorno de prueba.

---

## Funciones

| Función | Descripción |
|---|---|
| Cifrar archivo | AES-256-GCM en el navegador; produce un archivo `.c3v` |
| Enlace seguro | El blob cifrado sube a la bóveda; enlace de **una sola descarga** que expira en **5 minutos** |
| Descifrar | Por enlace recibido o subiendo un `.c3v` local |
| Cifrado de carpeta | Cifra o descifra todos los archivos de una carpeta con una sola clave maestra |
| Cifrado USB | Igual que el de carpeta, más la detección de discos removibles y la guía de cifrado LUKS |
| App portátil | Enlace en la barra superior que entrega `cryptum-portable.zip` junto a un diagrama de uso de tres pasos |

El cifrado de carpeta y el de USB usan el mismo motor. El selector de carpetas
lo abre el navegador en el equipo del visitante (File System Access API), así
que el modo carpeta funciona igual en local y en producción; el panel de
dispositivos detectados solo es útil en instalaciones locales.

## Arquitectura (MVC)

```
cryptum/
├── config/config.php            → constantes globales (versión, límites, rutas)
├── public/
│   ├── index.php                → front controller: cabeceras de seguridad + rutas
│   └── assets/                  → css/app.css · js/app.js (toda la criptografía)
├── app/
│   ├── core/Controller.php      → clase base: render de vistas y respuestas JSON
│   ├── controllers/             → HomeController · VaultController (API)
│   ├── models/Vault.php         → almacenamiento temporal de blobs cifrados
│   └── views/                   → layouts (header/footer) + home
├── storage/vault/               → blobs cifrados (.enc) y metadatos (.meta); acceso web denegado
├── public/descargas/            → cryptum-portable.zip + su huella SHA-256
└── portable/                    → aplicación de escritorio (ver portable/README.md)
```

### API

| Ruta | Método | Función |
|---|---|---|
| `/api/upload` | POST | Sube el blob cifrado como **binario crudo** (`application/octet-stream`) |
| `/api/download?t=<token>` | GET | Sirve y elimina el blob — descarga única con reclamo atómico |
| `/api/status?t=<token>` | GET | Validez y tiempo restante del vault |
| `/api/list-usb` | GET | Lista discos removibles (**solo desde localhost** por defecto) |

## Criptografía

- **Cifrado:** AES-256-GCM (tag de autenticación de 128 bits, IV de 96 bits único por archivo).
- **Derivación de clave:** PBKDF2-SHA-512 con 210 000 iteraciones y sal de 256 bits.
- **Token de descarga:** 128 bits de `random_bytes` (32 caracteres hex).

### Formato binario C3VL

Todos los archivos `.c3v` comienzan con la firma ASCII `C3VL` seguida de un
byte de versión:

| Versión | Uso | Estructura |
|---|---|---|
| `0x03` (actual) | Archivo individual | `[C3VL][03][SALT 32][IV 12][CIFRADO+TAG]` |
| `0x04` (actual) | Carpeta / USB (clave maestra) | `[C3VL][04][IV 12][CIFRADO+TAG]` |
| `0x01` (legado) | Archivo individual | `[C3VL][01][SALT 32][IV 12][NOMLEN 4][NOMBRE][CIFRADO+TAG]` |
| `0x02` (legado) | Carpeta / USB | `[C3VL][02][IV 12][NOMLEN 4][NOMBRE][CIFRADO+TAG]` |

En v3/v4 el contenido cifrado es `[NOMLEN 4][NOMBRE][DATOS]`: **el nombre del
archivo viaja cifrado**. Las versiones legadas lo guardaban en claro en el
encabezado (fuga de metadatos) y se mantienen **solo para descifrar** archivos
antiguos; ya no se generan.

El modo carpeta/USB guarda la sal del directorio en `.cryptum_meta.bin`
(`[C3VM][SALT 32]`). **Este archivo se escribe antes de cifrar el primer
archivo**: si el proceso se interrumpe, la sal ya está en el disco y los `.c3v`
creados siguen siendo recuperables con la contraseña.

## Ciclo de vida de un vault (enlace seguro)

1. El cliente cifra el archivo en el navegador y sube el blob (binario crudo).
2. El servidor lo valida (firma `C3VL`, tamaño) escribiéndolo a disco por
   bloques y asigna un token de 128 bits.
3. Al primer GET del enlace, la petición **reclama el archivo con un `rename()`
   atómico** — dos descargas simultáneas no pueden ganar las dos — lo sirve y
   lo destruye.
4. Todo vault que supere los 300 segundos se elimina en la siguiente limpieza
   (se ejecuta en cada subida y en cada consulta de estado).

## Límites y protecciones

| Límite | Valor | Dónde |
|---|---|---|
| Tamaño máximo de archivo | 100 MB | `MAX_FILE_MB` |
| Expiración del enlace | 300 s | `EXPIRY_SEC` |
| Vaults activos totales | 500 | `MAX_VAULTS` |
| Vaults activos por visitante | 20 | `MAX_VAULTS_PER_IP` |

Cabeceras aplicadas: CSP estricta (`script-src 'self'`, `form-action 'none'`,
`base-uri 'none'`), `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`,
HSTS cuando hay HTTPS.

## Cryptum Portable

Aplicación de escritorio en Python que descifra y cifra los mismos archivos
`.c3v`, **sin conexión y sin instalación**. Está en [`portable/`](portable/),
con su propia documentación.

```
   EN LA BASE                    TRASLADO                  EN EL DESTINO
   ──────────                    ────────                  ─────────────
  Aplicación web              Memoria USB                Cryptum Portable
   ┌─────────────┐            ┌─────────────┐            ┌─────────────┐
   │  CIFRAR     │  ───────►  │  orden.c3v  │  ───────►  │  DESCIFRAR  │
   │  AES-256    │            │  mapa.c3v   │            │  AES-256    │
   └─────────────┘            └─────────────┘            └─────────────┘
    contraseña               material ilegible            contraseña
    del soldado               para cualquiera             del soldado

  La contraseña NUNCA viaja con la USB. Solo la sabe el soldado.
```

El botón **App portátil** de la barra superior abre ese mismo diagrama y entrega
el paquete desde `public/descargas/cryptum-portable.zip`, con su huella SHA-256
publicada al lado para que quien lo descargue pueda verificarlo.

### Por qué publicar el descifrador no debilita el cifrado

Es la pregunta correcta, y la respuesta está en el **principio de Kerckhoffs**:
la seguridad de Cryptum no está en el algoritmo, está en la contraseña del
usuario. El algoritmo y el formato ya eran públicos — están en el JavaScript que
cualquiera descarga al abrir la página. La aplicación portátil **no lleva ninguna
llave incrustada**: quien la capture obtiene un descifrador que sin la contraseña
es tan inútil como el archivo cifrado.

### Verificación cruzada

El formato binario está probado **en las dos direcciones**: lo que cifra el
navegador lo abre Python, y lo que cifra Python lo abre el navegador. La prueba
no usa una reimplementación del motor — carga el `public/assets/js/app.js` real
en Node con un DOM simulado y usa sus objetos `Crypto` y `USBCrypto` tal cual
corren en el navegador.

```bash
cd portable
python3 tests/test_compatibilidad.py   # 31 pruebas del formato y el cifrado
python3 tests/test_gui.py              # 34 pruebas de la ventana gráfica
python3 tests/test_windows.py          # 60 pruebas de compatibilidad Linux ↔ Windows
```

> **Al modificar la criptografía de la web hay que reflejarlo en
> `portable/app/config.py`.** Si las constantes de los dos lados dejan de
> coincidir, las aplicaciones dejan de entenderse y el material cifrado en el
> servidor no se podrá abrir en destino.

### Ejecutables compilados

La carpeta `.github/workflows/` define una construcción automática que compila
la aplicación portátil en máquinas Windows y Linux reales, ejecuta las 125
pruebas y comprueba que el ejecutable resultante descifra un archivo antes de
publicarlo. Se descarga desde la pestaña **Actions** → ejecución más reciente de
*Construir Cryptum Portable* → **Artifacts**.

Gracias a eso **no hace falta una máquina Windows con Python** para obtener el
`cryptum.exe` que se entrega al personal desplegado.

### Regenerar el paquete de descarga

Después de cualquier cambio en `portable/`:

```bash
bash portable/build/empaquetar_web.sh    # actualiza el ZIP y su huella
```

---

## Requisitos

- PHP 8.0 o superior.
- Apache con `mod_rewrite` habilitado (los `.htaccess` redirigen todo a
  `public/`).
- Navegador con Web Crypto API. Para los modos de carpeta y USB se necesita
  además la File System Access API (Chrome, Edge u Opera de escritorio);
  el cifrado de archivo individual y los enlaces seguros funcionan en
  cualquier navegador moderno.

## Instalación

```bash
git clone <url-del-repositorio> cryptum
cd cryptum

# Permisos de escritura para la bóveda temporal
mkdir -p storage/vault
chmod 775 storage/vault
chown -R www-data:www-data storage
```

Apunta el `DocumentRoot` del VirtualHost a la carpeta `cryptum/` (el
`.htaccess` de la raíz reenvía a `public/`) y asegúrate de que
`AllowOverride All` esté activo para ese directorio.

Verifica la instalación abriendo `http://localhost/cryptum/` — o el dominio que
hayas configurado.

## Despliegue

- `APP_BASE_URL` en el entorno para servir desde otra ruta. Vacío (`""`) = raíz
  del dominio; sin definir = `/cryptum`.
- `CRYPTUM_USB_REMOTE=1` en el entorno habilita `/api/list-usb` desde clientes
  remotos (por defecto solo localhost).
- Al publicar cambios de CSS/JS, subir `APP_VERSION` en `config/config.php`
  para invalidar la caché de los navegadores.

Ejemplo de VirtualHost para producción en la raíz del dominio:

```apache
<VirtualHost *:443>
    ServerName cryptum.ciberdefensa.com.mx
    DocumentRoot /var/www/html/cryptum

    SetEnv APP_BASE_URL ""

    <Directory /var/www/html/cryptum>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Limitaciones que el usuario debe conocer

- **Borrado con sobreescritura = mejor esfuerzo.** En el servidor los archivos
  se sobrescriben con ceros en sitio antes de borrarse, pero en SSD y sistemas
  con journaling el disco puede conservar copias internas. En el navegador, la
  File System Access API escribe en un archivo temporal, por lo que la
  sobreescritura del original no está garantizada a nivel físico. No debe
  presentarse como borrado forense certificado.
- **Contraseña perdida = archivo irrecuperable.** No existe mecanismo de
  recuperación por diseño.
- **PBKDF2 vs Argon2id:** se usa PBKDF2 (210k iteraciones) por estar disponible
  de forma nativa en Web Crypto sin dependencias externas. Migrar a Argon2id
  (resistente a GPU) requeriría incorporar una librería WASM auditada y una
  versión 5 del formato; queda como trabajo futuro documentado.

## Aviso de seguridad

Cryptum es una herramienta de cifrado; su seguridad depende de la fortaleza de
la contraseña que elija el usuario y del equipo donde se ejecuta. El proyecto se
entrega **tal cual**, sin auditoría externa formal. Si detectas una
vulnerabilidad, repórtala de forma privada al autor antes de divulgarla.

---

**Cryptum — desarrollado por C3r0d4y.**
Aplicación web y aplicación portátil, un solo formato de cifrado.
