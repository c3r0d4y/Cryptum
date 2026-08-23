"""
Cryptum Portable — Interfaz grafica (VISTA)
Autor: C3r0d4y

Ventana sencilla pensada para que cualquier persona la use sin instruccion
previa: se elige el archivo o la carpeta, se escribe la contrasena y se
presiona el boton.

Se usa Tkinter porque viene incluido con Python: el ejecutable final no
arrastra ninguna libreria grafica pesada y sigue cabiendo en una USB.

El trabajo pesado corre en un hilo aparte para que la ventana no se
congele mientras PBKDF2 hace sus 210 000 repeticiones.
"""

import os
import queue
import threading
import tkinter as tk
from tkinter import filedialog, messagebox, ttk

from app import config
from app.controllers import file_ctrl, folder_ctrl
from app.models.crypto_engine import ErrorCryptum

# Paleta de la aplicacion, en el mismo tono que la version web
COLOR_FONDO   = "#0d1117"
COLOR_PANEL   = "#161b22"
COLOR_BORDE   = "#30363d"
COLOR_TEXTO   = "#e6edf3"
COLOR_TENUE   = "#8b949e"
COLOR_ACENTO  = "#2f81f7"
COLOR_OK      = "#3fb950"
COLOR_ERROR   = "#f85149"


class VentanaCryptum(tk.Tk):
    """Ventana principal. Contiene todo el flujo de la aplicacion."""

    def __init__(self):
        super().__init__()

        self.title(f"{config.APP_NOMBRE} v{config.APP_VERSION} — {config.APP_AUTOR}")
        self.geometry("620x560")
        self.minsize(520, 500)
        self.configure(bg=COLOR_FONDO)

        # Cola por donde el hilo de trabajo le manda mensajes a la ventana.
        # Tkinter no admite que otro hilo toque los controles directamente.
        self.cola = queue.Queue()
        self.trabajando = False

        self.ruta = tk.StringVar()
        self.es_carpeta = tk.BooleanVar(value=False)
        self.borrar_origen = tk.BooleanVar(value=False)

        self._construir()
        self.after(100, self._revisar_cola)

    # ── Construccion de la interfaz ────────────────────────────────────

    def _construir(self):
        cont = tk.Frame(self, bg=COLOR_FONDO, padx=24, pady=20)
        cont.pack(fill="both", expand=True)

        # Encabezado
        tk.Label(cont, text="CRYPTUM PORTABLE", bg=COLOR_FONDO,
                 fg=COLOR_TEXTO, font=("Segoe UI", 17, "bold")).pack(anchor="w")
        tk.Label(cont, text="AES-256-GCM · PBKDF2-SHA-512 · 100% fuera de linea",
                 bg=COLOR_FONDO, fg=COLOR_TENUE,
                 font=("Segoe UI", 9)).pack(anchor="w", pady=(0, 16))

        # --- Paso 1: que se va a proteger ---
        p1 = self._panel(cont, "1 · Elige que abrir o proteger")

        fila = tk.Frame(p1, bg=COLOR_PANEL)
        fila.pack(fill="x", pady=(6, 8))

        self.entrada = tk.Entry(fila, textvariable=self.ruta, bg=COLOR_FONDO,
                                fg=COLOR_TEXTO, insertbackground=COLOR_TEXTO,
                                relief="flat", font=("Segoe UI", 10))
        self.entrada.pack(side="left", fill="x", expand=True, ipady=6, padx=(0, 8))

        tk.Button(fila, text="Archivo", command=self._elegir_archivo,
                  bg=COLOR_BORDE, fg=COLOR_TEXTO, relief="flat",
                  activebackground=COLOR_ACENTO, activeforeground="white",
                  font=("Segoe UI", 9), padx=12, pady=4,
                  cursor="hand2").pack(side="left", padx=(0, 6))

        tk.Button(fila, text="Carpeta / USB", command=self._elegir_carpeta,
                  bg=COLOR_BORDE, fg=COLOR_TEXTO, relief="flat",
                  activebackground=COLOR_ACENTO, activeforeground="white",
                  font=("Segoe UI", 9), padx=12, pady=4,
                  cursor="hand2").pack(side="left")

        tk.Checkbutton(p1, text="Borrar el original al terminar",
                       variable=self.borrar_origen, bg=COLOR_PANEL,
                       fg=COLOR_TENUE, selectcolor=COLOR_FONDO,
                       activebackground=COLOR_PANEL, activeforeground=COLOR_TEXTO,
                       font=("Segoe UI", 9)).pack(anchor="w")

        # --- Paso 2: contrasena ---
        p2 = self._panel(cont, "2 · Escribe la contrasena")

        self.pwd = tk.Entry(p2, show="•", bg=COLOR_FONDO, fg=COLOR_TEXTO,
                            insertbackground=COLOR_TEXTO, relief="flat",
                            font=("Segoe UI", 11))
        self.pwd.pack(fill="x", ipady=7, pady=(6, 6))

        self.ver_pwd = tk.BooleanVar(value=False)
        tk.Checkbutton(p2, text="Mostrar contrasena", variable=self.ver_pwd,
                       command=self._alternar_pwd, bg=COLOR_PANEL,
                       fg=COLOR_TENUE, selectcolor=COLOR_FONDO,
                       activebackground=COLOR_PANEL, activeforeground=COLOR_TEXTO,
                       font=("Segoe UI", 9)).pack(anchor="w")

        tk.Label(p2, text="Si pierdes la contrasena no hay forma de recuperar "
                          "el archivo. Nadie puede reponerla.",
                 bg=COLOR_PANEL, fg=COLOR_TENUE, font=("Segoe UI", 8),
                 wraplength=520, justify="left").pack(anchor="w", pady=(4, 0))

        # --- Paso 3: accion ---
        p3 = self._panel(cont, "3 · Ejecuta la operacion")

        botones = tk.Frame(p3, bg=COLOR_PANEL)
        botones.pack(fill="x", pady=(8, 4))

        self.btn_desc = tk.Button(botones, text="DESCIFRAR", command=self._descifrar,
                                  bg=COLOR_ACENTO, fg="white", relief="flat",
                                  font=("Segoe UI", 11, "bold"), pady=9,
                                  cursor="hand2", activebackground="#1f6feb")
        self.btn_desc.pack(side="left", fill="x", expand=True, padx=(0, 6))

        self.btn_cif = tk.Button(botones, text="CIFRAR", command=self._cifrar,
                                 bg=COLOR_BORDE, fg=COLOR_TEXTO, relief="flat",
                                 font=("Segoe UI", 11, "bold"), pady=9,
                                 cursor="hand2", activebackground="#3d444d")
        self.btn_cif.pack(side="left", fill="x", expand=True)

        # Barra de avance y mensaje de estado
        self.barra = ttk.Progressbar(cont, mode="determinate")
        self.estado = tk.Label(cont, text="Listo.", bg=COLOR_FONDO,
                               fg=COLOR_TENUE, font=("Segoe UI", 9),
                               wraplength=560, justify="left", anchor="w")
        self.estado.pack(fill="x", pady=(12, 0))

    def _panel(self, padre, titulo):
        """Crea una tarjeta con titulo para agrupar controles."""
        marco = tk.Frame(padre, bg=COLOR_PANEL, padx=16, pady=12,
                         highlightbackground=COLOR_BORDE, highlightthickness=1)
        marco.pack(fill="x", pady=(0, 12))
        tk.Label(marco, text=titulo, bg=COLOR_PANEL, fg=COLOR_TEXTO,
                 font=("Segoe UI", 10, "bold")).pack(anchor="w")
        return marco

    def _alternar_pwd(self):
        """Muestra u oculta los caracteres de la contrasena."""
        self.pwd.config(show="" if self.ver_pwd.get() else "•")

    # ── Seleccion de rutas ─────────────────────────────────────────────

    def _elegir_archivo(self):
        r = filedialog.askopenfilename(
            title="Selecciona un archivo",
            filetypes=[("Vault Cryptum", "*.c3v"), ("Todos los archivos", "*.*")],
        )
        if r:
            self.ruta.set(r)
            self.es_carpeta.set(False)

    def _elegir_carpeta(self):
        r = filedialog.askdirectory(title="Selecciona una carpeta o unidad USB")
        if r:
            self.ruta.set(r)
            self.es_carpeta.set(True)

    # ── Ejecucion de las operaciones ───────────────────────────────────

    def _validar(self, cifrando: bool):
        """Revisa que haya ruta y contrasena antes de empezar."""
        if self.trabajando:
            return None
        ruta = self.ruta.get().strip()
        pwd = self.pwd.get()

        if not ruta or not os.path.exists(ruta):
            messagebox.showerror("Cryptum", "Elige un archivo o una carpeta valida.")
            return None
        if not pwd:
            messagebox.showerror("Cryptum", "Escribe la contrasena.")
            return None
        if cifrando and len(pwd) < 8:
            messagebox.showerror(
                "Cryptum",
                "Usa una contrasena de al menos 8 caracteres.\n"
                "Mientras mas larga, mas dificil de adivinar."
            )
            return None
        if cifrando and not messagebox.askyesno(
            "Confirmar contrasena",
            "Verifica que la contrasena sea correcta.\n\n"
            "Si esta mal escrita, el archivo quedara inservible y NO hay "
            "manera de recuperarlo.\n\n¿Continuar?"
        ):
            return None

        # La casilla se lee AQUI, en el hilo de la ventana. Los controles
        # de Tkinter solo pueden consultarse desde este hilo: leerlos desde
        # el hilo de trabajo provoca el error "main thread is not in main
        # loop" y la operacion se cae a la mitad.
        return ruta, pwd, self.borrar_origen.get()

    def _descifrar(self):
        datos = self._validar(cifrando=False)
        if datos:
            self._lanzar(self._tarea_descifrar, *datos)

    def _cifrar(self):
        datos = self._validar(cifrando=True)
        if datos:
            self._lanzar(self._tarea_cifrar, *datos)

    def _lanzar(self, tarea, ruta, pwd, borrar):
        """
        Arranca la tarea en un hilo aparte.
        Si corriera en el hilo de la ventana, la interfaz se quedaria
        congelada durante toda la derivacion de la clave.
        """
        self.trabajando = True
        self.btn_desc.config(state="disabled")
        self.btn_cif.config(state="disabled")
        self.barra.pack(fill="x", pady=(10, 0))
        self.barra["value"] = 0
        self._mostrar("Trabajando...", COLOR_TENUE)

        threading.Thread(target=tarea, args=(ruta, pwd, borrar), daemon=True).start()

    def _avance(self, hechos, total, nombre):
        """Callback que el controlador llama desde el hilo de trabajo."""
        self.cola.put(("avance", hechos, total, nombre))

    def _tarea_descifrar(self, ruta, pwd, borrar):
        try:
            if os.path.isdir(ruta):
                r = folder_ctrl.descifrar_carpeta(ruta, pwd, self._avance)
                msg = f"Listo · {r['descifrados']} archivo(s) recuperados."
                if r["errores"]:
                    msg += f"  Atencion: {r['errores']} con problemas."
            else:
                salida = file_ctrl.descifrar(ruta, pwd, None, borrar)
                msg = f"Listo · Archivo recuperado:\n{salida}"
            self.cola.put(("ok", msg))
        except ErrorCryptum as e:
            self.cola.put(("error", str(e)))
        except Exception as e:
            self.cola.put(("error", f"Error inesperado: {e}"))

    def _tarea_cifrar(self, ruta, pwd, borrar):
        try:
            if os.path.isdir(ruta):
                r = folder_ctrl.cifrar_carpeta(ruta, pwd, self._avance)
                msg = f"Listo · {r['cifrados']} archivo(s) cifrados con AES-256-GCM."
                if r["errores"]:
                    msg += f"  Atencion: {r['errores']} con problemas."
            else:
                salida = file_ctrl.cifrar(ruta, pwd, None, borrar)
                msg = f"Listo · Archivo cifrado:\n{salida}"
            self.cola.put(("ok", msg))
        except ErrorCryptum as e:
            self.cola.put(("error", str(e)))
        except Exception as e:
            self.cola.put(("error", f"Error inesperado: {e}"))

    # ── Puente entre el hilo de trabajo y la ventana ───────────────────

    def _revisar_cola(self):
        """
        Revisa cada decima de segundo si el hilo de trabajo mando algo.
        Este es el unico lugar donde se tocan los controles graficos.
        """
        try:
            while True:
                msg = self.cola.get_nowait()

                if msg[0] == "avance":
                    _, hechos, total, nombre = msg
                    if hechos >= 0 and total > 0:
                        self.barra["value"] = (hechos / total) * 100
                        self.estado.config(text=f"[{hechos + 1}/{total}]  {nombre}")
                    else:
                        self.estado.config(text=nombre)

                elif msg[0] == "ok":
                    self._terminar(msg[1], COLOR_OK)

                elif msg[0] == "error":
                    self._terminar(msg[1], COLOR_ERROR)
                    messagebox.showerror("Cryptum", msg[1])

        except queue.Empty:
            pass

        self.after(100, self._revisar_cola)

    def _terminar(self, texto, color):
        """Restablece la ventana al terminar la operacion."""
        self.trabajando = False
        self.btn_desc.config(state="normal")
        self.btn_cif.config(state="normal")
        self.barra.pack_forget()
        self.pwd.delete(0, "end")   # La contrasena no se queda en pantalla
        self._mostrar(texto, color)

    def _mostrar(self, texto, color):
        self.estado.config(text=texto, fg=color)


def ejecutar() -> int:
    """Abre la ventana. Devuelve el codigo de salida del programa."""
    VentanaCryptum().mainloop()
    return 0
