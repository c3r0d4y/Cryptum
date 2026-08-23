"""
Cryptum Portable — Nombres de archivo entre Linux y Windows (MODELO)
Autor: C3r0d4y

El problema que resuelve este archivo:

Linux permite nombres de archivo que Windows rechaza. Un soldado puede
cifrar en Linux un archivo llamado "informe:04.txt" y, al descifrarlo en
Windows, el sistema se niega a crearlo. El material estaria correctamente
descifrado pero no habria forma de guardarlo en el disco.

Aqui se adapta el nombre al sistema donde se esta escribiendo. El contenido
NUNCA se toca: solo cambia como se llama el archivo al guardarlo.

Reglas de Windows que se aplican:
  - Prohibidos los caracteres  < > : " | ? *  y los de control.
  - Prohibidos ciertos nombres heredados de MS-DOS (CON, PRN, AUX, NUL,
    COM1 a COM9, LPT1 a LPT9), incluso con extension.
  - Un nombre no puede terminar en espacio ni en punto.
"""

import os

# Caracteres que Windows no acepta en un nombre de archivo
PROHIBIDOS_WINDOWS = '<>:"|?*'

# Nombres heredados de MS-DOS que Windows sigue reservando
RESERVADOS_WINDOWS = (
    {"CON", "PRN", "AUX", "NUL"}
    | {f"COM{i}" for i in range(1, 10)}
    | {f"LPT{i}" for i in range(1, 10)}
)

# Carpetas y archivos que el sistema operativo administra por su cuenta.
# Cifrarlos no protege nada y puede dejar inservible la memoria USB.
EXCLUIDOS = {
    # Windows
    "system volume information", "$recycle.bin", "recycler",
    "thumbs.db", "desktop.ini", "autorun.inf", "pagefile.sys",
    "hiberfil.sys", "swapfile.sys", "bootmgr", "ntuser.dat",
    # macOS
    ".spotlight-v100", ".fseventsd", ".trashes", ".ds_store",
    "._.trashes", ".documentrevisions-v100", ".temporaryitems",
    # Linux
    "lost+found", ".trash-1000",
}


def se_excluye(nombre: str) -> bool:
    """
    Indica si un archivo o carpeta debe dejarse en paz.

    Se compara en minusculas porque Windows no distingue mayusculas, y
    tambien se atrapan las papeleras de Linux, que llevan el numero de
    usuario al final (".Trash-1000", ".Trash-1001", etc.).
    """
    n = nombre.lower()
    return n in EXCLUIDOS or n.startswith(".trash-")


def nombre_seguro(nombre: str, para_windows: bool = None) -> str:
    """
    Devuelve un nombre que el sistema de archivos actual si acepta.

    En Linux y macOS el nombre se devuelve tal cual: ahi es valido.
    En Windows se adapta lo minimo necesario para poder guardarlo.

    Parametros:
        nombre:       nombre recuperado de dentro del archivo cifrado.
        para_windows: se aplican las reglas de Windows. Si no se indica,
                      se decide segun el sistema donde corre el programa.
                      El parametro existe para poder probar las dos
                      variantes desde cualquier equipo.

    Devuelve el nombre ya adaptado. Nunca devuelve una cadena vacia.
    """
    if para_windows is None:
        para_windows = (os.name == "nt")

    # Se descarta cualquier ruta que venga dentro del nombre: solo se
    # conserva el nombre puro. Esto impide que un archivo cifrado escriba
    # fuera de la carpeta de destino.
    nombre = nombre.replace("\\", "/").split("/")[-1]
    nombre = nombre.replace("\x00", "")

    if not para_windows:
        return nombre or "recuperado.bin"

    # Los caracteres que Windows no admite se cambian por un guion bajo.
    limpio = "".join(
        "_" if (c in PROHIBIDOS_WINDOWS or ord(c) < 32) else c
        for c in nombre
    )

    # Windows no deja que un nombre termine en espacio ni en punto.
    limpio = limpio.rstrip(" .")

    if not limpio:
        return "recuperado.bin"

    # Los nombres heredados de MS-DOS siguen reservados aunque lleven
    # extension: "CON.txt" tampoco se puede crear.
    base, ext = os.path.splitext(limpio)
    if base.upper() in RESERVADOS_WINDOWS:
        limpio = f"{base}_{ext}"

    return limpio
