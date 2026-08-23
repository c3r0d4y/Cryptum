"""
Cryptum Portable — Motor criptografico (MODELO)
Autor: C3r0d4y

Aqui vive toda la matematica del cifrado. Es el equivalente exacto del objeto
"Crypto" y del objeto "USBCrypto" de la aplicacion web, pero escrito en Python.

Reglas que sigue este motor:
  - Cifrado AES-256-GCM con etiqueta de autenticacion de 128 bits.
    GCM no solo oculta el contenido: tambien avisa si alguien modifico
    el archivo aunque sea en un solo bit.
  - La clave se obtiene de la contrasena con PBKDF2-SHA-512 y 210 000
    repeticiones. Esa lentitud es a proposito: encarece muchisimo el
    trabajo de quien intente adivinar la contrasena por fuerza bruta.
  - El vector de inicializacion (IV) es aleatorio y distinto para cada
    archivo. Nunca se repite, porque repetir un IV en GCM rompe el cifrado.

Este archivo NO contiene ninguna clave ni secreto. Si alguien obtiene el
programa completo, no gana nada: sin la contrasena del usuario los archivos
siguen siendo indescifrables.
"""

import hashlib
import os
import struct

from cryptography.hazmat.primitives.ciphers.aead import AESGCM

from app import config


class ErrorCryptum(Exception):
    """Error controlado que la interfaz puede mostrarle al usuario tal cual."""


class ErrorVersionArchivoSuelto(ErrorCryptum):
    """
    Se lanza cuando, descifrando una carpeta, aparece un archivo que en
    realidad fue cifrado en modo individual (con su propia sal).
    El controlador lo atrapa y reintenta con la contrasena directa.
    """


# ═══════════════════════════════════════════════════════════════════════
#  DERIVACION DE CLAVE
# ═══════════════════════════════════════════════════════════════════════

def derivar_clave(password: str, salt: bytes) -> bytes:
    """
    Convierte una contrasena de texto en una clave AES de 256 bits.

    La contrasena se codifica en UTF-8, igual que hace el navegador con
    TextEncoder(). Ese detalle importa: si se codificara diferente, las
    contrasenas con acentos o enes producirian claves distintas y los
    archivos de la web no abririan aqui.

    Parametros:
        password: contrasena escrita por el usuario.
        salt:     sal aleatoria de 32 bytes leida del archivo.

    Devuelve:
        La clave de 32 bytes lista para AES-256.
    """
    return hashlib.pbkdf2_hmac(
        config.KDF_HASH,
        password.encode("utf-8"),
        salt,
        config.KDF_ITER,
        dklen=config.LARGO_CLAVE,
    )


# ═══════════════════════════════════════════════════════════════════════
#  EMPAQUETADO DEL NOMBRE JUNTO A LOS DATOS
# ═══════════════════════════════════════════════════════════════════════

def empaquetar_nombre(nombre: str, datos: bytes) -> bytes:
    """
    Une el nombre del archivo y su contenido en un solo bloque que despues
    se cifra completo. Asi el nombre queda tan protegido como los datos.

    Estructura del bloque: [largo del nombre: 4 bytes][nombre][datos]
    El largo va en formato "little endian" porque asi lo escribe el
    navegador con DataView.setUint32(pos, valor, true).
    """
    nom = nombre.encode("utf-8")
    return struct.pack("<I", len(nom)) + nom + datos


def desempaquetar_nombre(plano: bytes) -> tuple[str, bytes]:
    """
    Separa un bloque ya descifrado en (nombre, datos).
    Valida el largo antes de usarlo para no confiar en un encabezado danado.
    """
    if len(plano) < 4:
        raise ErrorCryptum("Encabezado corrupto: el archivo esta incompleto.")

    (largo,) = struct.unpack("<I", plano[:4])
    if largo > config.MAX_LARGO_NOMBRE or 4 + largo > len(plano):
        raise ErrorCryptum("Encabezado corrupto: el nombre no es valido.")

    nombre = plano[4:4 + largo].decode("utf-8", errors="replace")
    return nombre, plano[4 + largo:]


# ═══════════════════════════════════════════════════════════════════════
#  MODO ARCHIVO SUELTO  (formato v3 actual, v1 solo lectura)
# ═══════════════════════════════════════════════════════════════════════

def cifrar_archivo(datos: bytes, nombre: str, password: str) -> bytes:
    """
    Cifra un archivo con su propia sal. Produce el formato v3:

        [C3VL][0x03][SAL 32][IV 12][CIFRADO + ETIQUETA]

    Cada llamada genera sal e IV nuevos, asi que cifrar dos veces el mismo
    archivo con la misma contrasena da resultados distintos. Eso es correcto
    y deseable: impide saber si dos archivos cifrados son iguales.
    """
    salt = os.urandom(config.LARGO_SAL)
    iv = os.urandom(config.LARGO_IV)
    clave = derivar_clave(password, salt)

    plano = empaquetar_nombre(nombre, datos)
    cifrado = AESGCM(clave).encrypt(iv, plano, None)

    return config.MAGIC + bytes([config.VER_ARCHIVO]) + salt + iv + cifrado


def descifrar_archivo(blob: bytes, password: str) -> tuple[str, bytes]:
    """
    Descifra un archivo suelto. Acepta el formato actual (v3) y el viejo
    (v1, que guardaba el nombre sin cifrar en el encabezado).

    Devuelve (nombre original, contenido).
    """
    if not blob.startswith(config.MAGIC):
        raise ErrorCryptum(
            "El archivo no es un vault Cryptum valido o esta danado."
        )

    pos = len(config.MAGIC)
    ver = blob[pos]
    pos += 1

    if ver not in (config.VER_ARCHIVO, config.VER_ARCHIVO_LEGADO):
        raise ErrorCryptum(f"Version de formato {ver} no soportada.")

    salt = blob[pos:pos + config.LARGO_SAL]
    pos += config.LARGO_SAL
    iv = blob[pos:pos + config.LARGO_IV]
    pos += config.LARGO_IV

    # Solo la version vieja lleva el nombre en claro antes del texto cifrado
    nombre_legado = ""
    if ver == config.VER_ARCHIVO_LEGADO:
        (largo,) = struct.unpack("<I", blob[pos:pos + 4])
        pos += 4
        if largo > config.MAX_LARGO_NOMBRE:
            raise ErrorCryptum("Encabezado corrupto.")
        nombre_legado = blob[pos:pos + largo].decode("utf-8", errors="replace")
        pos += largo

    cifrado = blob[pos:]
    clave = derivar_clave(password, salt)

    try:
        plano = AESGCM(clave).decrypt(iv, cifrado, None)
    except Exception:
        # AES-GCM falla igual si la contrasena es mala o si el archivo fue
        # alterado. No se distingue a proposito: dar mas detalle le serviria
        # a un atacante para afinar sus intentos.
        raise ErrorCryptum(
            "Contrasena incorrecta o archivo danado. "
            "Verifica la contrasena e intentalo de nuevo."
        )

    if ver == config.VER_ARCHIVO_LEGADO:
        return nombre_legado, plano
    return desempaquetar_nombre(plano)


# ═══════════════════════════════════════════════════════════════════════
#  MODO CARPETA / USB  (formato v4 actual, v2 solo lectura)
# ═══════════════════════════════════════════════════════════════════════
#
#  Aqui la sal es una sola para toda la carpeta y se guarda aparte, en el
#  archivo .cryptum_meta.bin. Gracias a eso la contrasena se procesa una
#  unica vez aunque haya miles de archivos: si se derivara por archivo,
#  cifrar una USB tardaria horas.
#  Cada archivo conserva su propio IV aleatorio, que es lo que realmente
#  hace falta para que compartir la clave sea seguro.
# ═══════════════════════════════════════════════════════════════════════

def cifrar_con_clave_maestra(datos: bytes, nombre: str, clave: bytes) -> bytes:
    """
    Cifra un archivo usando una clave maestra ya derivada. Formato v4:

        [C3VL][0x04][IV 12][CIFRADO + ETIQUETA]
    """
    iv = os.urandom(config.LARGO_IV)
    plano = empaquetar_nombre(nombre, datos)
    cifrado = AESGCM(clave).encrypt(iv, plano, None)

    return config.MAGIC + bytes([config.VER_CARPETA]) + iv + cifrado


def descifrar_con_clave_maestra(blob: bytes, clave: bytes) -> tuple[str, bytes]:
    """
    Descifra un archivo de carpeta o USB. Acepta v4 (actual) y v2 (viejo).

    Si encuentra un archivo cifrado en modo individual lanza
    ErrorVersionArchivoSuelto para que el controlador lo reintente con la
    contrasena, tal como hace la aplicacion web.
    """
    if not blob.startswith(config.MAGIC):
        raise ErrorCryptum("No es un archivo Cryptum.")

    ver = blob[len(config.MAGIC)]

    if ver in (config.VER_ARCHIVO, config.VER_ARCHIVO_LEGADO):
        raise ErrorVersionArchivoSuelto()

    if ver not in (config.VER_CARPETA, config.VER_CARPETA_LEGADO):
        raise ErrorCryptum(f"Version {ver} no soportada.")

    pos = len(config.MAGIC) + 1
    iv = blob[pos:pos + config.LARGO_IV]
    pos += config.LARGO_IV

    # Solo la version vieja lleva el nombre en claro
    nombre_legado = ""
    if ver == config.VER_CARPETA_LEGADO:
        (largo,) = struct.unpack("<I", blob[pos:pos + 4])
        pos += 4
        if largo > config.MAX_LARGO_NOMBRE:
            raise ErrorCryptum("Encabezado corrupto.")
        nombre_legado = blob[pos:pos + largo].decode("utf-8", errors="replace")
        pos += largo

    cifrado = blob[pos:]

    try:
        plano = AESGCM(clave).decrypt(iv, cifrado, None)
    except Exception:
        raise ErrorCryptum("Contrasena incorrecta o archivo danado.")

    if ver == config.VER_CARPETA_LEGADO:
        return nombre_legado, plano
    return desempaquetar_nombre(plano)
