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

test('toggle restores exact original sizing after a JSON save/reload and preserves unrelated edits',()=>{
    const original={type:'div',class:'container container-lg w-50 py-3 brand',style:'color: red;',style_rules:[{name:'color',value:'blue',pseudos:[':hover']}]};
    let node=structuredClone(original);
    c.setFullWidth(node,true);
    assert.equal(c.isFullWidth(node.class,node.type),true);
    const once=JSON.stringify(node);
    c.setFullWidth(node,true);
    assert.equal(JSON.stringify(node),once);
    node=JSON.parse(JSON.stringify(node));
    node.class=node.class.replace('py-3','py-4')+' bg-primary';
    c.setFullWidth(node,false);
    assert.equal(node.class,'container container-lg w-50 brand py-4 bg-primary');
    assert.equal(Object.hasOwn(node,'editor_full_width'),false);
    assert.equal(node.style,original.style);
    assert.deepEqual(node.style_rules,original.style_rules);
});
test('independent roots keep their own restore state including nested attributes and inline display',()=>{
    const nodes=[{type:'span',attributes:{class:'d-inline col-md-6 brand'}},{type:'div',class:'card w-25'}];
    const before=JSON.stringify(nodes);
    c.setFullWidth(nodes[0],true);
    c.setFullWidth(nodes[1],true);
    c.setFullWidth(nodes[0],false);
    assert.equal(nodes[0].attributes.class,'d-inline col-md-6 brand');
    assert.equal(c.isFullWidth(nodes[1].class),true);
    c.setFullWidth(nodes[1],false);
    assert.equal(JSON.stringify(nodes),before);
});
test('older full-width definitions can be switched off without a restore snapshot',()=>{
    const node={type:'div',class:'container-fluid col-12 w-100 py-3'};
    assert.equal(c.isFullWidth(node.class),true);
    c.setFullWidth(node,false);
    assert.equal(node.class,'container col py-3');
    assert.equal(c.isFullWidth(node.class),false);
    assert.equal(c.isFullWidth('container w-100'),false);
    assert.equal(c.isFullWidth('col-12 col-md-6 w-100'),false);
});
