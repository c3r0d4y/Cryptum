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

REM -- 1. Buscar Python ------------------------------------------------
REM  Encontrar Python en Windows es mas dificil de lo que parece, porque cada
REM  instalacion lo deja en un sitio distinto. Se busca en este orden:
REM
REM    1) py -3   El lanzador oficial. Funciona aunque Python NO se haya
REM               agregado al PATH. Es el mas fiable, por eso va primero.
REM    2) Lo que el PATH conozca como python o python3, descartando el atajo
REM       de la tienda de Microsoft (ver mas abajo).
REM    3) Las carpetas donde el instalador suele dejarlo, por si Python esta
REM       instalado pero nadie lo agrego al PATH ni instalo el lanzador.
REM
REM  El atajo de la tienda: Windows 10 y 11 traen un archivo llamado
REM  python.exe que NO es Python. Al ejecutarlo abre la Microsoft Store. Vive
REM  en la carpeta WindowsApps, asi que se reconoce por la ruta y ni siquiera
REM  se intenta ejecutar, para no abrirle la tienda al usuario en la cara.

set "PY="
set "PYPRUEBA=%TEMP%\cryptum_probar.py"
set "PYDIAG=%TEMP%\cryptum_diag.txt"
set "PYVER=%TEMP%\cryptum_version.txt"
del "%PYDIAG%" 2>nul

REM  Programita que sirve de prueba: deja la version en un archivo y sale con
REM  codigo 0 si vale, o 7 si es demasiado antigua.
REM
REM  Dos detalles que parecen rodeos y no lo son:
REM   - El codigo va en un archivo y no escrito en la misma linea, porque los
REM     parentesis y el simbolo mayor-que confunden al interprete de comandos
REM     de Windows cuando aparecen dentro de un bucle.
REM   - La version se escribe en un archivo en vez de leerla de la pantalla,
REM     porque para leer la pantalla habria que envolver el comando en mas
REM     comillas, y eso se rompe con rutas como "C:\Program Files\...".
>"%PYPRUEBA%"  echo import sys
>>"%PYPRUEBA%" echo v = sys.version_info
>>"%PYPRUEBA%" echo open(sys.argv[1], "w").write(str(v[0]) + "." + str(v[1]) + "." + str(v[2]))
>>"%PYPRUEBA%" echo sys.exit(0 if v ^>= (3, 8) else 7)

REM  1) El lanzador oficial
set "CAND=py -3"
call :probar

REM  2) Lo que el PATH conozca, saltando el atajo de la tienda
for /f "delims=" %%P in ('where python 2^>nul')  do call :probar_si_real "%%~P"
for /f "delims=" %%P in ('where python3 2^>nul') do call :probar_si_real "%%~P"

REM  3) Las carpetas habituales de instalacion.
REM     Se guarda antes en una variable con nombre sencillo porque el nombre
REM     original lleva parentesis y eso rompe los bucles del interprete.
set "PF86=%ProgramFiles(x86)%"
for /d %%D in ("%LOCALAPPDATA%\Programs\Python\Python3*") do call :probar_ruta "%%~fD\python.exe"
for /d %%D in ("%ProgramFiles%\Python3*")                do call :probar_ruta "%%~fD\python.exe"
for /d %%D in ("%PF86%\Python3*")                        do call :probar_ruta "%%~fD\python.exe"
for /d %%D in ("C:\Python3*")                            do call :probar_ruta "%%~fD\python.exe"

del "%PYPRUEBA%" "%PYVER%" 2>nul

if not defined PY (
    echo   ERROR: no se encontro Python 3.8 o superior.
    echo.
    echo   Esto es lo que se probo y que respondio cada cosa:
    echo.
    if exist "%PYDIAG%" type "%PYDIAG%"
    if not exist "%PYDIAG%" echo       ^(no se encontro ningun candidato que probar^)
    echo.
    echo   Que hacer segun lo que diga la lista de arriba:
    echo.
    echo   - Si no aparece ningun candidato, Python no esta instalado.
    echo     Bajalo de https://www.python.org/downloads/ y marca la casilla
    echo     "Add python.exe to PATH" en la primera pantalla del instalador.
    echo.
    echo   - Si aparece "demasiado antiguo", actualizalo desde esa misma
    echo     pagina. Hace falta 3.8 o superior.
    echo.
    echo   - Si aparece "atajo de la tienda", lo que tienes no es Python
    echo     sino un acceso directo a la Microsoft Store. Instala Python
    echo     de verdad desde python.org.
    echo.
    echo   - Si aparece "no responde", la instalacion esta rota.
    echo     Reinstala Python sin quitar componentes del instalador.
    echo.
    echo   Para reportar el problema, copia la lista de arriba completa.
    echo.
    pause
    exit /b 1
)

del "%PYDIAG%" 2>nul

echo   Interprete encontrado: %PY%  ^(Python %VERFINAL%^)
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

exit /b 0

REM ====================================================================
REM  SUBRUTINAS DE BUSQUEDA
REM ====================================================================

REM  Prueba el candidato guardado en CAND. Si sirve, lo deja en PY.
REM  Anota en el archivo de diagnostico que paso con el, para que el usuario
REM  vea algo util si al final no se encontro nada.
:probar
if defined PY goto :eof
del "%PYVER%" 2>nul
REM  Se ejecuta directamente, sin envolverlo en comillas de mas: asi funciona
REM  igual si CAND es "py -3" que si es la ruta completa a un python.exe que
REM  vive en una carpeta con espacios en el nombre.
%CAND% "%PYPRUEBA%" "%PYVER%" >nul 2>&1
set "COD=%ERRORLEVEL%"
set "VERHALL=desconocida"
if exist "%PYVER%" (
    set /p VERHALL=<"%PYVER%"
)
if "%COD%"=="0" (
    set "PY=%CAND%"
    set "VERFINAL=%VERHALL%"
    >>"%PYDIAG%" echo       %CAND%  -^> Python %VERHALL%  ^(sirve^)
    goto :eof
)
if "%COD%"=="7" (
    >>"%PYDIAG%" echo       %CAND%  -^> Python %VERHALL%  ^(demasiado antiguo^)
    goto :eof
)
>>"%PYDIAG%" echo       %CAND%  -^> no responde
goto :eof

REM  Prueba una ruta concreta a python.exe, si es que ese archivo existe.
:probar_ruta
if defined PY goto :eof
if not exist "%~1" goto :eof
set CAND="%~1"
call :probar
goto :eof

REM  Igual que la anterior, pero descarta el atajo de la Microsoft Store:
REM  vive en la carpeta WindowsApps y ejecutarlo abriria la tienda.
:probar_si_real
if defined PY goto :eof
echo "%~1" | findstr /i "WindowsApps" >nul && (
    >>"%PYDIAG%" echo       %~1  -^> atajo de la tienda, no es Python
    goto :eof
)
call :probar_ruta "%~1"
goto :eof
