#!/usr/bin/env python3
"""
Cryptum Portable — Punto de entrada
Autor: C3r0d4y

Companion de escritorio de la aplicacion web Cryptum. Descifra y cifra los
archivos .c3v sin ninguna conexion a internet y sin instalar nada.

Como decide que interfaz abrir:
  - Sin argumentos  -> ventana grafica (o consola si el equipo no tiene Tk).
  - Con argumentos  -> linea de comandos.
  - Con --gui       -> ventana grafica siempre.

Uso rapido:
    python3 main.py                        ventana grafica
    python3 main.py -d secreto.pdf.c3v     descifrar un archivo
    python3 main.py -c secreto.pdf         cifrar un archivo
    python3 main.py -d /media/usb          descifrar una USB completa
"""

import os
import sys

# Permite ejecutar el programa desde cualquier carpeta y tambien
# empaquetado con PyInstaller, donde las rutas cambian.
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))


def main() -> int:
    """Elige la interfaz adecuada y arranca la aplicacion."""
    args = sys.argv[1:]
    quiere_gui = not args or "--gui" in args

    if quiere_gui:
        try:
            from app.views import gui
            return gui.ejecutar()
        except ImportError:
            # Algunos Linux vienen sin el paquete grafico de Python.
            # En lugar de fallar, se explica como instalarlo y se ofrece
            # la consola, que siempre funciona.
            print(
                "\n  Este equipo no tiene la libreria grafica de Python.\n"
                "  Instalala con:   sudo apt install python3-tk\n"
                "  Mientras tanto puedes usar la consola:  "
                "python3 main.py --help\n"
            )
            return 1

    from app.views import cli
    return cli.ejecutar(args)


if __name__ == "__main__":
    sys.exit(main())
