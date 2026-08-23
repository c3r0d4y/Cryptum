#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────
# Cryptum Portable — Generador del ejecutable portatil
# Autor: C3r0d4y
#
# Produce UN SOLO archivo ejecutable que no necesita instalacion:
#   Linux   -> dist/cryptum
#   Windows -> dist/cryptum.exe   (hay que correr este script en Windows)
#
# Uso:  bash build/construir.sh
# ─────────────────────────────────────────────────────────────────────
set -e

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
cd "$RAIZ"

echo ""
echo "  Cryptum Portable — construccion del ejecutable"
echo "  ─────────────────────────────────────────────"

# 1. Dependencias necesarias para compilar
echo "  [1/3] Revisando dependencias..."
python3 -m pip install --quiet --upgrade pyinstaller cryptography

# 2. Compilar en un solo archivo
echo "  [2/3] Compilando..."
python3 -m PyInstaller \
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
    main.py

# 3. Huella del binario, para que quien lo descargue pueda verificarlo
echo "  [3/3] Calculando huella SHA-256..."
cd "$RAIZ/dist"
BIN=$(ls cryptum cryptum.exe 2>/dev/null | head -1)
sha256sum "$BIN" > "$BIN.sha256"

echo ""
echo "  Listo:  $RAIZ/dist/$BIN"
echo "  Huella: $(cat "$BIN.sha256")"
echo ""
echo "  Copia ese archivo a la memoria USB del soldado. No necesita"
echo "  instalacion ni permisos de administrador para ejecutarse."
echo ""
