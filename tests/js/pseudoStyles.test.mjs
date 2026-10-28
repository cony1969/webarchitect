import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {webcrypto} from 'node:crypto';
const context = {window:{waCssPseudos:JSON.parse(readFileSync(new URL('../../resources/data/css-pseudos.json',import.meta.url))),waComponentPropertyConfig:{cssProperties:[]}},crypto:webcrypto,document:{addEventListener(){},createElement(){return {style:{cssText:'',setProperty(name,value,priority){this.cssText=name+': '+value+(priority?' !important':'')+';';}}};}}};
for (const name of ['component-controls','pseudo-styles','box-model','alignment-controls']) vm.runInNewContext(readFileSync(new URL('../../public/js/'+name+'.js',import.meta.url),'utf8'),context);
const c=context.window.waComponentControls,p=context.window.waPseudoStyles,b=context.window.waBoxModel;
const plain=value=>JSON.parse(JSON.stringify(value));
test('pseudo rows are excluded from inline CSS and survive JSON controls synchronization',()=>{
    const draft={controls:c.state()};
    c.sync(draft,{objects:{type:'div',style:'color: red;',style_rules:[{name:'color',value:'blue',pseudos:[':hover',':focus']}]}});
    assert.equal(draft.controls.styleRows.length,3);
    assert.equal(c.serializeStyles(draft.controls.styleRows),'color: red;');
    assert.deepEqual(plain(draft.controls.styleRows[1].pseudos),[':hover']);
    assert.deepEqual(plain(draft.controls.styleRows[2].pseudos),[':focus']);
});
test('box and alignment controls update only base values and preserve pseudo variants',()=>{
    const rows=[{id:'hover',name:'padding-top',value:'40px !important',pseudos:[':hover']},{id:'align',name:'text-align',value:'right',pseudos:[':focus']}];
    b.upsert(rows,'padding-top','16px');context.window.waAlignment.write(rows,'center');
    assert.equal(rows.length,4);assert.equal(rows[0].value,'40px !important');assert.equal(rows[1].value,'right');
    assert.equal(b.important(rows,'padding-top'),false);
    context.window.waAlignment.write(rows,'');assert.equal(rows.filter(row=>row.name==='text-align').length,1);
});
test('multiple states produce independent scoped selectors and retain important values',()=>{
    const css=p.css('.component-1','color: red;',[{name:'color',value:'blue !important',pseudos:[':hover',':focus',':hover']}]);
    assert.equal(css,'.component-1{color: red;}\n.component-1:hover,.component-1:focus{color: blue !important;}\n');
    assert.ok(!css.includes(':hover:focus'));
    assert.throws(()=>p.states({pseudos:[':hover, body']}),/Neznámy/);
    assert.throws(()=>p.css('.x','',[{name:'x} body',value:'red',pseudos:[':hover']}]),/Neplatný/);
});
test('pseudo elements remain separate selectors and unsupported JSON shapes are rejected',()=>{
    assert.equal(p.css('.x','',[{name:'content',value:'"→"',pseudos:['::before']}]),'.x::before{content: "→";}\n');
    assert.throws(()=>p.rules({style_rules:'bad'}),/Neplatné/);
    assert.throws(()=>p.rules({style_rules:[{name:'color',value:5,pseudos:[':hover']}]}),/Neplatné/);
});
