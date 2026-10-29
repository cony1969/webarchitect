import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {randomUUID} from 'node:crypto';
const plain = value => JSON.parse(JSON.stringify(value));
function editor() {
    let store;
    const events = [], requests = [];
    const context = {window: {waProjectEditorConfig: {csrf:'token'}, waComponentPreview: source => JSON.stringify(source), dispatchEvent:event => events.push(event), addEventListener(){}},
        document: {addEventListener: (name, handler) => handler()}, Alpine:{store:(name, value) => value ? store = value : store, data(){}},
        CustomEvent:class {constructor(type, options) {this.type=type;Object.assign(this,options);}}, crypto:{randomUUID}, setTimeout:()=>1, clearTimeout(){},
        fetch: async (url, options) => {requests.push({url,...options}); return {ok:true,json: async()=>({data: {preview:{objects:{type:'div'},css:'',js:''}}})};}};
    vm.runInNewContext(readFileSync(new URL('../../public/js/layout-editor.js',import.meta.url),'utf8'), context);
    store.catalogLoaded = true;
    store.catalog = {layouts:[], components:[{id:'container',name:'Kontajner',content_target:'root'}]};
    return {store,context,events,requests};
}
test('keeps nested content and width per instance and restores the complete saved layout', async () => {
    const {store,context,requests,events} = editor();
    await store.activate({id:'layout',mode:'layout',layoutNew:true,title:'Môj layout'});
    store.addContainer('layout');
    const parent = store.selected('layout');
    store.toggleWidth('layout'); store.addContainer('layout',true); store.addText('layout'); store.setText('layout','Obsah vnútri');
    assert.equal(parent.attributes.full_width,true);
    assert.equal(parent.children[0].attributes.full_width,false);
    assert.equal(parent.children[0].children[0].children[0],'Obsah vnútri');
    assert.equal(store.rows('layout').length,3);
    assert.equal(store.dirty('layout'),true);
    const graph = plain(store.current.form.document);
    context.fetch = async (url,options) => {
        requests.push({url,...options});
        const payload=JSON.parse(options.body);
        assert.deepEqual(payload.document, graph);
        return {ok:true,json:async()=>({data:{...payload,id:'saved',revision:1,shared:false,preview:{objects:[]}},catalog:store.catalog})};
    };
    await store.save('layout');
    assert.equal(store.dirty('layout'),false);
    assert.deepEqual(plain(store.current.form.document),graph);
    assert.equal(events.at(-2).type,'layout-document-saved');
    assert.equal(store.catalog.components.length,1);
    store.discard('layout');
    store.apply('reopen',{id:'saved',name:'Môj layout',revision:1,shared:false,document:graph,preview:{objects:[]}});
    assert.deepEqual(plain(store.drafts.reopen.form.document),graph);
    assert.equal(store.dirty('reopen'),false);
});
test('failed saves retain unsaved contents and never announce a successful save',async()=>{
    const {store,context,events}=editor();
    await store.activate({id:'layout',mode:'layout',layoutNew:true,title:'Layout'});
    store.addContainer('layout');
    const graph=plain(store.current.form.document);
    context.fetch=async()=>({ok:false,json:async()=>({message:'Layout medzitým zmenil iný editor.'})});
    await store.save('layout');
    assert.equal(store.dirty('layout'),true);
    assert.deepEqual(plain(store.current.form.document),graph);
    assert.match(store.current.error,/iný editor/);
    assert.equal(store.current.saving,false);
    assert.equal(events.some(e=>e.type==='layout-document-saved'),false);
});
test('an outdated preview response cannot replace a newer preview or a closed draft',async()=>{
    const {store,context}=editor();
    await store.activate({id:'layout',mode:'layout',layoutNew:true,title:'Layout'});
    const pending=[];
    context.fetch=()=>new Promise(resolve=>pending.push(resolve));
    store.current.sequence=1; const first=store.preview('layout',1);
    store.current.sequence=2; const second=store.preview('layout',2);
    const response=label=>({ok:true,json:async()=>({data:{preview:{objects:label}}})});
    pending[1](response('new')); await second;
    pending[0](response('old')); await first;
    assert.equal(store.current.preview,JSON.stringify({objects:'new'}));
    store.current.sequence=3; const third=store.preview('layout',3); store.discard('layout');
    pending[2](response('closed')); await third;
    assert.equal(store.drafts.layout,undefined);
});
