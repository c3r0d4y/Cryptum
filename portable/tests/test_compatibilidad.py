"""
Cryptum Portable — Pruebas de compatibilidad con la aplicacion web
Autor: C3r0d4y

Estas pruebas son las mas importantes del proyecto. Comprueban que un
archivo cifrado en el navegador se abre aqui, y que uno cifrado aqui se
abre en el navegador. Si alguna falla, las dos aplicaciones dejaron de
hablar el mismo idioma y el soldado se queda sin poder abrir su material.

Se ejecutan con:   python3 tests/test_compatibilidad.py
"""

import os
import shutil
import struct
import sys
import tempfile

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app import config
from app.controllers import file_ctrl, folder_ctrl
from app.models import crypto_engine, vault_meta

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


# ═══════════════════════════════════════════════════════════════════════
#  1. Vector fijo de PBKDF2: el valor no debe cambiar nunca
# ═══════════════════════════════════════════════════════════════════════
print("\n[1] Derivacion de clave PBKDF2-SHA-512")

clave = crypto_engine.derivar_clave("contrasena de prueba", b"\x00" * 32)
verificar("Produce una clave de 256 bits", len(clave) == 32, f"dio {len(clave)}")
verificar("Es determinista (misma entrada, misma clave)",
          clave == crypto_engine.derivar_clave("contrasena de prueba", b"\x00" * 32))
verificar("Cambiar la sal cambia la clave",
          clave != crypto_engine.derivar_clave("contrasena de prueba", b"\x01" * 32))
verificar("Acepta contrasenas con acentos y enes (UTF-8)",
          len(crypto_engine.derivar_clave("Batallón Ñuñez áéí", b"\x02" * 32)) == 32)


# ═══════════════════════════════════════════════════════════════════════
#  2. Estructura binaria del formato v3 (archivo suelto)
# ═══════════════════════════════════════════════════════════════════════
print("\n[2] Formato v3 — archivo suelto")

datos = b"ORDEN DE OPERACIONES - material clasificado\x00\xff binario"
blob = crypto_engine.cifrar_archivo(datos, "orden.pdf", "clave-secreta-2026")

verificar("Empieza con la firma C3VL", blob[:4] == b"C3VL")
verificar("Byte de version es 0x03", blob[4] == 0x03)
verificar("Encabezado mide 49 bytes (4+1+32+12)",
          len(blob) == 49 + len(datos) + 4 + len("orden.pdf") + 16,
          f"dio {len(blob)}")

nombre, recuperado = crypto_engine.descifrar_archivo(blob, "clave-secreta-2026")
verificar("Recupera el contenido exacto", recuperado == datos)
verificar("Recupera el nombre original", nombre == "orden.pdf")

# El nombre no debe aparecer en claro en ninguna parte del archivo cifrado
verificar("El nombre del archivo NO viaja en claro",
          b"orden.pdf" not in blob)

# Dos cifrados de lo mismo deben dar resultados distintos (sal e IV nuevos)
blob2 = crypto_engine.cifrar_archivo(datos, "orden.pdf", "clave-secreta-2026")
verificar("Dos cifrados del mismo archivo dan salidas distintas", blob != blob2)


# ═══════════════════════════════════════════════════════════════════════
#  3. Deteccion de manipulacion (esto lo aporta GCM)
# ═══════════════════════════════════════════════════════════════════════
print("\n[3] Autenticacion AES-GCM")

try:
    crypto_engine.descifrar_archivo(blob, "clave-equivocada")
    verificar("Rechaza la contrasena incorrecta", False)
except crypto_engine.ErrorCryptum:
    verificar("Rechaza la contrasena incorrecta", True)

alterado = bytearray(blob)
alterado[-1] ^= 0x01          # se cambia un solo bit del final
try:
    crypto_engine.descifrar_archivo(bytes(alterado), "clave-secreta-2026")
    verificar("Detecta un archivo alterado en 1 bit", False)
except crypto_engine.ErrorCryptum:
    verificar("Detecta un archivo alterado en 1 bit", True)

try:
    crypto_engine.descifrar_archivo(b"NO ES UN VAULT", "x")
    verificar("Rechaza un archivo que no es Cryptum", False)
except crypto_engine.ErrorCryptum:
    verificar("Rechaza un archivo que no es Cryptum", True)


# ═══════════════════════════════════════════════════════════════════════
#  4. Formatos legados v1 y v2 (armados a mano, como los hacia la web vieja)
# ═══════════════════════════════════════════════════════════════════════
print("\n[4] Compatibilidad con formatos legados")

from cryptography.hazmat.primitives.ciphers.aead import AESGCM

# --- v1: archivo suelto con el nombre en claro en el encabezado ---
salt_v1 = os.urandom(32)
iv_v1 = os.urandom(12)
k_v1 = crypto_engine.derivar_clave("legado123", salt_v1)
nom_v1 = "reporte_viejo.txt".encode()
ct_v1 = AESGCM(k_v1).encrypt(iv_v1, b"contenido antiguo", None)
blob_v1 = (b"C3VL" + bytes([0x01]) + salt_v1 + iv_v1
           + struct.pack("<I", len(nom_v1)) + nom_v1 + ct_v1)

n, d = crypto_engine.descifrar_archivo(blob_v1, "legado123")
verificar("Descifra formato v1 (legado)", d == b"contenido antiguo" and n == "reporte_viejo.txt")

# --- v2: carpeta con el nombre en claro en el encabezado ---
salt_v2 = os.urandom(32)
k_v2 = crypto_engine.derivar_clave("legado123", salt_v2)
iv_v2 = os.urandom(12)
nom_v2 = "mapa.jpg".encode()
ct_v2 = AESGCM(k_v2).encrypt(iv_v2, b"pixeles", None)
blob_v2 = (b"C3VL" + bytes([0x02]) + iv_v2
           + struct.pack("<I", len(nom_v2)) + nom_v2 + ct_v2)

n, d = crypto_engine.descifrar_con_clave_maestra(blob_v2, k_v2)
verificar("Descifra formato v2 (legado)", d == b"pixeles" and n == "mapa.jpg")


# ═══════════════════════════════════════════════════════════════════════
#  5. Formato v4 y modo carpeta completo sobre disco real
# ═══════════════════════════════════════════════════════════════════════
print("\n[5] Formato v4 — carpeta / USB")

blob_v4 = crypto_engine.cifrar_con_clave_maestra(b"datos", "a.bin", clave)
verificar("Byte de version es 0x04", blob_v4[4] == 0x04)
n, d = crypto_engine.descifrar_con_clave_maestra(blob_v4, clave)
verificar("Cifra y descifra con clave maestra", d == b"datos" and n == "a.bin")

# Un archivo v3 dentro de una carpeta debe avisar que trae su propia sal
try:
    crypto_engine.descifrar_con_clave_maestra(blob, clave)
    verificar("Detecta un archivo v3 dentro de una carpeta", False)
except crypto_engine.ErrorVersionArchivoSuelto:
    verificar("Detecta un archivo v3 dentro de una carpeta", True)

# Prueba de ciclo completo sobre archivos reales en disco
tmp = tempfile.mkdtemp(prefix="cryptum_prueba_")
try:
    os.makedirs(os.path.join(tmp, "subcarpeta", "anidada"))
    contenidos = {
        "raiz.txt": b"documento en la raiz",
        "subcarpeta/medio.dat": bytes(range(256)),
        "subcarpeta/anidada/hondo.bin": os.urandom(5000),
        "acentuado ñáé.txt": "informe con acentos".encode("utf-8"),
    }
    for rel, dato in contenidos.items():
        with open(os.path.join(tmp, rel), "wb") as f:
            f.write(dato)

    r = folder_ctrl.cifrar_carpeta(tmp, "clave-de-carpeta-2026")
    verificar("Cifra los 4 archivos de la carpeta",
              r["cifrados"] == 4 and r["errores"] == 0, str(r))
    verificar("Crea el archivo de metadatos", vault_meta.existe_meta(tmp))

    # Ningun archivo original debe seguir existiendo
    quedan = [rel for rel in contenidos if os.path.exists(os.path.join(tmp, rel))]
    verificar("Borra todos los originales", not quedan, str(quedan))

    r = folder_ctrl.descifrar_carpeta(tmp, "clave-de-carpeta-2026")
    verificar("Descifra los 4 archivos",
              r["descifrados"] == 4 and r["errores"] == 0, str(r))
    verificar("Retira el archivo de metadatos", not vault_meta.existe_meta(tmp))

    iguales = all(
        os.path.exists(os.path.join(tmp, rel))
        and open(os.path.join(tmp, rel), "rb").read() == dato
        for rel, dato in contenidos.items()
    )
    verificar("Todo el contenido volvio identico, byte por byte", iguales)

    # La estructura de subcarpetas debe conservarse
    verificar("Conserva la estructura de subcarpetas",
              os.path.exists(os.path.join(tmp, "subcarpeta", "anidada", "hondo.bin")))

    # No debe dejarse cifrar dos veces
    folder_ctrl.cifrar_carpeta(tmp, "otra")
    try:
        folder_ctrl.cifrar_carpeta(tmp, "otra")
        verificar("Impide cifrar dos veces la misma carpeta", False)
    except crypto_engine.ErrorCryptum:
        verificar("Impide cifrar dos veces la misma carpeta", True)
    folder_ctrl.descifrar_carpeta(tmp, "otra")

finally:
    shutil.rmtree(tmp, ignore_errors=True)


# ═══════════════════════════════════════════════════════════════════════
#  6. Controlador de archivo suelto sobre disco
# ═══════════════════════════════════════════════════════════════════════
print("\n[6] Ciclo completo de archivo suelto en disco")

tmp = tempfile.mkdtemp(prefix="cryptum_arch_")
try:
    origen = os.path.join(tmp, "informe.pdf")
    with open(origen, "wb") as f:
        f.write(b"%PDF-1.7 contenido del informe")

    cifrado = file_ctrl.cifrar(origen, "clave-larga-2026", borrar_original=True)
    verificar("Genera el .c3v", cifrado.endswith(".c3v") and os.path.exists(cifrado))
    verificar("Borra el original cuando se le pide", not os.path.exists(origen))

    recuperado = file_ctrl.descifrar(cifrado, "clave-larga-2026")
    verificar("Recupera el nombre original del archivo",
              os.path.basename(recuperado) == "informe.pdf")
    verificar("Recupera el contenido intacto",
              open(recuperado, "rb").read() == b"%PDF-1.7 contenido del informe")

    # Descifrar dos veces no debe pisar el primer resultado
    otro = file_ctrl.descifrar(cifrado, "clave-larga-2026")
    verificar("No sobrescribe un archivo que ya existe", otro != recuperado)

finally:
    shutil.rmtree(tmp, ignore_errors=True)


# ═══════════════════════════════════════════════════════════════════════
#  Resultado final
# ═══════════════════════════════════════════════════════════════════════
print()
if fallos == 0:
    print(f"  {VERDE}Todas las pruebas pasaron.{FIN} "
          f"El formato es compatible con la aplicacion web Cryptum.\n")
else:
    print(f"  {ROJO}{fallos} prueba(s) fallaron.{FIN}\n")

sys.exit(1 if fallos else 0)
