# Uyuni Tour

Sistema web local para la gestión de una agencia de turismo: tours, salidas,
turistas, reservas, flota, pagos manuales y auditoría operativa.

## Funcionalidades

- Autenticación JWT y roles de Administrador, Operador, Conductor/Guía y Auditor.
- Catálogo de tours y programación de salidas.
- Registro de turistas y reservas con control transaccional de cupos.
- Vehículos, conductores, asignaciones, mantenimiento e incidencias.
- Pagos manuales en BOB, saldos, idempotencia y anulaciones trazables.
- Auditoría transaccional con filtros y paginación.
- Interfaz adaptable en español para la operación local.

## Tecnologías

- PHP 8.3 y PDO.
- MySQL 8.4 con InnoDB.
- JavaScript y Bootstrap 5.
- Docker Compose.
- Composer, PHPUnit 11 y PHP_CodeSniffer.

## Requisitos

- Docker Desktop con Compose.
- Git.
- En Windows, Docker puede estar instalado en:
  `$env:LOCALAPPDATA\Programs\DockerDesktop\resources\bin\docker.exe`

No se requiere instalar PHP, MySQL ni Composer en el equipo anfitrión si se
utiliza el contenedor.

## Instalación local

1. Copia `.env.example` como `.env`.
2. Sustituye los marcadores por valores locales aleatorios. No compartas `.env`.
3. Desde la raíz del proyecto, inicia los servicios:

   ```powershell
   docker compose up -d
   ```

4. Aplica las migraciones:

   ```powershell
   docker compose exec -T app php bin/migrate.php
   docker compose exec -T app php bin/migrate.php --test
   ```

5. Abre <http://127.0.0.1:8080/>.

En Windows, si `docker` no está en `PATH`, define la ruta conocida:

```powershell
$docker = Join-Path $env:LOCALAPPDATA 'Programs\DockerDesktop\resources\bin\docker.exe'
& $docker compose up -d
```

El volumen `mysql_data` conserva la base de datos. No uses
`docker compose down -v` salvo que quieras eliminarla deliberadamente.

## Pruebas y calidad

Las pruebas usan la base separada `uyuni_tour_test`:

```powershell
docker compose exec -T app composer test
docker compose exec -T app composer lint
docker compose exec -T app composer audit --locked
node --test tests/Frontend/tourist-form.test.js
node --check public/assets/js/app.js
git diff --check
```

La suite cubre autenticación, permisos, catálogo, reservas, concurrencia,
flota, pagos, auditoría, rollback y conexión real con MySQL.

## Migraciones

Las migraciones SQL están en `database/migrations/` y se ejecutan de forma
idempotente mediante `bin/migrate.php`. Para comprobar la base de pruebas:

```powershell
docker compose exec -T app php bin/migrate.php --test
```

## Roles y permisos

- **Administrador:** gestiona la operación y consulta auditoría.
- **Operador:** gestiona la operación y pagos, sin administrar usuarios ni
  modificar auditoría.
- **Conductor/Guía:** consulta sus salidas, pasajeros necesarios e incidencias.
- **Auditor:** consulta auditoría sin modificar operaciones ni eventos.

La autorización se valida en el servidor; ocultar una sección en la interfaz
no sustituye el control de la API.

## Estructura

```text
bin/                    Comandos de migración
database/migrations/    Esquema versionado
public/                 Entrada HTTP, interfaz, CSS y JavaScript
src/Domain/              Entidades y contratos de dominio
src/Application/         Reglas de aplicación y permisos
src/Infrastructure/      PDO, HTTP, seguridad y repositorios
tests/                   Pruebas unitarias, integración y frontend
docs/                    Requisitos, plan, API y evidencias
```

## Estado y limitaciones

E1-E4 están implementadas y verificadas localmente. E5 y E6 están
implementadas y probadas automáticamente, con limitaciones de evidencia
manual documentadas en [`docs/informe-final.md`](docs/informe-final.md).

La compatibilidad real con InfinityFree todavía no está confirmada. Falta
verificar allí la versión de PHP, `pdo_mysql`, MySQL/MariaDB, reescritura de
`/api/v1` y recepción de `Authorization`. No se ha publicado en InfinityFree.

## Seguridad del repositorio

No se versionan `.env`, dependencias instaladas, coberturas, copias de
seguridad ni registros. Usa `.env.example` como referencia y nunca guardes
credenciales, tokens o datos reales en Git.
