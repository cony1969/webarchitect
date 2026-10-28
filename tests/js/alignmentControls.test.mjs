import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {webcrypto} from 'node:crypto';
const context = {window:{}, crypto:webcrypto, document:{addEventListener() {}}};
for (const file of ['box-model.js','alignment-controls.js']) vm.runInNewContext(readFileSync(new URL('../../public/js/' + file, import.meta.url), 'utf8'),context);
const a = context.window.waAlignment;
test('alignment changes reuse the CSS row and preserve all unrelated declarations', () => {
    const rows = [{id:'first',name:'padding',value:'12px'}, {id:'align',name:'text-align',value:'left'}, {id:'last',name:'--brand',value:'teal'}];
    a.write(rows,'center'); a.write(rows,'right');
    assert.equal(rows.length,3); assert.equal(rows[2].id,'align'); assert.equal(rows[2].value,'right');
    assert.equal(rows[0].value,'12px'); assert.equal(rows[1].value,'teal');
    a.write(rows,''); assert.deepEqual(rows.map(row=>row.name),['padding','--brand']);
});
test('Bootstrap text utilities and important inline alignment are overridden without changing classes', () => {
    const classes = ['container','text-md-start','p-3']; const rows=[];
    a.write(rows,'center',classes); assert.equal(rows[0].value,'center !important');
    a.write(rows,'justify',[]); assert.equal(rows[0].value,'justify !important');
    assert.deepEqual(classes,['container','text-md-start','p-3']);
    a.write(rows,''); assert.equal(rows.length,0);
    a.write(rows,'left',['text-primary','align-items-center']); assert.equal(rows[0].value,'left');
});
test('reset removes duplicate declarations while unsupported values leave source untouched', () => {
    const rows = [{id:'a',name:'Text-Align',value:'left'}, {id:'b',name:'text-align',value:'right'}];
    const before=JSON.stringify(rows); assert.equal(a.write(rows,'unsupported'),false); assert.equal(JSON.stringify(rows),before);
    assert.equal(a.write(rows,''),true); assert.equal(rows.length,0);
});
