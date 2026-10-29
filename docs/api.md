# API de E1, E2, E3, E4 y E5

Base local: `http://localhost:8080/api/v1`. Los endpoints JSON responden con `data` al tener éxito y `error: {code, message}` al fallar. Las rutas protegidas usan la cabecera `Authorization` con esquema Bearer; 401 significa falta/invalidación de identidad y 403 identidad válida sin permiso. Las validaciones devuelven 422, los conflictos de cupos e idempotencia 409 y las rutas inexistentes 404.

| Método | Ruta | Permiso |
|---|---|---|
| `POST` | `/auth/login` | Público; correo y contraseña en JSON. Devuelve token y usuario; los fallos de credenciales usan un mensaje genérico. |
| `GET` | `/auth/me` | Usuario activo autenticado; devuelve identidad y rol vigentes consultados en MySQL. |
| `POST` | `/auth/logout` | Usuario activo autenticado; el cliente descarta el token. No revoca el JWT ya emitido; conserva validez hasta expirar. |
| `GET` | `/users` | Solo administrador. |
| `POST` | `/users` | Solo administrador; crea correo, contraseña y rol válidos. |
| `PATCH` | `/users/{id}` | Solo administrador; modifica rol y/o estado activo. No permite dejar cero administradores activos. |
| `POST` | `/users/{id}/password` | Solo administrador; restablece contraseña. |
| `GET` | `/tours` | Administrador u operador; lista tours activos e inactivos. |
| `POST` | `/tours` | Administrador u operador; crea un tour con `name`, `description`, `itinerary`, `duration_days` (días) y `base_price` (precio base por persona en Bs, decimal de dos posiciones). |
| `PATCH` | `/tours/{id}` | Administrador u operador; actualiza cualquier subconjunto de los campos del tour; `active: false` lo desactiva y `active: true` lo reactiva. No existe operación de borrado. |
| `GET` | `/departures` | Administrador u operador; consulta salidas con filtros opcionales `tour_id`, `from`, `to` (`YYYY-MM-DD`, inclusivos) y `status`. Las fechas filtran por día de inicio. Las fechas deben existir y estar entre 1000 y 9999; `from` no puede ser posterior a `to`. |
| `POST` | `/tours/{id}/departures` | Administrador u operador; crea salida de tour activo con `start_at`, `end_at` (`YYYY-MM-DDTHH:MM`, hora local sin conversión de zona), `capacity` positiva y estado inicial `programada`. Las fechas deben existir y estar entre 1000 y 9999; el fin debe ser posterior al inicio. |
| `PATCH` | `/departures/{id}` | Administrador u operador; cuerpo `{ "status": "..." }`, sujeto a las transiciones válidas de abajo. |
| `PATCH` | `/departures/{id}` | Administrador u operador; también acepta `{ "capacity": n }` por separado. Devuelve 409 si la capacidad es menor que las plazas confirmadas. |
| `GET` | `/tourists` | Administrador u operador; lista turistas. |
| `POST` | `/tourists` | Administrador u operador; crea `{ "full_name": "...", "email": null, "phone": null }`. Solo el nombre es obligatorio. |
| `PATCH` | `/tourists/{id}` | Administrador u operador; actualiza nombre, correo y teléfono. No elimina turistas ni historial. |
| `GET` | `/reservations` | Administrador u operador; lista reservas y pasajeros con precio pactado, total y estado. |
| `POST` | `/reservations` | Administrador u operador; requiere `Idempotency-Key` único (8–128 caracteres) y JSON `{ "departure_id": 1, "tourist_id": 1, "passengers": ["Nombre"], "price_per_person": "125.50" }`. La cantidad de plazas se deriva del número de pasajeros; el precio se guarda como importe pactado y el total se calcula en centavos enteros. Una repetición con la misma clave y datos devuelve la reserva existente; reutilizarla con datos distintos da 409. |
| `PATCH` | `/reservations/{id}` | Administrador u operador; modifica `{ "departure_id": n, "tourist_id": n, "passengers": ["Nombre"] }`. Revalida el destino y los cupos; conserva el precio unitario pactado y recalcula el total. |
| `POST` | `/reservations/{id}/cancel` | Administrador u operador; cancela sin borrar historial. Repetir la cancelación es seguro y no vuelve a liberar plazas. |
| `GET` | `/reservations/{id}/payments` | Administrador u operador; lista pagos aplicados y anulados de la reserva. |
| `POST` | `/reservations/{id}/payments` | Administrador u operador; requiere `Idempotency-Key` y `{ "amount": "40.00", "paid_at": "YYYY-MM-DDTHH:MM", "method": "efectivo|transferencia|qr|tarjeta|otro", "reference": null }`. Bloquea la reserva y rechaza montos no positivos, superiores al saldo o pagos de reservas canceladas. |
| `POST` | `/payments/{id}/void` | Solo administrador; requiere `{ "reason": "..." }`. Anula de forma trazable sin borrar ni simular una devolución bancaria; una segunda anulación responde 409. |
| `GET` | `/audit` | Administrador o Auditor; consulta eventos E6 con filtros opcionales `from`, `to`, `entity`, `action`, `actor_id`, `page` y `per_page`. Devuelve actor, acción, entidad, identificador, fecha y valores anteriores/nuevos relevantes. Operador y Conductor/Guía reciben 403. |
| `GET` | `/vehicles` | Administrador u operador; lista vehículos e historial. |
| `POST` | `/vehicles` | Administrador u operador; crea `{ "plate": "ABC-123", "capacity": 8 }` en estado `operativo`. La placa se normaliza a mayúsculas y es única. |
| `PATCH` | `/vehicles/{id}` | Administrador u operador; cambia placa, capacidad y/o `status` (`operativo`/`inactivo`). No elimina. La capacidad no puede quedar por debajo de una salida asignada; un vehículo en curso no puede desactivarse. |
| `GET` | `/drivers` | Administrador u operador; lista cuentas activas con rol `driver_guide`. |
| `GET` | `/assignments` | Administrador u operador; lista la asignación vigente y su arreglo `history` con los recursos y fechas de asignaciones anteriores. |
| `PUT` | `/departures/{id}/assignment` | Administrador u operador; asigna o reemplaza `{ "vehicle_id": n, "driver_user_id": n }` de una salida programada. Rechaza recursos inactivos, exceso de capacidad, mantenimiento y solapamientos con HTTP 409. Cada reemplazo conserva la asignación anterior. |
| `PATCH` | `/departures/{id}` | Administrador u operador; `{ "start_at": "...", "end_at": "..." }` actualiza juntos el horario de una salida programada y revalida los recursos asignados y los solapamientos. |
| `GET` | `/maintenance` | Administrador u operador; historial de mantenimientos, opcionalmente filtrado por `vehicle_id`. |
| `POST` | `/vehicles/{id}/maintenance` | Administrador u operador; registra `{ "start_at": "...", "end_at": "...", "description": "..." }`; 409 si choca con una salida asignada. |
| `PATCH` | `/maintenance/{id}` | Administrador u operador; transición válida de mantenimiento: `programado` → `en_curso`/`cancelado`, `en_curso` → `completado`. |
| `POST` | `/departures/{id}/incidents` | Administrador/operador; Conductor/Guía solo para su propia salida. Requiere `occurred_at` y `description`; conserva historial. |
| `GET` | `/incidents` | Administrador u operador; lista incidencias con filtro opcional `departure_id`. Conductor/Guía solo ve incidencias de sus salidas. |
| `GET` | `/my/departures` | Solo Conductor/Guía; lista únicamente salidas asignadas y nombres de pasajeros con reservas confirmadas; no entrega datos de contacto ni pagos. |

Las reservas incluyen `total_price`, `paid_net` y `balance_due` en BOB. Una reserva puede incluir opcionalmente `payment` con `amount`, `paid_at`, `method`, `reference` e `idempotency_key`; reserva y anticipo se confirman en una misma transacción o se revierten juntos. Los pagos usan importes decimales seguros, conservan historial y las anulaciones liberan el saldo sin borrar el registro.

## Estados de salidas

| Estado actual | Transiciones permitidas |
|---|---|
| `programada` | `en_curso`, `cancelada` |
| `en_curso` | `finalizada` |
| `finalizada` | Ninguna |
| `cancelada` | Ninguna |

Conductor/Guía y Auditor no pueden gestionar catálogo, reservas, vehículos, asignaciones o mantenimiento. Conductor/Guía consulta sus salidas y pasajeros necesarios mediante `/my/departures` y puede registrar incidencias únicamente en sus salidas. Auditor no accede a módulos operativos. La auditoría es de solo lectura y no expone contraseñas, tokens, documentos personales ni nombres de pasajeros.

Administrador y Operador pueden gestionar turistas y reservas; Conductor/Guía y Auditor reciben 403. Pagos, repuestos, alertas automáticas y asignaciones múltiples siguen fuera del alcance. La auditoría E6 está implementada localmente y no se puede editar ni eliminar desde la aplicación.
