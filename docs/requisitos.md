# Requisitos de Uyuni Tour

## Alcance

Aplicación web para una agencia de turismo en Uyuni. El sistema será un monolito modular con capas de Dominio, Aplicación e Infraestructura; reglas de negocio fuera de controladores y vistas; API REST bajo `/api/v1`; interfaz HTML/CSS/Bootstrap y JavaScript; PHP, PDO, MySQL con InnoDB, Composer, Docker Compose para desarrollo y PHPUnit.

### Épicas

- **E2. Tours y salidas.**
- **E3. Turistas y reservas sin sobreventa.**
- **E4. Vehículos, conductores, asignaciones sin solapamientos, mantenimiento básico y estados de las salidas.**
- **E5. Anticipos, pagos manuales y saldos.**
- **E6. Auditoría de cambios.**

No se crearán endpoints, pantallas ni registros operativos ficticios para estas épicas.

E1–E5 están implementadas localmente. E3 se incorpora con las migraciones `004_create_tourists_and_reservations.sql` y `005_set_idempotency_key_binary_collation.sql`. E4 usa usuarios existentes con rol Conductor/Guía, una asignación de vehículo y conductor por salida y mantenimientos con intervalo y estado; los reemplazos de asignación se conservan en `departure_assignment_history`. E5 añade pagos manuales en BOB, saldos, idempotencia, concurrencia, anulación trazable y anticipos atómicos junto con reservas. E6 sigue pendiente.

## E1. Autenticación y roles

### Roles y permisos

| Rol | Permisos |
|---|---|
| Administrador | Administra usuarios y roles; acceso a todos los módulos. |
| Operador | Gestiona turistas, reservas, catálogo y salidas, asignaciones y pagos; no administra usuarios ni modifica auditoría. |
| Conductor/Guía | Consulta únicamente sus salidas y los datos de pasajeros necesarios para operarlas; no accede a pagos ni administra recursos. |
| Auditor | Consulta auditoría; no modifica registros operativos. |

Aplicar autorización en el servidor y denegar por defecto. Para módulos pendientes, conservar estos permisos como requisitos; no implementar sus rutas ahora.

### Identidades y credenciales

- No existe registro público. El inicio de sesión usa un correo único y contraseña.
- Cada usuario tiene rol y estado activo/inactivo.
- Solo un administrador puede crear usuarios, cambiar roles, restablecer contraseñas y activar/desactivar cuentas.
- Impedir que una operación deje cero administradores activos.
- Guardar contraseñas con `password_hash` y verificarlas con `password_verify`; preferir Argon2id si está disponible y usar BCrypt como alternativa documentada. Al usar BCrypt, rechazar contraseñas cuya representación UTF-8 exceda 72 bytes; no truncarlas.
- Inicializar el primer administrador mediante un comando explícito, idempotente, sin contraseñas fijas ni credenciales versionadas.

### Tokens y sesiones

- Emitir JWT con una biblioteca mantenida instalada por Composer, clave secreta fuera de Git y duración corta configurable.
- Aceptar solamente el algoritmo de firma configurado y validar firma y expiración.
- Enviar el JWT mediante `Authorization: Bearer`; el cliente lo conserva solo en memoria. Tras recargar puede ser necesario iniciar sesión otra vez.
- En cada petición protegida, volver a consultar en MySQL que la cuenta siga activa y obtener el rol vigente; no confiar en roles incluidos en el JWT.
- El cierre de sesión elimina el token en el cliente. Hasta que se implemente revocación, el JWT emitido conserva validez hasta expirar.

### Seguridad de la API

- Responder con un mensaje genérico ante credenciales incorrectas.
- Limitar intentos de autenticación con estado persistente entre peticiones.
- Validar entradas y usar consultas preparadas con PDO.
- Mantener un formato JSON uniforme para errores, distinguir acceso no autenticado (401) de falta de permiso (403) y no registrar contraseñas ni tokens.

### Interfaces y pruebas del primer incremento

Implementar login, consulta de la identidad autenticada, cierre de sesión y gestión administrativa básica de usuarios; proteger rutas y acciones en el servidor; proporcionar una pantalla de login, una vista mínima autenticada y gestión de usuarios para administradores.

Agregar migración de usuarios con restricciones y rol válido; pruebas unitarias y de integración con MySQL real para login correcto/incorrecto, JWT inválido/expirado, cuentas inactivas, 401, 403, límites de gestión de usuarios y protección del último administrador activo. Ejecutar las pruebas, el análisis de estilo y un smoke test; documentar resultados observados sin inventar cobertura.

## E2. Catálogo de tours y salidas

- Administrador y operador pueden crear, editar, listar y desactivar tours con nombre, descripción, itinerario, duración en días y precio base por persona en Bs (decimal). El catálogo almacena el precio; no calcula totales ni reservas.
- No borrar tours con salidas asociadas; desactivar conserva el historial.
- Crear y consultar salidas vinculadas a tours, con inicio, fin, capacidad total y estado. La capacidad no representa plazas disponibles mientras E3 (reservas) no esté implementada.
- Filtrar salidas por fecha de inicio inclusiva y estado; puede limitarse además por tour.
- Estados de salida: `programada`, `en_curso`, `finalizada` y `cancelada`; permitir únicamente `programada` → `en_curso`/`cancelada` y `en_curso` → `finalizada`.
- Validar entradas en servidor y proteger las rutas con permisos server-side; Conductor/Guía y Auditor no acceden al catálogo ni a las salidas.
- Validar las fechas con formato exacto, años 1000–9999 compatibles con MySQL/MariaDB, fechas reales y orden cronológico antes de guardar o filtrar.
- Mantener las capas Dominio, Aplicación e Infraestructura, e InnoDB con migraciones compatibles con MySQL 8 y MariaDB 11.4.
- No implementar en E2 reservas, asignaciones, mantenimiento ni pagos.

## E3. Turistas, reservas y cupos

- Administrador y Operador pueden registrar y editar turistas con nombre obligatorio y correo/teléfono opcionales.
- Crear, listar, modificar y cancelar reservas vinculadas a una salida programada de un tour activo. Una reserva guarda su titular y los nombres de sus pasajeros; el número de plazas es el número de pasajeros.
- Cada reserva fija un precio pactado decimal por persona y conserva ese valor aunque cambie el precio base del tour. Multiplicar y formatear usando centavos enteros, nunca `float`.
- Mostrar capacidad total, plazas confirmadas ocupadas y cupos disponibles. Cancelar cambia el estado, conserva la reserva/pasajeros y libera plazas una sola vez.
- Aplicar `Idempotency-Key` por usuario al crear reservas; reenvíos con el mismo contenido recuperan el resultado original y la misma clave con otro contenido produce 409.
- Crear y cambiar reservas dentro de transacciones InnoDB. Bloquear la fila de salida con `SELECT FOR UPDATE`, volver a validar el estado, la actividad del tour y la capacidad; al mover entre salidas, bloquearlas en orden ascendente de ID.
- Prevenir sobreventa bajo concurrencia y devolver 409 ante falta de cupos. No permitir reducir la capacidad de una salida por debajo de plazas confirmadas.
- Mantener autorización server-side; Conductor/Guía y Auditor no gestionan reservas ni turistas.
- No implementar en E3 pagos, vehículos, conductores, asignaciones ni mantenimiento. E3 no constituye una auditoría completa de E6.

## E4. Flota, asignaciones, mantenimiento e incidencias

- Administrador y Operador gestionan vehículos con placa única, capacidad positiva y estado `operativo`/`inactivo`. Los recursos con historial se desactivan; no se borran.
- Los conductores son usuarios activos existentes con rol `driver_guide`. Por salida se asigna exactamente un vehículo y un conductor.
- Las asignaciones de un vehículo o conductor no pueden solaparse. Los intervalos son semiabiertos `[inicio, fin)`: una salida que comienza al terminar otra no colisiona. Comprobar conflictos en servidor, al asignar/cambiar recursos y al modificar el horario de una salida.
- La capacidad de la salida no puede superar la del vehículo. No reducir la capacidad de vehículo por debajo de una salida asignada, ni la de salida por debajo de plazas reservadas. No iniciar una salida si carece de vehículo/conductor activos y válidos.
- Registrar mantenimiento de vehículo con horario, descripción y estado. Bloquear asignaciones que coincidan con mantenimiento programado/en curso; no aceptar mantenimientos que se crucen con salidas asignadas. Finalizados/cancelados conservan historial y dejan de bloquear.
- Coordinar asignaciones, mantenimientos y cambios de horario/capacidad con transacciones InnoDB y bloqueos en orden consistente, incluyendo solicitudes concurrentes.
- Registrar incidencias con fecha/hora y descripción vinculadas a una salida; conservar registros.
- Conductor/Guía solo consulta sus salidas asignadas y los nombres de pasajeros confirmados necesarios para operar. Puede registrar incidencias de sus salidas; no administra vehículos/asignaciones/mantenimiento ni ve datos de contacto o pagos.
- No implementar repuestos, alertas, varios vehículos/conductores por salida, pagos ni otras funciones fuera de este MVP.

## E5. Anticipos, pagos y saldos

- Registrar pagos manuales en BOB vinculados a una reserva, con monto, fecha, método, referencia opcional y usuario responsable.
- Mostrar total, pagos netos aplicados y saldo pendiente; usar operaciones decimales seguras.
- Bloquear la reserva con transacción para rechazar sobrepagos concurrentes e impedir montos no positivos.
- Requerir idempotencia para reintentos; anular solo como administrador, con motivo y sin borrar pagos.
- Cancelar una reserva no elimina pagos ni simula devoluciones bancarias. Un anticipo incluido al crear una reserva se confirma o revierte junto con ella.
- E5 no completa la auditoría E6 ni implementa pasarelas bancarias.

## E6. Auditoría de cambios

- Registrar en la misma transacción las altas, modificaciones y cancelaciones de reservas; cambios de precio de tours; registro y anulación de pagos; asignaciones de recursos y mantenimiento.
- Cada evento conserva actor, acción, entidad, identificador, fecha y valores anteriores/nuevos relevantes. No guardar contraseñas, tokens, documentos personales, nombres de pasajeros ni otros datos sensibles innecesarios.
- Si falla la escritura del evento, la operación de negocio debe revertirse y no debe quedar un evento de éxito huérfano.
- Permitir consulta paginada y filtrada por fecha, entidad, acción y actor únicamente a Administrador y Auditor. Operador y Conductor/Guía reciben 403.
- No permitir editar ni eliminar eventos desde la aplicación. No inventar eventos anteriores al inicio efectivo de E6.
- La implementación local comienza con la migración `009_create_audit_events.sql`; `010` y `011` dejan la FK del actor con `ON DELETE RESTRICT` para preservar el historial.

## Versiones y publicación

PHP 8.3 se utiliza provisionalmente en Docker local porque es una rama mantenida y PHP 8.2 de XAMPP no se toma como versión del proyecto. La versión de PHP y la disponibilidad de `pdo_mysql` en InfinityFree no se han confirmado; la compatibilidad de publicación es una decisión pendiente. Docker es únicamente para desarrollo local.

La publicación futura por InfinityFree y FileZilla no forma parte de E1. DuckDNS es una demostración separada que requeriría un servidor accesible. No configurar servicios externos ni publicar durante este incremento.

## Fuera de alcance

Pasarelas bancarias, facturación electrónica, WhatsApp, hoteles externos, aplicación móvil, multimoneda, reseñas y analítica avanzada.
