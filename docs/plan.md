# Plan de implementación

## Estado

E1–E4 están implementadas y verificadas localmente; E1–E3 fueron aprobadas previamente y E4 fue aprobada manualmente por el usuario. E5 y E6 quedan cerradas administrativamente con limitaciones: sus pruebas automatizadas pasaron, pero no se convierten en aprobación manual. El producto no está publicado. La prueba automatizada de navegador anterior de E2 continúa incompleta por timeout, aunque existe aprobación manual histórica del usuario.

1. Preparar y verificar Docker Compose con PHP/PDO, MySQL/InnoDB, Composer y herramientas de calidad.
2. Implementar las capas de Dominio, Aplicación e Infraestructura para credenciales, usuarios, roles, JWT y protección de rutas.
3. Añadir migraciones y comando explícito e idempotente para crear el primer administrador.
4. Implementar los endpoints `/api/v1` y la interfaz mínima de login, identidad autenticada y administración de usuarios.
5. **E1 completada:** pruebas unitarias y de integración sobre MySQL real, análisis de estilo y smoke tests HTTP.
6. **E2 completada:** catálogo de tours y salidas, roles administrador/operador, transiciones de estado, migraciones y pantallas adaptables.
7. **Verificación E2 completada:** regresión, PHPCS y smoke HTTP en el contenedor PHP 8.3; el usuario aprobó la prueba manual E2. La ejecución automatizada anterior del navegador quedó incompleta por timeout y sigue sin aprobarse.
8. **E3 implementada y aprobada manualmente:** el usuario confirmó guardar turista conservando sesión, reserva descontando cupos, rechazo al exceder capacidad y cancelación liberando plazas sin borrar historial. Verificada con suite E1/E2/E3 (26 pruebas/201 aserciones), regresión frontend, PHPCS y prueba concurrente real del último cupo (201/409).
9. **E4 aprobada manualmente:** vehículos, asignaciones, intervalos semiabiertos, mantenimiento, bloqueo de salida sin recursos válidos, incidencias y consultas limitadas del conductor. El usuario confirmó los flujos principales.
10. **E5 implementada localmente:** pagos manuales en BOB, saldos, idempotencia, anulación trazable, bloqueo transaccional y reserva con anticipo atómico. La suite completa pasó con **37 pruebas/348 aserciones**; falta la aprobación manual.
11. **E6 cerrada con limitaciones:** auditoría transaccional de tours, reservas, pagos, asignaciones, mantenimiento y vehículos; consulta paginada para Administrador/Auditor; rollback verificado cuando falla la escritura del evento. La suite histórica pasó con **39 pruebas/368 aserciones** y PHPCS. La consulta de Administrador fue comprobada manualmente; faltan evidencias manuales de los roles restantes.

## Épicas

| Épica | Estado |
|---|---|
| E1. Autenticación y roles | Implementada y verificada localmente; incluida en la suite E1/E2/E3 de 26 pruebas/201 aserciones. |
| E2. Tours y salidas | Implementada y verificada localmente; incluida en regresión, PHPCS, migraciones y smoke HTTP. E3 añade ocupación y disponibilidad calculadas a partir de reservas confirmadas. |
| E3. Turistas y reservas sin sobreventa | Implementada y aprobada manualmente: el usuario confirmó flujo de turista, consumo y liberación de plazas y bloqueo de sobreventa; 26 pruebas/201 aserciones, regresión frontend y PHPCS. |
| E4. Vehículos, asignaciones, mantenimiento e incidencias | Implementada, verificada y aprobada manualmente. |
| E5. Anticipos, pagos manuales y saldos | Cerrada con limitaciones: pruebas automáticas y de concurrencia satisfactorias; falta evidencia manual de navegador. |
| E6. Auditoría de cambios | Cerrada con limitaciones: pruebas automáticas satisfactorias y Administrador comprobado manualmente; faltan roles restantes y evidencia académica. |

## Pendientes de publicación

- Consultar en InfinityFree las versiones de PHP disponibles y las extensiones, especialmente `pdo_mysql`. PHP 8.3 es solo la versión provisional del contenedor local; no publicar hasta verificar compatibilidad.
- No se configura InfinityFree, FileZilla ni DuckDNS durante E1–E4.
- InfinityFree sigue pendiente de confirmar la versión PHP, `pdo_mysql` y comportamiento de Apache; estos pendientes no bloquean el desarrollo local y no se publica.
