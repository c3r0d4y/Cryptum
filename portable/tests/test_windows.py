"""
Cryptum Portable — Pruebas de compatibilidad con Windows
Autor: C3r0d4y

Comprueban que el material cifrado en Linux se pueda abrir en Windows y al
reves. Las reglas de nombres de Windows se pueden probar desde cualquier
sistema, porque la funcion acepta un parametro para forzarlas.

Se ejecutan con:   python3 tests/test_windows.py
"""

import os
import shutil
import sys
import tempfile

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.controllers import file_ctrl, folder_ctrl
from app.models import crypto_engine
from app.models.nombres import nombre_seguro, se_excluye

VERDE, ROJO, FIN = "\033[92m", "\033[91m", "\033[0m"
fallos = 0


def verificar(nombre, condicion, detalle=""):
    """Imprime el resultado de una comprobacion y lleva la cuenta de fallos."""
    global fallos
    if condicion:
        print(f"  {VERDE}OK{FIN}    {nombre}")
    else:
        fallos += 1
        print(f"  {ROJO}FALLA{FIN} {nombre}  {detalle}")


print("\n[1] Nombres validos en Linux que Windows rechaza")

# Cada caso: nombre original, y lo que debe quedar al guardarlo en Windows
casos = [
    ("informe:04.txt",    "informe_04.txt"),
    ("coord<norte>.dat",  "coord_norte_.dat"),
    ("ruta|alterna.map",  "ruta_alterna.map"),
    ("que?.pdf",          "que_.pdf"),
    ("anexo*.jpg",        "anexo_.jpg"),
    ('cita".txt',         "cita_.txt"),
    ("mapa.",             "mapa"),
    ("orden .txt",        "orden .txt"),
]
for original, esperado in casos:
    obtenido = nombre_seguro(original, para_windows=True)
    verificar(f"{original!r} se guarda como {esperado!r}",
              obtenido == esperado, f"dio {obtenido!r}")

print("\n[2] Nombres heredados de MS-DOS que Windows sigue reservando")

for reservado in ["CON.txt", "NUL.dat", "COM1.log", "LPT1.bin", "AUX.cfg", "PRN"]:
    obtenido = nombre_seguro(reservado, para_windows=True)
    verificar(f"{reservado!r} se renombra a {obtenido!r}",
              obtenido != reservado and obtenido.startswith(reservado.split(".")[0]))

verificar("Un nombre que solo se parece a uno reservado no se toca",
          nombre_seguro("CONTROL.txt", para_windows=True) == "CONTROL.txt")

print("\n[3] En Linux los nombres NO se alteran")

for original, _ in casos:
    verificar(f"{original!r} se conserva tal cual",
              nombre_seguro(original, para_windows=False) == original)

print("\n[4] Los acentos y las enes sobreviven en los dos sistemas")

for n in ["Batallón Ñuñez.pdf", "informe áéíóú.txt", "misión_2026.dat"]:
    verificar(f"{n!r} se conserva en Windows",
              nombre_seguro(n, para_windows=True) == n)
    verificar(f"{n!r} se conserva en Linux",
              nombre_seguro(n, para_windows=False) == n)

print("\n[5] Ningun nombre puede escribir fuera de su carpeta")

for ataque in ["../../etc/passwd", "..\\..\\Windows\\System32\\cmd.exe",
               "/etc/shadow", "C:\\Windows\\notepad.exe"]:
    for win in (True, False):
        r = nombre_seguro(ataque, para_windows=win)
        sistema = "Windows" if win else "Linux"
        verificar(f"{ataque!r} queda contenido en {sistema} ({r!r})",
                  "/" not in r and "\\" not in r and r not in ("", ".", ".."))

verificar("Un nombre vacio recibe uno de reemplazo",
          nombre_seguro("", para_windows=True) == "recuperado.bin")

print("\n[6] Carpetas y archivos del sistema que no deben cifrarse")

for sistema in ["System Volume Information", "$RECYCLE.BIN", "Thumbs.db",
                "desktop.ini", "autorun.inf", ".Spotlight-V100", ".DS_Store",
                "lost+found", ".Trash-1000", ".Trash-1042"]:
    verificar(f"{sistema!r} se deja en paz", se_excluye(sistema))

verificar("Se reconoce sin importar mayusculas",
          se_excluye("SYSTEM VOLUME INFORMATION") and se_excluye("thumbs.DB"))
verificar("Un archivo normal SI se cifra",
          not se_excluye("informe.pdf") and not se_excluye("Sistema de armas.doc"))

print("\n[7] Una USB con estructura de Windows se cifra sin tocar el sistema")

tmp = tempfile.mkdtemp(prefix="cryptum_win_")
try:
    # Se recrea lo que trae una memoria formateada en Windows
    os.makedirs(os.path.join(tmp, "System Volume Information"))
    os.makedirs(os.path.join(tmp, "$RECYCLE.BIN"))
    os.makedirs(os.path.join(tmp, "Documentos"))

    with open(os.path.join(tmp, "System Volume Information", "IndexerVolumeGuid"), "wb") as f:
        f.write(b"guid del sistema")
    with open(os.path.join(tmp, "$RECYCLE.BIN", "desktop.ini"), "wb") as f:
        f.write(b"config de papelera")
    with open(os.path.join(tmp, "Thumbs.db"), "wb") as f:
        f.write(b"miniaturas")
    with open(os.path.join(tmp, "Documentos", "orden.pdf"), "wb") as f:
        f.write(b"ORDEN DE OPERACIONES")
    with open(os.path.join(tmp, "informe.txt"), "wb") as f:
        f.write(b"situacion del sector")

    r = folder_ctrl.cifrar_carpeta(tmp, "ClaveDelSoldado2026")

    verificar("Cifra solo los 2 archivos del usuario",
              r["cifrados"] == 2 and r["errores"] == 0, str(r))
    verificar("No toca System Volume Information",
              os.path.exists(os.path.join(tmp, "System Volume Information", "IndexerVolumeGuid")))
    verificar("No toca la papelera de Windows",
              os.path.exists(os.path.join(tmp, "$RECYCLE.BIN", "desktop.ini")))
    verificar("No toca Thumbs.db", os.path.exists(os.path.join(tmp, "Thumbs.db")))
    verificar("Si cifro el documento del usuario",
              os.path.exists(os.path.join(tmp, "Documentos", "orden.pdf.c3v")))

    r = folder_ctrl.descifrar_carpeta(tmp, "ClaveDelSoldado2026")
    verificar("Recupera los 2 archivos del usuario",
              r["descifrados"] == 2 and r["errores"] == 0, str(r))
    verificar("El contenido volvio intacto",
              open(os.path.join(tmp, "Documentos", "orden.pdf"), "rb").read() == b"ORDEN DE OPERACIONES")

finally:
    shutil.rmtree(tmp, ignore_errors=True)

print("\n[8] Un archivo de solo lectura si se puede reemplazar")

tmp = tempfile.mkdtemp(prefix="cryptum_ro_")
try:
    ruta = os.path.join(tmp, "protegido.txt")
    with open(ruta, "wb") as f:
        f.write(b"documento marcado como solo lectura")
    os.chmod(ruta, 0o444)   # equivale al atributo de solo lectura de Windows

    cifrado = file_ctrl.cifrar(ruta, "ClaveDelSoldado2026", borrar_original=True)
    verificar("Genera el archivo cifrado", os.path.exists(cifrado))
    verificar("Y logra borrar el original de solo lectura", not os.path.exists(ruta))

finally:
    os.system(f"chmod -R u+w {tmp} 2>/dev/null")
    shutil.rmtree(tmp, ignore_errors=True)

print("\n[9] Un archivo cifrado en Linux se abre con nombre valido en Windows")

tmp = tempfile.mkdtemp(prefix="cryptum_cruz_")
try:
    # Se cifra con un nombre que Windows no admite, como pasaria en Linux
    contenido = "ORDEN FRAGMENTARIA - material clasificado".encode("utf-8")
    blob = crypto_engine.cifrar_archivo(contenido, "orden:04|final.pdf", "ClaveDelSoldado2026")
    ruta = os.path.join(tmp, "vault.c3v")
    with open(ruta, "wb") as f:
        f.write(blob)

    # El nombre real que quedaria guardado en cada sistema
    n, d = crypto_engine.descifrar_archivo(blob, "ClaveDelSoldado2026")
    verificar("El nombre viaja completo dentro del cifrado",
              n == "orden:04|final.pdf", n)
    verificar("En Windows se guarda como 'orden_04_final.pdf'",
              nombre_seguro(n, para_windows=True) == "orden_04_final.pdf")
    verificar("El contenido no se altera en ningun caso", d == contenido)

finally:
    shutil.rmtree(tmp, ignore_errors=True)

print()
if fallos == 0:
    print(f"  {VERDE}Todas las pruebas de Windows pasaron.{FIN}\n")
else:
    print(f"  {ROJO}{fallos} prueba(s) fallaron.{FIN}\n")

sys.exit(1 if fallos else 0)
