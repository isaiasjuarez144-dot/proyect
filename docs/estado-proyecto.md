# Estado del proyecto Uyuni Tour

Actualizado: 29-10-2026, consolidación final local

## Avance

- **E1: aprobada.** Autenticación, roles y administración de usuarios implementados y verificados.
- **E2: cerrada y aprobada con evidencia histórica del usuario.** Catálogo de tours y salidas implementado; el usuario aprobó la comprobación manual en navegador. La prueba automatizada anterior de navegador terminó por timeout y permanece incompleta.
- **E3: aprobada.** Turistas, reservas, idempotencia y control de cupos implementados. El usuario confirmó manualmente que guardar turistas conserva la sesión, las reservas consumen cupos, se rechaza superar capacidad y cancelar libera plazas sin borrar historial.
- **E4: aprobada manualmente.** El usuario confirmó asignaciones por salida, rechazo de solapamientos, bloqueo por mantenimiento y acceso del conductor a sus salidas e incidencias.
- **E5: cerrada con limitaciones.** Pagos manuales en BOB, saldos, idempotencia, concurrencia, anulación trazable y registro opcional de anticipo junto con una reserva están implementados y probados automáticamente; falta evidencia manual de navegador.
- **E6: cerrada con limitaciones.** La auditoría transaccional está implementada y probada automáticamente. La consulta de Administrador y el rechazo sin autenticación fueron comprobados manualmente; faltan evidencias manuales de Auditor, Operador y Conductor/Guía.

No se ha publicado en InfinityFree ni se han configurado FileZilla o DuckDNS. La versión PHP del hosting, `pdo_mysql` y el comportamiento remoto de `/api/v1` y `Authorization` siguen pendientes.

## Verificaciones ejecutadas

- Suite completa de regresión E1–E4 en el contenedor PHP 8.3.36/MySQL 8.4: **34 pruebas, 318 aserciones; pasó nuevamente en esta continuidad**.
- Suite completa de regresión E1–E5: **37 pruebas, 348 aserciones; pasó**.
- Pruebas dirigidas de pagos y reservas: **8 pruebas, 86 aserciones; pasaron**.
- Pruebas dirigidas de E4 (`FleetApiTest.php`): **8 pruebas, 115 aserciones; pasaron nuevamente**.
- PHPCS dentro del contenedor: pasó sin errores ni avisos.
- Prueba frontend `node --test tests/Frontend/tourist-form.test.js`: pasó.
- Migraciones de desarrollo y pruebas: no hay migraciones pendientes.
- Docker verificado desde la ubicación conocida: Engine 29.9.2, Compose v5.6.1; `app` está activo y `db` está saludable.
- Concurrencia de asignaciones y mantenimiento con procesos PHP independientes y base de pruebas separada: solo una operación en conflicto se confirma; la otra responde HTTP 409.
- Smoke HTTP local: `/` y `/health.php` respondieron HTTP 200; `/api/v1/assignments` sin token respondió HTTP 401.
- Smoke E5: `/api/v1/reservations/1/payments` sin token respondió HTTP 401.
- Las ocho migraciones se aplicaron en una instancia aislada MariaDB 11.4.13; las tablas de E4 y pagos quedaron en InnoDB. También se aplicaron en las bases local y de pruebas.
- No se borraron bases, datos ni volúmenes. La tabla de historial es InnoDB y el volumen de datos MySQL existente se conserva.
- La aprobación manual de E4 fue confirmada por el usuario.
- Regresión E1–E6: **39 pruebas, 368 aserciones; pasó** en el contenedor PHP 8.3.36/MySQL 8.4.
- Pruebas específicas E6: **2 pruebas, 20 aserciones; pasaron**. Cubren actor/acción/entidad/identificador/valores, permisos, filtros, paginación y rollback cuando falla auditoría.
- `bin/migrate.php` y `bin/migrate.php --test`: sin migraciones pendientes después de aplicar `011_restore_audit_actor_restrict.sql`.
- PHPCS en el contenedor: pasó sin errores ni avisos después de formatear la prueba E6.
- `node --check public/assets/js/app.js`: pasó después de corregir la carga independiente de auditoría.

## Siguiente paso

Completar la comprobación manual de E5 en `http://127.0.0.1:8080/` y la evidencia manual de E6:

1. Iniciar sesión como administrador u operador y crear una reserva confirmada.
2. En la sección de pagos, registrar un anticipo en BOB y comprobar total, pagado neto y saldo pendiente.
3. Reintentar un pago con la misma clave solo mediante API y confirmar que no se duplica; probar un monto superior al saldo y verificar el rechazo.
4. Anular un pago como administrador con un motivo y confirmar que queda visible como anulado; repetir la anulación y verificar el rechazo.
5. Intentar registrar y anular pagos con roles no permitidos y confirmar los mensajes de permisos.
6. Como administrador, crear/modificar un tour y abrir Auditoría; comprobar actor, acción, entidad, identificador y valores antes/después.
7. Filtrar por fecha, entidad y acción; cambiar de página si hay más de 20 eventos.
8. Iniciar como Auditor y comprobar lectura; como Operador y Conductor/Guía comprobar que Auditoría no aparece ni responde con permiso.

E5 y E6 quedan cerradas administrativamente con limitaciones, no aprobadas en los escenarios manuales que no se ejecutaron. El siguiente paso recomendado es obtener esas evidencias en navegador antes de presentar una aprobación funcional completa. No publicar hasta verificar InfinityFree.

## Docker en Windows

Docker Desktop está instalado en esta ubicación conocida:

```powershell
$docker = Join-Path $env:LOCALAPPDATA 'Programs\DockerDesktop\resources\bin\docker.exe'
```

Si `docker` no aparece en `PATH`, utilizar `& $docker` en los comandos siguientes desde la raíz del repositorio:

```powershell
& $docker version
& $docker info
& $docker compose version
& $docker compose ps
& $docker compose up -d
& $docker compose exec -T app php bin/migrate.php
& $docker compose exec -T app php bin/migrate.php --test
& $docker compose exec -T app composer test
& $docker compose exec -T app composer lint
```

El proyecto está disponible en `http://127.0.0.1:8080/`. Las pruebas usan la base aislada `uyuni_tour_test`. No ejecutar `docker compose down -v`: eliminaría el volumen persistente de MySQL.

## Continuidad y repositorio

Conservar los cambios existentes. No incluir credenciales, contraseñas ni tokens en documentación o mensajes. Esta actualización no transfiere el repositorio ni modifica su remoto.
