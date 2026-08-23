"""
Cryptum Portable — Constantes del formato criptografico
Autor: C3r0d4y

Estos valores son EXACTAMENTE los mismos que usa la aplicacion web Cryptum
(archivo public/assets/js/app.js). Si alguno cambia alla, debe cambiar aqui,
porque de lo contrario los archivos dejan de ser compatibles entre las dos
aplicaciones.
"""

APP_NOMBRE  = "Cryptum Portable"
APP_AUTOR   = "C3r0d4y"
APP_VERSION = "1.0.0"

# Firma que llevan todos los archivos cifrados: las letras "C3VL"
MAGIC = b"C3VL"

# Firma del archivo de metadatos de una carpeta cifrada: las letras "C3VM"
META_MAGIC = b"C3VM"

# Nombre del archivo donde se guarda la sal de una carpeta o USB cifrada
META_NOMBRE = ".cryptum_meta.bin"

# Extension que se agrega a cada archivo cifrado
EXT = ".c3v"

# --- Versiones del formato ---------------------------------------------
# Un byte despues de la firma indica como esta armado el archivo.
VER_ARCHIVO        = 0x03  # Archivo suelto, version actual
VER_ARCHIVO_LEGADO = 0x01  # Archivo suelto, version vieja (solo se lee)
VER_CARPETA        = 0x04  # Carpeta o USB con clave maestra, version actual
VER_CARPETA_LEGADO = 0x02  # Carpeta o USB, version vieja (solo se lee)

# --- Parametros criptograficos -----------------------------------------
LARGO_SAL   = 32      # 32 bytes = 256 bits de sal aleatoria
LARGO_IV    = 12      # 12 bytes = 96 bits, el tamano optimo para AES-GCM
LARGO_TAG   = 16      # 16 bytes = 128 bits de etiqueta de autenticacion
LARGO_CLAVE = 32      # 32 bytes = clave AES de 256 bits
KDF_ITER    = 210_000 # Repeticiones de PBKDF2 (minimo recomendado por OWASP)
KDF_HASH    = "sha512"

# Largo maximo que se acepta para el nombre de un archivo dentro del vault.
# Sirve para detectar un encabezado corrupto antes de reservar memoria.
MAX_LARGO_NOMBRE = 4096
