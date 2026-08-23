"""
Cryptum Portable — Controlador de archivo suelto (CONTROLADOR)
Autor: C3r0d4y

Coordina lo que pasa cuando el usuario elige UN archivo: leerlo del disco,
pedirle al motor que lo cifre o lo descifre, y escribir el resultado sin
pisar nada que ya exista.
"""

import os

from app import config
from app.models import crypto_engine
from app.models.borrado_seguro import borrar_sobrescribiendo
from app.models.nombres import nombre_seguro


def _ruta_libre(carpeta: str, nombre: str) -> str:
    """
    Devuelve una ruta que todavia no existe.

    Si ya hay un archivo con ese nombre, se le agrega un numero entre
    parentesis: "informe (1).pdf", "informe (2).pdf", etc. Nunca se
    sobrescribe un archivo del usuario sin avisar.
    """
    ruta = os.path.join(carpeta, nombre)
    if not os.path.exists(ruta):
        return ruta

    base, ext = os.path.splitext(nombre)
    n = 1
    while True:
        candidato = os.path.join(carpeta, f"{base} ({n}){ext}")
        if not os.path.exists(candidato):
            return candidato
        n += 1


def cifrar(ruta_origen: str, password: str, carpeta_destino: str = None,
           borrar_original: bool = False) -> str:
    """
    Cifra un archivo y deja el resultado con extension .c3v.

    Parametros:
        ruta_origen:     archivo a proteger.
        password:        contrasena elegida por el usuario.
        carpeta_destino: donde dejar el .c3v (por omision, junto al original).
        borrar_original: si es True, borra el original sobrescribiendolo.

    Devuelve la ruta del archivo cifrado que quedo en el disco.
    """
    if not os.path.isfile(ruta_origen):
        raise crypto_engine.ErrorCryptum(f"No existe el archivo: {ruta_origen}")

    nombre = os.path.basename(ruta_origen)
    destino = carpeta_destino or os.path.dirname(os.path.abspath(ruta_origen))

    with open(ruta_origen, "rb") as f:
        datos = f.read()

    blob = crypto_engine.cifrar_archivo(datos, nombre, password)

    ruta_final = _ruta_libre(destino, nombre + config.EXT)
    with open(ruta_final, "wb") as f:
        f.write(blob)

    # Antes de tocar el original se comprueba que la copia cifrada quedo
    # completa en el disco. Si algo fallo, el original NO se borra.
    if borrar_original:
        if os.path.getsize(ruta_final) != len(blob):
            raise crypto_engine.ErrorCryptum(
                "No se pudo verificar la escritura del archivo cifrado. "
                "El original NO fue borrado."
            )
        borrar_sobrescribiendo(ruta_origen)

    return ruta_final


def descifrar(ruta_origen: str, password: str, carpeta_destino: str = None,
              borrar_cifrado: bool = False) -> str:
    """
    Descifra un archivo .c3v y lo devuelve con su nombre original.

    El nombre no se toma de la ruta del archivo cifrado sino de adentro del
    contenido cifrado, que es donde Cryptum lo guarda desde la version 3.

    Devuelve la ruta del archivo recuperado.
    """
    if not os.path.isfile(ruta_origen):
        raise crypto_engine.ErrorCryptum(f"No existe el archivo: {ruta_origen}")

    destino = carpeta_destino or os.path.dirname(os.path.abspath(ruta_origen))

    with open(ruta_origen, "rb") as f:
        blob = f.read()

    nombre, datos = crypto_engine.descifrar_archivo(blob, password)

    # El nombre recuperado se adapta antes de usarlo. Cumple dos funciones:
    # impide que apunte fuera de la carpeta de destino (un nombre como
    # "../../passwd" no debe poder escribir en otro directorio), y lo ajusta
    # a lo que acepta el sistema actual — un archivo cifrado en Linux como
    # "informe:04.txt" no se puede crear en Windows con ese nombre.
    nombre = nombre_seguro(nombre)

    ruta_final = _ruta_libre(destino, nombre)
    with open(ruta_final, "wb") as f:
        f.write(datos)

    if borrar_cifrado:
        borrar_sobrescribiendo(ruta_origen)

    return ruta_final
