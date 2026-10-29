# Revisión y preparación del entorno

**Fecha:** 2026-10-26

**Alcance:** preparación del equipo y del entorno Docker local, e implementación/verificación del incremento E1 de Uyuni Tour. No se publicó la aplicación ni se configuraron servicios externos; E2–E6 siguen pendientes.

## Resumen

El equipo ejecuta Windows 10 Pro de 64 bits (22H2, compilación 19045.6466) y PowerShell 5.1. Windows 10 Home/Pro terminó su soporte general el 14-10-2025; el usuario confirma que el equipo cuenta con cobertura aplicable/ESU, pero no se verificó esa inscripción en Windows Update ni mediante la organización. El proyecto estaba en `main` al iniciar la revisión; se conservaron los cambios staged preexistentes. VS Code, Git, PHP, Composer y FileZilla están instalados. El PHP CLI del host es el de XAMPP; el proyecto usa PHP 8.3 dentro de Docker.

La sesión actual de este asistente, ejecutada mediante Copilot SDK en VS Code, está operativa; esto no comprueba por separado la extensión oficial ni la licencia de GitHub Copilot de la interfaz. WSL 3.0.1 (kernel 6.18.40.1) y Docker Desktop 4.97.0 están instalados; el backend WSL 2 está activo y Docker Engine/Compose responden. Los contenedores locales de Uyuni Tour están en ejecución. Hay 8 GB de RAM física instalada; Windows enumera 7.81 GiB utilizables. La diferencia no basta para declarar que el equipo incumple el requisito publicado de 8 GB.

El repositorio conserva `README.md` (su contenido actual es `ns`) y contiene ahora el entorno Docker PHP 8.3/MySQL 8.4 e implementación E1: autenticación, roles, JWT, administración de usuarios e interfaz mínima. PHPUnit pasó 15 pruebas/62 aserciones; PHPCS y `composer audit --locked` pasaron. Los smoke tests devolvieron HTTP 200 para `/` y `/health.php` (MySQL `ok`) y HTTP 401 para `/api/v1/auth/me` sin token. E2–E6, la confirmación de compatibilidad con InfinityFree, el bootstrap del primer administrador y la publicación siguen pendientes.

## Inventario y comprobaciones

| Componente | Obligatorio/opcional | Versión detectada | Comprobación realizada | Resultado y acción pendiente |
|---|---|---|---|---|
| Sistema operativo | Obligatorio | Windows 10 Pro 22H2, build 19045.6466, x64 | Inventario de Windows y [ciclo de vida de Microsoft](https://learn.microsoft.com/en-us/lifecycle/products/windows-10-home-and-pro) | El ciclo de soporte general terminó el 14-10-2025. El usuario confirma cobertura aplicable/ESU; no se verificó localmente. Microsoft indica que 22H2 fue la versión final de Windows 10. Confirmar que la cobertura satisface la política vigente de Docker Desktop. |
| Terminal | Obligatorio | Windows PowerShell 5.1 | Comandos de inventario y Git ejecutados | Funcional en esta sesión. La terminal integrada de VS Code requiere la comprobación manual indicada abajo. |
| Git | Obligatorio | 2.55.0.windows.5 | `git status`, rama, archivos y `git ls-remote --heads origin` | Repositorio válido, rama `main`, inicialmente limpio y remoto `origin` legible. Los archivos de preparación creados durante esta revisión están ahora sin confirmar. No se mostraron URL con credenciales. Nombre y correo de autor ya están configurados; no se modificó su configuración. |
| Visual Studio Code | Obligatorio | 1.143.1, x64 | `code --version` | Ejecutable funcional. |
| Asistente de esta sesión (Copilot SDK en VS Code) | Disponible en la sesión actual | Sesión activa | Lectura/edición de archivos, comandos y metadatos de la sesión actual | Funcionamiento efectivo comprobado para esta conversación. Es distinto de comprobar la extensión oficial, el inicio de sesión de GitHub o el derecho de uso de Copilot en la interfaz. |
| Extensiones GitHub Copilot / Copilot Chat | Recomendadas si se usarán directamente en el editor | No aparecen en `code --list-extensions --show-versions` | Inventario CLI de extensiones de usuario; identificadores Marketplace `GitHub.copilot` y `GitHub.copilot-chat` | Que no aparezcan en esta lista no demuestra que el SDK/sesión actual no funcione. La autenticación, entitlement y respuesta de la extensión oficial requieren comprobación en la interfaz. No se instalaron. |
| Modo Agent de Copilot en la interfaz | Opcional | Requiere comprobación manual | No se inspeccionó el selector de modo en la interfaz | Confirmar que aparece Agent y probar una tarea inocua en el panel Chat. No inferir su disponibilidad desde la lista de extensiones ni desde el SDK de esta sesión. |
| Confianza del espacio de trabajo | Obligatorio revisarla | Requiere comprobación manual | No se puede leer ni cambiar con las herramientas de terminal disponibles | No se alteró la decisión de confianza. Consultar **Manage Workspace Trust** en VS Code y conservar el estado actual salvo decisión explícita del usuario. |
| Edición/lectura de archivos y ejecución | Obligatorio | Disponible en esta sesión | Se leyó el README, se inspeccionó el repositorio y se prepararon archivos de configuración; se ejecutaron comandos de PowerShell | Lectura y edición del proyecto y ejecución externa comprobadas. Los permisos específicos de Agent y la terminal integrada requieren la comprobación manual indicada abajo. |
| Extensión PHP | Recomendada | Intelephense `bmewburn.vscode-intelephense-client` 1.18.5, instalada | Lista de extensiones de VS Code | Ya está instalada. Se recomienda también para el espacio de trabajo para que VS Code la sugiera a otros colaboradores. |
| Extensión de contenedores | Recomendada cuando Docker esté instalado | No instalada | Se confirmó en Marketplace `ms-azuretools.vscode-containers` (Container Tools, reemplazo de la extensión Docker anterior) | Incluida como recomendación de VS Code; Docker Engine ya está disponible. No se recomienda instalar varias extensiones de contenedores. |
| Extensión de depuración PHP | Opcional | No instalada | Xdebug no está cargado en el PHP detectado; se confirmó el identificador Marketplace `xdebug.php-debug` | No se recomienda por ahora: no hay Xdebug configurado. Instalar/configurar ambos solo si se decide depurar con Xdebug. |
| Docker Desktop | Obligatorio para el entorno principal seleccionado | 4.97.0, instalación por usuario | Instalador x64 descargado del enlace de [Docker](https://docs.docker.com/desktop/setup/install/windows-install/); firma Authenticode válida de Docker Inc.; `docker desktop status` | Instalado en `%LOCALAPPDATA%\Programs\DockerDesktop`; Docker Desktop informa estado `running`. El instalador añadió el CLI al PATH del usuario; una terminal que ya estaba abierta puede necesitar reiniciarse para recogerlo. |
| Docker Engine y Compose | Obligatorio para contenedores | Engine 29.9.2; Compose 5.6.1 | `docker version`, `docker info` y `docker compose version`, ejecutados desde PATH | Las tres comprobaciones terminaron con código 0. Engine Linux responde mediante el contexto `desktop-linux`; `docker compose ps` muestra `app` activo y `db` saludable. |
| Requisitos de Docker en Windows | Necesarios para preparar el entorno Docker | Windows 10 Pro 22H2 build 19045.6466; 8 GB físicos; CPU Intel i7-6600U; WSL 3.0.1 | Inventario CIM, estado de funciones opcionales, `wsl --status`, `wsl --version`, `wsl --list --verbose`; requisitos de [Docker Desktop](https://docs.docker.com/desktop/setup/install/windows-install/) consultados el 2026-10-26 | WSL 2 es el modo predeterminado; `Microsoft-Windows-Subsystem-Linux` y `VirtualMachinePlatform` figuran habilitadas (InstallState 1). WSL informa kernel 6.18.40.1 y Docker creó su distribución `docker-desktop`, que está ejecutándose. Un módulo de 8 GB está instalado; Windows informa 7.81 GiB utilizables. Docker requiere WSL 2.1.5+ y que Windows siga dentro del ciclo de servicio de Microsoft: la versión de WSL y la ejecución de Engine se comprobaron correctamente; el usuario confirma cobertura ESU, pero no se verificó su inscripción localmente. La consulta CIM actual muestra `HypervisorPresent=True`; los indicadores de virtualización del procesador aparecen falsos bajo el hipervisor activo. |
| Puertos locales | Comprobación previa a contenedores | 80, 443, 3306, 8080 y 9003 libres | Consulta de puertos TCP en escucha | Sin conflictos en esos puertos al momento de la revisión. Repetir antes de iniciar servicios. |
| Recursos de Windows | Comprobación previa a contenedores | 8 GB físicos instalados; 7.81 GiB reportados por Windows; 42.5 GB libres en C: | `Win32_PhysicalMemory` y `Win32_ComputerSystem.TotalPhysicalMemory`; inventario de disco | La capacidad instalada coincide con el umbral nominal de 8 GB publicado por Docker. El valor utilizable de Windows es menor; no se midió presión de memoria ni se declara incompatibilidad. |
| PHP CLI | Opcional en el equipo si se usa Docker | Host 8.2.12; contenedor del proyecto 8.3.36 | `php -v`, `php -m`, consulta de `password_algos()` y `docker compose exec app php -v` | El host tiene PDO y `pdo_mysql`; el contenedor instala las extensiones de aplicación y ejecuta el proyecto. La versión 8.3 local es provisional respecto a InfinityFree: no fijar compatibilidad de publicación hasta confirmar versiones y extensiones en el panel. |
| Composer | Obligatorio para dependencias, preferiblemente dentro de Docker | 2.9.5 en host; dependencias bloqueadas en Composer | `docker compose exec app composer check-platform-reqs` y comandos Composer del proyecto | Dependencias instaladas desde `composer.lock`; requisitos de plataforma satisfechos. No se modificó el manifiesto durante las verificaciones finales. |
| PHPUnit | Obligatorio para pruebas; dependencia de desarrollo Composer | 11.5.57 | `docker compose exec app composer test` | **15 pruebas y 62 aserciones pasaron**, incluida integración con MySQL real. No se requiere instalación global. |
| PHP_CodeSniffer | Obligatorio para análisis de estilo; dependencia de desarrollo Composer | 3.13.6 | `docker compose exec app composer lint` | Pasó para `bin`, `public`, `src` y `tests`. |
| Cobertura | Obligatorio para reportes; elegir un controlador compatible | Xdebug y PCOV no detectados | Lista de módulos PHP | Pendiente. Preferir una sola opción compatible en el contenedor de pruebas; verificarla junto con la versión de PHPUnit. |
| Cliente MySQL | Opcional si se usa Docker | No detectado | Búsqueda del comando `mysql` | No necesario instalarlo en Windows si el flujo usa el cliente/servicio dentro de Docker. |
| MySQL/InnoDB | Obligatorio para integración y concurrencia | MySQL 8.4 en contenedor | `docker compose ps`, `/health.php` y PHPUnit | Base en red privada de Compose, InnoDB configurado; health check HTTP devuelve `database: ok` y las pruebas consultan MySQL real. |
| FileZilla Client | Opcional para publicación posterior | 3.71.1 | Registro de aplicaciones y ejecutable existente | Instalado; no se abrió ni se realizó transferencia. También aparece FileZilla Server 1.12.6 instalado; no se configuró ni inició. |
| Inicio HTTP / smoke test | Obligatorio | PHP 8.3/Apache en `127.0.0.1:8080` | Peticiones HTTP locales a `/`, `/health.php` y `/api/v1/auth/me` sin token | Respuestas observadas: 200, 200 con MySQL `ok`, y 401 (protección esperada), respectivamente. |
| InfinityFree | Opcional para publicar | No comprobado en panel | No se usaron credenciales ni se accedió a una cuenta | Confirmar en el panel: versiones PHP disponibles; extensiones (especialmente `pdo_mysql`); host/puerto y límites de MySQL; directorio público (por ejemplo, `htdocs`/`public_html`, según el panel); límites de almacenamiento, transferencia, tamaño/tiempo de ejecución y HTTPS. No publicar ni compartir contraseñas aquí. |
| DuckDNS | Opcional; demostración separada | Pendiente | No se configuró DNS ni servidor | Requiere un servidor accesible desde Internet. No es requisito para desarrollo local; un nombre DNS no evita CGNAT ni puertos cerrados. No se modificaron router, firewall ni DNS. |

### Versiones PHP y hospedaje

El entorno local usa PHP 8.3, declarado por el contenedor y `composer.json`. Esta versión no está confirmada para InfinityFree. Consultar en el panel las versiones de PHP y extensiones realmente habilitadas (en especial `pdo_mysql`) antes de fijar compatibilidad o publicar; PHP 8.2.12 del host tampoco es la especificación del proyecto.

## Configuración preparada

- `.gitignore`: excluye `vendor`, archivos `.env` (excepto `.env.example`), caché/resultados de PHPUnit, cobertura y archivos temporales comunes. No se encontraron reglas previas que sobrescribir.
- `.vscode/extensions.json`: recomienda Copilot, Copilot Chat, Intelephense y Microsoft Container Tools. Intelephense ya estaba instalado; Copilot y la herramienta de contenedores no estaban en la lista de extensiones de usuario. Sus identificadores se comprobaron en Marketplace.
- No se creó `.vscode/settings.json`: todavía no hay convenciones del proyecto que configurar.
- No se crearon `tasks.json` ni `launch.json`: no existen comandos Compose, pruebas, PHPCS ni configuración Xdebug que puedan invocarse de forma real.
- No se instalaron extensiones de VS Code ni herramientas globales de Windows; Composer instaló las dependencias del proyecto en Docker. No se tocaron ajustes globales y no se modificó la confianza del espacio de trabajo.

## Comprobaciones manuales de VS Code

1. **Confianza:** abrir la paleta con `Ctrl+Shift+P`, ejecutar **Workspaces: Manage Workspace Trust** y revisar el estado. No pulsar opciones para cambiarlo automáticamente.
2. **Copilot oficial del editor:** esta conversación ya funciona mediante Copilot SDK en VS Code; no es una prueba de la extensión oficial ni de la sesión de GitHub de la interfaz. Para comprobar estas por separado, abrir **Accounts**, revisar la cuenta sin copiar credenciales, abrir Chat y enviar una pregunta inocua. Si se quiere usar la extensión oficial y no aparece en Extensions, buscar los identificadores comprobados `GitHub.copilot` y `GitHub.copilot-chat`; instalar solo si se desea y completar la autenticación en la interfaz.
3. **Agent:** en el panel Chat, revisar si el selector ofrece **Agent** y ejecutar una petición inocua. Su disponibilidad depende de la versión, la cuenta y las políticas; no se deduce de que Copilot SDK esté activo en esta sesión.
4. **Terminal integrada:** elegir **Terminal > New Terminal**, confirmar que abre PowerShell y ejecutar `git status --short --branch` y `php -v`. Esto comprueba el terminal integrado (la ejecución externa desde esta sesión ya funcionó).
5. **Lectura/edición por Agent:** en modo Agent, comprobar permisos y aprobación antes de permitir comandos o ediciones. Las herramientas de esta sesión sí pudieron leer y editar archivos del proyecto; eso no certifica permisos de otra sesión o del Agent dentro de VS Code.

## Comandos diarios que funcionan en esta máquina

Ejecutados en PowerShell desde la raíz del repositorio:

```powershell
git status --short --branch
git ls-remote --heads origin
php -v
php -m
composer --version
code --version
code --list-extensions --show-versions
```

Comandos del proyecto comprobados:

```powershell
docker compose up --build -d
docker compose ps
docker compose exec app composer test
docker compose exec app composer lint
docker compose exec app php bin/migrate.php
```

La página local está en `http://localhost:8080/`; el health check es `/health.php`. La cobertura no se configuró ni se midió.

## Errores iniciales de comprobación y estado actualizado

| Comprobación inicial | Resultado observado | Interpretación correcta / estado actual |
|---|---|---|
| Consulta PHP de capacidades con `php -r` | Error de sintaxis PHP al pasar comillas desde PowerShell | Falló el escapado de la línea de comandos, no PHP ni Argon2id. Al repetir la consulta correctamente, PHP devolvió `argon2id`; `php -v` y `php -m` también funcionaron. |
| `phpunit --version` del PATH | El lanzador heredado de XAMPP termina con código 255 y advertencia de sintaxis antigua en PHP 8.2 | Existe un PHPUnit global heredado, pero no es una instalación funcional para este entorno. No es un bloqueo del flujo recomendado: PHPUnit se configurará como `require-dev` de Composer dentro del proyecto; no instalarlo globalmente. |
| `Get-WindowsOptionalFeature -Online` | La consulta inicial respondió que requería elevación | DISM no permitió la consulta en esta sesión. Después del reinicio, una consulta CIM de solo lectura mostró `Microsoft-Windows-Subsystem-Linux` y `VirtualMachinePlatform` habilitadas (InstallState 1). |
| `wsl --status`, `wsl --list` y `wsl --version` | Antes de actualizar, el `wsl.exe` antiguo no admitía `--version` ni informaba detalles actuales | WSL se actualizó después desde Microsoft; ahora informa versión 3.0.1, kernel 6.18.40.1 y WSL 2 predeterminado. La distribución `docker-desktop` está ejecutándose. |
| `docker`, `docker compose`, `docker info` | No estaban disponibles durante la revisión inicial | Tras instalar Docker Desktop 4.97.0, las tres comprobaciones terminan con código 0; Engine 29.9.2 y Compose 5.6.1 están disponibles. No se arrancaron contenedores del proyecto. |

## Pendientes por objetivo

### Para continuar el proyecto

- El entorno Docker local y E1 (autenticación y roles) están implementados y verificados. E2–E6 siguen fuera del alcance del incremento actual.
- La extensión PHP Intelephense ya está instalada. Las extensiones oficiales de Copilot y el estado de Agent en la interfaz son opcionales para programar con las herramientas de esta sesión; verificar manualmente si se quiere utilizar ese flujo desde el editor.
- Consultar InfinityFree antes de decidir la versión PHP y sus extensiones. No se ha accedido al panel y no se ha fijado una versión de proyecto.

### Entorno Docker local

1. WSL 3.0.1/kernel 6.18.40.1 y Docker Desktop 4.97.0 están instalados; Docker Engine 29.9.2 y Compose 5.6.1 responden desde la terminal.
2. El proyecto ejecuta PHP 8.3/Apache y MySQL 8.4/InnoDB con Compose. El sitio se publica solo en `127.0.0.1:8080`; MySQL permanece en la red privada de Compose.
3. `docker compose ps` confirmó el servicio `app` activo y `db` saludable. `/health.php` informó `database: ok`; PHPUnit probó PDO/MySQL real.
4. El usuario confirma cobertura ESU de Windows, pero no se comprobó localmente su inscripción. No se configuraron InfinityFree, FileZilla, DuckDNS, router ni DNS.

### Pruebas y validaciones observadas

- PHPUnit: 15 pruebas y 62 aserciones pasaron en PHP 8.3.36/MySQL 8.4.
- PHPCS pasó; `composer audit --locked` no encontró avisos.
- HTTP: `/` respondió 200, `/health.php` respondió 200 con MySQL `ok`, y `/api/v1/auth/me` sin credenciales respondió 401.
- `docker version`, `docker info` y `docker compose version` finalizaron correctamente.
- Cobertura porcentual y prueba de concurrencia independiente no se ejecutaron. No hay administrador inicial creado.

### Para publicar o demostrar DNS

- En el panel de InfinityFree, consultar versiones de PHP, extensiones (incluido `pdo_mysql`), datos/puertos y límites de MySQL, directorio público, almacenamiento, transferencia, límites de ejecución y HTTPS. No pedir ni almacenar contraseñas en el chat o repositorio.
- FileZilla Client 3.71.1 ya está instalado. Para una futura transferencia harán falta host, protocolo/puerto, usuario, ruta remota y, si corresponde, configuración TLS; no se inició FileZilla ni se transfirieron archivos.
- DuckDNS queda como demostración independiente hasta disponer de servidor accesible desde Internet. No es necesario para desarrollo local y no resuelve CGNAT/puertos cerrados. No se tocaron router, firewall ni DNS.

## Resultado

**Entorno local preparado y E1 verificada; producto aún incompleto.** WSL y Docker Desktop están preparados, Docker Engine/Compose responden y el stack local de PHP 8.3 y MySQL 8.4 está activo. E1 pasó 15 pruebas/62 aserciones, PHPCS, auditoría de dependencias y smoke tests HTTP con conexión MySQL real. E2–E6, cobertura, la confirmación de PHP/extensiones en InfinityFree y la publicación siguen pendientes. No se creó el primer administrador ni se configuraron servicios externos. El usuario confirma cobertura ESU, pero su inscripción no se verificó localmente. El resultado no declara terminado el sistema Uyuni Tour.
