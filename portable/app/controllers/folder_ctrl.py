"""
Cryptum Portable — Controlador de carpeta y USB (CONTROLADOR)
Autor: C3r0d4y

Hace el mismo trabajo que el modo "carpeta / USB" de la aplicacion web:
recorre todos los archivos de un directorio, incluidos los subdirectorios,
y los cifra o descifra con una sola clave maestra.

La contrasena se procesa UNA sola vez para toda la carpeta. Derivarla por
archivo, con 210 000 repeticiones cada vez, haria que una USB con mil
archivos tardara horas en vez de segundos.
"""

import os

from app import config
from app.models import crypto_engine, vault_meta
from app.models.borrado_seguro import borrar_sobrescribiendo
from app.models.nombres import nombre_seguro, se_excluye


def _recorrer(carpeta: str):
    """
    Va entregando uno por uno todos los archivos de la carpeta y sus
    subcarpetas.

    Se dejan fuera dos cosas:
      - El archivo de metadatos, que guarda la sal y no debe cifrarse.
      - Las carpetas y archivos que administra el sistema operativo
        ("System Volume Information", "$RECYCLE.BIN", ".Trashes"...).
        Cifrarlos no protege nada y puede dejar inservible la memoria USB
        al conectarla en otro equipo.
    """
    for raiz, dirs, archivos in os.walk(carpeta):
        # Al quitar una carpeta de esta lista, os.walk ya no entra en ella
        dirs[:] = [d for d in dirs if not se_excluye(d)]

        for nombre in archivos:
            if nombre == config.META_NOMBRE or se_excluye(nombre):
                continue
            yield os.path.join(raiz, nombre)


def cifrar_carpeta(carpeta: str, password: str, progreso=None) -> dict:
    """
    Cifra todos los archivos de la carpeta.

    Cada archivo original se reemplaza por su version .c3v en el mismo lugar,
    conservando la estructura de subcarpetas.

    Parametros:
        carpeta:  ruta del directorio o de la USB montada.
        password: contrasena maestra.
        progreso: funcion opcional que recibe (hechos, total, nombre_actual)
                  para que la interfaz muestre el avance.

    Devuelve un resumen: {"cifrados": n, "errores": n, "detalle": [...]}
    """
    if not os.path.isdir(carpeta):
        raise crypto_engine.ErrorCryptum(f"No existe la carpeta: {carpeta}")

    # No se permite cifrar dos veces: quedaria una capa sobre otra y el
    # usuario perderia la referencia de que contrasena abre cada nivel.
    if vault_meta.existe_meta(carpeta):
        raise crypto_engine.ErrorCryptum(
            "Esta ubicacion ya tiene un vault Cryptum activo. "
            "Desciframela primero antes de volver a cifrarla."
        )

    # Se listan los archivos ANTES de escribir nada, para no incluir
    # en la lista los .c3v que se van generando.
    pendientes = [r for r in _recorrer(carpeta) if not r.endswith(config.EXT)]

    if not pendientes:
        raise crypto_engine.ErrorCryptum(
            "No se encontraron archivos para cifrar en esa ubicacion."
        )

    salt = os.urandom(config.LARGO_SAL)

    # La sal se guarda primero. Ver el comentario en vault_meta.escribir_meta.
    vault_meta.escribir_meta(carpeta, salt)

    if progreso:
        progreso(-1, len(pendientes), "Derivando clave maestra (PBKDF2-SHA-512)...")
    clave = crypto_engine.derivar_clave(password, salt)

    hechos, errores, detalle = 0, 0, []

    for ruta in pendientes:
        relativa = os.path.relpath(ruta, carpeta)
        if progreso:
            progreso(hechos, len(pendientes), relativa)

        try:
            with open(ruta, "rb") as f:
                datos = f.read()

            blob = crypto_engine.cifrar_con_clave_maestra(
                datos, os.path.basename(ruta), clave
            )

            ruta_cifrada = ruta + config.EXT
            with open(ruta_cifrada, "wb") as f:
                f.write(blob)

            # Se confirma que la copia cifrada quedo completa antes de
            # destruir el original. Si no coincide, se conserva el original.
            if os.path.getsize(ruta_cifrada) != len(blob):
                raise crypto_engine.ErrorCryptum(
                    "escritura incompleta; el original no fue borrado"
                )

            borrar_sobrescribiendo(ruta)
            hechos += 1

        except Exception as e:
            errores += 1
            detalle.append(f"{relativa}: {e}")

    return {"cifrados": hechos, "errores": errores, "detalle": detalle}


def descifrar_carpeta(carpeta: str, password: str, progreso=None) -> dict:
    """
    Descifra todos los archivos .c3v de la carpeta y sus subcarpetas.

    Al terminar, si no hubo errores, se retira el archivo de metadatos para
    que la ubicacion quede lista para cifrarse de nuevo. Si algun archivo
    fallo, el metadato se conserva: sin el se perderia la sal y ya no habria
    forma de recuperar lo que quedo pendiente.

    Devuelve un resumen: {"descifrados": n, "errores": n, "detalle": [...]}
    """
    if not os.path.isdir(carpeta):
        raise crypto_engine.ErrorCryptum(f"No existe la carpeta: {carpeta}")

    salt = vault_meta.leer_meta(carpeta)

    if progreso:
        progreso(-1, 0, "Derivando clave maestra (PBKDF2-SHA-512)...")
    clave = crypto_engine.derivar_clave(password, salt)

    pendientes = [r for r in _recorrer(carpeta) if r.endswith(config.EXT)]

    if not pendientes:
        # Un vault vacio se desactiva para no dejar la ubicacion bloqueada.
        vault_meta.borrar_meta(carpeta)
        raise crypto_engine.ErrorCryptum(
            "No se encontraron archivos .c3v; el vault estaba vacio y "
            "quedo desactivado. Ya puedes cifrar esta ubicacion de nuevo."
        )

    hechos, errores, detalle = 0, 0, []

    for ruta in pendientes:
        relativa = os.path.relpath(ruta, carpeta)
        if progreso:
            progreso(hechos, len(pendientes), relativa)

        try:
            with open(ruta, "rb") as f:
                blob = f.read()

            try:
                nombre, datos = crypto_engine.descifrar_con_clave_maestra(blob, clave)
            except crypto_engine.ErrorVersionArchivoSuelto:
                # Este archivo se cifro en modo individual y trae su propia
                # sal. Se reintenta con la contrasena directa, igual que
                # hace la aplicacion web.
                nombre, datos = crypto_engine.descifrar_archivo(blob, password)

            # El nombre se adapta al sistema actual: no puede escapar de su
            # carpeta, y en Windows se ajusta a lo que ese sistema acepta.
            nombre = nombre_seguro(nombre)
            if not nombre:
                nombre = os.path.basename(ruta)[:-len(config.EXT)]

            destino = os.path.join(os.path.dirname(ruta), nombre)
            with open(destino, "wb") as f:
                f.write(datos)

            borrar_sobrescribiendo(ruta)
            hechos += 1

        except Exception as e:
            errores += 1
            detalle.append(f"{relativa}: {e}")

    # Solo se retira la sal si todo salio bien. Ver explicacion arriba.
    if errores == 0:
        vault_meta.borrar_meta(carpeta)

    return {"descifrados": hechos, "errores": errores, "detalle": detalle}
