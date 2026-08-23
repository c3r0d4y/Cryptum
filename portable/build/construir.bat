@echo off
REM ─────────────────────────────────────────────────────────────────────
REM  Cryptum Portable — Generador del ejecutable para Windows
REM  Autor: C3r0d4y
REM
REM  Produce dist\cryptum.exe: un solo archivo que no necesita instalacion
REM  y que corre desde la propia memoria USB.
REM
REM  Requisito: Python 3.8 o superior.
REM  Uso: doble clic a este archivo, o desde la consola:
REM         build\construir.bat
REM ─────────────────────────────────────────────────────────────────────
setlocal enabledelayedexpansion
cd /d "%~dp0.."

echo.
echo   Cryptum Portable - construccion del ejecutable para Windows
echo   ----------------------------------------------------------
echo.

REM ── 1. Buscar Python ────────────────────────────────────────────────
REM  Se prueban tres nombres porque Windows los reparte de forma distinta:
REM    py      -> el lanzador oficial. Funciona aunque Python NO se haya
REM               agregado al PATH durante la instalacion. Por eso va primero.
REM    python  -> el nombre habitual, solo si se marco "Add to PATH".
REM    python3 -> algunas instalaciones lo dejan con este nombre.
REM
REM  Se comprueba con "-c" y no con "--version" a proposito: Windows 10 y 11
REM  traen un atajo a la Microsoft Store llamado "python" que responde al
REM  comando sin ser Python. Al pedirle que ejecute codigo, ese atajo falla
REM  y no lo confundimos con una instalacion real.

set "PY="

for %%C in ("py -3" "python" "python3") do (
    if not defined PY (
        %%~C -c "import sys; sys.exit(0 if sys.version_info >= (3,8) else 1)" >nul 2>&1
        if not errorlevel 1 set "PY=%%~C"
    )
)

if not defined PY (
    echo   ERROR: no se encontro Python 3.8 o superior.
    echo.
    echo   Comprueba que tienes en tu equipo. Abre una consola y escribe:
    echo.
    echo       py -3 --version
    echo       python --version
    echo       where python
    echo.
    echo   Segun lo que veas:
    echo.
    echo   - Si "py -3 --version" responde con un numero de version, este
    echo     script ya deberia funcionar. Vuelve a intentarlo.
    echo.
    echo   - Si se abre la tienda de Microsoft, lo que tienes es un atajo,
    echo     no Python. Instala Python de verdad desde python.org.
    echo.
    echo   - Si no responde nada, instalalo desde:
    echo         https://www.python.org/downloads/
    echo     y marca la casilla "Add Python to PATH" en la primera pantalla
    echo     del instalador.
    echo.
    echo   - Si tu version es anterior a la 3.8, actualizala desde la misma
    echo     pagina.
    echo.
    pause
    exit /b 1
)

for /f "tokens=*" %%V in ('%PY% --version 2^>^&1') do set "VER=%%V"
echo   Interprete encontrado: %PY%  ^(%VER%^)
echo.

REM ── 2. Entorno aislado ──────────────────────────────────────────────
REM  Todo lo que hace falta para compilar se instala en build\venv y no toca
REM  el Python del equipo. La carpeta se puede borrar despues sin
REM  consecuencias. Ademas evita los problemas de permisos que aparecen
REM  cuando Python se instalo para todos los usuarios.
echo   [1/4] Preparando entorno aislado...

if not exist "%CD%\build\venv" (
    %PY% -m venv "%CD%\build\venv"
    if errorlevel 1 (
        rmdir /s /q "%CD%\build\venv" 2>nul
        echo.
        echo   ERROR: no se pudo crear el entorno virtual.
        echo   Reinstala Python desde https://www.python.org/downloads/
        echo   sin quitar ningun componente del instalador.
        echo.
        pause
        exit /b 1
    )
)

set "PYV=%CD%\build\venv\Scripts\python.exe"

REM ── 3. Dependencias, dentro del entorno aislado ─────────────────────
echo   [2/4] Instalando dependencias...
"%PYV%" -m pip install --quiet --upgrade pip
"%PYV%" -m pip install --quiet --upgrade pyinstaller cryptography
if errorlevel 1 (
    echo.
    echo   ERROR: no se pudieron instalar las dependencias.
    echo   Revisa que el equipo tenga salida a internet, o instalalas a mano:
    echo       "%PYV%" -m pip install pyinstaller cryptography
    echo.
    pause
    exit /b 1
)

REM ── 4. Compilar los dos ejecutables ─────────────────────────────────
REM  Se generan DOS archivos, igual que hace el propio Python con python.exe
REM  y pythonw.exe:
REM
REM    cryptum.exe      ventana grafica, sin consola negra detras. El de
REM                     doble clic (--windowed).
REM    cryptum-cli.exe  version de consola. Hace falta porque un ejecutable
REM                     compilado con --windowed NO tiene entrada ni salida
REM                     de texto en Windows: la linea de comandos no funciona
REM                     con el, ni siquiera para pedir la contrasena.
echo   [3/5] Compilando la version de ventana...
"%PYV%" -m PyInstaller ^
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
    echo.
    echo   ERROR: fallo la compilacion.
    echo   Si el antivirus bloqueo el proceso, agregalo a las excepciones y
    echo   vuelve a intentarlo: PyInstaller escribe un ejecutable nuevo y
    echo   algunos antivirus lo interpretan como sospechoso.
    echo.
    pause
    exit /b 1
)

echo   [4/5] Compilando la version de consola...
"%PYV%" -m PyInstaller ^
    --onefile ^
    --name cryptum-cli ^
    --console ^
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
    echo.
    echo   ERROR: fallo la compilacion de la version de consola.
    echo.
    pause
    exit /b 1
)

REM ── 5. Comprobar que el ejecutable sirve ────────────────────────────
REM  Compilar sin errores no garantiza que funcione: PyInstaller puede dejar
REM  fuera una dependencia y el fallo solo aparece al usarlo. Se cifra un
REM  archivo de prueba y se comprueba que el binario recien creado lo abre.
echo   [5/5] Comprobando los ejecutables...

"%PYV%" -c "import sys; sys.path.insert(0,'.'); from app.models import crypto_engine; open('build\\prueba.txt.c3v','wb').write(crypto_engine.cifrar_archivo(b'PRUEBA DE CONSTRUCCION','prueba.txt','ClaveDePrueba2026'))"
echo ClaveDePrueba2026| "%CD%\dist\cryptum-cli.exe" -d "build\prueba.txt.c3v" >nul

findstr /c:"PRUEBA DE CONSTRUCCION" "build\prueba.txt" >nul 2>&1
if errorlevel 1 (
    echo.
    echo   ERROR: el ejecutable se creo pero no logro descifrar el archivo
    echo   de prueba. No lo entregues asi.
    echo.
    del "build\prueba.txt*" 2>nul
    pause
    exit /b 1
)
del "build\prueba.txt" "build\prueba.txt.c3v" 2>nul

REM ── 6. Huella del binario, para poder verificarlo despues ───────────
echo   Calculando huella SHA-256...
certutil -hashfile "%CD%\dist\cryptum.exe" SHA256 > "%CD%\dist\cryptum.exe.sha256"
certutil -hashfile "%CD%\dist\cryptum-cli.exe" SHA256 > "%CD%\dist\cryptum-cli.exe.sha256"

echo.
echo   Listo:
echo     %CD%\dist\cryptum.exe        ventana grafica (doble clic)
echo     %CD%\dist\cryptum-cli.exe    linea de comandos
echo.
echo   Comprobado: el ejecutable descifra correctamente.
echo   Copia los dos a la memoria USB del soldado. No necesitan
echo   instalacion ni permisos de administrador para ejecutarse.
echo.
pause
