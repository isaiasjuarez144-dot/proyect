const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const vm = require("node:vm");

class Element {
    constructor(id = "") {
        this.id = id;
        this.value = "";
        this.textContent = "";
        this.className = "";
        this.disabled = false;
        this.children = [];
        this.listeners = new Map();
        this.attributes = new Map();
        this.classes = new Set();
        this.classList = {
            add: (value) => this.classes.add(value),
            remove: (value) => this.classes.delete(value),
            contains: (value) => this.classes.has(value),
            toggle: (value, force) => {
                if (force ?? !this.classes.has(value)) {
                    this.classes.add(value);
                    return true;
                }
                this.classes.delete(value);
                return false;
            },
        };
    }

    addEventListener(name, callback) {
        this.listeners.set(name, callback);
    }

    async submit() {
        const handler = this.listeners.get("submit");
        assert.ok(handler, `submit handler registered for #${this.id}`);
        let prevented = false;
        await handler({ preventDefault: () => { prevented = true; }, currentTarget: this });
        return prevented;
    }

    reset() {
        this.value = "";
    }

    replaceChildren(...children) {
        this.children = children;
    }

    append(...children) {
        this.children.push(...children);
    }

    add(option) {
        this.children.push(option);
    }

    setAttribute(name, value) {
        this.attributes.set(name, value);
    }

    removeAttribute(name) {
        this.attributes.delete(name);
    }

    setCustomValidity(value) {
        this.validationMessage = value;
    }

    reportValidity() {
        return true;
    }

    scrollIntoView() {
    }
}

class Option {
    constructor(text, value = text) {
        this.text = text;
        this.value = value;
    }
}

test("tourist form sends authenticated requests and keeps session on validation errors", async () => {
    const elements = new Map();
    const element = (selector) => {
        const id = selector.slice(1);
        if (!elements.has(id)) {
            elements.set(id, new Element(id));
        }
        return elements.get(id);
    };
    const document = {
        querySelector: element,
        createElement: (tag) => new Element(tag),
    };
    let savedTourist = null;
    let expireNextRequest = false;
    const requests = [];
    const fetch = async (url, options) => {
        const body = options.body ? JSON.parse(options.body) : null;
        requests.push({ url, authorization: options.headers.Authorization || null });
        if (expireNextRequest) {
            expireNextRequest = false;
            return {
                status: 401,
                ok: false,
                json: async () => ({ error: { code: "unauthenticated", message: "No autenticado." } }),
            };
        }
        let status = 200;
        let data = [];
        if (url.endsWith("/auth/login")) {
            data = { token: "test-token", user: { email: "operator@example.test", role: "operator" } };
        } else if (url.endsWith("/tourists") && options.method === "POST") {
            if (body.email === "invalid-email") {
                status = 422;
                return {
                    status,
                    ok: false,
                    json: async () => ({
                        error: { code: "validation_error", message: "Ingresa un correo válido.", field: "email" },
                    }),
                };
            }
            savedTourist = { id: 1, full_name: body.full_name, email: body.email, phone: body.phone };
            status = 201;
            data = savedTourist;
        } else if (url.endsWith("/tourists")) {
            data = savedTourist ? [savedTourist] : [];
        }
        return { status, ok: status < 400, json: async () => ({ data }) };
    };
    const context = {
        document,
        fetch,
        Headers,
        URLSearchParams,
        Option,
        FormData: class {
            get(name) {
                return element(name === "email" ? "#login-email" : "#login-password").value;
            }
        },
        window: { confirm: () => true, prompt: () => null },
        console,
    };
    const app = fs.readFileSync(
        path.join(__dirname, "../../public/assets/js/app.js"),
        "utf8"
    );
    vm.runInNewContext(app, context);

    element("#login-email").value = "operator@example.test";
    element("#login-password").value = "local-test-password";
    assert.equal(await element("#login-form").submit(), true);
    assert.equal(element("#account-panel").classList.contains("d-none"), false);
    assert.equal(element("#users-panel").classList.contains("d-none"), true);
    assert.equal(element("#payments-panel").classList.contains("d-none"), false);
    assert.ok(element("#payment-form").listeners.has("submit"));

    element("#tourist-name").value = "Turista de prueba";
    element("#tourist-email").value = "invalid-email";
    assert.equal(await element("#tourist-form").submit(), true);
    assert.equal(element("#account-panel").classList.contains("d-none"), false);
    assert.equal(element("#login-panel").classList.contains("d-none"), true);
    assert.equal(element("#tourist-email-error").textContent, "Ingresa un correo válido.");
    assert.equal(requests.at(-1).authorization, "Bearer test-token");

    element("#tourist-name").value = "Turista de prueba";
    element("#tourist-email").value = "turista@example.test";
    assert.equal(await element("#tourist-form").submit(), true);
    assert.equal(savedTourist.id, 1);
    assert.equal(element("#tourists-list").children.length, 1);
    assert.equal(element("#account-panel").classList.contains("d-none"), false);
    assert.equal(requests.at(-2).authorization, "Bearer test-token");
    assert.equal(requests.at(-1).authorization, "Bearer test-token");

    expireNextRequest = true;
    assert.equal(await element("#departure-filters").submit(), true);
    assert.equal(element("#account-panel").classList.contains("d-none"), true);
    assert.equal(element("#login-panel").classList.contains("d-none"), false);
    assert.equal(element("#notice").textContent, "Tu sesión expiró. Inicia sesión nuevamente");
});
