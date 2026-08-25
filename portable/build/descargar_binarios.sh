#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────
#  Cryptum Portable — Traer los ejecutables ya compilados al servidor
#  Autor: C3r0d4y
#
#  Copia a public/descargas/ los programas listos para usar (Windows y
#  Linux) que publica el Release de GitHub, y comprueba la huella
#  SHA-256 de cada uno antes de darlos por buenos.
#
#  ¿Para qué sirve esto?
#  Para que el botón "App portátil" de la página entregue los archivos
#  desde este mismo servidor. Así el personal puede descargarlos aunque
#  la instalación esté en una red cerrada, sin salida a internet.
#
#  Los ejecutables pesan decenas de megabytes y por eso NO se guardan
#  dentro del repositorio: se bajan aquí cuando hace falta.
#
#  Uso:
#      bash portable/build/descargar_binarios.sh            # última versión
#      bash portable/build/descargar_binarios.sh v1.4.5     # una versión concreta
# ─────────────────────────────────────────────────────────────────────

set -euo pipefail

REPO="c3r0d4y/cryptum"
# Si no se indica versión, se usa la que espera el modelo Descargas.php
ETIQUETA="${1:-}"

# Rutas: el script vive en portable/build/, la raíz del proyecto está dos niveles arriba
AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ="$(cd "$AQUI/../.." && pwd)"
DESTINO="$RAIZ/public/descargas"

# Los tres programas que produce la compilación automática
ARCHIVOS=(cryptum.exe cryptum-cli.exe cryptum)

echo
echo "  Cryptum Portable — descarga de ejecutables"
echo "  ─────────────────────────────────────────────"

# Si no dijeron versión, se lee del propio código de la aplicación web para
# que el servidor y la página nunca queden apuntando a versiones distintas.
if [[ -z "$ETIQUETA" ]]; then
    ETIQUETA="$(grep -oP "RELEASE_TAG\s*=\s*'\K[^']+" "$RAIZ/app/models/Descargas.php" || true)"
fi
if [[ -z "$ETIQUETA" ]]; then
    echo "  ERROR: no se pudo determinar la versión. Indícala a mano:" >&2
    echo "         bash portable/build/descargar_binarios.sh v1.4.5" >&2
    exit 1
fi

echo "  Versión:  $ETIQUETA"
echo "  Destino:  $DESTINO"
echo

mkdir -p "$DESTINO"

# Se prefiere la herramienta oficial de GitHub porque también funciona con
# repositorios privados; si no está instalada se recurre a curl.
if command -v gh >/dev/null 2>&1; then
    METODO="gh"
elif command -v curl >/dev/null 2>&1; then
    METODO="curl"
else
    echo "  ERROR: hace falta 'gh' o 'curl' para poder descargar." >&2
    exit 1
fi

BASE="https://github.com/$REPO/releases/download/$ETIQUETA"
FALLOS=0

for archivo in "${ARCHIVOS[@]}"; do
    echo "  → $archivo"

    # Se baja a un nombre temporal: si algo sale mal, el archivo que ya
    # estaba publicado en la página no se toca.
    TMP="$DESTINO/.$archivo.parcial"
    TMPH="$DESTINO/.$archivo.sha256.parcial"
    rm -f "$TMP" "$TMPH"

    if [[ "$METODO" == "gh" ]]; then
        gh release download "$ETIQUETA" -R "$REPO" -p "$archivo"        -O "$TMP"  --clobber 2>/dev/null || true
        gh release download "$ETIQUETA" -R "$REPO" -p "$archivo.sha256" -O "$TMPH" --clobber 2>/dev/null || true
    else
        curl -fsSL "$BASE/$archivo"        -o "$TMP"  2>/dev/null || true
        curl -fsSL "$BASE/$archivo.sha256" -o "$TMPH" 2>/dev/null || true
    fi

    if [[ ! -s "$TMP" ]]; then
        echo "     no se pudo descargar (¿existe el Release $ETIQUETA?)"
        rm -f "$TMP" "$TMPH"
        FALLOS=$((FALLOS + 1))
        continue
    fi

    # Comprobación de la huella: si el archivo llegó alterado o incompleto,
    # se descarta. Un ejecutable corrupto en manos del personal es peor que
    # no tener ninguno.
    if [[ -s "$TMPH" ]]; then
        ESPERADA="$(awk '{print $1}' "$TMPH" | tr -d '\r' | tr 'A-F' 'a-f')"
        REAL="$(sha256sum "$TMP" | awk '{print $1}')"
        if [[ "$ESPERADA" != "$REAL" ]]; then
            echo "     ¡LA HUELLA NO COINCIDE! El archivo se descarta."
            echo "     esperada: $ESPERADA"
            echo "     obtenida: $REAL"
            rm -f "$TMP" "$TMPH"
            FALLOS=$((FALLOS + 1))
            continue
        fi
        mv -f "$TMPH" "$DESTINO/$archivo.sha256"
        echo "     huella verificada: ${REAL:0:16}…"
    else
        echo "     aviso: el Release no trae huella para este archivo"
        rm -f "$TMPH"
    fi

    mv -f "$TMP" "$DESTINO/$archivo"
    # El binario de Linux se sirve tal cual, pero conviene que sea ejecutable
    # por si alguien lo copia directo desde el servidor.
    [[ "$archivo" == "cryptum" ]] && chmod 755 "$DESTINO/$archivo"
    echo "     listo ($(du -h "$DESTINO/$archivo" | cut -f1))"
done

echo
if [[ "$FALLOS" -eq 0 ]]; then
    echo "  Todo en orden. El botón \"App portátil\" ya entrega los programas"
    echo "  desde este servidor, sin depender de internet."
else
    echo "  Terminó con $FALLOS archivo(s) sin descargar."
    echo "  Los que falten se seguirán ofreciendo desde GitHub."
fi
echo
