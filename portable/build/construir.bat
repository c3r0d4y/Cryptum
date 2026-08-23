@echo off
REM ─────────────────────────────────────────────────────────────────────
REM  Cryptum Portable — Generador del ejecutable para Windows
REM  Autor: C3r0d4y
REM
REM  Produce dist\cryptum.exe: un solo archivo que no necesita instalacion
REM  y que corre desde la propia memoria USB.
REM
REM  Requisito: Python 3.8 o superior instalado y agregado al PATH.
REM  Uso: doble clic a este archivo, o desde la consola:
REM         build\construir.bat
REM ─────────────────────────────────────────────────────────────────────
setlocal
cd /d "%~dp0.."

echo.
echo   Cryptum Portable - construccion del ejecutable para Windows
echo   ----------------------------------------------------------

REM 1. Comprobar que Python este disponible
python --version >nul 2>&1
if errorlevel 1 (
    echo.
    echo   ERROR: no se encontro Python.
    echo   Instalalo desde https://www.python.org/downloads/
    echo   y marca la casilla "Add Python to PATH" durante la instalacion.
    echo.
    pause
    exit /b 1
)

REM 2. Dependencias necesarias para compilar
echo   [1/3] Revisando dependencias...
python -m pip install --quiet --upgrade pyinstaller cryptography
if errorlevel 1 (
    echo   ERROR: no se pudieron instalar las dependencias.
    pause
    exit /b 1
)

REM 3. Compilar en un solo archivo
REM    --windowed evita que se abra una consola negra detras de la ventana
echo   [2/3] Compilando...
python -m PyInstaller ^
    --onefile ^
    --name cryptum ^
    --windowed ^
    --clean ^
    --noconfirm ^
    --distpath "%CD%\dist" ^
    --workpath "%CD%\build\tmp" ^
    --specpath "%CD%\build" ^
    --hidden-import cryptography.hazmat.primitives.ciphers.aead ^
    --exclude-module numpy ^
    --exclude-module PIL ^
    --exclude-module matplotlib ^
    main.py
if errorlevel 1 (
    echo   ERROR: fallo la compilacion.
    pause
    exit /b 1
)

REM 4. Huella del binario, para poder verificarlo despues
echo   [3/3] Calculando huella SHA-256...
certutil -hashfile "%CD%\dist\cryptum.exe" SHA256 > "%CD%\dist\cryptum.exe.sha256"

echo.
echo   Listo:  %CD%\dist\cryptum.exe
echo.
echo   Copia ese archivo a la memoria USB del soldado. No necesita
echo   instalacion ni permisos de administrador para ejecutarse.
echo.
pause
