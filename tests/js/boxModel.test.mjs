import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {webcrypto} from 'node:crypto';
const context = {window: {}, crypto: webcrypto, CSS: {supports: (name, value) => value !== 'invalid'}, document: {addEventListener() {}}};
vm.runInNewContext(readFileSync(new URL('../../public/js/box-model.js', import.meta.url), 'utf8'), context);
const b = context.window.waBoxModel;
const plain = value => JSON.parse(JSON.stringify(value));
test('numbers gain px while explicit CSS units and functions remain intact', () => {
    for (const [value, expected] of [['20','20px'],['-.5','-.5px'],['0','0px'],[' auto ','auto'],['1rem','1rem'],['calc(100% - 2rem)','calc(100% - 2rem)'],['var(--space)','var(--space)'],['','']]) assert.equal(b.normalize('margin-top', value), expected);
    assert.throws(() => b.normalize('margin-top', 'invalid'), /Neplatná CSS hodnota/);
});
test('editing a side overrides its shorthand without duplicating rows or touching other declarations', () => {
    const rows = [{id:'edge',name:'margin-top',value:'1px'}, {id:'short',name:'margin',value:'5px 10px'}, {id:'brand',name:'--brand',value:'teal'}, {id:'duplicate',name:'margin-top',value:'2px'}];
    b.upsert(rows, 'margin-top', '12px');
    assert.deepEqual(plain(rows), [{id:'short',name:'margin',value:'5px 10px'}, {id:'brand',name:'--brand',value:'teal'}, {id:'edge',name:'margin-top',value:'12px'}]);
    b.upsert(rows, 'margin-top', '');
    assert.deepEqual(rows.map(r => r.name), ['margin','--brand']);
});
test('important shorthands and Bootstrap utilities keep box changes effective', () => {
    assert.equal(b.important([{name:'padding',value:'2rem !important'}], 'padding-top'), true);
    assert.equal(b.important([], 'padding-left', ['px-md-3']), true);
    assert.equal(b.important([], 'margin-top', ['my-4']), true);
    assert.equal(b.important([], 'border-left-color', ['border-primary']), true);
    assert.equal(b.important([], 'margin-top', ['p-3', 'custom-margin']), false);
    const rows = []; b.upsert(rows, 'border-top-width', '2px', true);
    assert.equal(rows[0].value, '2px !important');
    b.upsert(rows, 'border-top-width', '3px !important', true);
    assert.equal(rows[0].value, '3px !important');
});
test('linked sides create individual physical properties usable by the existing CSS editor', () => {
    const rows = [];
    for (const side of b.sides) b.upsert(rows, b.property('padding', side), '16px');
    assert.deepEqual(rows.map(r => r.name), ['padding-top','padding-right','padding-bottom','padding-left']);
    for (const side of b.sides) b.upsert(rows, b.property('border', side), '1px');
    assert.equal(rows[7].name, 'border-left-width');
    assert.equal(new Set(rows.map(r => r.id)).size, 8);
});

test('existing border styles including variable shorthands are retained', () => {
    assert.equal(b.hasBorderStyle([{name:'border', value:'var(--frame)'}], 'top'), true);
    assert.equal(b.hasBorderStyle([{name:'border-top-style', value:'none'}], 'top'), true);
    assert.equal(b.hasBorderStyle([{name:'border-left-style', value:'none'}], 'top'), false);
    assert.equal(b.hasBorderStyle([{name:'border-color', value:'red'}], 'top'), false);
    assert.equal(b.important([{name:'Margin',value:'2px !important'}], 'margin-top'), true);
});
