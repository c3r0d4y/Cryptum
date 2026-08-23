# Cryptum Portable — v1.0.0

**Autor: C3r0d4y**

Aplicación de escritorio que **descifra y cifra los archivos `.c3v`** creados
por la aplicación web Cryptum, **sin conexión a internet y sin instalación**.

Vive en la carpeta `portable/` del repositorio de [Cryptum](../README.md), para
que las constantes criptográficas de las dos aplicaciones viajen siempre en el
mismo commit y no puedan desincronizarse.

Está pensada para el personal que se despliega: el material se cifra en el
servidor antes de salir, se transporta en una USB, y en el destino se abre con
este programa en cualquier equipo Linux o Windows, sin necesidad de alcanzar
el servidor.

---

## Diagrama de uso

```
   EN LA BASE                    TRASLADO                  EN EL DESTINO
   ──────────                    ────────                  ─────────────

  Aplicación web              Memoria USB                Cryptum Portable
   Cryptum                                                (este programa)

  ┌─────────────┐            ┌─────────────┐            ┌─────────────┐
  │  archivo    │            │             │            │  cryptum    │
  │  original   │            │  orden.c3v  │            │  (portátil) │
  │      ↓      │  ───────►  │  mapa.c3v   │  ───────►  │      ↓      │
  │  CIFRAR     │            │  fotos.c3v  │            │  DESCIFRAR  │
  │  AES-256    │            │             │            │  AES-256    │
  └─────────────┘            └─────────────┘            └─────────────┘
        │                            │                          │
        │                            │                          │
   contraseña                 material ilegible           contraseña
   del soldado                 para cualquiera            del soldado
                                                                 │
                                                                 ▼
                                                        archivos originales
                                                            recuperados

  La contraseña NUNCA viaja con la USB. Solo la sabe el soldado.
  Si la USB se pierde o la captura el adversario, el contenido
  es indescifrable sin esa contraseña.
```

---

## Instalación

### Opción A — Ejecutable listo (recomendada para el terreno)

No requiere Python ni permisos de administrador. Se copia y se ejecuta.

| Sistema | Archivo | Cómo se abre |
|---|---|---|
| Windows | `cryptum.exe` | Doble clic |
| Linux | `cryptum` | `chmod +x cryptum` y luego `./cryptum` |

Puede correr desde la propia memoria USB.

### Opción B — Desde el código fuente

```bash
# 1. Instalar la única dependencia
pip install cryptography

# 2. En Linux, la ventana gráfica necesita además:
sudo apt install python3-tk        # Debian / Ubuntu
sudo dnf install python3-tkinter   # Fedora / RHEL

# 3. Ejecutar
python3 main.py
```

En Windows, Python ya trae la parte gráfica: basta con `pip install cryptography`.

---

## Uso

### Ventana gráfica

```bash
python3 main.py          # o simplemente doble clic al ejecutable
```

Tres pasos: elegir el archivo o la carpeta, escribir la contraseña,
presionar **DESCIFRAR** o **CIFRAR**.

### Línea de comandos

```bash
# Descifrar un archivo recibido
cryptum -d orden_frag.pdf.c3v

# Descifrar una memoria USB completa
cryptum -d /media/usb --carpeta
cryptum -d E:\ --carpeta            # Windows

# Cifrar material generado en el terreno
cryptum -c informe_situacion.pdf --borrar

# Cifrar una carpeta entera antes de moverse
cryptum -c /home/soldado/expediente --carpeta

# Dejar el resultado en otra ubicación
cryptum -d orden.pdf.c3v -o /home/soldado/documentos
```

La contraseña **nunca** se pasa como argumento: el programa la pide aparte y no
se muestra al escribirla. Si fuera un argumento quedaría guardada en el
historial de la terminal y visible para cualquiera que liste los procesos.

---

## Compatibilidad con la aplicación web

Este programa habla exactamente el mismo formato binario que la web. Lo que se
cifra en un lado se abre en el otro, en las dos direcciones.

| Versión | Origen | Estructura |
|---|---|---|
| `0x03` | Archivo suelto (actual) | `[C3VL][03][SAL 32][IV 12][CIFRADO+TAG]` |
| `0x04` | Carpeta / USB (actual) | `[C3VL][04][IV 12][CIFRADO+TAG]` |
| `0x01` | Archivo suelto (legado) | `[C3VL][01][SAL 32][IV 12][LARGO 4][NOMBRE][CIFRADO+TAG]` |
| `0x02` | Carpeta / USB (legado) | `[C3VL][02][IV 12][LARGO 4][NOMBRE][CIFRADO+TAG]` |

En las versiones actuales el nombre del archivo viaja **dentro** del contenido
cifrado: quien intercepte el `.c3v` no sabe siquiera cómo se llamaba el
archivo. Las versiones legadas lo guardaban en claro y se mantienen solo para
poder abrir material antiguo.

La sal de una carpeta se guarda en `.cryptum_meta.bin` (`[C3VM][SAL 32]`).
**Ese archivo no debe borrarse**: sin él la carpeta ya no se puede recuperar
aunque se tenga la contraseña correcta.

---

## Criptografía

| Elemento | Valor |
|---|---|
| Cifrado | AES-256-GCM, etiqueta de autenticación de 128 bits |
| Vector de inicialización | 96 bits, aleatorio y distinto por archivo |
| Derivación de clave | PBKDF2-SHA-512, 210 000 repeticiones |
| Sal | 256 bits, aleatoria |
| Fuente de aleatoriedad | `os.urandom`, el generador del sistema operativo |

### Por qué publicar este programa no debilita el cifrado

Es la pregunta correcta y la respuesta es clara: **la seguridad de Cryptum no
está en el algoritmo, está en la contraseña del usuario.**

1. **Principio de Kerckhoffs.** El algoritmo y el formato ya eran públicos:
   están en el JavaScript que cualquiera descarga al abrir la página web.
   Reimplementarlos en Python no revela nada nuevo.
2. **El programa no lleva secretos.** No hay clave incrustada, ni certificado,
   ni llave del servidor. Si el adversario captura el equipo con la aplicación
   instalada, obtiene un descifrador que sin la contraseña es tan inútil como
   el archivo cifrado.
3. **Criptografía estándar y auditada.** AES-GCM y PBKDF2 son estándares
   públicos, revisados durante décadas. Un cifrado que dependiera de mantener
   su diseño en secreto sería mucho más débil, no más fuerte.
4. **Menor superficie de ataque que la web.** Funciona sin red, sin servidor y
   sin navegador.

---

## Verificación

```bash
python3 tests/test_compatibilidad.py   # 31 pruebas del formato y el cifrado
python3 tests/test_gui.py              # 34 pruebas de la ventana gráfica
python3 tests/test_windows.py          # 60 pruebas de compatibilidad Linux ↔ Windows
```

**Formato y cifrado (31):** vectores de derivación de clave, estructura binaria
de los cuatro formatos, detección de archivos alterados, ciclos completos de
cifrado y descifrado sobre disco, y recuperación byte por byte.

**Ventana gráfica (34):** abre la ventana de verdad y la maneja desde el código
como lo haría una persona — escribe la ruta, escribe la contraseña y presiona el
botón — y después comprueba el resultado en el disco. Cubre también los casos que
deben rechazarse (sin archivo, sin contraseña, contraseña corta o equivocada) y
que la contraseña se borre de la pantalla al terminar. Necesita entorno gráfico;
en Debian y Ubuntu requiere `sudo apt install python3-tk`.

**Linux ↔ Windows (60):** nombres válidos en un sistema y prohibidos en el otro,
nombres reservados de MS-DOS, acentos y eñes, intentos de escribir fuera de la
carpeta, exclusión de las carpetas del sistema en una USB con formato de Windows,
y archivos marcados como solo lectura. Se ejecutan desde cualquier sistema.

---

## Arquitectura (MVC)

```
cryptum_portable/
├── main.py                          → punto de entrada; elige ventana o consola
├── app/
│   ├── config.py                    → constantes del formato (espejo del app.js web)
│   ├── models/                      → MODELO
│   │   ├── crypto_engine.py         →   AES-256-GCM, PBKDF2, los 4 formatos
│   │   ├── nombres.py               →   nombres válidos en Linux y en Windows
│   │   ├── vault_meta.py            →   archivo .cryptum_meta.bin
│   │   └── borrado_seguro.py        →   sobrescritura del original
│   ├── controllers/                 → CONTROLADOR
│   │   ├── file_ctrl.py             →   un archivo suelto
│   │   └── folder_ctrl.py           →   carpeta o USB completa
│   └── views/                       → VISTA
│       ├── gui.py                   →   ventana Tkinter
│       └── cli.py                   →   línea de comandos
├── tests/
│   ├── test_compatibilidad.py       → pruebas contra el formato de la web
│   ├── test_gui.py                  → pruebas de la ventana gráfica
│   └── test_windows.py              → pruebas de compatibilidad Linux ↔ Windows
└── build/
    ├── construir.sh                 → genera el ejecutable en Linux
    ├── construir.bat                → genera cryptum.exe en Windows
    └── empaquetar_web.sh            → genera el ZIP que publica el servidor
```

---

## Diferencias entre Linux y Windows

El cifrado es idéntico en los dos sistemas: mismo algoritmo, mismo formato,
mismos archivos. Lo que cambia es cómo cada sistema guarda los nombres, y el
programa se encarga de eso solo.

| Situación | Qué hace el programa |
|---|---|
| Un archivo cifrado en Linux se llama `informe:04.txt` | Windows no acepta los dos puntos. Al descifrarlo ahí se guarda como `informe_04.txt`. El contenido no cambia. |
| Un archivo se llama `CON.txt` o `NUL.dat` | Windows reserva esos nombres desde MS-DOS. Se guardan como `CON_.txt` y `NUL_.dat`. |
| Un nombre termina en espacio o en punto | Windows no lo permite. Se recorta al guardarlo. |
| Se cifra una USB formateada en Windows | Se dejan intactas `System Volume Information`, `$RECYCLE.BIN`, `Thumbs.db` y `desktop.ini`. Cifrarlas no protege nada y puede dejar la USB inservible en otro equipo. |
| Un archivo está marcado como solo lectura | Se le quita el atributo antes de reemplazarlo, para que el original no quede en el disco junto a su versión cifrada. |
| El nombre lleva acentos o eñes | Se conserva igual en los dos sistemas. |

Los nombres solo se adaptan **al guardar en Windows**. En Linux se respetan tal
cual, y el nombre original siempre viaja completo dentro del archivo cifrado:
la adaptación ocurre al escribir en el disco, nunca dentro del `.c3v`.

---

## Lo que el usuario debe saber

- **Contraseña perdida = archivo perdido.** No existe recuperación. Es una
  decisión de diseño, no una carencia: una puerta trasera de recuperación
  sería exactamente lo que el adversario buscaría.
- **El borrado del original es de mejor esfuerzo.** Se sobrescribe con ceros,
  pero en discos SSD y memorias USB el controlador puede conservar copias
  internas fuera del alcance del sistema operativo. Para material de alta
  clasificación hay que cifrar el disco completo además (LUKS o BitLocker).
- **La contraseña se escribe en el equipo del destino.** Si ese equipo está
  comprometido con un registrador de teclas, ninguna herramienta de cifrado
  puede protegerlo. Usar equipo confiable.
- **No cifrar dos veces la misma carpeta.** El programa lo impide, pero hay que
  entender por qué: quedaría una capa sobre otra y se perdería la referencia de
  qué contraseña abre cada nivel.

---

**Cryptum Portable — desarrollado por C3r0d4y.**
Compañero de escritorio de la aplicación web Cryptum.
