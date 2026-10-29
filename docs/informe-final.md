# Informe final de Uyuni Tour

## 1. Objetivo

Uyuni Tour es una aplicación web local para gestionar tours, salidas,
turistas, reservas, flota, pagos manuales y auditoría operativa.

## 2. Arquitectura y tecnologías

- PHP 8.3, PDO y MySQL 8.4 con tablas InnoDB.
- Monolito modular por capas de Dominio, Aplicación e Infraestructura.
- API HTTP bajo `/api/v1` y una interfaz HTML/Bootstrap/JavaScript.
- Docker Compose, Composer, PHPUnit y PHP_CodeSniffer.
- JWT para autenticación y autorización server-side por roles.

## 3. Módulos implementados

E1 autenticación y roles; E2 tours y salidas; E3 turistas, reservas y cupos;
E4 vehículos, conductores, asignaciones, mantenimiento e incidencias; E5
pagos manuales en BOB y saldos; E6 auditoría transaccional.

## 4. Pruebas ejecutadas

Los resultados históricos conservados incluyen:

- Regresión E1-E6: 39 pruebas y 368 aserciones satisfactorias.
- Pruebas dirigidas de E6: 2 pruebas y 20 aserciones satisfactorias.
- Batería dirigida reciente de auditoría, reservas y catálogo: 15 pruebas y
  190 aserciones satisfactorias.
- Pruebas automatizadas de frontend y `node --check`: satisfactorias.
- PHPCS: sin errores ni advertencias.
- `composer audit --locked`: sin vulnerabilidades reportadas.
- Migraciones de desarrollo y pruebas: sin pendientes en las ejecuciones
  registradas.
- Smoke local: `/` y `/health.php` respondieron HTTP 200; auditoría sin token
  respondió HTTP 401.

## 5. Seguridad y permisos

La autorización se aplica en el servidor. Administrador y Auditor pueden
consultar auditoría; Operador y Conductor/Guía no. Los eventos se escriben
dentro de la transacción de negocio y no incluyen contraseñas, tokens ni datos
personales innecesarios. Los registros de auditoría no se editan ni eliminan
desde la aplicación.

## 6. Estado de E2, E5 y E6

| Funcionalidad | Estado final | Evidencia |
|---|---|---|
| E2 | Cerrado y aprobado con evidencia histórica del usuario | Pruebas automáticas y comprobación manual previamente confirmada; la automatización de navegador anterior terminó por timeout. |
| E5 | Cerrado con limitaciones | Pruebas automáticas, concurrencia y rollback satisfactorios; falta evidencia manual de registro y anulación desde navegador. |
| E6 | Cerrado con limitaciones | Administrador y usuario no autenticado comprobados manualmente; faltan comprobaciones manuales de Auditor, Operador y Conductor/Guía. |

“Cerrado con limitaciones” no significa aprobado: identifica evidencia que no
se obtuvo y evita inventar resultados.

## 7. Incidencias corregidas

Se corrigieron validaciones de fechas, expiración de sesión, autorización,
idempotencia, bloqueos de cupos y pagos, auditoría transaccional, preservación
del historial y separación visual de auditoría y pagos. La presente
consolidación no añade correcciones de código.

## 8. Limitaciones y InfinityFree

La compatibilidad real con InfinityFree permanece pendiente. No se han
confirmado allí PHP, `pdo_mysql`, MariaDB/MySQL, reescritura de `/api/v1`,
recepción de `Authorization` ni la configuración equivalente de variables de
entorno. No se ha publicado ni se han enviado credenciales.

## 9. Evidencias disponibles

Existen resultados de PHPUnit, PHPCS, frontend, concurrencia, smoke HTTP,
migraciones y comprobaciones manuales históricas de E1-E4 y del
Administrador en E6. No existen capturas nuevas para los escenarios manuales
pendientes de E5 y de los roles restantes de E6.

## 10. Declaración de cierre

El alcance local implementado queda consolidado para entrega académica con
limitaciones documentadas. No se declara terminada la validación manual
completa ni la compatibilidad con InfinityFree. El siguiente paso concreto es
recopilar esas evidencias sin modificar el código ni publicar el sistema.
