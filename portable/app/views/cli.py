"""
Cryptum Portable — Interfaz de linea de comandos (VISTA)
Autor: C3r0d4y

Esta vista sirve para el operador tecnico y para automatizar tareas desde
un script. No muestra ventanas y funciona en cualquier terminal.

La contrasena NUNCA se pide como argumento del comando: se escribe cuando
el programa la solicita, sin que aparezca en pantalla. Si fuera un
argumento quedaria registrada en el historial de la terminal y visible
para cualquiera que liste los procesos del equipo.
"""

import argparse
import getpass
import os
import sys

from app import config
from app.controllers import file_ctrl, folder_ctrl
from app.models.crypto_engine import ErrorCryptum


def _banner() -> str:
    """Encabezado que identifica la herramienta."""
    return (
        f"\n  {config.APP_NOMBRE} v{config.APP_VERSION} — {config.APP_AUTOR}\n"
        "  AES-256-GCM · PBKDF2-SHA-512 · 100% fuera de linea\n"
    )


def _leer_password(mensaje: str) -> str:
    """
    Lee una contrasena sin mostrarla en pantalla.

    Cuando el programa se usa a mano, la contrasena se teclea y no aparece
    en la pantalla. Cuando se usa dentro de un script, llega por la entrada
    estandar y se lee de ahi.

    Ese segundo caso necesita atencion especial en Windows: alli getpass lee
    directamente del teclado de la consola e ignora lo que llegue por una
    tuberia, asi que el programa se quedaria esperando para siempre una tecla
    que nadie va a pulsar. Por eso se comprueba primero si la entrada viene
    de una terminal o no.
    """
    if sys.stdin is not None and sys.stdin.isatty():
        return getpass.getpass(mensaje)

    # La entrada no es una terminal: viene de un script o de una tuberia.
    # No hay pantalla que proteger, asi que se lee de forma directa.
    linea = sys.stdin.readline()
    if not linea:
        print("  ! No se recibio ninguna contrasena por la entrada estandar.")
        sys.exit(1)
    return linea.rstrip("\r\n")


def _pedir_password(confirmar: bool) -> str:
    """
    Pide la contrasena sin mostrarla en pantalla.
    Cuando se va a cifrar la pide dos veces: una contrasena mal tecleada
    al cifrar significa perder el archivo para siempre.
    """
    pwd = _leer_password("  Contrasena: ")
    if not pwd:
        print("  ! La contrasena no puede estar vacia.")
        sys.exit(1)

    if confirmar:
        # Al leer de un script no tiene sentido pedir la confirmacion: la
        # contrasena no se tecleo, asi que no puede haber un error de tecleo.
        if sys.stdin is None or not sys.stdin.isatty():
            if len(pwd) < 8:
                print("  ! Usa al menos 8 caracteres. Mientras mas larga, mejor.")
                sys.exit(1)
            return pwd

        pwd2 = _leer_password("  Repite la contrasena: ")
        if pwd != pwd2:
            print("  ! Las contrasenas no coinciden.")
            sys.exit(1)
        if len(pwd) < 8:
            print("  ! Usa al menos 8 caracteres. Mientras mas larga, mejor.")
            sys.exit(1)

    return pwd


def _progreso(hechos: int, total: int, nombre: str) -> None:
    """Muestra el avance en una sola linea que se va reescribiendo."""
    if hechos < 0:
        sys.stdout.write(f"\r  {nombre}".ljust(78))
    else:
        sys.stdout.write(f"\r  [{hechos + 1}/{total}] {nombre[:55]}".ljust(78))
    sys.stdout.flush()


def ejecutar(argv=None) -> int:
    """Punto de entrada de la linea de comandos. Devuelve el codigo de salida."""
    ap = argparse.ArgumentParser(
        prog="cryptum",
        description=f"{config.APP_NOMBRE} — descifra y cifra archivos "
                    f"del formato Cryptum (.c3v) sin conexion.",
        epilog="Ejemplos:\n"
               "  cryptum -d informe.pdf.c3v\n"
               "  cryptum -c informe.pdf --borrar\n"
               "  cryptum -d /media/usb --carpeta\n",
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )

    grupo = ap.add_mutually_exclusive_group(required=True)
    grupo.add_argument("-d", "--descifrar", metavar="RUTA",
                       help="archivo .c3v o carpeta a descifrar")
    grupo.add_argument("-c", "--cifrar", metavar="RUTA",
                       help="archivo o carpeta a cifrar")

    ap.add_argument("--carpeta", action="store_true",
                    help="tratar la ruta como carpeta o USB completa")
    ap.add_argument("-o", "--salida", metavar="DIR",
                    help="carpeta donde dejar el resultado (solo archivo suelto)")
    ap.add_argument("--borrar", action="store_true",
                    help="borrar el archivo de origen al terminar")
    ap.add_argument("--gui", action="store_true",
                    help="abrir la ventana grafica en lugar de la consola")

    args = ap.parse_args(argv)
    print(_banner())

    ruta = args.descifrar or args.cifrar
    es_cifrado = args.cifrar is not None
    modo_carpeta = args.carpeta or os.path.isdir(ruta)

    try:
        pwd = _pedir_password(confirmar=es_cifrado)

        if modo_carpeta:
            if es_cifrado:
                r = folder_ctrl.cifrar_carpeta(ruta, pwd, _progreso)
                print(f"\n\n  OK · {r['cifrados']} archivo(s) cifrados con AES-256-GCM.")
            else:
                r = folder_ctrl.descifrar_carpeta(ruta, pwd, _progreso)
                print(f"\n\n  OK · {r['descifrados']} archivo(s) recuperados.")

            if r["errores"]:
                print(f"  ! {r['errores']} archivo(s) con problemas:")
                for linea in r["detalle"][:10]:
                    print(f"      - {linea}")
        else:
            if es_cifrado:
                salida = file_ctrl.cifrar(ruta, pwd, args.salida, args.borrar)
                print(f"  OK · Archivo cifrado: {salida}")
            else:
                salida = file_ctrl.descifrar(ruta, pwd, args.salida, args.borrar)
                print(f"  OK · Archivo recuperado: {salida}")

        print()
        return 0

    except ErrorCryptum as e:
        print(f"\n  ! {e}\n")
        return 1
    except KeyboardInterrupt:
        print("\n  Operacion cancelada por el usuario.\n")
        return 130
    finally:
        # Se limpia la referencia a la contrasena en cuanto deja de usarse.
        # Python no garantiza que el dato se borre de la memoria fisica, pero
        # reduce el tiempo que permanece accesible.
        pwd = None
