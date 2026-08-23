"""
Cryptum Portable — Borrado del archivo original (MODELO)
Autor: C3r0d4y

ADVERTENCIA IMPORTANTE PARA EL USUARIO
--------------------------------------
Esto es un borrado de MEJOR ESFUERZO, no un borrado forense certificado.

Se sobrescribe el archivo con ceros antes de eliminarlo, lo que sirve
contra la recuperacion sencilla con herramientas comunes. Pero en discos
SSD y memorias USB el controlador reparte las escrituras internamente
(wear leveling) y puede conservar copias del contenido viejo en zonas a
las que el sistema operativo ni siquiera puede llegar. Lo mismo pasa en
sistemas de archivos con journaling.

Si el material es de alta clasificacion, el equipo debe cifrarse a nivel
de disco completo (LUKS en Linux, BitLocker en Windows) ademas de usar
esta aplicacion.
"""

import os
import stat


def _quitar_solo_lectura(ruta: str) -> None:
    """
    Le quita al archivo el atributo de solo lectura.

    En Windows un archivo marcado como solo lectura no se puede sobrescribir
    NI borrar: el original quedaria en el disco junto a su version cifrada,
    que es justo lo contrario de lo que el usuario pidio. En Linux el permiso
    de escritura depende del dueno y este ajuste tambien ayuda.
    """
    try:
        os.chmod(ruta, os.stat(ruta).st_mode | stat.S_IWRITE)
    except OSError:
        pass


def borrar_sobrescribiendo(ruta: str) -> None:
    """
    Sobrescribe el archivo con ceros y despues lo elimina.

    Si la sobrescritura falla por cualquier motivo, se intenta el borrado
    normal de todas formas: es preferible eso a dejar el original intacto.
    """
    _quitar_solo_lectura(ruta)

    try:
        tam = os.path.getsize(ruta)
        with open(ruta, "r+b") as f:
            # Se escribe por bloques de 1 MB para no cargar en memoria un
            # archivo grande completo.
            bloque = b"\x00" * (1024 * 1024)
            restante = tam
            while restante > 0:
                n = min(len(bloque), restante)
                f.write(bloque[:n])
                restante -= n
            f.flush()
            os.fsync(f.fileno())
    except OSError:
        pass

    try:
        os.remove(ruta)
    except OSError:
        pass
