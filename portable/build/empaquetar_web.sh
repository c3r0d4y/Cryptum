#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────
# Cryptum Portable — Genera el ZIP que se descarga desde la web
# Autor: C3r0d4y
#
# Arma el paquete que el soldado baja desde http://<servidor>/cryptum/
# y lo deja publicado con su huella SHA-256 para que pueda verificarlo.
#
# Uso:  bash build/empaquetar_web.sh
# ─────────────────────────────────────────────────────────────────────
set -e

# La portable vive dentro del repositorio de la aplicacion web, en la
# carpeta "portable/". El ZIP se publica en la carpeta de descargas de la web.
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
WEB="$(cd "$RAIZ/.." && pwd)/public/descargas"
ZIP="$WEB/cryptum-portable.zip"

mkdir -p "$WEB"
cd "$(dirname "$RAIZ")"

echo "  Empaquetando Cryptum Portable..."

# El ZIP se arma con la carpeta "cryptum_portable" dentro, para que al
# descomprimirlo el usuario obtenga una carpeta con nombre claro y no un
# monton de archivos sueltos.
rm -f "$ZIP"
TMP="$(mktemp -d)"
cp -r "$RAIZ" "$TMP/cryptum_portable"
(cd "$TMP" && zip -r -q "$ZIP" cryptum_portable \
    -x "*/__pycache__/*" "*/.git/*" "*/build/tmp/*" "*/dist/*" "*.pyc")
rm -rf "$TMP"

# Huella publicada junto al ZIP: quien descarga puede comprobar que el
# archivo no fue alterado en el camino.
cd "$WEB"
sha256sum "$(basename "$ZIP")" > "$(basename "$ZIP").sha256"

echo "  Paquete:  $ZIP"
echo "  Tamano:   $(du -h "$ZIP" | cut -f1)"
echo "  Huella:   $(cut -d' ' -f1 "$(basename "$ZIP").sha256")"
