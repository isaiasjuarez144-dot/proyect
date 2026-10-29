<?php

declare(strict_types=1);

?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Uyuni Tour</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous">
    <link href="/assets/css/app.css?v=20261029-panel-2" rel="stylesheet">
</head>
<body>
    <main class="container app-shell py-4 py-lg-5">
        <header class="site-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <a
                        class="brand d-flex align-items-center gap-3 text-decoration-none"
                        href="/"
                        aria-label="Uyuni Tour, inicio">
                        <span class="brand-mark" aria-hidden="true">UT</span>
                        <span>
                            <span class="brand-kicker d-block">Agencia de turismo</span>
                            <span class="brand-name d-block">Uyuni Tour</span>
                        </span>
                    </a>
                    <button id="logout-button" class="btn btn-outline-secondary d-none" type="button">
                        Cerrar sesión
                    </button>
        </header>
        <div id="notice" class="alert d-none" role="status" aria-live="polite"></div>
        <section id="login-panel" class="auth-shell">
                    <div class="hero-copy">
                        <span class="eyebrow">Viajes que dejan huella</span>
                        <h2>Descubre el salar, <em>vive Bolivia.</em></h2>
                        <p>Organiza tours, salidas y reservas con una vista clara de cada detalle de tu operación.</p>
                        <div class="hero-highlights" aria-label="Ventajas de Uyuni Tour">
                            <span><strong>01</strong> Experiencias únicas</span>
                            <span><strong>02</strong> Operación ordenada</span>
                        </div>
                    </div>
                    <div class="card auth-card">
                        <div class="card-body p-4 p-lg-5">
                            <span class="section-label">Acceso seguro</span>
                            <h2 class="h4 mb-2">Bienvenido de nuevo</h2>
                            <p class="text-secondary small mb-4">Ingresa para gestionar tu agencia.</p>
                            <form id="login-form">
                                <div class="mb-3">
                                    <label class="form-label" for="login-email">Correo electrónico</label>
                                    <input class="form-control" id="login-email" name="email" type="email"
                                           autocomplete="username" maxlength="254" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" for="login-password">Contraseña</label>
                                    <input class="form-control" id="login-password" name="password" type="password"
                                           autocomplete="current-password" required>
                                </div>
                                <button class="btn btn-primary w-100" type="submit">Entrar a mi cuenta</button>
                            </form>
                        </div>
                    </div>
        </section>
        <section id="account-panel" class="d-none">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h4">Cuenta</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Correo</dt>
                        <dd id="account-email" class="col-sm-9"></dd>
                        <dt class="col-sm-3">Rol</dt>
                        <dd id="account-role" class="col-sm-9"></dd>
                    </dl>
                </div>
            </div>
            <section id="users-panel" class="card shadow-sm d-none">
                <div class="card-body">
                    <h2 class="h4">Usuarios</h2>
                    <form id="create-user-form" class="row g-3 align-items-end mb-4">
                        <div class="col-md-4">
                            <label class="form-label" for="new-email">Correo</label>
                            <input class="form-control" id="new-email" type="email" maxlength="254" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="new-password">Contraseña inicial</label>
                            <input class="form-control" id="new-password" type="password"
                                   autocomplete="new-password" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="new-role">Rol</label>
                            <select class="form-select" id="new-role">
                                <option value="administrator">Administrador</option>
                                <option value="operator">Operador</option>
                                <option value="driver_guide">Conductor/Guía</option>
                                <option value="auditor">Auditor</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" type="submit">Crear usuario</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr><th>Correo</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr>
                            </thead>
                            <tbody id="users-list"></tbody>
                        </table>
                    </div>
                </div>
            </section>
            <section id="payments-panel" class="card shadow-sm mt-4 d-none">
                <div class="card-body">
                    <h2 class="h4">Pagos y saldos</h2>
                    <p class="text-secondary">
                        Registra anticipos o pagos en bolivianos. El saldo se calcula sin borrar pagos anulados.
                    </p>
                    <form id="payment-form" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label" for="payment-reservation">Reserva</label>
                            <select class="form-select" id="payment-reservation" required></select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="payment-amount">Monto (Bs)</label>
                            <input class="form-control" id="payment-amount" type="number" min="0.01"
                                   max="9999999999.99" step="0.01" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="payment-method">Método</label>
                            <select class="form-select" id="payment-method">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="qr">QR</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="payment-reference">Referencia (opcional)</label>
                            <input class="form-control" id="payment-reference" maxlength="120">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="payment-date">Fecha</label>
                            <input class="form-control" id="payment-date" type="datetime-local"
                                   min="1000-01-01T00:00" max="9999-12-31T23:59" required>
                        </div>
                        <div class="col-md-1">
                            <button class="btn btn-primary w-100" type="submit">Guardar</button>
                        </div>
                    </form>
                </div>
            </section>
            <section id="audit-panel" class="card shadow-sm mt-4 d-none">
                <div class="card-body">
                    <h2 class="h4">Auditoría</h2>
                    <p class="text-secondary">
                        Aquí puedes consultar quién realizó cambios, qué cambió y cuándo.
                    </p>
                    <form id="audit-filters" class="row g-3 align-items-end mb-3">
                        <div class="col-md-2">
                            <label class="form-label" for="audit-from">Desde</label>
                            <input class="form-control" id="audit-from" type="date">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="audit-to">Hasta</label>
                            <input class="form-control" id="audit-to" type="date">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="audit-entity">Entidad</label>
                            <select class="form-select" id="audit-entity">
                                <option value="">Todas</option>
                                <option value="reservation">Reserva</option>
                                <option value="tour">Tour</option>
                                <option value="payment">Pago</option>
                                <option value="assignment">Asignación</option>
                                <option value="maintenance">Mantenimiento</option>
                                <option value="vehicle">Vehículo</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="audit-action">Acción</label>
                            <select class="form-select" id="audit-action">
                                <option value="">Todas</option>
                                <option value="created">Alta</option>
                                <option value="updated">Modificación</option>
                                <option value="cancelled">Cancelación</option>
                                <option value="voided">Anulación</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-primary w-100" type="submit">Buscar</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr><th>Fecha</th><th>Actor</th><th>Acción</th><th>Entidad</th>
                                    <th>Identificador</th><th>Antes</th><th>Después</th></tr>
                            </thead>
                            <tbody id="audit-list"></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <button class="btn btn-outline-secondary" id="audit-previous" type="button">Anterior</button>
                        <span id="audit-page" class="small text-secondary"></span>
                        <button class="btn btn-outline-secondary" id="audit-next" type="button">Siguiente</button>
                    </div>
                </div>
            </section>
            <section id="driver-panel" class="card shadow-sm mt-4 d-none">
                <div class="card-body">
                    <h2 class="h4">Mis salidas</h2>
                    <p class="text-secondary">
                        Aquí ves únicamente las salidas asignadas a tu cuenta y los nombres de sus pasajeros.
                    </p>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Tour</th><th>Inicio</th><th>Fin</th><th>Vehículo</th>
                                <th>Estado</th><th>Pasajeros</th></tr></thead>
                            <tbody id="driver-departures-list"></tbody>
                        </table>
                    </div>
                    <h3 class="h5 mt-4">Registrar una incidencia</h3>
                    <form id="driver-incident-form" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="driver-incident-departure">Mi salida</label>
                            <select class="form-select" id="driver-incident-departure" required></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="driver-incident-time">Fecha y hora</label>
                            <input class="form-control" id="driver-incident-time" type="datetime-local"
                                   min="1000-01-01T00:00" max="9999-12-31T23:59" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="driver-incident-description">Qué ocurrió</label>
                            <input class="form-control" id="driver-incident-description" maxlength="2000" required>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Guardar incidencia</button>
                        </div>
                    </form>
                    <div class="table-responsive mt-4">
                        <table class="table align-middle">
                            <thead><tr><th>Salida</th><th>Fecha y hora</th><th>Incidencia</th>
                                <th>Registrada por</th></tr></thead>
                            <tbody id="driver-incidents-list"></tbody>
                        </table>
                    </div>
                </div>
            </section>
            <section id="catalog-panel" class="mt-4">
                <p class="lead">
                    Primero crea un tour. Después programa una salida indicando cuándo se realizará y
                    cuántas personas podrá llevar.
                </p>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h2 class="h4">Tours</h2>
                        <p class="text-secondary">
                            Un tour es el recorrido que ofrece la agencia, por ejemplo: Salar de Uyuni de 1 día.
                        </p>
                        <form id="tour-form" class="row g-3 mb-4">
                            <input id="tour-id" type="hidden">
                            <div class="col-md-6">
                                <label class="form-label" for="tour-name">Nombre</label>
                                <input class="form-control" id="tour-name" maxlength="150" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="tour-duration">Duración (días)</label>
                                <input class="form-control" id="tour-duration" type="number" min="1" step="1" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="tour-price">Precio base por persona (Bs)</label>
                                <input class="form-control" id="tour-price" type="number" min="0" step="0.01" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="tour-description">Descripción</label>
                                <textarea class="form-control" id="tour-description" rows="2" required></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="tour-itinerary">Itinerario</label>
                                <textarea class="form-control" id="tour-itinerary" rows="3" required></textarea>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button class="btn btn-primary" type="submit" id="tour-submit">Crear tour</button>
                                <button class="btn btn-outline-secondary d-none" type="button" id="tour-cancel">
                                    Cancelar edición
                                </button>
                            </div>
                        </form>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Tour</th>
                                        <th>Duración</th>
                                        <th>Precio base por persona (Bs)</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tours-list"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h2 class="h4">Salidas</h2>
                        <p class="text-secondary">
                            Una salida es una fecha y horario concretos para realizar un tour.
                        </p>
                        <p class="text-secondary">La capacidad indica cuántas personas puede llevar la salida.</p>
                        <section aria-labelledby="schedule-departure-heading" class="mb-4">
                            <h3 id="schedule-departure-heading" class="h5">Programar una salida</h3>
                            <form id="departure-form" class="row g-3 align-items-end mb-4">
                            <input id="departure-id" type="hidden">
                            <div class="col-md-3">
                                <label class="form-label" for="departure-tour">Tour a realizar</label>
                                <select class="form-select" id="departure-tour" required></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="departure-start">Fecha y hora de inicio</label>
                                <input
                                    class="form-control"
                                    id="departure-start"
                                    type="datetime-local"
                                    min="1000-01-01T00:00"
                                    max="9999-12-31T23:59"
                                    aria-describedby="departure-start-error"
                                    required>
                                <div id="departure-start-error" class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="departure-end">Fecha y hora de fin</label>
                                <input
                                    class="form-control"
                                    id="departure-end"
                                    type="datetime-local"
                                    min="1000-01-01T00:00"
                                    max="9999-12-31T23:59"
                                    aria-describedby="departure-end-error"
                                    required>
                                <div id="departure-end-error" class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="departure-capacity">Capacidad total (personas)</label>
                                <input
                                    class="form-control"
                                    id="departure-capacity"
                                    type="number"
                                    min="1"
                                    step="1"
                                    required>
                            </div>
                            <div class="col-md-1">
                                <button class="btn btn-primary w-100" type="submit">Programar salida</button>
                            </div>
                            </form>
                        </section>
                        <section aria-labelledby="search-departures-heading">
                            <h3 id="search-departures-heading" class="h5">Buscar salidas</h3>
                            <form id="departure-filters" class="row g-3 align-items-end mb-3">
                            <div class="col-sm-3">
                                <label class="form-label" for="filter-from">Desde</label>
                                <input
                                    class="form-control"
                                    id="filter-from"
                                    type="date"
                                    min="1000-01-01"
                                    max="9999-12-31"
                                    aria-describedby="filter-from-error">
                                <div id="filter-from-error" class="invalid-feedback"></div>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label" for="filter-to">Hasta</label>
                                <input
                                    class="form-control"
                                    id="filter-to"
                                    type="date"
                                    min="1000-01-01"
                                    max="9999-12-31"
                                    aria-describedby="filter-to-error">
                                <div id="filter-to-error" class="invalid-feedback"></div>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label" for="filter-status">Estado</label>
                                <select class="form-select" id="filter-status">
                                    <option value="">Todos</option>
                                    <option value="programada">Programada</option>
                                    <option value="en_curso">En curso</option>
                                    <option value="finalizada">Finalizada</option>
                                    <option value="cancelada">Cancelada</option>
                                </select>
                            </div>
                            <div class="col-sm-3 d-flex gap-2">
                                <button class="btn btn-outline-primary" type="submit">Filtrar salidas</button>
                                <button class="btn btn-outline-secondary" type="reset">Limpiar filtros</button>
                            </div>
                            </form>
                            <ul class="small text-secondary">
                                <li>Programada: todavía no comenzó.</li>
                                <li>En curso: el recorrido está realizándose.</li>
                                <li>Finalizada: el recorrido terminó.</li>
                                <li>Cancelada: la salida no se realizará.</li>
                            </ul>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Tour</th>
                                            <th>Inicio</th>
                                            <th>Fin</th>
                                            <th>Capacidad total</th>
                                            <th>Plazas ocupadas</th>
                                            <th>Cupos disponibles</th>
                                            <th>Estado</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="departures-list"></tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>
                <section id="reservations-panel" class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h4">Turistas y reservas</h2>
                        <p class="text-secondary">
                            Una reserva registra quiénes viajarán en una salida y cuántas plazas ocuparán.
                        </p>
                        <section aria-labelledby="tourists-heading" class="mb-4">
                            <h3 id="tourists-heading" class="h5">Turistas</h3>
                            <form id="tourist-form" class="row g-3 align-items-end mb-3">
                                <input id="tourist-id" type="hidden">
                                <div class="col-md-4">
                                    <label class="form-label" for="tourist-name">Nombre completo</label>
                                    <input
                                        class="form-control"
                                        id="tourist-name"
                                        maxlength="150"
                                        aria-describedby="tourist-name-error"
                                        required>
                                    <div id="tourist-name-error" class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="tourist-email">Correo (opcional)</label>
                                    <input
                                        class="form-control"
                                        id="tourist-email"
                                        type="email"
                                        maxlength="254"
                                        aria-describedby="tourist-email-error">
                                    <div id="tourist-email-error" class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="tourist-phone">Teléfono (opcional)</label>
                                    <input
                                        class="form-control"
                                        id="tourist-phone"
                                        maxlength="40"
                                        aria-describedby="tourist-phone-error">
                                    <div id="tourist-phone-error" class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-2 d-flex gap-2">
                                    <button
                                        class="btn btn-primary"
                                        type="submit"
                                        id="tourist-submit">Guardar turista</button>
                                    <button class="btn btn-outline-secondary d-none" id="tourist-cancel" type="button">
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tourists-list"></tbody>
                                </table>
                            </div>
                        </section>
                        <section aria-labelledby="reservations-heading">
                            <h3 id="reservations-heading" class="h5">Reservas</h3>
                            <form id="reservation-form" class="row g-3 mb-4">
                                <input id="reservation-id" type="hidden">
                                <div class="col-md-4">
                                    <label class="form-label" for="reservation-tourist">Titular de la reserva</label>
                                    <select class="form-select" id="reservation-tourist" required></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="reservation-departure">Salida programada</label>
                                    <select class="form-select" id="reservation-departure" required></select>
                                </div>
                                <div class="col-md-4">
                                    <label
                                        class="form-label"
                                        for="reservation-price">Precio pactado por persona (Bs)</label>
                                    <input class="form-control" id="reservation-price" type="number" min="0"
                                           max="99999999.99" step="0.01" required>
                                </div>
                                <div class="col-12">
                                    <label
                                        class="form-label"
                                        for="reservation-passengers">
                                        Pasajeros (un nombre por línea)
                                    </label>
                                    <textarea
                                        class="form-control"
                                        id="reservation-passengers"
                                        rows="3"
                                        required></textarea>
                                </div>
                                <div class="col-12 d-flex gap-2">
                                    <button
                                        class="btn btn-primary"
                                        type="submit"
                                        id="reservation-submit">Crear reserva</button>
                                    <button
                                        class="btn btn-outline-secondary d-none"
                                        id="reservation-cancel-edit"
                                        type="button">
                                        Cancelar edición
                                    </button>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Turista</th><th>Tour y salida</th><th>Pasajeros</th>
                                            <th>Precio/persona</th><th>Total</th><th>Pagado neto</th><th>Saldo</th>
                                            <th>Estado</th><th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reservations-list"></tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </section>
                <section id="fleet-panel" class="card shadow-sm mt-4">
                    <div class="card-body">
                        <h2 class="h4">Vehículos, asignaciones y mantenimiento</h2>
                        <p class="text-secondary">
                            Cada salida usa un vehículo y un conductor. No se asignan recursos
                            en horarios que se cruzan.
                        </p>
                        <section aria-labelledby="vehicles-heading" class="mb-4">
                            <h3 id="vehicles-heading" class="h5">Vehículos</h3>
                            <form id="vehicle-form" class="row g-3 align-items-end mb-3">
                                <input id="vehicle-id" type="hidden">
                                <div class="col-md-4">
                                    <label class="form-label" for="vehicle-plate">Placa</label>
                                    <input class="form-control" id="vehicle-plate" maxlength="20" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="vehicle-capacity">Capacidad (personas)</label>
                                    <input class="form-control" id="vehicle-capacity" type="number" min="1"
                                           step="1" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="vehicle-status">Estado</label>
                                    <select class="form-select" id="vehicle-status">
                                        <option value="operativo">Operativo</option>
                                        <option value="inactivo">Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex gap-2">
                                    <button class="btn btn-primary" id="vehicle-submit" type="submit">Guardar</button>
                                    <button class="btn btn-outline-secondary d-none" id="vehicle-cancel"
                                            type="button">Cancelar</button>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr><th>Placa</th><th>Capacidad</th><th>Estado</th><th>Acción</th></tr>
                                    </thead>
                                    <tbody id="vehicles-list"></tbody>
                                </table>
                            </div>
                        </section>
                        <section aria-labelledby="assignments-heading" class="mb-4">
                            <h3 id="assignments-heading" class="h5">Asignaciones</h3>
                            <p class="small text-secondary">
                                La capacidad de la salida no puede superar la del vehículo.
                                Las horas consecutivas sí se permiten.
                            </p>
                            <form id="assignment-form" class="row g-3 align-items-end mb-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="assignment-departure">Salida programada</label>
                                    <select class="form-select" id="assignment-departure" required></select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="assignment-vehicle">Vehículo operativo</label>
                                    <select class="form-select" id="assignment-vehicle" required></select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="assignment-driver">Conductor activo</label>
                                    <select class="form-select" id="assignment-driver" required></select>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-primary w-100" type="submit">Guardar asignación</button>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead><tr><th>Salida</th><th>Horario</th><th>Vehículo</th>
                                        <th>Conductor</th><th>Estado</th><th>Acción</th></tr></thead>
                                    <tbody id="assignments-list"></tbody>
                                </table>
                            </div>
                        </section>
                        <section aria-labelledby="maintenance-heading" class="mb-4">
                            <h3 id="maintenance-heading" class="h5">Mantenimiento</h3>
                            <form id="maintenance-form" class="row g-3 align-items-end mb-3">
                                <div class="col-md-3">
                                    <label class="form-label" for="maintenance-vehicle">Vehículo</label>
                                    <select class="form-select" id="maintenance-vehicle" required></select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="maintenance-start">Inicio</label>
                                    <input class="form-control" id="maintenance-start" type="datetime-local"
                                           min="1000-01-01T00:00" max="9999-12-31T23:59" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="maintenance-end">Fin</label>
                                    <input class="form-control" id="maintenance-end" type="datetime-local"
                                           min="1000-01-01T00:00" max="9999-12-31T23:59" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="maintenance-description">Trabajo previsto</label>
                                    <input class="form-control" id="maintenance-description" maxlength="1000" required>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit">Registrar mantenimiento</button>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead><tr><th>Vehículo</th><th>Horario</th><th>Descripción</th>
                                        <th>Estado</th><th>Acción</th></tr></thead>
                                    <tbody id="maintenance-list"></tbody>
                                </table>
                            </div>
                        </section>
                        <section aria-labelledby="incidents-heading">
                            <h3 id="incidents-heading" class="h5">Incidencias de salidas</h3>
                            <form id="incident-form" class="row g-3 align-items-end mb-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="incident-departure">Salida</label>
                                    <select class="form-select" id="incident-departure" required></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="incident-time">Fecha y hora</label>
                                    <input class="form-control" id="incident-time" type="datetime-local"
                                           min="1000-01-01T00:00" max="9999-12-31T23:59" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="incident-description">Qué ocurrió</label>
                                    <input class="form-control" id="incident-description" maxlength="2000" required>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit">Guardar incidencia</button>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead><tr><th>Salida</th><th>Fecha y hora</th><th>Incidencia</th>
                                        <th>Registrada por</th></tr></thead>
                                    <tbody id="incidents-list"></tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </section>
            </section>
        </section>
        <footer class="site-footer">
            <span class="brand-kicker">Uyuni Tour</span>
            <span>Gestión clara para experiencias inolvidables en Bolivia.</span>
        </footer>
    </main>
    <script src="/assets/js/app.js" defer></script>
</body>
</html>
