'use strict';

// Source and synthetic-DOM contracts only. No PHP execution, server, HTTP, login or database.
// PHP grouping/search/pagination and real browser behavior still need integration acceptance.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '..', 'supervisor.php'), 'utf8');
const script = [...source.matchAll(/<script>([\s\S]*?)<\/script>/g)].at(-1)[1];
const sampleUsers = {
    1: {username: 'Sample <One>', overtime_minutes: -30, ueberstunden: '-00:30', regelarbeitszeit: 8, total_hours: 7.5, total_days: 1, avg_hours_per_day: 7.5},
    2: {username: 'Sample Two', overtime_minutes: 90, ueberstunden: '01:30', regelarbeitszeit: 8, total_hours: 9.5, total_days: 1, avg_hours_per_day: 9.5},
};

class Element {
    constructor(classes = '') {
        const tokens = new Set(classes.split(/\s+/).filter(Boolean));
        this.classList = {
            contains: value => tokens.has(value),
            add: value => { tokens.add(value); },
            remove: value => { tokens.delete(value); },
            toggle: value => { if (tokens.delete(value)) return false; tokens.add(value); return true; },
        };
        this.style = {};
        this.dataset = {};
        this.attributes = {};
        this.events = {};
        this.children = {};
        this.value = '';
        this.textContent = '';
    }
    addEventListener(type, handler) { (this.events[type] ??= []).push(handler); }
    fire(type) { for (const handler of this.events[type] ?? []) handler.call(this, {target: this}); }
    querySelector(selector) { return this.children[selector] ?? null; }
    querySelectorAll(selector) { return this.children[selector] ?? []; }
    setAttribute(name, value) { this.attributes[name] = value; }
}

function rowPair({toggle = true, chevron = true} = {}) {
    const header = new Element('group-header');
    const detail = new Element('detail-row hidden');
    const button = new Element();
    button.setAttribute('aria-expanded', 'false');
    const icon = new Element();
    header.nextElementSibling = detail;
    if (toggle) header.children['button[aria-expanded]'] = button;
    if (chevron) header.children['.fa-chevron-right'] = icon;
    return {header, detail, button, icon};
}

function boot({hasControls = true, rows = [], users = sampleUsers, chartAvailable = true} = {}) {
    const ids = {};
    const cards = [];
    const badges = [];
    const charts = [];
    if (hasControls) {
        ids.searchInput = new Element();
        ids.timeRecordsBody = new Element();
        ids.timeRecordsBody.children['.group-header'] = rows;
        ids.overtimeChart = new Element();
        ids.regularHoursChart = new Element();
        for (const [username, minutes] of Object.keys(users).length ? [['Sample One', -30], ['Sample Two', 90], ['Other', 0]] : []) {
            const card = new Element('card');
            card.dataset.overtimeMinutes = String(minutes);
            card.children['.card-title'] = {textContent: username};
            cards.push(card);
        }
        for (const filter of ['all', 'overtime', 'undertime']) {
            const badge = new Element('filter-badge');
            badge.dataset.filter = filter;
            badges.push(badge);
        }
    }
    ids.details_modal = new Element();
    ids.details_modal.showModal = () => { ids.details_modal.open = true; };
    ids.modal_title = new Element();
    ids.modal_content = new Element();
    const document = new Element();
    document.getElementById = id => ids[id] ?? null;
    document.querySelectorAll = selector => ({'#overtimeCards .card': cards, '.filter-badge': badges}[selector] ?? []);
    document.createElement = () => ({
        textContent: '',
        get innerHTML() { return this.textContent.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;'); },
    });
    const context = {document};
    if (chartAvailable) context.Chart = function(canvas, config) { charts.push({canvas, config}); };
    vm.createContext(context);
    const compiled = script.replace(/<\?= json_encode\(\$(\w+),[^?]+\) \?>/g, (_, variable) => {
        assert.ok(['detaillierteDaten', 'ueberstundenListe'].includes(variable), 'no unused raw time-entry payload');
        return JSON.stringify(users);
    });
    assert.doesNotMatch(compiled, /<\?/);
    vm.runInContext(compiled, context, {filename: 'supervisor-inline.js'});
    document.fire('DOMContentLoaded');
    return {context, ids, cards, badges, charts};
}

test('source: one server-rendered table with scoped, pre-pagination GET search', () => {
    assert.doesNotMatch(script, /\b(displayTable|updatePagination|changePage|const zeiten)\b/);
    assert.doesNotMatch(script, /getElementById\(['"](?:tableBody|pagination)['"]\)/);
    assert.match(source, /WHERE u\.supervisor_id = \? OR \?='admin'/);
    assert.match(source, /\$stmt->execute\(\[\$user_id,\$user_role\]\)/);
    assert.match(source, /if \(\$user_role === 'admin' \|\| \$user_role === 'supervisor'\)/);
    assert.match(source, /<form method="get" action="supervisor\.php"/);
    assert.match(source, /name="user_search"/);
    assert.match(source, /is_string\(\$_GET\['user_search'\]\)/);
    assert.match(source, /htmlspecialchars\(\$userSearch, ENT_QUOTES, 'UTF-8'\)/);
    assert.ok(source.indexOf('mb_stripos(') < source.indexOf('$totalRecords = count($groupedEntries)'));
    assert.ok(source.indexOf('$totalRecords = count($groupedEntries)') < source.indexOf('array_slice($groupedEntries'));
    assert.match(source, /mb_stripos\(\$group\['username'\], \$userSearch\) !== false/);
    // A search submission has no page field; a new search returns to the first page.
    const form = source.match(/<form method="get" action="supervisor\.php"[\s\S]*?<\/form>/)[0];
    assert.doesNotMatch(form, /name="page"/);
});

test('source: bounded server pages, query retention, real disabled edges and empty states', () => {
    assert.match(source, /\$tableQuery = \['lang' => \$lang, 'user_search' => \$userSearch\]/);
    assert.match(source, /htmlspecialchars\(http_build_query\(array_merge\(\$tableQuery, \['page' => \$page\]\)\), ENT_QUOTES, 'UTF-8'\)/);
    assert.match(source, /\$totalPages = max\(1, \(int\) ceil\(\$totalRecords \/ \$itemsPerPage\)\)/);
    assert.match(source, /\$currentPage = max\(1, min\(\$currentPage, \$totalPages\)\)/);
    assert.match(source, /is_string\(\$_GET\['page'\]\)/);
    assert.match(source, /if \(\$totalRecords === 0\)[\s\S]*?NO_ENTRIES_FOUND/);
    assert.match(source, /<\?php if \(\$totalRecords > 0\) : \?>/);
    assert.equal((source.match(/<span class="btn btn-sm btn-disabled" aria-disabled="true">/g) ?? []).length, 2);
    assert.doesNotMatch(source, /href=['"]\?page=/);
    assert.equal((source.match(/NO_ENTRIES_FOUND/g) ?? []).length, 2);
    assert.match(source, /aria-controls="' \. \$detailId \. '"/);
    assert.match(source, /<tr id="' \. \$detailId \. '" class="detail-row hidden"/);
});

test('synthetic DOM: regular-role/missing controls and empty team do not dereference null', () => {
    assert.equal(boot({hasControls: false}).charts.length, 0);
    const empty = boot({users: {}});
    assert.equal(empty.cards.length, 0);
    assert.equal(empty.charts.length, 2);
    assert.equal(empty.charts[0].config.data.datasets[0].data.length, 0);
    assert.equal(boot({chartAvailable: false}).charts.length, 0);
});

test('synthetic DOM: server-rendered row pairs expand and repeatedly collapse independently', () => {
    const first = rowPair();
    const second = rowPair();
    const malformed = new Element('group-header');
    malformed.nextElementSibling = new Element('not-a-detail');
    const missingControls = rowPair({toggle: false, chevron: false});
    boot({rows: [first.header, second.header, malformed, missingControls.header]});
    first.header.fire('click');
    assert.equal(first.detail.classList.contains('hidden'), false);
    assert.equal(first.button.attributes['aria-expanded'], 'true');
    assert.equal(first.icon.style.transform, 'rotate(90deg)');
    assert.equal(second.detail.classList.contains('hidden'), true);
    first.header.fire('click');
    assert.equal(first.detail.classList.contains('hidden'), true);
    assert.equal(first.button.attributes['aria-expanded'], 'false');
    assert.equal(first.icon.style.transform, '');
    second.header.fire('click');
    assert.equal(second.detail.classList.contains('hidden'), false);
    assert.doesNotThrow(() => missingControls.header.fire('click'));
    assert.equal((malformed.events.click ?? []).length, 0);
});

test('synthetic DOM: existing card search and signed-minute badges combine', () => {
    const {ids, cards, badges} = boot();
    ids.searchInput.value = 'SAMPLE';
    ids.searchInput.fire('input');
    assert.deepEqual(cards.map(card => card.style.display), ['', '', 'none']);
    badges[1].fire('click');
    assert.deepEqual(cards.map(card => card.style.display), ['none', '', 'none']);
    ids.searchInput.value = 'one';
    ids.searchInput.fire('input');
    assert.deepEqual(cards.map(card => card.style.display), ['none', 'none', 'none']);
    badges[2].fire('click');
    assert.deepEqual(cards.map(card => card.style.display), ['', 'none', 'none']);
    ids.searchInput.value = '';
    ids.searchInput.fire('input');
    badges[0].fire('click');
    assert.deepEqual(cards.map(card => card.style.display), ['', '', '']);
});

test('synthetic DOM: prior signed numeric minute chart conversion and modal remain intact', () => {
    const {context, ids, charts} = boot();
    assert.deepEqual(Array.from(charts[0].config.data.datasets[0].data), [-0.5, 1.5]);
    vm.runInContext('showDetails("1")', context);
    assert.equal(ids.modal_title.textContent, 'Übersicht für Sample <One>');
    assert.equal(ids.details_modal.open, true);
    assert.match(ids.modal_content.innerHTML, /8h/);
});
