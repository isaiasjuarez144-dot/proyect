(() => {
    let token = null;
    const loginPanel = document.querySelector("#login-panel");
    const accountPanel = document.querySelector("#account-panel");
    const usersPanel = document.querySelector("#users-panel");
    const paymentsPanel = document.querySelector("#payments-panel");
    const auditPanel = document.querySelector("#audit-panel");
    const catalogPanel = document.querySelector("#catalog-panel");
    const fleetPanel = document.querySelector("#fleet-panel");
    const driverPanel = document.querySelector("#driver-panel");
    const notice = document.querySelector("#notice");
    const logoutButton = document.querySelector("#logout-button");
    const tourForm = document.querySelector("#tour-form");
    const createUserForm = document.querySelector("#create-user-form");
    const departureForm = document.querySelector("#departure-form");
    const departureFilters = document.querySelector("#departure-filters");
    let tours = [];
    let reservationDepartures = [];
    let tourists = [];
    let vehicles = [];
    let drivers = [];
    let managedDepartures = [];
    let auditPage = 1;
    let auditPages = 0;
    const dateMin = "1000-01-01";
    const dateMax = "9999-12-31";
    const dateTimeMin = `${dateMin}T00:00`;
    const dateTimeMax = `${dateMax}T23:59`;
    const roleLabels = {
        administrator: "Administrador",
        operator: "Operador",
        driver_guide: "Conductor/Guía",
        auditor: "Auditor",
    };

    function showNotice(message, isError = false) {
        notice.textContent = message;
        notice.className = `alert ${isError ? "alert-danger" : "alert-success"}`;
    }

    class ApiError extends Error {
        constructor(message, field = null, sessionExpired = false) {
            super(message);
            this.field = field;
            this.sessionExpired = sessionExpired;
        }
    }

    function clearSession() {
        token = null;
        accountPanel.classList.add("d-none");
        loginPanel.classList.remove("d-none");
        logoutButton.classList.add("d-none");
        usersPanel.classList.add("d-none");
        paymentsPanel.classList.add("d-none");
        auditPanel.classList.add("d-none");
        catalogPanel.classList.add("d-none");
        fleetPanel.classList.add("d-none");
        driverPanel.classList.add("d-none");
    }

    async function request(path, options = {}) {
        const headers = { Accept: "application/json", ...options.headers };
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }
        if (options.body) {
            headers["Content-Type"] = "application/json";
        }
        const response = await fetch(`/api/v1${path}`, { ...options, headers });
        if (response.status === 204) {
            return null;
        }
        const payload = await response.json();
        if (!response.ok) {
            if (response.status === 401 && token && path !== "/auth/login") {
                clearSession();
                throw new ApiError("Tu sesión expiró. Inicia sesión nuevamente", null, true);
            }
            throw new ApiError(
                payload.error?.message || "No se pudo completar la solicitud.",
                payload.error?.field
            );
        }
        return payload.data;
    }

    function setFieldError(field, message) {
        const fieldMap = {
            start_at: "departure-start",
            end_at: "departure-end",
            from: "filter-from",
            to: "filter-to",
            full_name: "tourist-name",
            email: "tourist-email",
            phone: "tourist-phone",
        };
        const inputId = fieldMap[field];
        if (!inputId) {
            return;
        }
        const input = document.querySelector(`#${inputId}`);
        const error = document.querySelector(`#${inputId}-error`);
        if (!error) {
            return;
        }
        input.setCustomValidity(message);
        input.setAttribute("aria-invalid", "true");
        error.textContent = message;
        error.classList.add("d-block");
    }

    function clearFieldError(input) {
        input.setCustomValidity("");
        input.removeAttribute("aria-invalid");
        const error = document.querySelector(`#${input.id}-error`);
        if (error) {
            error.textContent = "";
            error.classList.remove("d-block");
        }
    }

    function showApiError(error) {
        if (error.field) {
            setFieldError(error.field, error.message);
        }
        showNotice(error.message, true);
    }

    function isValidDate(value) {
        if (!/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(value)) {
            return false;
        }
        const [year, month, day] = value.split("-").map(Number);
        if (year < 1000 || year > 9999) {
            return false;
        }
        const parsed = new Date(Date.UTC(year, month - 1, day));
        return parsed.getUTCFullYear() === year
            && parsed.getUTCMonth() === month - 1
            && parsed.getUTCDate() === day;
    }

    function isValidDateTime(value) {
        if (!/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/.test(value)) {
            return false;
        }
        const [date, time] = value.split("T");
        if (!isValidDate(date)) {
            return false;
        }
        const [hour, minute] = time.split(":").map(Number);
        return hour <= 23 && minute <= 59;
    }

    function updateDateConstraints() {
        const start = document.querySelector("#departure-start");
        const end = document.querySelector("#departure-end");
        const from = document.querySelector("#filter-from");
        const to = document.querySelector("#filter-to");
        start.min = dateTimeMin;
        start.max = dateTimeMax;
        end.min = start.value || dateTimeMin;
        end.max = dateTimeMax;
        from.min = dateMin;
        from.max = to.value || dateMax;
        to.min = from.value || dateMin;
        to.max = dateMax;
    }

    function validateDepartureForm() {
        const start = document.querySelector("#departure-start");
        const end = document.querySelector("#departure-end");
        clearFieldError(start);
        clearFieldError(end);

        if (!isValidDateTime(start.value)) {
            setFieldError("start_at", "Ingresa una fecha y hora válida (año 1000 a 9999).");
        }
        if (!isValidDateTime(end.value)) {
            setFieldError("end_at", "Ingresa una fecha y hora válida (año 1000 a 9999).");
        } else if (isValidDateTime(start.value) && end.value <= start.value) {
            setFieldError("end_at", "El fin debe ser posterior al inicio.");
        }
        updateDateConstraints();
        return departureForm.reportValidity();
    }

    function validateDepartureFilters() {
        const from = document.querySelector("#filter-from");
        const to = document.querySelector("#filter-to");
        clearFieldError(from);
        clearFieldError(to);

        if (from.value && !isValidDate(from.value)) {
            setFieldError("from", "Ingresa una fecha válida (año 1000 a 9999).");
        } else if (to.value && !isValidDate(to.value)) {
            setFieldError("to", "Ingresa una fecha válida (año 1000 a 9999).");
        } else if (from.value && to.value && from.value > to.value) {
            setFieldError("from", "Desde no puede ser posterior a Hasta.");
        }
        updateDateConstraints();
        return departureFilters.reportValidity();
    }

    async function loadUsers() {
        const users = await request("/users");
        const list = document.querySelector("#users-list");
        list.replaceChildren();
        for (const user of users) {
            const row = document.createElement("tr");
            const email = document.createElement("td");
            email.textContent = user.email;
            const roleCell = document.createElement("td");
            const roleSelect = document.createElement("select");
            roleSelect.className = "form-select form-select-sm";
            for (const [value, label] of Object.entries(roleLabels)) {
                roleSelect.add(new Option(label, value, false, user.role === value));
            }
            roleSelect.addEventListener("change", async () => {
                try {
                    await request(`/users/${user.id}`, {
                        method: "PATCH",
                        body: JSON.stringify({ role: roleSelect.value }),
                    });
                    await loadUsers();
                    showNotice("Rol actualizado.");
                } catch (error) {
                    roleSelect.value = user.role;
                    showNotice(error.message, true);
                }
            });
            roleCell.append(roleSelect);
            const stateCell = document.createElement("td");
            stateCell.textContent = user.active ? "Activo" : "Inactivo";
            const actions = document.createElement("td");
            const toggle = document.createElement("button");
            toggle.className = "btn btn-sm btn-outline-secondary me-2";
            toggle.textContent = user.active ? "Desactivar" : "Activar";
            toggle.addEventListener("click", async () => {
                try {
                    await request(`/users/${user.id}`, {
                        method: "PATCH",
                        body: JSON.stringify({ active: !user.active }),
                    });
                    await loadUsers();
                    showNotice("Estado actualizado.");
                } catch (error) {
                    showNotice(error.message, true);
                }
            });
            const reset = document.createElement("button");
            reset.className = "btn btn-sm btn-outline-primary";
            reset.textContent = "Restablecer contraseña";
            reset.addEventListener("click", async () => {
                const password = window.prompt("Nueva contraseña");
                if (password === null) {
                    return;
                }
                try {
                    await request(`/users/${user.id}/password`, {
                        method: "POST",
                        body: JSON.stringify({ password }),
                    });
                    showNotice("Contraseña restablecida.");
                } catch (error) {
                    showNotice(error.message, true);
                }
            });
            actions.append(toggle, reset);
            row.append(email, roleCell, stateCell, actions);
            list.append(row);
        }
    }

    function statusLabel(status) {
        return {
            programada: "Programada",
            en_curso: "En curso",
            finalizada: "Finalizada",
            cancelada: "Cancelada",
        }[status] || status;
    }

    function makeCell(text) {
        const cell = document.createElement("td");
        cell.textContent = text;

        return cell;
    }

    function resetTourForm() {
        tourForm.reset();
        document.querySelector("#tour-id").value = "";
        document.querySelector("#tour-submit").textContent = "Crear tour";
        document.querySelector("#tour-cancel").classList.add("d-none");
    }

    function resetDepartureForm() {
        departureForm.reset();
        document.querySelector("#departure-id").value = "";
        departureForm.querySelector("button[type=submit]").textContent = "Programar salida";
        document.querySelector("#departure-capacity").disabled = false;
        updateDateConstraints();
    }

    async function loadTours() {
        tours = await request("/tours");
        const list = document.querySelector("#tours-list");
        const select = document.querySelector("#departure-tour");
        list.replaceChildren();
        select.replaceChildren();

        for (const tour of tours) {
            const row = document.createElement("tr");
            const details = document.createElement("td");
            const title = document.createElement("strong");
            title.textContent = tour.name;
            const description = document.createElement("div");
            description.className = "small text-secondary";
            description.textContent = tour.description;
            details.append(title, description);
            row.append(
                details,
                makeCell(`${tour.duration_days} días`),
                makeCell(`${tour.base_price} Bs`),
                makeCell(tour.active ? "Activo" : "Inactivo")
            );

            const actions = document.createElement("td");
            const edit = document.createElement("button");
            edit.type = "button";
            edit.className = "btn btn-sm btn-outline-primary me-2";
            edit.textContent = "Editar";
            edit.addEventListener("click", () => {
                document.querySelector("#tour-id").value = tour.id;
                document.querySelector("#tour-name").value = tour.name;
                document.querySelector("#tour-description").value = tour.description;
                document.querySelector("#tour-itinerary").value = tour.itinerary;
                document.querySelector("#tour-duration").value = tour.duration_days;
                document.querySelector("#tour-price").value = tour.base_price;
                document.querySelector("#tour-submit").textContent = "Guardar cambios";
                document.querySelector("#tour-cancel").classList.remove("d-none");
                tourForm.scrollIntoView({ behavior: "smooth", block: "center" });
            });
            actions.append(edit);

            const toggle = document.createElement("button");
            toggle.type = "button";
            toggle.className = "btn btn-sm btn-outline-secondary";
            toggle.textContent = tour.active ? "Desactivar" : "Activar";
            toggle.addEventListener("click", async () => {
                try {
                    await request(`/tours/${tour.id}`, {
                        method: "PATCH",
                        body: JSON.stringify({ active: !tour.active }),
                    });
                    await loadTours();
                    showNotice(tour.active ? "Tour desactivado." : "Tour activado.");
                } catch (error) {
                    showNotice(error.message, true);
                }
            });
            actions.append(toggle);
            row.append(actions);
            list.append(row);

            if (tour.active) {
                select.add(new Option(tour.name, String(tour.id)));
            }
        }
    }

    async function loadDepartures() {
        const parameters = new URLSearchParams();
        for (const [field, parameter] of [
            ["#filter-from", "from"],
            ["#filter-to", "to"],
            ["#filter-status", "status"],
        ]) {
            const value = document.querySelector(field).value;
            if (value) {
                parameters.set(parameter, value);
            }
        }
        const query = parameters.toString();
        const departures = await request(`/departures${query ? `?${query}` : ""}`);
        const allDepartures = query ? await request("/departures") : departures;
        const list = document.querySelector("#departures-list");
        list.replaceChildren();
        if (departures.length === 0) {
            let message = "Todavía no existen salidas.";
            if (query) {
                if (allDepartures.length > 0) {
                    message = "Ninguna salida coincide con los filtros.";
                }
            }
            const row = document.createElement("tr");
            const cell = makeCell(message);
            cell.colSpan = 6;
            row.append(cell);
            list.append(row);
            return;
        }
        for (const departure of departures) {
            const row = document.createElement("tr");
            row.append(
                makeCell(departure.tour_name),
                makeCell(departure.start_at),
                makeCell(departure.end_at),
                makeCell(String(departure.capacity)),
                makeCell(String(departure.occupied_seats)),
                makeCell(String(departure.available_seats)),
                makeCell(statusLabel(departure.status))
            );

            const actions = document.createElement("td");
            if (departure.status === "programada") {
                const editSchedule = document.createElement("button");
                editSchedule.type = "button";
                editSchedule.className = "btn btn-sm btn-outline-secondary me-2";
                editSchedule.textContent = "Editar horario";
                editSchedule.addEventListener("click", () => {
                    document.querySelector("#departure-id").value = departure.id;
                    document.querySelector("#departure-tour").value = String(departure.tour_id);
                    document.querySelector("#departure-start").value = departure.start_at.slice(0, 16).replace(" ", "T");
                    document.querySelector("#departure-end").value = departure.end_at.slice(0, 16).replace(" ", "T");
                    document.querySelector("#departure-capacity").value = departure.capacity;
                    document.querySelector("#departure-capacity").disabled = true;
                    departureForm.querySelector("button[type=submit]").textContent = "Guardar horario";
                    departureForm.scrollIntoView({ behavior: "smooth", block: "center" });
                });
                actions.append(editSchedule);
            }
            const transitions = {
                programada: ["en_curso", "cancelada"],
                en_curso: ["finalizada"],
                finalizada: [],
                cancelada: [],
            }[departure.status];
            if (transitions.length > 0) {
                const next = document.createElement("select");
                next.className = "form-select form-select-sm mb-2";
                for (const status of transitions) {
                    next.add(new Option(statusLabel(status), status));
                }
                const saveStatus = document.createElement("button");
                saveStatus.type = "button";
                saveStatus.className = "btn btn-sm btn-outline-primary";
                saveStatus.textContent = "Cambiar estado";
                saveStatus.addEventListener("click", async () => {
                    try {
                        await request(`/departures/${departure.id}`, {
                            method: "PATCH",
                            body: JSON.stringify({ status: next.value }),
                        });
                        await loadDepartures();
                        showNotice("Estado de salida actualizado.");
                    } catch (error) {
                        showNotice(error.message, true);
                    }
                });
                actions.append(next, saveStatus);
            }
            row.append(actions);
            list.append(row);
        }
    }

    async function loadTourists() {
        tourists = await request("/tourists");
        const list = document.querySelector("#tourists-list");
        const select = document.querySelector("#reservation-tourist");
        list.replaceChildren();
        select.replaceChildren(new Option("Selecciona un turista", ""));
        for (const tourist of tourists) {
            const row = document.createElement("tr");
            row.append(
                makeCell(tourist.full_name),
                makeCell(tourist.email || "—"),
                makeCell(tourist.phone || "—")
            );
            const actions = document.createElement("td");
            const edit = document.createElement("button");
            edit.type = "button";
            edit.className = "btn btn-sm btn-outline-primary";
            edit.textContent = "Editar";
            edit.addEventListener("click", () => {
                document.querySelector("#tourist-id").value = tourist.id;
                document.querySelector("#tourist-name").value = tourist.full_name;
                document.querySelector("#tourist-email").value = tourist.email || "";
                document.querySelector("#tourist-phone").value = tourist.phone || "";
                document.querySelector("#tourist-submit").textContent = "Guardar cambios";
                document.querySelector("#tourist-cancel").classList.remove("d-none");
                document.querySelector("#tourist-form").scrollIntoView({ behavior: "smooth", block: "center" });
            });
            actions.append(edit);
            row.append(actions);
            list.append(row);
            select.add(new Option(tourist.full_name, String(tourist.id)));
        }
    }

    async function loadReservationDepartures() {
        reservationDepartures = await request("/departures?status=programada");
        updateReservationDepartureOptions();
    }

    function updateReservationDepartureOptions(selectedId = "") {
        const select = document.querySelector("#reservation-departure");
        select.replaceChildren(new Option("Selecciona una salida disponible", ""));
        const choices = reservationDepartures.filter(
            (departure) => departure.available_seats > 0 || String(departure.id) === String(selectedId)
        );
        for (const departure of choices) {
            const option = new Option(
                `${departure.tour_name} · ${departure.start_at} · ${departure.available_seats} cupos`,
                String(departure.id),
                false,
                String(departure.id) === String(selectedId)
            );
            select.add(option);
        }
    }

    function auditFilters() {
        const params = new URLSearchParams({ page: String(auditPage), per_page: "20" });
        for (const [id, key] of [
            ["audit-from", "from"], ["audit-to", "to"], ["audit-entity", "entity"], ["audit-action", "action"],
        ]) {
            const value = document.querySelector(`#${id}`).value;
            if (value) {
                params.set(key, value);
            }
        }
        return params.toString();
    }

    async function loadAudit() {
        const result = await request(`/audit?${auditFilters()}`);
        const list = document.querySelector("#audit-list");
        list.replaceChildren();
        auditPages = result.pages;
        document.querySelector("#audit-page").textContent = result.pages
            ? `Página ${result.page} de ${result.pages} (${result.total} cambios)`
            : "Sin cambios registrados";
        document.querySelector("#audit-previous").disabled = auditPage <= 1;
        document.querySelector("#audit-next").disabled = auditPages === 0 || auditPage >= auditPages;
        for (const event of result.items) {
            const row = document.createElement("tr");
            row.append(
                makeCell(event.occurred_at),
                makeCell(event.actor),
                makeCell(event.action),
                makeCell(event.entity),
                makeCell(String(event.entity_id)),
                makeCell(event.old_values ? JSON.stringify(event.old_values) : "—"),
                makeCell(event.new_values ? JSON.stringify(event.new_values) : "—")
            );
            list.append(row);
        }
    }

    async function loadReservations() {
        const reservations = await request("/reservations");
        const list = document.querySelector("#reservations-list");
        list.replaceChildren();
        if (reservations.length === 0) {
            const row = document.createElement("tr");
            const cell = makeCell("Todavía no existen reservas.");
            cell.colSpan = 9;
            row.append(cell);
            list.append(row);
            return;
        }

        for (const reservation of reservations) {
            const row = document.createElement("tr");
            row.append(
                makeCell(reservation.tourist_name),
                makeCell(`${reservation.tour_name} · ${reservation.departure_start_at}`),
                makeCell(reservation.passengers.join(", ")),
                makeCell(`${reservation.price_per_person} Bs`),
                makeCell(`${reservation.total_price} Bs`),
                makeCell(`${reservation.paid_net} Bs`),
                makeCell(`${reservation.balance_due} Bs`),
                makeCell(reservation.status === "confirmada" ? "Confirmada" : "Cancelada")
            );
            const actions = document.createElement("td");
            if (reservation.status === "confirmada") {
                const edit = document.createElement("button");
                edit.type = "button";
                edit.className = "btn btn-sm btn-outline-primary me-2";
                edit.textContent = "Editar";
                edit.addEventListener("click", () => {
                    document.querySelector("#reservation-id").value = reservation.id;
                    document.querySelector("#reservation-tourist").value = String(reservation.tourist_id);
                    updateReservationDepartureOptions(reservation.departure_id);
                    document.querySelector("#reservation-departure").value = String(reservation.departure_id);
                    document.querySelector("#reservation-passengers").value = reservation.passengers.join("\n");
                    document.querySelector("#reservation-price").value = reservation.price_per_person;
                    document.querySelector("#reservation-price").disabled = true;
                    document.querySelector("#reservation-submit").textContent = "Guardar cambios";
                    document.querySelector("#reservation-cancel-edit").classList.remove("d-none");
                    document.querySelector("#reservation-form").scrollIntoView({ behavior: "smooth", block: "center" });
                });
                const cancel = document.createElement("button");
                cancel.type = "button";
                cancel.className = "btn btn-sm btn-outline-danger";
                cancel.textContent = "Cancelar reserva";
                cancel.addEventListener("click", async () => {
                    if (!window.confirm("¿Deseas cancelar esta reserva? Se liberarán sus plazas.")) {
                        return;
                    }
                    try {
                        await request(`/reservations/${reservation.id}/cancel`, { method: "POST" });
                        await Promise.all([loadReservations(), loadDepartures(), loadReservationDepartures()]);
                        showNotice("Reserva cancelada y plazas liberadas.");
                    } catch (error) {
                        showNotice(error.message, true);
                    }
                });
                actions.append(edit, cancel);
            }
            row.append(actions);
            list.append(row);
        }
        setSelectOptions(
            document.querySelector("#payment-reservation"),
            reservations.filter((reservation) => reservation.status === "confirmada" && reservation.balance_due !== "0.00"),
            (reservation) => `${reservation.tourist_name} · saldo ${reservation.balance_due} Bs`,
            "Selecciona una reserva"
        );
    }

    function resetTouristForm() {
        document.querySelector("#tourist-form").reset();
        document.querySelector("#tourist-id").value = "";
        document.querySelector("#tourist-submit").textContent = "Guardar turista";
        document.querySelector("#tourist-cancel").classList.add("d-none");
    }

    function resetReservationForm() {
        document.querySelector("#reservation-form").reset();
        document.querySelector("#reservation-id").value = "";
        document.querySelector("#reservation-price").disabled = false;
        document.querySelector("#reservation-submit").textContent = "Crear reserva";
        document.querySelector("#reservation-cancel-edit").classList.add("d-none");
        updateReservationDepartureOptions();
    }

    function setSelectOptions(select, items, label, emptyLabel) {
        select.replaceChildren(new Option(emptyLabel, ""));
        for (const item of items) {
            select.add(new Option(label(item), String(item.id)));
        }
    }

    async function loadFleet() {
        const [loadedVehicles, loadedDrivers, loadedAssignments, maintenance, incidents, departures] =
            await Promise.all([
                request("/vehicles"),
                request("/drivers"),
                request("/assignments"),
                request("/maintenance"),
                request("/incidents"),
                request("/departures"),
            ]);
        vehicles = loadedVehicles;
        drivers = loadedDrivers;
        managedDepartures = departures;

        const vehicleList = document.querySelector("#vehicles-list");
        vehicleList.replaceChildren();
        for (const vehicle of vehicles) {
            const row = document.createElement("tr");
            row.append(makeCell(vehicle.plate), makeCell(String(vehicle.capacity)),
                makeCell(vehicle.status === "operativo" ? "Operativo" : "Inactivo"));
            const actions = document.createElement("td");
            const edit = document.createElement("button");
            edit.type = "button";
            edit.className = "btn btn-sm btn-outline-primary";
            edit.textContent = "Editar";
            edit.addEventListener("click", () => {
                document.querySelector("#vehicle-id").value = vehicle.id;
                document.querySelector("#vehicle-plate").value = vehicle.plate;
                document.querySelector("#vehicle-capacity").value = vehicle.capacity;
                document.querySelector("#vehicle-status").value = vehicle.status;
                document.querySelector("#vehicle-submit").textContent = "Guardar cambios";
                document.querySelector("#vehicle-cancel").classList.remove("d-none");
            });
            actions.append(edit);
            row.append(actions);
            vehicleList.append(row);
        }
        setSelectOptions(
            document.querySelector("#assignment-departure"),
            departures.filter((departure) => departure.status === "programada"),
            (departure) => `${departure.tour_name} · ${departure.start_at}`,
            "Selecciona una salida"
        );
        setSelectOptions(
            document.querySelector("#assignment-vehicle"),
            vehicles.filter((vehicle) => vehicle.status === "operativo"),
            (vehicle) => `${vehicle.plate} · ${vehicle.capacity} personas`,
            "Selecciona un vehículo"
        );
        setSelectOptions(
            document.querySelector("#assignment-driver"),
            drivers,
            (driver) => driver.email,
            "Selecciona un conductor"
        );
        setSelectOptions(
            document.querySelector("#maintenance-vehicle"),
            vehicles,
            (vehicle) => vehicle.plate,
            "Selecciona un vehículo"
        );
        setSelectOptions(
            document.querySelector("#incident-departure"),
            departures.filter((departure) => departure.status !== "cancelada"),
            (departure) => `${departure.tour_name} · ${departure.start_at}`,
            "Selecciona una salida"
        );

        const assignmentList = document.querySelector("#assignments-list");
        assignmentList.replaceChildren();
        for (const assignment of loadedAssignments) {
            const row = document.createElement("tr");
            row.append(
                makeCell(assignment.tour_name),
                makeCell(`${assignment.start_at} – ${assignment.end_at}`),
                makeCell(`${assignment.plate} (${assignment.vehicle_capacity})`),
                makeCell(assignment.driver_email),
                makeCell(statusLabel(assignment.departure_status))
            );
            const actions = document.createElement("td");
            const edit = document.createElement("button");
            edit.type = "button";
            edit.className = "btn btn-sm btn-outline-primary";
            edit.textContent = "Cambiar recursos";
            edit.addEventListener("click", () => {
                document.querySelector("#assignment-departure").value = String(assignment.departure_id);
                document.querySelector("#assignment-vehicle").value = String(assignment.vehicle_id);
                document.querySelector("#assignment-driver").value = String(assignment.driver_user_id);
                document.querySelector("#assignment-form").scrollIntoView({ behavior: "smooth", block: "center" });
            });
            actions.append(edit);
            if (assignment.history.length > 0) {
                const history = document.createElement("details");
                history.className = "mt-2";
                const summary = document.createElement("summary");
                summary.textContent = `Historial (${assignment.history.length})`;
                const list = document.createElement("ul");
                list.className = "mb-0 mt-1 ps-3";
                for (const previous of assignment.history) {
                    const item = document.createElement("li");
                    item.textContent = `${previous.plate} · ${previous.driver_email} · reemplazada ${previous.replaced_at}`;
                    list.append(item);
                }
                history.append(summary, list);
                actions.append(history);
            }
            row.append(actions);
            assignmentList.append(row);
        }

        const maintenanceList = document.querySelector("#maintenance-list");
        maintenanceList.replaceChildren();
        for (const entry of maintenance) {
            const row = document.createElement("tr");
            row.append(
                makeCell(entry.plate),
                makeCell(`${entry.start_at} – ${entry.end_at}`),
                makeCell(entry.description),
                makeCell(maintenanceStatusLabel(entry.status))
            );
            const actions = document.createElement("td");
            const nextStatus = {
                programado: ["en_curso", "cancelado"],
                en_curso: ["completado"],
            }[entry.status] || [];
            for (const status of nextStatus) {
                const button = document.createElement("button");
                button.type = "button";
                button.className = "btn btn-sm btn-outline-secondary me-1";
                button.textContent = maintenanceStatusLabel(status);
                button.addEventListener("click", async () => {
                    try {
                        await request(`/maintenance/${entry.id}`, {
                            method: "PATCH",
                            body: JSON.stringify({ status }),
                        });
                        await loadFleet();
                        showNotice("Estado del mantenimiento actualizado.");
                    } catch (error) {
                        showNotice(error.message, true);
                    }
                });
                actions.append(button);
            }
            row.append(actions);
            maintenanceList.append(row);
        }

        renderIncidents(incidents, document.querySelector("#incidents-list"));
    }

    function renderIncidents(incidents, list) {
        list.replaceChildren();
        for (const incident of incidents) {
            const row = document.createElement("tr");
            row.append(
                makeCell(incident.tour_name),
                makeCell(incident.occurred_at),
                makeCell(incident.description),
                makeCell(incident.reported_by)
            );
            list.append(row);
        }
    }

    function maintenanceStatusLabel(status) {
        return {
            programado: "Programado",
            en_curso: "En curso",
            completado: "Completado",
            cancelado: "Cancelado",
        }[status] || status;
    }

    async function loadDriverDepartures() {
        const departures = await request("/my/departures");
        const list = document.querySelector("#driver-departures-list");
        list.replaceChildren();
        const select = document.querySelector("#driver-incident-departure");
        select.replaceChildren(new Option("Selecciona una salida", ""));
        for (const departure of departures) {
            const row = document.createElement("tr");
            row.append(
                makeCell(departure.tour_name),
                makeCell(departure.start_at),
                makeCell(departure.end_at),
                makeCell(departure.vehicle_plate),
                makeCell(statusLabel(departure.status)),
                makeCell(departure.passengers.length ? departure.passengers.join(", ") : "Sin pasajeros confirmados")
            );
            list.append(row);
            if (departure.status !== "cancelada") {
                select.add(new Option(
                    `${departure.tour_name} · ${departure.start_at}`,
                    String(departure.id)
                ));
            }
        }
    }

    async function showAccount(user) {
        loginPanel.classList.add("d-none");
        accountPanel.classList.remove("d-none");
        logoutButton.classList.remove("d-none");
        document.querySelector("#account-email").textContent = user.email;
        document.querySelector("#account-role").textContent = roleLabels[user.role] || user.role;
        usersPanel.classList.toggle("d-none", user.role !== "administrator");
        paymentsPanel.classList.toggle("d-none", !["administrator", "operator"].includes(user.role));
        auditPanel.classList.toggle("d-none", !["administrator", "auditor"].includes(user.role));
        driverPanel.classList.toggle("d-none", user.role !== "driver_guide");
        if (user.role === "administrator") {
            await loadUsers();
        }
        if (["administrator", "auditor"].includes(user.role)) {
            await loadAudit();
        }
        catalogPanel.classList.toggle("d-none", !["administrator", "operator"].includes(user.role));
        fleetPanel.classList.toggle("d-none", !["administrator", "operator"].includes(user.role));
        if (["administrator", "operator"].includes(user.role)) {
            await loadTours();
            await Promise.all([
                loadDepartures(),
                loadTourists(),
                loadReservationDepartures(),
            ]);
            await loadReservations();
            await loadFleet();
        } else if (user.role === "driver_guide") {
            await loadDriverDepartures();
            renderIncidents(await request("/incidents"), document.querySelector("#driver-incidents-list"));
        }
    }

    const loginForm = document.querySelector("#login-form");
    loginForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        const form = new FormData(loginForm);
        try {
            const result = await request("/auth/login", {
                method: "POST",
                body: JSON.stringify({
                    email: form.get("email"),
                    password: form.get("password"),
                }),
            });
            token = result.token;
            loginForm.reset();
            notice.classList.add("d-none");
            await showAccount(result.user);
        } catch (error) {
            showNotice(error.message, true);
        }
    });

    createUserForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        try {
            await request("/users", {
                method: "POST",
                body: JSON.stringify({
                    email: document.querySelector("#new-email").value,
                    password: document.querySelector("#new-password").value,
                    role: document.querySelector("#new-role").value,
                }),
            });
            createUserForm.reset();
            await loadUsers();
            showNotice("Usuario creado.");
        } catch (error) {
            showNotice(error.message, true);
        }
    });

    tourForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        const id = document.querySelector("#tour-id").value;
        const body = JSON.stringify({
            name: document.querySelector("#tour-name").value,
            description: document.querySelector("#tour-description").value,
            itinerary: document.querySelector("#tour-itinerary").value,
            duration_days: Number(document.querySelector("#tour-duration").value),
            base_price: document.querySelector("#tour-price").value,
        });
        try {
            await request(id ? `/tours/${id}` : "/tours", {
                method: id ? "PATCH" : "POST",
                body,
            });
            resetTourForm();
            await loadTours();
            showNotice(id ? "Tour actualizado." : "Tour creado.");
        } catch (error) {
            showNotice(error.message, true);
        }
    });

    document.querySelector("#tour-cancel").addEventListener("click", resetTourForm);

    logoutButton.addEventListener("click", async () => {
        try {
            await request("/auth/logout", { method: "POST" });
        } catch (error) {
            showNotice(error.message, true);
        } finally {
            clearSession();
        }
    });

    for (const input of [
        document.querySelector("#departure-start"),
        document.querySelector("#departure-end"),
        document.querySelector("#filter-from"),
        document.querySelector("#filter-to"),
    ]) {
        input.addEventListener("input", () => {
            clearFieldError(input);
            updateDateConstraints();
        });
    }

    departureForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!validateDepartureForm()) {
            return;
        }
        const departureId = document.querySelector("#departure-id").value;
        const tourId = document.querySelector("#departure-tour").value;
        try {
            await request(
                departureId ? `/departures/${departureId}` : `/tours/${tourId}/departures`,
                {
                method: departureId ? "PATCH" : "POST",
                body: JSON.stringify({
                    start_at: document.querySelector("#departure-start").value,
                    end_at: document.querySelector("#departure-end").value,
                    ...(departureId ? {} : {
                        capacity: Number(document.querySelector("#departure-capacity").value),
                    }),
                }),
                }
            );

            resetDepartureForm();
            await Promise.all([loadDepartures(), loadFleet()]);
            showNotice(departureId ? "Horario actualizado." : "Salida programada.");
        } catch (error) {
            showApiError(error);
        }
    });

    document.querySelector("#vehicle-form").addEventListener("submit", async (event) => {
        event.preventDefault();
        const id = document.querySelector("#vehicle-id").value;
        const body = {
            plate: document.querySelector("#vehicle-plate").value,
            capacity: Number(document.querySelector("#vehicle-capacity").value),
        };
        if (id) {
            body.status = document.querySelector("#vehicle-status").value;
        }
        try {
            await request(id ? `/vehicles/${id}` : "/vehicles", {
                method: id ? "PATCH" : "POST",
                body: JSON.stringify(body),
            });
            document.querySelector("#vehicle-form").reset();
            document.querySelector("#vehicle-id").value = "";
            document.querySelector("#vehicle-submit").textContent = "Guardar";
            document.querySelector("#vehicle-cancel").classList.add("d-none");
            await loadFleet();
            showNotice(id ? "Vehículo actualizado." : "Vehículo registrado.");
        } catch (error) {
            showApiError(error);
        }
    });

    document.querySelector("#vehicle-cancel").addEventListener("click", () => {
        document.querySelector("#vehicle-form").reset();
        document.querySelector("#vehicle-id").value = "";
        document.querySelector("#vehicle-submit").textContent = "Guardar";
        document.querySelector("#vehicle-cancel").classList.add("d-none");
    });

    document.querySelector("#assignment-form").addEventListener("submit", async (event) => {
        event.preventDefault();
        const departureId = document.querySelector("#assignment-departure").value;
        try {
            await request(`/departures/${departureId}/assignment`, {
                method: "PUT",
                body: JSON.stringify({
                    vehicle_id: Number(document.querySelector("#assignment-vehicle").value),
                    driver_user_id: Number(document.querySelector("#assignment-driver").value),
                }),
            });
            await loadFleet();
            showNotice("Recursos asignados a la salida.");
        } catch (error) {
            showApiError(error);
        }
    });

    document.querySelector("#maintenance-form").addEventListener("submit", async (event) => {
        event.preventDefault();
        const vehicleId = document.querySelector("#maintenance-vehicle").value;
        try {
            await request(`/vehicles/${vehicleId}/maintenance`, {
                method: "POST",
                body: JSON.stringify({
                    start_at: document.querySelector("#maintenance-start").value,
                    end_at: document.querySelector("#maintenance-end").value,
                    description: document.querySelector("#maintenance-description").value,
                }),
            });
            document.querySelector("#maintenance-form").reset();
            await loadFleet();
            showNotice("Mantenimiento registrado.");
        } catch (error) {
            showApiError(error);
        }
    });

    async function submitIncident(form, departureId, time, description) {
        await request(`/departures/${departureId}/incidents`, {
            method: "POST",
            body: JSON.stringify({
                occurred_at: time,
                description,
            }),
        });
        form.reset();
    }

    document.querySelector("#incident-form").addEventListener("submit", async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        try {
            await submitIncident(
                form,
                document.querySelector("#incident-departure").value,
                document.querySelector("#incident-time").value,
                document.querySelector("#incident-description").value
            );
            await loadFleet();
            showNotice("Incidencia registrada.");
        } catch (error) {
            showApiError(error);
        }
    });

    document.querySelector("#driver-incident-form").addEventListener("submit", async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        try {
            await submitIncident(
                form,
                document.querySelector("#driver-incident-departure").value,
                document.querySelector("#driver-incident-time").value,
                document.querySelector("#driver-incident-description").value
            );
            renderIncidents(await request("/incidents"), document.querySelector("#driver-incidents-list"));
            showNotice("Incidencia registrada.");
        } catch (error) {
            showApiError(error);
        }
    });

    document.querySelector("#tourist-form").addEventListener("submit", async (event) => {
        event.preventDefault();
        const id = document.querySelector("#tourist-id").value;
        const body = JSON.stringify({
            full_name: document.querySelector("#tourist-name").value,
            email: document.querySelector("#tourist-email").value || null,
            phone: document.querySelector("#tourist-phone").value || null,
        });
        let saved = false;
        try {
            await request(id ? `/tourists/${id}` : "/tourists", {
                method: id ? "PATCH" : "POST",
                body,
            });
            saved = true;
            resetTouristForm();
            await loadTourists();
            showNotice(id ? "Turista actualizado." : "Turista registrado.");
        } catch (error) {
            if (error.sessionExpired) {
                showNotice(
                    saved
                        ? `Turista guardado. ${error.message}`
                        : error.message,
                    true
                );
            } else {
                showApiError(error);
            }
        }
    });

    document.querySelector("#tourist-cancel").addEventListener("click", resetTouristForm);
    for (const field of ["tourist-name", "tourist-email", "tourist-phone"]) {
        document.querySelector(`#${field}`).addEventListener("input", (event) => {
            clearFieldError(event.currentTarget);
        });
    }

    document.querySelector("#reservation-departure").addEventListener("change", () => {
        const selected = reservationDepartures.find(
            (departure) => String(departure.id) === document.querySelector("#reservation-departure").value
        );
        const tour = selected && tours.find((item) => item.id === selected.tour_id);
        if (tour && !document.querySelector("#reservation-id").value) {
            document.querySelector("#reservation-price").value = tour.base_price;
        }
    });

    document.querySelector("#reservation-form").addEventListener("submit", async (event) => {
        event.preventDefault();
        const reservationId = document.querySelector("#reservation-id").value;
        const names = document.querySelector("#reservation-passengers").value
            .split(/\r?\n/)
            .map((name) => name.trim())
            .filter(Boolean);
        const body = {
            departure_id: Number(document.querySelector("#reservation-departure").value),
            tourist_id: Number(document.querySelector("#reservation-tourist").value),
            passengers: names,
        };
        if (!reservationId) {
            body.price_per_person = document.querySelector("#reservation-price").value;
        }
        const submit = document.querySelector("#reservation-submit");
        submit.disabled = true;
        try {
            await request(reservationId ? `/reservations/${reservationId}` : "/reservations", {
                method: reservationId ? "PATCH" : "POST",
                headers: reservationId ? {} : { "Idempotency-Key": crypto.randomUUID() },
                body: JSON.stringify(body),
            });

            resetReservationForm();
            await Promise.all([loadReservations(), loadDepartures(), loadReservationDepartures()]);
            showNotice(reservationId ? "Reserva actualizada." : "Reserva confirmada.");
        } catch (error) {
            showNotice(error.message, true);
        } finally {
            submit.disabled = false;
        }
    });

    document.querySelector("#payment-form").addEventListener("submit", async (event) => {
        event.preventDefault();
        const reservationId = document.querySelector("#payment-reservation").value;
        try {
            await request(`/reservations/${reservationId}/payments`, {
                method: "POST",
                headers: { "Idempotency-Key": crypto.randomUUID() },
                body: JSON.stringify({
                    amount: document.querySelector("#payment-amount").value,
                    paid_at: document.querySelector("#payment-date").value,
                    method: document.querySelector("#payment-method").value,
                    reference: document.querySelector("#payment-reference").value || null,
                }),
            });

            document.querySelector("#payment-form").reset();
            await loadReservations();
            showNotice("Pago registrado y saldo actualizado.");
        } catch (error) {
            showApiError(error);
        }
    });

    document.querySelector("#audit-filters").addEventListener("submit", async (event) => {
        event.preventDefault();
        auditPage = 1;
        try {
            await loadAudit();
        } catch (error) {
            showApiError(error);
        }
    });
    document.querySelector("#audit-previous").addEventListener("click", async () => {
        if (auditPage <= 1) {
            return;
        }
        auditPage -= 1;
        await loadAudit();
    });
    document.querySelector("#audit-next").addEventListener("click", async () => {
        if (auditPage >= auditPages) {
            return;
        }
        auditPage += 1;
        await loadAudit();
    });

    document.querySelector("#reservation-cancel-edit").addEventListener("click", resetReservationForm);

    departureFilters.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!validateDepartureFilters()) {
            return;
        }
        try {
            await loadDepartures();
        } catch (error) {
            showApiError(error);
        }
    });

    departureFilters.addEventListener("reset", (event) => {
        event.preventDefault();
        document.querySelector("#filter-from").value = "";
        document.querySelector("#filter-to").value = "";
        document.querySelector("#filter-status").value = "";
        for (const id of ["filter-from", "filter-to"]) {
            clearFieldError(document.querySelector(`#${id}`));
        }
        updateDateConstraints();
        loadDepartures().catch(showApiError);
    });

    updateDateConstraints();
})();
