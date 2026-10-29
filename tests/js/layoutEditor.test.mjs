import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {randomUUID} from 'node:crypto';
const plain = value => JSON.parse(JSON.stringify(value));
function editor() {
    let store;
    const events = [], requests = [], factories = {}, listeners = new Map();
    const context = {window: {waProjectEditorConfig: {csrf:'token'}, waComponentPreview: source => JSON.stringify(source), dispatchEvent:event => events.push(event), addEventListener:(type, handler)=>listeners.set(type,handler), removeEventListener:(type,handler)=>{if(listeners.get(type)===handler)listeners.delete(type);}},
        document: {addEventListener: (name, handler) => handler()}, Alpine:{store:(name, value) => value ? store = value : store, data:(name, factory)=>factories[name]=factory},
        CustomEvent:class {constructor(type, options) {this.type=type;Object.assign(this,options);}}, crypto:{randomUUID}, setTimeout:()=>1, clearTimeout(){},
        fetch: async (url, options) => {requests.push({url,...options}); return {ok:true,json: async()=>({data: {preview:{objects:{type:'div'},css:'',js:''}}})};}};
    vm.runInNewContext(readFileSync(new URL('../../public/js/layout-editor.js',import.meta.url),'utf8'), context);
    store.catalogLoaded = true;
    store.catalog = {layouts:[], components:[{id:'container',name:'Kontajner',content_target:'root'}]};
    return {store,context,events,requests,factories,listeners};
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

test('preview captions select the exact instance without modifying the layout and reject other frames',async()=>{
    const {store,factories,listeners}=editor();
    await store.activate({id:'layout',mode:'layout',layoutNew:true,title:'Layout'});
    store.addContainer('layout'); const outer=store.selected('layout');
    store.addContainer('layout',true); const inner=store.selected('layout');
    const before=JSON.stringify(store.current.form), preview=store.current.preview, baseline=store.current.baseline;
    const posts=[], frame={postMessage:(...args)=>posts.push(args)}, watchers=[];
    const form=factories.waLayoutForm('layout',{});
    Object.assign(form,{$store:{layoutEditor:store},$refs:{preview:{contentWindow:frame}},$watch:(name,callback)=>watchers.push(callback)});
    form.init();
    const event=(nodeId,source=frame,token=store.drafts.layout.previewToken)=>({source,origin:'null',data:{type:'wa-layout-select',token,nodeId}});
    const handler=listeners.get('message');
    handler(event(outer.id,{})); assert.equal(store.current.selectedId,inner.id);
    handler(event(outer.id,frame,'wrong')); assert.equal(store.current.selectedId,inner.id);
    handler(event(outer.id)); assert.equal(store.current.selectedId,outer.id);
    handler(event('missing')); assert.equal(store.current.selectedId,outer.id);
    handler(event(inner.id)); assert.equal(store.current.selectedId,inner.id);
    assert.equal(JSON.stringify(store.current.form),before); assert.equal(store.current.preview,preview); assert.equal(store.current.baseline,baseline);
    watchers[0](); assert.equal(posts.at(-1)[0].nodeId,inner.id);
    store.activeKey=null; handler(event(outer.id)); assert.equal(store.drafts.layout.selectedId,inner.id);
    form.destroy(); assert.equal(listeners.has('message'),false);
});
test('sandbox bridge synchronizes outline and caption state and only accepts the parent channel',()=>{
    const {context}=editor(), handlers={}, posts=[];
    const caption={tagName:'BUTTON',classList:{contains:name=>name==='wa-preview-caption'},dataset:{waSelectNode:'inner'},setAttribute(name,value){this[name]=value;}};
    const container=id=>({dataset:{waLayoutNode:id},children:id==='inner'?[caption]:[],classList:{toggle(name,value){this[name]=value;}}});
    const outer=container('outer'), inner=container('inner'), parent={postMessage:(...args)=>posts.push(args)};
    const bridgeContext={parent, document:{body:{dataset:{waPreviewToken:'channel',waSelectedNode:'outer'}},querySelectorAll:()=>[outer,inner],addEventListener:(type,handler)=>handlers[type]=handler},window:{addEventListener:(type,handler)=>handlers[type]=handler}};
    vm.runInNewContext(context.window.waLayoutPreviewScript,bridgeContext);
    assert.equal(outer.classList['wa-preview-selected'],true); assert.equal(caption['aria-pressed'],'false');
    handlers.click({target:{closest:()=>caption},preventDefault(){},stopPropagation(){}});
    assert.deepEqual(plain(posts[0][0]),{type:'wa-layout-select',token:'channel',nodeId:'inner'});
    const message={type:'wa-layout-selection',token:'channel',nodeId:'inner'};
    handlers.message({source:{},data:message}); assert.equal(outer.classList['wa-preview-selected'],true);
    handlers.message({source:parent,data:message}); assert.equal(inner.classList['wa-preview-selected'],true); assert.equal(outer.classList['wa-preview-selected'],false); assert.equal(caption['aria-pressed'],'true');
});

test('full height toggles only the selected instance and returns an older layout to a clean state',async()=>{
    const {store}=editor();
    await store.activate({id:'layout',mode:'layout',layoutNew:true,title:'Layout'});
    store.addContainer('layout'); const parent=store.selected('layout');
    store.addContainer('layout',true); const child=store.selected('layout');
    const before=JSON.stringify(store.current.form);
    store.current.baseline=before;
    store.toggleHeight('layout');
    assert.equal(child.attributes.full_height,true); assert.equal(parent.attributes.full_height,undefined);
    assert.equal(store.dirty('layout'),true);
    store.toggleHeight('layout');
    assert.equal(JSON.stringify(store.current.form),before); assert.equal(store.dirty('layout'),false);
    store.current.saving=true; store.toggleHeight('layout');
    assert.equal(child.attributes.full_height,undefined);
});
