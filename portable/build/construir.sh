#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────
# Cryptum Portable — Generador del ejecutable portatil
# Autor: C3r0d4y
#
# Produce UN SOLO archivo ejecutable que no necesita instalacion:
#   Linux   -> dist/cryptum
#   Windows -> dist/cryptum.exe   (usar build/construir.bat)
#
# Uso:  bash build/construir.sh
# ─────────────────────────────────────────────────────────────────────
set -e

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
VENV="$RAIZ/build/venv"
cd "$RAIZ"

echo ""
echo "  Cryptum Portable — construccion del ejecutable"
echo "  ─────────────────────────────────────────────"

# ── 1. Buscar el interprete ────────────────────────────────────────────
PY=""
for c in python3 python; do
    if command -v "$c" >/dev/null 2>&1 && \
       "$c" -c "import sys; sys.exit(0 if sys.version_info >= (3,8) else 1)" 2>/dev/null; then
        PY="$c"; break
    fi
done

if [ -z "$PY" ]; then
    echo ""
    echo "  ERROR: no se encontro Python 3.8 o superior."
    echo "  Instalalo con:  sudo apt install python3 python3-venv python3-tk"
    echo ""
    exit 1
fi

echo "  Interprete: $($PY --version)"

# ── 2. Entorno virtual ─────────────────────────────────────────────────
# Las versiones recientes de Debian y Ubuntu no dejan instalar paquetes de
# Python en el sistema (error "externally-managed-environment", definido en
# la norma PEP 668). Es una proteccion legitima: instalar ahi puede romper
# herramientas del propio sistema operativo que dependen de esas librerias.
#
# Por eso se crea un entorno aislado en build/venv. Todo lo que hace falta
# para compilar se instala ahi dentro y no toca nada del equipo. La carpeta
# se puede borrar despues sin consecuencias.
echo "  [1/4] Preparando entorno aislado..."

if [ ! -d "$VENV" ]; then
    if ! "$PY" -m venv "$VENV" 2>/dev/null; then
        # Un intento fallido deja la carpeta a medias. Hay que borrarla: si
        # se queda, la proxima ejecucion la encuentra y sigue adelante con
        # un entorno roto, y el error resulta mucho mas dificil de entender.
        rm -rf "$VENV"

        # Debian y Ubuntu nombran el paquete con el numero de version
        # (python3.12-venv, python3.14-venv...). Se calcula el nombre exacto
        # para no mandar al usuario a instalar un paquete que no existe.
        VERS="$("$PY" -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')"

        echo ""
        echo "  ERROR: no se pudo crear el entorno virtual."
        echo "  Falta un paquete del sistema. Instalalo con:"
        echo ""
        echo "      sudo apt install python${VERS}-venv"
        echo ""
        echo "  Si ese nombre no existe en tu distribucion, prueba:"
        echo "      sudo apt install python3-venv python3-full"
        echo ""
        echo "  Si prefieres no instalar nada, descarga el ejecutable ya"
        echo "  compilado desde la pestana Actions del repositorio."
        echo ""
        exit 1
    fi
fi

PYV="$VENV/bin/python"

# ── 3. Dependencias, dentro del entorno aislado ────────────────────────
echo "  [2/4] Instalando dependencias..."
"$PYV" -m pip install --quiet --upgrade pip
"$PYV" -m pip install --quiet --upgrade pyinstaller cryptography

# La ventana grafica necesita tkinter, que en Debian y Ubuntu viene en un
# paquete aparte. Se avisa ahora y no cuando el usuario abra el programa.
if ! "$PYV" -c "import tkinter" 2>/dev/null; then
    echo ""
    echo "  AVISO: falta la libreria grafica de Python."
    echo "  El ejecutable se compilara, pero solo funcionara la consola."
    echo "  Para tener tambien la ventana:  sudo apt install python3-tk"
    echo "  Despues borra build/venv y vuelve a ejecutar este script."
    echo ""
fi

# ── 4. Compilar en un solo archivo ─────────────────────────────────────
echo "  [3/4] Compilando..."
"$PYV" -m PyInstaller \
    --onefile \
    --name cryptum \
    --windowed \
    --clean \
    --noconfirm \
    --distpath "$RAIZ/dist" \
    --workpath "$RAIZ/build/tmp" \
    --specpath "$RAIZ/build" \
    --hidden-import cryptography.hazmat.primitives.ciphers.aead \
    --exclude-module numpy \
    --exclude-module PIL \
    --exclude-module matplotlib \
    main.py >/dev/null

# ── 5. Comprobar que el ejecutable sirve ───────────────────────────────
# Compilar sin errores no garantiza que funcione: PyInstaller puede dejar
# fuera una dependencia y el fallo solo aparece al usarlo. Se cifra un
# archivo de prueba y se comprueba que el binario recien creado lo abre.
echo "  [4/4] Comprobando el ejecutable..."

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

"$PYV" -c "
import sys; sys.path.insert(0, '$RAIZ')
from app.models import crypto_engine
open('$TMP/prueba.txt.c3v','wb').write(
    crypto_engine.cifrar_archivo(b'PRUEBA DE CONSTRUCCION', 'prueba.txt', 'ClaveDePrueba2026'))
"

echo "ClaveDePrueba2026" | "$RAIZ/dist/cryptum" -d "$TMP/prueba.txt.c3v" >/dev/null

if ! grep -q "PRUEBA DE CONSTRUCCION" "$TMP/prueba.txt" 2>/dev/null; then
    echo ""
    echo "  ERROR: el ejecutable se creo pero no logro descifrar el archivo"
    echo "  de prueba. No lo entregues asi."
    echo ""
    exit 1
fi

# ── 6. Huella, para que quien lo reciba pueda verificarlo ──────────────
cd "$RAIZ/dist"
sha256sum cryptum > cryptum.sha256

echo ""
echo "  Listo:  $RAIZ/dist/cryptum"
echo "  Huella: $(cut -d' ' -f1 cryptum.sha256)"
echo ""
echo "  Comprobado: el ejecutable descifra correctamente."
echo "  Copialo a la memoria USB del soldado. No necesita instalacion"
echo "  ni permisos de administrador para ejecutarse."
echo ""
