"""
Cryptum Portable — Metadatos del vault de carpeta/USB (MODELO)
Autor: C3r0d4y

Cuando se cifra una carpeta completa, la sal es una sola para todos los
archivos y hay que guardarla en algun lado. Ese lugar es el archivo
".cryptum_meta.bin", que se deja en la raiz de la carpeta cifrada.

Su contenido es minimo:  [C3VM][SAL de 32 bytes]

La sal NO es un secreto: sirve para que dos personas con la misma
contrasena obtengan claves distintas y para frenar los ataques con tablas
precalculadas. Puede viajar en claro sin ningun problema.
"""

import os

from app import config
from app.models.crypto_engine import ErrorCryptum


def ruta_meta(carpeta: str) -> str:
    """Devuelve la ruta completa del archivo de metadatos de una carpeta."""
    return os.path.join(carpeta, config.META_NOMBRE)


def escribir_meta(carpeta: str, salt: bytes) -> None:
    """
    Guarda la sal en la raiz de la carpeta.

    Se escribe ANTES de cifrar el primer archivo, a proposito. Si el proceso
    se corta a la mitad (se desconecta la USB, se apaga el equipo), la sal ya
    quedo en el disco y todo lo que alcanzo a cifrarse sigue siendo
    recuperable con la contrasena. Al reves se perderia todo.
    """
    with open(ruta_meta(carpeta), "wb") as f:
        f.write(config.META_MAGIC + salt)


def leer_meta(carpeta: str) -> bytes:
    """
    Lee la sal de una carpeta cifrada. Falla con un mensaje claro si el
    archivo no existe o si esta danado.
    """
    ruta = ruta_meta(carpeta)

    if not os.path.isfile(ruta):
        raise ErrorCryptum(
            "No se encontraron metadatos Cryptum en esta ubicacion. "
            "Verifica que la carpeta o la USB haya sido cifrada con Cryptum."
        )

    with open(ruta, "rb") as f:
        datos = f.read()

    largo_esperado = len(config.META_MAGIC) + config.LARGO_SAL
    if len(datos) < largo_esperado or not datos.startswith(config.META_MAGIC):
        raise ErrorCryptum("Los metadatos del vault estan corruptos.")

    return datos[len(config.META_MAGIC):largo_esperado]


def borrar_meta(carpeta: str) -> None:
    """Quita el archivo de metadatos. Se usa al terminar de descifrar."""
    try:
        os.remove(ruta_meta(carpeta))
    except OSError:
        pass


def existe_meta(carpeta: str) -> bool:
    """Indica si la carpeta ya tiene un vault Cryptum activo."""
    return os.path.isfile(ruta_meta(carpeta))
