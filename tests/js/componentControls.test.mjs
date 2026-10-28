import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { webcrypto } from 'node:crypto';
const context = { window: {}, crypto: webcrypto, document: { addEventListener() {} } };
vm.runInNewContext(readFileSync(new URL('../../public/js/component-controls.js', import.meta.url), 'utf8'), context);
const c = context.window.waComponentControls;
const plain = value => JSON.parse(JSON.stringify(value));

test('changing one Bootstrap family preserves custom classes and responsive alternatives', () => {
    const field = { options: { '': ['p-0', 'p-2', 'p-3'], md: ['p-md-0', 'p-md-2', 'p-md-3'] } };
    assert.deepEqual(plain(c.replaceField('container p-2 p-md-3 px-4 custom', field, '', 'p-3')), ['container', 'p-md-3', 'px-4', 'custom', 'p-3']);
    assert.deepEqual(plain(c.replaceField('container p-2 p-md-3', field, 'md', '')), ['container', 'p-2']);
});
test('parsing inline CSS preserves semicolons in URLs strings and custom property blocks', () => {
    const style = 'padding: 20px; background-image: url("data:image/svg+xml;a;b"); --caption: "a;b:c"; --rules: {a;b}; color: red !important;';
    const rows = plain(c.styleRows(style));
    assert.equal(rows.length, 5);
    assert.equal(rows[1].value, 'url("data:image/svg+xml;a;b")');
    assert.equal(rows[2].value, '"a;b:c"');
    assert.equal(rows[3].value, '{a;b}');
    assert.equal(rows[4].value, 'red !important');
    assert.deepEqual(plain(c.styleRows(c.serializeStyles(rows))), rows);
});
test('unnamed CSS rows are omitted even when they contain a value', () => {
    assert.equal(c.serializeStyles([{ name: '', value: 'red' }, { name: '  ', value: 'display:none' }, { name: ' padding ', value: ' 20px ' }]), 'padding: 20px;');
});
test('controls preserve nested attribute representation and other roots', () => {
    const source = { objects: [{ type: 'div', class: 'first' }, { type: 'section', attributes: { class: 'second custom', style: 'color: red;' } }] };
    c.setAttribute(c.roots(source)[1], 'class', 'second custom p-3');
    c.setAttribute(c.roots(source)[1], 'style', 'padding: 20px;');
    assert.equal(source.objects[0].class, 'first');
    assert.equal(source.objects[1].attributes.class, 'second custom p-3');
    assert.equal(source.objects[1].attributes.style, 'padding: 20px;');
    assert.equal(source.objects[1].style, undefined);
    c.setAttribute(source.objects[1], 'style', '');
    assert.equal(source.objects[1].attributes.style, undefined);
});
test('JSON synchronization targets a valid root and retains UI tab and breakpoint', () => {
    const draft = { controls: c.state() }; draft.controls.tab = 'css'; draft.controls.breakpoint = 'md'; draft.controls.rootIndex = 99;
    c.sync(draft, { objects: [{ type: 'div', class: 'container p-md-3', style: 'max-width: 900px;' }] });
    assert.equal(draft.controls.rootIndex, 0);
    assert.equal(draft.controls.tab, 'css'); assert.equal(draft.controls.breakpoint, 'md');
    assert.deepEqual(plain(draft.controls.classTokens), ['container', 'p-md-3']);
    assert.equal(draft.controls.styleRows[0].name, 'max-width');
});

test('inline comments with colons do not become property names', () => {
    assert.deepEqual(plain(c.styleRows('/* notes: theme */ color: teal; padding /* note: space */: 10px;')), [{name:'color',value:'teal'}, {name:'padding',value:'10px'}]);
});

test('property search matches Slovak accents English words and Bootstrap class names', () => {
    const config = { fields: [
        { id: 'padding', label: 'Vnútorné odsadenie', common: true, options: { '': ['p-0', 'p-3'] } },
        { id: 'border-color', label: 'Farba orámovania', common: false, options: { '': ['border-primary'] } },
        { id: 'background', label: 'Farba pozadia', common: true, options: { '': ['bg-primary'] } },
    ] };
    const ui = c.state();
    for (const query of ['VNUTORNE ODSADENIE', 'padding', 'p-3']) {
        ui.search = query; assert.deepEqual(plain(c.visibleFields(config, ui)).map(f => f.id), ['padding']);
    }
    for (const query of ['border color', 'farba oramovania', 'border-primary']) {
        ui.search = query; assert.deepEqual(plain(c.visibleFields(config, ui)).map(f => f.id), ['border-color']);
    }
    ui.search = 'background color'; assert.deepEqual(plain(c.visibleFields(config, ui)).map(f => f.id), ['background']);
    ui.search = 'does not exist'; assert.equal(c.visibleFields(config, ui).length, 0);
    ui.search = ''; assert.deepEqual(plain(c.visibleFields(config, ui)).map(f => f.id), ['padding', 'background']);
    assert.equal(ui.allFields, false); assert.equal(ui.extraFields.length, 0);
});

test('CSS search finds bilingual names and existing custom properties without duplicates', () => {
    assert.ok(c.cssSearchResults('zaoblenie rohov', []).some(p => p.name === 'border-radius'));
    assert.ok(c.cssSearchResults('border radius', []).some(p => p.name === 'border-radius'));
    assert.ok(c.cssSearchResults('farba pozadia', []).some(p => p.name === 'background-color'));
    assert.ok(c.cssSearchResults('background-color', []).some(p => p.name === 'background-color'));
    const rows = [{ name: '--brand-accent', value: 'teal' }, { name: '--brand-accent', value: 'red' }];
    assert.equal(c.cssSearchResults('brand', rows).length, 1);
    assert.equal(c.cssSearchResults('zzzz-no-match', rows).length, 0);
    assert.equal(rows.length, 2);
});
