// app.js - progressive enhancement: the page works without this file; with it,
// every action goes through the JSON API with fetch() and no page reloads.
// User text is only ever inserted with textContent, never innerHTML (no XSS).

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const list = document.getElementById("notes");
const form = document.getElementById("note-form");
const statusLine = document.getElementById("status");
const searchInput = document.getElementById("q");
const SEARCH_DEBOUNCE_MS = 250;
const REQUEST_TIMEOUT_MS = 8000;

/** fetch() wrapper: JSON in and out, CSRF header, timeout, and thrown errors for non-2xx. */
async function api(method, url, body) {
    const response = await fetch(url, {
        method,
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
        body: body === undefined ? undefined : JSON.stringify(body),
        signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
    });
    const data = response.status === 204 ? null : await response.json();
    if (!response.ok) {
        const error = new Error(data?.error ?? `Request failed (${response.status})`);
        error.fieldErrors = data?.errors ?? {};
        throw error;
    }
    return data;
}

function announce(message) {
    statusLine.textContent = message;
}

function el(tag, props = {}, children = []) {
    const node = Object.assign(document.createElement(tag), props);
    node.append(...children);
    return node;
}

function button(label, className, onClick) {
    return el("button", { type: "button", className, textContent: label, onclick: onClick });
}

function renderNote(note) {
    const item = el("li", { className: "note" + (note.is_pinned ? " is-pinned" : "") });
    item.dataset.id = note.id;
    item.append(
        el("h3", { textContent: note.title }),
        el("p", { textContent: note.body }),
        el("time", { dateTime: note.updated_at, textContent: note.updated_at.slice(0, 10) }),
        el("div", { className: "actions" }, [
            button(note.is_pinned ? "Unpin" : "Pin", "secondary", () => togglePin(note)),
            button("Edit", "secondary", () => startEdit(item, note)),
            button("Delete", "danger", () => remove(note)),
        ]),
    );
    return item;
}

function renderList(notes) {
    list.replaceChildren(...notes.map(renderNote));
    document.querySelector(".empty")?.remove();
    if (notes.length === 0) announce(searchInput.value ? "No notes match your search." : "No notes yet.");
}

async function refresh() {
    const query = searchInput.value.trim();
    const { notes } = await api("GET", "/api/notes" + (query ? "?q=" + encodeURIComponent(query) : ""));
    renderList(notes);
}

function showFieldErrors(scope, errors) {
    for (const field of ["title", "body"]) {
        const input = scope.querySelector(`[name="${field}"]`);
        const message = scope.querySelector(`#${field}-error`) ?? null;
        input?.setAttribute("aria-invalid", errors[field] ? "true" : "false");
        if (message) message.textContent = errors[field] ?? "";
    }
}

form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(form));
    try {
        await api("POST", "/api/notes", { title: data.title, body: data.body, is_pinned: data.is_pinned === "1" });
        form.reset();
        showFieldErrors(form, {});
        announce("Note saved.");
        await refresh();
        form.title.focus();
    } catch (error) {
        showFieldErrors(form, error.fieldErrors ?? {});
        announce(error.message);
    }
});

async function togglePin(note) {
    try {
        await api("PUT", `/api/notes/${note.id}`, { ...note, is_pinned: !note.is_pinned });
        await refresh();
    } catch (error) {
        announce(error.message);
    }
}

function startEdit(item, note) {
    const title = el("input", { name: "title", value: note.title, required: true, ariaLabel: "Title" });
    const body = el("textarea", { name: "body", value: note.body, rows: 4, ariaLabel: "Note" });
    const save = el("button", { type: "submit", textContent: "Save" });
    const cancel = button("Cancel", "secondary", () => item.replaceWith(renderNote(note)));
    const editor = el("form", { className: "note" }, [title, body, el("div", { className: "actions" }, [save, cancel])]);

    editor.addEventListener("submit", async (event) => {
        event.preventDefault();
        try {
            await api("PUT", `/api/notes/${note.id}`, { title: title.value, body: body.value, is_pinned: note.is_pinned });
            announce("Note updated.");
            await refresh();
        } catch (error) {
            title.setAttribute("aria-invalid", error.fieldErrors?.title ? "true" : "false");
            announce(Object.values(error.fieldErrors ?? {})[0] ?? error.message);
        }
    });
    item.replaceWith(el("li", {}, [editor]));
    title.focus();
}

async function remove(note) {
    if (!confirm(`Delete "${note.title}"?`)) return;
    try {
        await api("DELETE", `/api/notes/${note.id}`);
        announce("Note deleted.");
        await refresh();
    } catch (error) {
        announce(error.message);
    }
}

// Live search: wait until typing pauses, then query the API (and keep the URL shareable).
let searchTimer;
searchInput.addEventListener("input", () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const url = new URL(location.href);
        searchInput.value ? url.searchParams.set("q", searchInput.value) : url.searchParams.delete("q");
        history.replaceState(null, "", url);
        refresh().catch((error) => announce(error.message));
    }, SEARCH_DEBOUNCE_MS);
});
searchInput.form.addEventListener("submit", (event) => event.preventDefault());

// Replace the server-rendered cards with interactive ones.
refresh().catch((error) => announce(error.message));
