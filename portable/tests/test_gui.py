"""
Cryptum Portable — Prueba de la interfaz grafica (VISTA)
Autor: C3r0d4y

Abre la ventana de verdad y la maneja desde el codigo, como si una persona
estuviera usandola: escribe la ruta, escribe la contrasena y presiona el
boton. Despues comprueba que el archivo quedo recuperado en el disco.

Los avisos emergentes se sustituyen por respuestas automaticas, porque una
ventana de confirmacion detendria la prueba esperando un clic que nadie
va a dar.

Se ejecuta con:   python3 tests/test_gui.py
Necesita un entorno grafico disponible.
"""

import os
import shutil
import sys
import tempfile
import time

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import tkinter
from tkinter import messagebox

from app.models import crypto_engine
from app.views import gui

VERDE, ROJO, FIN = "\033[92m", "\033[91m", "\033[0m"
fallos = 0
avisos = []


def verificar(nombre, condicion, detalle=""):
    """Imprime el resultado de una comprobacion y lleva la cuenta de fallos."""
    global fallos
    if condicion:
        print(f"  {VERDE}OK{FIN}    {nombre}")
    else:
        fallos += 1
        print(f"  {ROJO}FALLA{FIN} {nombre}  {detalle}")


# Los avisos emergentes se anotan en una lista en lugar de mostrarse.
# askyesno responde que si, que es lo que haria el usuario al confirmar.
messagebox.showerror = lambda t, m, **k: avisos.append(("error", m))
messagebox.showinfo = lambda t, m, **k: avisos.append(("info", m))
messagebox.askyesno = lambda t, m, **k: avisos.append(("confirma", m)) or True
gui.messagebox = messagebox


def esperar(ventana, condicion, segundos=90):
    """
    Deja correr la ventana hasta que se cumpla la condicion.
    Hay que llamar a update() para que Tkinter atienda la cola de mensajes
    del hilo de trabajo; sin eso la prueba se quedaria esperando para siempre.
    """
    limite = time.time() + segundos
    while time.time() < limite:
        ventana.update()
        if condicion():
            return True
        time.sleep(0.05)
    return False


print("\n[1] La ventana se construye")

tmp = tempfile.mkdtemp(prefix="cryptum_gui_")
try:
    v = gui.VentanaCryptum()
    v.update()

    verificar("Abre sin errores", isinstance(v, tkinter.Tk))
    verificar("Titulo con la marca C3r0d4y", "C3r0d4y" in v.title(), v.title())
    verificar("Tiene el campo de ruta", hasattr(v, "entrada"))
    verificar("Tiene el campo de contrasena", hasattr(v, "pwd"))
    verificar("La contrasena se escribe oculta", v.pwd.cget("show") == "•")
    verificar("Tiene el boton DESCIFRAR", v.btn_desc.cget("text") == "DESCIFRAR")
    verificar("Tiene el boton CIFRAR", v.btn_cif.cget("text") == "CIFRAR")
    verificar("Arranca en estado Listo", v.estado.cget("text") == "Listo.")

    print("\n[2] Mostrar y ocultar la contrasena")

    v.pwd.insert(0, "secreta")
    v.ver_pwd.set(True)
    v._alternar_pwd()
    verificar("El boton de mostrar deja ver el texto", v.pwd.cget("show") == "")
    v.ver_pwd.set(False)
    v._alternar_pwd()
    verificar("Se vuelve a ocultar", v.pwd.cget("show") == "•")
    v.pwd.delete(0, "end")

    print("\n[3] Rechaza lo que esta incompleto")

    avisos.clear()
    v.ruta.set("")
    v.pwd.delete(0, "end")
    verificar("Sin ruta no hace nada", v._validar(cifrando=False) is None)
    verificar("Y avisa al usuario", any("archivo o una carpeta" in m for _, m in avisos))

    avisos.clear()
    v.ruta.set(tmp)
    verificar("Sin contrasena no hace nada", v._validar(cifrando=False) is None)
    verificar("Y avisa al usuario", any("contrasena" in m.lower() for _, m in avisos))

    avisos.clear()
    v.pwd.insert(0, "corta")
    verificar("Rechaza contrasena corta al cifrar", v._validar(cifrando=True) is None)
    verificar("Y explica por que", any("8 caracteres" in m for _, m in avisos))
    v.pwd.delete(0, "end")

    print("\n[4] Descifra un archivo desde la ventana")

    # Se prepara un archivo cifrado, tal como llegaria desde la aplicacion web
    contenido = "ORDEN DE OPERACIONES - prueba de la ventana. Ñáéí".encode("utf-8")
    blob = crypto_engine.cifrar_archivo(contenido, "orden_gui.pdf", "Cl4v3-Ventana-2026")
    cifrado = os.path.join(tmp, "orden_gui.pdf.c3v")
    with open(cifrado, "wb") as f:
        f.write(blob)

    avisos.clear()
    v.ruta.set(cifrado)
    v.pwd.delete(0, "end")
    v.pwd.insert(0, "Cl4v3-Ventana-2026")
    v._descifrar()

    verificar("Bloquea los botones mientras trabaja",
              str(v.btn_desc.cget("state")) == "disabled")

    listo = esperar(v, lambda: not v.trabajando)
    verificar("Termina el trabajo", listo)
    verificar("Sin avisos de error", not [m for t, m in avisos if t == "error"],
              str(avisos))
    verificar("Muestra el resultado en verde",
              v.estado.cget("fg") == gui.COLOR_OK, v.estado.cget("fg"))
    verificar("Recupera el nombre original del archivo",
              os.path.exists(os.path.join(tmp, "orden_gui.pdf")))
    verificar("El contenido volvio intacto",
              open(os.path.join(tmp, "orden_gui.pdf"), "rb").read() == contenido)
    verificar("Limpia la contrasena de la pantalla al terminar", v.pwd.get() == "")
    verificar("Vuelve a habilitar los botones",
              str(v.btn_desc.cget("state")) == "normal")

    print("\n[5] Avisa cuando la contrasena esta mal")

    avisos.clear()
    v.pwd.insert(0, "contrasena-equivocada")
    v._descifrar()
    esperar(v, lambda: not v.trabajando)

    verificar("Avisa al usuario", any(t == "error" for t, _ in avisos))
    verificar("Con un mensaje que se entiende",
              any("Contrasena incorrecta" in m for _, m in avisos), str(avisos))
    verificar("Muestra el estado en rojo", v.estado.cget("fg") == gui.COLOR_ERROR)

    print("\n[6] Cifra una carpeta completa desde la ventana")

    carpeta = os.path.join(tmp, "expediente")
    os.makedirs(os.path.join(carpeta, "anexos"))
    with open(os.path.join(carpeta, "informe.txt"), "wb") as f:
        f.write(b"situacion del sector")
    with open(os.path.join(carpeta, "anexos", "mapa.dat"), "wb") as f:
        f.write(os.urandom(2048))

    avisos.clear()
    v.ruta.set(carpeta)
    v.pwd.delete(0, "end")
    v.pwd.insert(0, "Cl4v3-Carpeta-2026")
    v._cifrar()

    verificar("Pide confirmar antes de cifrar",
              any(t == "confirma" for t, _ in avisos))

    esperar(v, lambda: not v.trabajando)
    verificar("Cifra los 2 archivos", "2 archivo(s) cifrados" in v.estado.cget("text"),
              v.estado.cget("text"))
    verificar("Deja el archivo de metadatos",
              os.path.exists(os.path.join(carpeta, ".cryptum_meta.bin")))
    verificar("Ya no quedan los originales",
              not os.path.exists(os.path.join(carpeta, "informe.txt")))

    print("\n[7] Descifra esa misma carpeta")

    avisos.clear()
    v.pwd.delete(0, "end")
    v.pwd.insert(0, "Cl4v3-Carpeta-2026")
    v._descifrar()
    esperar(v, lambda: not v.trabajando)

    verificar("Recupera los 2 archivos",
              "2 archivo(s) recuperados" in v.estado.cget("text"), v.estado.cget("text"))
    verificar("El contenido volvio intacto",
              open(os.path.join(carpeta, "informe.txt"), "rb").read() == b"situacion del sector")
    verificar("Conserva la estructura de subcarpetas",
              os.path.exists(os.path.join(carpeta, "anexos", "mapa.dat")))
    verificar("Retira el archivo de metadatos",
              not os.path.exists(os.path.join(carpeta, ".cryptum_meta.bin")))

    v.destroy()

finally:
    shutil.rmtree(tmp, ignore_errors=True)

print()
if fallos == 0:
    print(f"  {VERDE}Todas las pruebas de la ventana pasaron.{FIN}\n")
else:
    print(f"  {ROJO}{fallos} prueba(s) fallaron.{FIN}\n")

sys.exit(1 if fallos else 0)
