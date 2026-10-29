# Cumplimiento y verificación

## Consolidación final

La consolidación del 29-10-2026 no modifica funcionalidades ni resultados
históricos. E2 conserva la aprobación manual previamente confirmada por el
usuario. E5 queda cerrada con limitaciones porque no existe evidencia manual
nueva del flujo de pagos. E6 queda cerrada con limitaciones: la consulta del
Administrador y el rechazo sin autenticación se comprobaron manualmente, pero
no se ejecutaron manualmente los roles Auditor, Operador y Conductor/Guía.
Las pruebas automatizadas no sustituyen esas comprobaciones manuales.

## Funcionalidad

| Área | Estado | Evidencia |
|---|---|---|
| Stack Docker local: PHP, MySQL/InnoDB, PDO y Composer | Preparado | Docker Compose inicia PHP 8.3 y MySQL 8.4; el servicio MySQL usa InnoDB. |
| Respuesta HTTP local | Verificada | `GET /` respondió HTTP 200 en `http://127.0.0.1:8080/`. |
| Conexión real PHP a MySQL | Verificada | `GET /health.php` respondió `{"status":"ok","database":"ok"}`; PHPUnit consultó `SELECT 1` y el motor predeterminado InnoDB. |
| Herramientas de pruebas | Preparadas | PHPUnit 11.5.57 y PHP_CodeSniffer 3.13.6 están fijados por `composer.lock`. |
| E1: autenticación, roles, pantallas y gestión administrativa | Implementada y verificada localmente | API `/api/v1`, interfaz mínima, autorización server-side y administración protegida; incluida en la suite completa E1/E2/E3 de 26 pruebas y 201 aserciones. |
| E2: catálogo de tours | Implementado | Alta, edición parcial, listado y activación/desactivación; no hay borrado y la FK protege tours con historial. |
| E2: salidas y estados | Implementados | Alta vinculada a tours activos, consulta/filtros por tour, fechas inclusivas y estado; transiciones restringidas en Dominio y actualización condicional en BD. La capacidad sigue expresando el total configurado. |
| Permisos E2 | Verificados en pruebas HTTP | Administrador y operador pueden operar tours/salidas; auditor recibe 403; sin token recibe 401. |
| E3: turistas | Implementado y probado | Registro y edición de nombre requerido, correo y teléfono opcionales; no se borran turistas con historial. |
| E3: reservas, pasajeros y precio pactado | Implementados y probados | Reservas consultables, editables y cancelables; cada pasajero ocupa una plaza; precio unitario inmutable en cambios y total calculado en centavos enteros. Cancelar repetidamente conserva historial y libera plazas solo una vez. |
| E3: cupos, idempotencia y permisos | Verificados en MySQL/InnoDB | Se muestran capacidad total, plazas ocupadas y cupos disponibles. Bloqueo `SELECT ... FOR UPDATE` de salida dentro de transacciones; al mover se bloquean las dos salidas en orden ascendente. Clave de idempotencia binaria y única por usuario; 409 para conflictos. Administrador/Operador autorizados y Auditor denegado. No se permite reducir capacidad por debajo de plazas reservadas. |
| E3: prueba simultánea de último cupo | Aprobada en base de pruebas separada | Dos procesos PHP independientes enviaron solicitudes API concurrentes; una respondió 201 y la otra 409, con una sola reserva confirmada. |
| E3: guardar turista desde la interfaz real | Verificado tras corregir | En Edge con frontend, API y MySQL de desarrollo: `POST /tourists` con Authorization respondió 201 y el refresco autenticado respondió 200; el turista apareció una sola vez. Un correo inválido respondió 422, mostró el error junto al campo y mantuvo la sesión. La revisión no expuso el JWT. |
| Aprobación manual E3 | Aprobada por el usuario | Confirmó que guardar turista mantiene la sesión, la reserva descuenta cupos, se rechaza superar capacidad y cancelar libera plazas sin borrar historial. |
| E4: flota y asignaciones | Aprobada manualmente | El usuario confirmó asignaciones por salida, rechazo de solapamientos, bloqueo por mantenimiento y acceso del conductor a sus salidas e incidencias. |
| E4: seguridad operacional y conductor | Verificadas en pruebas API con MySQL/InnoDB | Capacidad del vehículo y plazas reservadas, inicio solo con recursos válidos, acceso del conductor solo a salidas asignadas y nombres de pasajeros necesarios, e incidencias limitadas a sus salidas. |
| E4: concurrencia de asignación/mantenimiento | Aprobada en base separada | Procesos PHP independientes compiten por el mismo vehículo/horario; solo se confirma una operación y la otra devuelve 409. |
| E5: pagos y saldos | Implementada y verificada localmente | Pagos manuales en BOB, total, pago neto y saldo pendiente; importes en centavos, métodos, referencia y usuario responsable. |
| E5: idempotencia, concurrencia y anulación | Verificadas en base separada | Reintentos no duplican pagos, dos pagos simultáneos por el saldo restante producen 201/409, la anulación exige administrador y motivo, conserva el registro y no puede repetirse. |
| E5: reserva con anticipo | Verificada con rollback/commit | La reserva y el anticipo incluidos juntos se confirman en una misma transacción; los datos quedan visibles con pago neto y saldo. |
| Comprobación manual E2 en navegador | Aprobada por el usuario | El usuario confirmó que la comprobación manual de E2 funcionó. |
| Prueba automatizada de navegador E2 | Incompleta; no aprobada | La ejecución automatizada anterior terminó por timeout; no se considera evidencia de aprobación. |
| Comprobación automatizada de navegador E4 | Incompleta; no aprobada | El navegador integrado agotó el timeout al intentar conectarse; E4 requiere comprobación manual y no se marca aprobada. |
| Administrador inicial y comprobación manual | Aprobado por el usuario | La base local contiene exactamente un administrador; no repetir el bootstrap. Tras Ctrl+F5, el usuario confirmó login, gestión de usuarios, panel oculto al operador, cierre de sesión y desaparición del error de reinicio del formulario. |
| Persistencia y secretos | Verificado localmente | MySQL usa el volumen Docker `proyect_mysql_data`; una fila temporal sobrevivió a la recreación de `app`. `.env` está ignorado, `.env.example` usa marcadores y el escaneo no halló valores locales reales en archivos del proyecto. |
| Pruebas aisladas de desarrollo | Verificado | PHPUnit usa `uyuni_tour_test`; el bootstrap ahora rechaza que el nombre coincida con la base de aplicación. |
| Límite de login y último administrador | Verificado localmente | Los intentos se persisten en InnoDB con transacciones; la gestión usa bloqueo y transacción para preservar al menos un administrador activo. Pruebas cubren intentos por correo/IP y rechazo de desactivar el último administrador. |
| Cabecera Authorization y rutas | Preparado localmente; hosting pendiente | Apache reenvía Authorization por rewrite; PHP acepta `HTTP_AUTHORIZATION` y `REDIRECT_HTTP_AUTHORIZATION`. Una prueba cubre el fallback de Apache. InfinityFree no se ha probado: falta verificar allí reescritura `/api/v1` y recepción de la cabecera. |
| Cobertura | Configurada y generada solo para desarrollo | Xdebug 3.5.3 queda apagado por defecto; la ejecución bajo demanda generó el informe de cobertura. No se establece un umbral. |
| Compatibilidad de migraciones con MariaDB | Verificada con MariaDB 11.4.13 | En servidor aislado se aplicaron las ocho migraciones; las tablas de E4 y pagos quedaron InnoDB. No se necesitó cambiar la collation ni las migraciones. |
| PHP y extensiones de InfinityFree | Pendientes; no bloquean E2 local | El panel revisado no muestra herramienta de versión PHP ni la lista de extensiones. `composer.json` exige PHP `^8.3`; quedan sin confirmar la versión de PHP y `pdo_mysql`. `password_algos()` es la comprobación adecuada para Argon2id. No publicar hasta aclararlo. |
| Recepción de Authorization/rutas en InfinityFree | Pendiente de verificar en el hosting | Preparado y probado localmente; no es posible confirmar el comportamiento del servidor remoto sin despliegue. No se publica hasta aclarar esto. |
| E4 | Aprobada manualmente | El usuario confirmó los flujos principales en navegador. |
| E5 | Implementada; aprobación manual pendiente | Pruebas API, concurrencia, rollback, regresión y PHPCS pasaron; falta la comprobación manual. |
| E6: auditoría de cambios | Implementada y verificada localmente; pendiente aprobación manual | Eventos transaccionales para tours, reservas, pagos, asignaciones, mantenimiento y vehículos. Incluyen actor, acción, entidad, identificador, fecha y valores relevantes. La escritura comparte la transacción de negocio; una falla revierte la operación. Lectura paginada y filtrada solo para Administrador/Auditor. No se guardan contraseñas, tokens ni nombres de pasajeros. |
| Publicación y servicios externos | No realizados | No se publicó ni se configuró InfinityFree, FileZilla ni DuckDNS. |

## Pruebas ejecutadas

- PHPUnit 11.5.57, suite completa E1/E2/E3 en el contenedor PHP 8.3.36/MySQL 8.4: **26 pruebas, 201 aserciones, pasaron**. Incluye autenticación/roles, tours/salidas y turistas/reservas, validaciones, idempotencia, cancelación, precio pactado, rollback y cupos simultáneos.
- PHPUnit 11.5.57, regresión completa E1–E4 tras los cambios de E4, en el contenedor PHP 8.3.36/MySQL 8.4: **34 pruebas, 318 aserciones, pasaron**. Incluye conservación del historial de asignaciones y solicitudes concurrentes de asignación frente a mantenimiento.
- PHPUnit 11.5.57, regresión completa E1–E5: **37 pruebas, 348 aserciones, pasaron**. Incluye pagos, sobrepago concurrente, idempotencia, anulación y transacción conjunta de reserva con anticipo.
- PHPUnit 11.5.57, pruebas dirigidas de pagos y reservas: **8 pruebas, 86 aserciones, pasaron**.
- En la continuidad del 27-10-2026 se repitió `FleetApiTest.php`: **8 pruebas, 115 aserciones, pasaron**; después se repitió la suite completa E1–E4: **34 pruebas, 318 aserciones, pasaron**.
- Regresión frontend con Node.js integrado, `node --test tests/Frontend/tourist-form.test.js`: **1 prueba pasó**; confirma que el submit se intercepta, Authorization acompaña las llamadas, la API 422 se asocia al correo y la sesión continúa visible; un 401 posterior sí devuelve al login con el mensaje de expiración.
- PHP_CodeSniffer 3.13.6: **pasó sin errores ni avisos** dentro del contenedor PHP 8.3.36, usando `vendor/bin/phpcs` con las rutas configuradas en `phpcs.xml.dist`.
- Docker verificado desde la ruta conocida: Engine **29.9.2**, Compose **v5.6.1**; `app` activo y `db` saludable. `bin/migrate.php --test` y `bin/migrate.php` informaron que no había migraciones pendientes.
- `composer audit --locked`: **sin avisos de seguridad**.
- Smoke HTTP: `GET /` **200**; `GET /health.php` **200**; `GET /api/v1/reservations` sin token **401** (ruta protegida). `node --check public/assets/js/app.js` pasó.
- `docker compose ps`: `app` está activo y `db` está `healthy`; MySQL no se publica fuera de la red de Compose.
- `docker version`, `docker info` y `docker compose version`: pasaron; Engine 29.9.2 y Compose 5.6.1.
- Una prueba intencional con `DB_TEST_DATABASE` igual a la base de aplicación se rechazó antes de ejecutar tests o tocar datos.
- La migración E2 `003_create_tours_and_departures.sql` se aplicó de forma aditiva en la base local existente. No se borraron tablas, bases, volúmenes ni datos.
- Las migraciones E3 `004_create_tourists_and_reservations.sql` y `005_set_idempotency_key_binary_collation.sql` se aplicaron tanto a la base de desarrollo como a `uyuni_tour_test` mediante el runner, sin borrar datos o volúmenes. Las pruebas usaron exclusivamente la base separada `uyuni_tour_test`.
- Las migraciones E4 `006_create_fleet_assignments_maintenance_and_incidents.sql` y `007_preserve_departure_assignment_history.sql` se aplicaron a las bases de desarrollo y pruebas mediante el runner; no se borraron bases ni volúmenes.
- En MariaDB 11.4.13 aislado se aplicaron las ocho migraciones; las tablas de E4 y pagos quedaron InnoDB. El contenedor temporal usó `tmpfs` y se retiró al terminar.
- Las tres migraciones se aplicaron en una instancia aislada MariaDB 11.4.13. La ejecución confirmó InnoDB, la collation, los `CHECK` y la FK; la instancia y su directorio temporal se retiraron sin tocar volúmenes Docker.
- Se reconstruyó y recreó `app`; una marca temporal en `uyuni_tour_test` siguió presente y luego se retiró. El volumen MySQL no se eliminó ni se recreó.
- PHPUnit generó cobertura con Xdebug 3.5.3 en desarrollo; el comando no impone umbral. El primer intento detectó que faltaba filtro de fuentes, se añadió el ámbito `src` y la generación final pasó.
- Se probó la interfaz E2 en navegador con respuestas API simuladas: login, creación y edición de un tour, creación de una salida y transición a `en_curso`; la etiqueta muestra “Capacidad total” y no hubo errores JavaScript. Esta comprobación de presentación no sustituye las pruebas de integración con MySQL.
- La comprobación manual E2 en navegador fue confirmada como exitosa por el usuario. Una ejecución automatizada de navegador anterior quedó incompleta por timeout; **no está aprobada** y no se presenta como verificación satisfactoria.
- Las migraciones se aplicaron a un MariaDB 11.4.13 aislado, almacenado en un directorio temporal dedicado; el contenedor y ese directorio se retiraron. No se usaron ni borraron volúmenes Docker.
- Comandos de respaldo y restauración en una base vacía documentados en `docs/desarrollo.md`; no se ejecutaron ni se eliminaron volúmenes.
- Migración E6 `009_create_audit_events.sql` y ajustes de FK `010`/`011` aplicados en desarrollo y `uyuni_tour_test`; la FK queda `ON DELETE RESTRICT` para no borrar historial al eliminar un actor directamente.
- Pruebas E6: **2 pruebas, 20 aserciones, pasaron**. Verifican alta/modificación de tour, actor, cambios anterior/nuevo, permisos Administrador/Auditor/Operador, filtros, paginación y rollback sin evento huérfano cuando falla auditoría.
- Regresión completa E1–E6: **39 pruebas, 368 aserciones, pasó**; PHPCS pasó sin errores ni avisos.
- La interfaz de auditoría fue corregida para cargar y paginar de forma independiente del formulario de pagos; `vehicle` está disponible como filtro.

E1–E6 están verificadas localmente con regresión y PHPCS en el contenedor PHP 8.3.36/MySQL 8.4. E1–E4 conservan su aprobación manual; E5 sigue pendiente de aprobación manual. E6 está implementada y verificada automáticamente, pero sigue pendiente de evidencia manual de navegador y cierre académico. La prueba automatizada anterior de navegador E2 sigue incompleta por timeout y no está aprobada. InfinityFree mantiene pendientes PHP, `pdo_mysql` y la recepción remota de Authorization/rutas. No se ha publicado ni configurado FileZilla/DuckDNS.
