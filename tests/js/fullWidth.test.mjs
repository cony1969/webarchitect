import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const context={window:{waComponentPropertyConfig:{cssProperties:[]}},document:{addEventListener(){}}};
vm.runInNewContext(readFileSync(new URL('../../public/js/component-controls.js',import.meta.url),'utf8'),context);
const c=context.window.waComponentControls;
const plain=value=>JSON.parse(JSON.stringify(value));
test('full width replaces every container constraint and retains unrelated Bootstrap and custom classes',()=>{
    assert.deepEqual(plain(c.fullWidthClasses('container container-lg w-50 py-3 brand')),['py-3','brand','container-fluid','w-100']);
    assert.deepEqual(plain(c.fullWidthClasses('container-fluid w-100 brand')),['brand','container-fluid','w-100']);
});
test('full width clears responsive column widths without treating ordinary components as grid columns',()=>{
    assert.deepEqual(plain(c.fullWidthClasses('col col-md-6 col-lg-auto order-2 brand')),['order-2','brand','col-12','w-100']);
    assert.deepEqual(plain(c.fullWidthClasses('card w-25 text-start custom-col')),['card','text-start','custom-col','w-100']);
    assert.deepEqual(plain(c.fullWidthClasses('col-md col-xxl-4')),['col-12','w-100']);
});
test('responsive column selection changes only its breakpoint and keeps parent container settings',()=>{
    const field={id:'columns',options:{'':['col','col-auto','col-6','col-12'],md:['col-md','col-md-auto','col-md-6','col-md-12']}};
    assert.deepEqual(plain(c.replaceField('col-6 col-md-6 col-lg-4 custom',field,'md','col-md-12')),['col-6','col-lg-4','custom','col-md-12']);
    assert.equal(c.fieldOptionLabel(field,'col-md-12'),'12 / 12 · celý riadok (col-md-12)');
});

test('inline elements gain a width-capable display without unhiding hidden content',()=>{
    assert.deepEqual(plain(c.fullWidthClasses('brand','span')),['brand','w-100','d-block']);
    assert.deepEqual(plain(c.fullWidthClasses('d-inline d-md-inline text-start','div')),['text-start','w-100','d-block']);
    assert.deepEqual(plain(c.fullWidthClasses('d-none brand','span')),['d-none','brand','w-100']);
    assert.deepEqual(plain(c.fullWidthClasses('d-flex brand','span')),['d-flex','brand','w-100']);
});
