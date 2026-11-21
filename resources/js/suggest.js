// Accessible autosuggest for <input data-suggest="places|cities|states">.
// Event delegation, so it also works for inputs added later (e.g. booking stops rendered by Alpine x-for).
// Picking an option sets the value and fires `input` + `change`, so Alpine bindings and fare previews update.
import clockIcon from 'bootstrap-icons/icons/clock-history.svg?raw';
import pinIcon from 'bootstrap-icons/icons/geo-alt.svg?raw';
import cityIcon from 'bootstrap-icons/icons/buildings.svg?raw';
import mapIcon from 'bootstrap-icons/icons/map.svg?raw';

const ICONS = { recent: clockIcon, google: pinIcon, city: cityIcon, state: mapIcon };
const t = (k, d) => window.__ui?.[k] || d;
let uid = 0;

function state(input) {
    if (input._sg) return input._sg;
    const list = document.createElement('ul');
    list.className = 'suggest-list';
    list.id = 'sg-' + (++uid);
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    input.insertAdjacentElement('afterend', list);
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', list.id);
    list.addEventListener('mousedown', (e) => {
        const li = e.target.closest('li[data-i]');
        e.preventDefault(); // keep focus in the input
        if (li) choose(input, Number(li.dataset.i));
    });
    input._sg = { list, items: [], index: -1, timer: null, ctrl: null, token: null, seq: 0 };
    return input._sg;
}

function render(input, items, query) {
    const s = state(input);
    s.items = items;
    s.index = -1;
    s.list.replaceChildren();
    if (!items.length) {
        if (query && input.dataset.suggest !== 'states') {
            const li = document.createElement('li');
            li.className = 'suggest-empty';
            li.textContent = t('suggestNone', 'No suggestions — you can type the full address.');
            s.list.append(li);
            open(input, true);
        } else {
            close(input);
        }
        return;
    }
    items.forEach((item, i) => {
        const li = document.createElement('li');
        li.dataset.i = i;
        li.id = s.list.id + '-' + i;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', 'false');
        const ico = document.createElement('span');
        ico.className = 'suggest-ico src-' + item.source;
        ico.innerHTML = ICONS[item.source] || ICONS.google; // trusted static SVG from the bundle
        const text = document.createElement('span');
        text.className = 'suggest-text';
        const main = document.createElement('b');
        main.textContent = item.label;
        text.append(main);
        if (item.sub) { const sub = document.createElement('small'); sub.textContent = item.sub; text.append(sub); }
        li.append(ico, text);
        if (item.source === 'recent') { const tag = document.createElement('em'); tag.textContent = t('suggestRecent', 'Recent'); li.append(tag); }
        s.list.append(li);
    });
    open(input, true);
}

function open(input, yes) {
    const s = state(input);
    s.list.hidden = !yes;
    input.setAttribute('aria-expanded', yes ? 'true' : 'false');
    if (!yes) input.removeAttribute('aria-activedescendant');
}
const close = (input) => input._sg && open(input, false);

function highlight(input, index) {
    const s = state(input);
    if (!s.items.length) return;
    s.index = (index + s.items.length) % s.items.length;
    [...s.list.children].forEach((li, i) => li.setAttribute('aria-selected', i === s.index ? 'true' : 'false'));
    const li = s.list.children[s.index];
    input.setAttribute('aria-activedescendant', li.id);
    li.scrollIntoView({ block: 'nearest' });
}

function setValue(input, value) {
    input.value = value;
    input._sgSilent = true;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    input._sgSilent = false;
    input.classList.remove('is-invalid');
}

function choose(input, i) {
    const s = state(input);
    const item = s.items[i];
    if (!item) return;
    setValue(input, item.value);
    // A picked city fills an empty linked state field.
    const stateField = input.dataset.suggestState && document.querySelector(input.dataset.suggestState);
    if (stateField && item.state && !stateField.value) setValue(stateField, item.state);
    close(input);
    s.token = null; // a pick ends the Places billing session
    input.dispatchEvent(new CustomEvent('suggest:select', { bubbles: true, detail: item }));
}

function localStates(input, q) {
    let options = [];
    try { options = JSON.parse(input.dataset.options || '[]'); } catch { /* malformed */ }
    const needle = q.trim().toLowerCase();
    const starts = options.filter((o) => o.toLowerCase().startsWith(needle));
    const has = options.filter((o) => !o.toLowerCase().startsWith(needle) && o.toLowerCase().includes(needle));
    return [...starts, ...has].map((o) => ({ label: o, value: o, source: 'state' }));
}

async function lookup(input, focusOnly = false) {
    const s = state(input);
    const q = input.value.trim();
    const kind = input.dataset.suggest;

    if (kind === 'states') {
        // On focus with a valid value show the full list; while typing, filter.
        render(input, localStates(input, focusOnly ? '' : q), q);
        return;
    }

    const params = new URLSearchParams({ q });
    if (kind === 'places') {
        if (q.length < 2) { close(input); return; }
        s.token ||= (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2));
        params.set('token', s.token);
    } else if (kind === 'cities') {
        const st = input.dataset.suggestState && document.querySelector(input.dataset.suggestState)?.value;
        if (st) params.set('state', st);
        if (!q && !st) { close(input); return; }
        if (focusOnly && q) params.set('q', '');
    }

    s.ctrl?.abort();
    s.ctrl = new AbortController();
    const mine = ++s.seq;
    try {
        const res = await fetch(input.dataset.suggestUrl + '?' + params, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: s.ctrl.signal });
        if (!res.ok) return;
        const json = await res.json();
        if (mine !== s.seq || document.activeElement !== input) return;
        render(input, json.items || [], kind === 'places' ? q : '');
    } catch { /* aborted or offline: keep the plain text field */ }
}

// Snap a typed state to the canonical name (case-insensitive / unique match); otherwise leave it for server validation.
function snapState(input) {
    const q = input.value.trim().toLowerCase();
    if (!q) return;
    const all = localStates(input, '');
    const exact = all.find((o) => o.value.toLowerCase() === q);
    const matches = localStates(input, q);
    const pick = exact || (matches.length === 1 ? matches[0] : null);
    if (pick && pick.value !== input.value) setValue(input, pick.value);
}

const target = (e) => (e.target instanceof HTMLInputElement && e.target.dataset.suggest ? e.target : null);

document.addEventListener('input', (e) => {
    const input = target(e);
    if (!input || input._sgSilent) return;
    const s = state(input);
    clearTimeout(s.timer);
    s.timer = setTimeout(() => lookup(input), input.dataset.suggest === 'states' ? 0 : 250);
});

document.addEventListener('focusin', (e) => {
    const input = target(e);
    if (!input) return;
    const kind = input.dataset.suggest;
    if (kind === 'states' || kind === 'cities') lookup(input, true);
});

document.addEventListener('focusout', (e) => {
    const input = target(e);
    if (!input) return;
    setTimeout(() => {
        close(input);
        if (input.dataset.suggest === 'states') snapState(input);
    }, 120);
});

document.addEventListener('keydown', (e) => {
    const input = target(e);
    if (!input) return;
    const s = state(input);
    const isOpen = !s.list.hidden && s.items.length;
    if (e.key === 'ArrowDown') { e.preventDefault(); if (isOpen) highlight(input, s.index + 1); else lookup(input, true); }
    else if (e.key === 'ArrowUp' && isOpen) { e.preventDefault(); highlight(input, s.index - 1); }
    else if (e.key === 'Enter' && isOpen && s.index >= 0) { e.preventDefault(); choose(input, s.index); }
    else if (e.key === 'Escape' && !s.list.hidden) { e.preventDefault(); close(input); }
    else if (e.key === 'Tab' && isOpen && s.index >= 0) choose(input, s.index);
});
