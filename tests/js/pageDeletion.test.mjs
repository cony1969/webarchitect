import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const plain=value=>JSON.parse(JSON.stringify(value));
function sidebar(response={ok:true,success:true}) {
    const events=[],requests=[],alerts=[];
    const projects=[{id:'project-1',name:'Project',domains:[{id:'domain-1',name:'example.test',pages:[{id:'page-1',name:'Home'},{id:'page-2',name:'Keep'}]}]}];
    const window={sidebarProjectsData:projects,sidebarPageIds:{'page-1':'uuid-1'},dispatchEvent:event=>events.push(event)};
    const context={window,confirm:()=>{throw Error('Native confirm is forbidden');},alert:message=>alerts.push(message),CustomEvent:class{constructor(type,options){this.type=type;Object.assign(this,options);}},fetch:async(url,options)=>{
        requests.push({url,...options});
        return {ok:response.ok,json:async()=>({...response,projects:plain(window.sidebarProjectsData),revision:2,page_ids:{'page-2':'uuid-2'}})};
    }};
    const blade=readFileSync(new URL('../../resources/views/components/ui/sidebar-left/projects.blade.php',import.meta.url),'utf8');
    const expression=blade.split('x-data="')[1].split('"\n         @project-modal-submit')[0];
    const state=vm.runInNewContext('('+expression+')',context);
    state.$nextTick=()=>{};
    state.selectedProject='project-1';state.selectedDomain='domain-1';state.selectedPage='page-1';
    return {state,events,requests,alerts,context};
}
test('page trash uses the existing tree endpoint and emits deletion only after success',async()=>{
    const {state,events,requests}=sidebar();
    const project=state.projects[0],domain=project.domains[0];
    state.deletePage(project,domain,domain.pages[0]);
    assert.equal(requests.length,0);
    assert.equal(state.deleteDialogOpen,true);
    await state.confirmPageDeletion();
    assert.deepEqual(plain(state.projects[0].domains[0].pages),[{id:'page-2',name:'Keep'}]);
    assert.equal(state.selectedPage,null);
    assert.equal(state.saving,false);
    assert.equal(requests[0].url,'/api/ui/save-projects');
    assert.equal(JSON.parse(requests[0].body).projects[0].domains[0].pages.length,1);
    const deleted=events.find(event=>event.type==='cms-page-deleted');
    assert.deepEqual(plain(deleted.detail),{pageId:'uuid-1',pageLegacyId:'page-1',domainLegacyId:'domain-1',projectLegacyId:'project-1'});
});
test('failed or stale deletion restores the row and keeps its selection and editor open',async()=>{
    const {state,events,alerts}=sidebar({ok:false,success:false,message:'Projekty medzitým zmenilo iné okno.'});
    const project=state.projects[0],domain=project.domains[0];
    state.deletePage(project,domain,domain.pages[0]);
    await state.confirmPageDeletion();
    assert.deepEqual(plain(state.projects[0].domains[0].pages).map(page=>page.id),['page-1','page-2']);
    assert.equal(state.selectedPage,'page-1');
    assert.equal(events.some(event=>event.type==='cms-page-deleted'),false);
    assert.equal(alerts.length,0);
    assert.equal(state.deleteDialogOpen,true);
    assert.match(state.treeError,/iné okno/);
});
test('cancelled and already busy deletions do not write or open a page',async()=>{
    const {state,events,requests,context}=sidebar();
    const project=state.projects[0],domain=project.domains[0];
    state.deletePage(project,domain,domain.pages[0]);
    state.cancelPageDeletion();
    await state.confirmPageDeletion();
    assert.equal(state.pendingDeletion,null);
    assert.equal(state.deleteDialogOpen,false);
    state.saving=true;
    state.deletePage(project,domain,domain.pages[0]);
    await state.confirmPageDeletion();
    assert.equal(requests.length,0);assert.equal(events.length,0);assert.equal(domain.pages.length,2);
});
function workspace(tabs,activeTab) {
    const factories={};
    const drafts={editor:{drafts:{}},components:{drafts:{}},pages:{drafts:{}}};
    for(const tab of tabs) for(const store of Object.values(drafts))store.drafts[tab.id]={};
    const context={window:{},document:{addEventListener(){}},Alpine:{data:(name,factory)=>factories[name]=factory}};
    vm.runInNewContext(readFileSync(new URL('../../public/js/project-editor.js',import.meta.url),'utf8'),context);
    const state=context.window.waCanvasTabs(tabs,activeTab,'token');
    Object.defineProperties(state,{editor:{value:drafts.editor},components:{value:drafts.components},pages:{value:drafts.pages}});
    state.ticks=[];
    state.frames=[];
    state.$nextTick=callback=>state.ticks.push(callback);
    state.flushDom=()=>{while(state.ticks.length)state.ticks.shift()();};
    state.$el={querySelectorAll:()=>state.frames};
    state.persistTabs=function(){this.persisted=true;};
    state.setActiveTab=function(id){this.activeTab=id;};
    state.addTab=function(title,mode){const tab={id:'replacement',title,mode};this.tabs.push(tab);this.activeTab=tab.id;};
    return state;
}
test('successful deletion closes only matching page tabs including legacy ones and clears their drafts',()=>{
    const state=workspace([{id:'component',mode:'component'},{id:'page',mode:'page',pageId:'uuid-1'},{id:'legacy',mode:'page',contentKey:'page:project-1|domain-1|page-1'},{id:'other',mode:'page',pageId:'uuid-2'}],'page');
    state.pageDeleted({pageId:'uuid-1',projectLegacyId:'project-1',domainLegacyId:'domain-1',pageLegacyId:'page-1'});
    assert.deepEqual(plain(state.tabs).map(tab=>tab.id),['component','other']);
    assert.equal(state.activeTab,'other');
    for(const store of [state.editor,state.components,state.pages])assert.deepEqual(Object.keys(store.drafts),['component','other']);
    assert.equal(state.persisted,true);
});
test('deleting the last page tab leaves a usable workspace and inactive deletion preserves active tab',()=>{
    const state=workspace([{id:'page',mode:'page',pageId:'uuid-1'}],'page');
    state.pageDeleted({pageId:'uuid-1'});
    assert.equal(state.tabs[0].mode,'layout');assert.equal(state.activeTab,'replacement');
    const other=workspace([{id:'active',mode:'component'},{id:'page',mode:'page',pageId:'uuid-1'}],'active');
    other.pageDeleted({pageId:'uuid-1'});
    assert.equal(other.activeTab,'active');
});

function previewFrame(tabId, source) {
    return {dataset:{waPreview:tabId}, rendered:source, writes:0,
        getAttribute(name){return name==='srcdoc'?source:null;},
        set srcdoc(value){this.rendered=value;this.writes++;},
    };
}
test('deleting a page before the active component restores the moved frame after DOM update without losing unsaved code',()=>{
    const state=workspace([{id:'stable',mode:'component'},{id:'page',mode:'page',pageId:'uuid-1'},{id:'component',mode:'component'}],'component');
    const stable=previewFrame('stable','<html>Stable</html>');
    const moved=previewFrame('component','<html><style>body{background:cyan}</style>Unsaved container</html>');
    state.frames=[stable,moved];
    state.components.drafts.component={code:'unsaved source',preview:moved.rendered,baseline:'saved source',revision:3};
    const draft=state.components.drafts.component;
    state.pageDeleted({pageId:'uuid-1'});
    // Safari loses the document when the keyed list moves this iframe, retaining srcdoc.
    moved.rendered='';
    assert.equal(state.activeTab,'component');
    assert.equal(moved.writes,0);
    state.flushDom();
    assert.equal(moved.rendered,draft.preview);
    assert.equal(stable.writes,0);
    assert.equal(state.components.drafts.component,draft);
    assert.equal(draft.code,'unsaved source');assert.equal(draft.baseline,'saved source');assert.equal(draft.revision,3);
});
test('ordinary tab closing repairs moved component and page frames while preserving the selected properties',()=>{
    const state=workspace([{id:'old',mode:'page',pageId:'uuid-1'},{id:'component',mode:'component'},{id:'page',mode:'page',pageId:'uuid-2'}],'component');
    const component=previewFrame('component','<html>Container and grid</html>');
    const page=previewFrame('page','<html>Composed page</html>');
    state.frames=[component,page];state.saving=()=>false;state.dirty=()=>false;
    state.components.drafts.component={controls:{tab:'css'}};
    state.closeTab('old',{stopPropagation(){}});
    component.rendered='';page.rendered='';state.flushDom();
    assert.equal(component.rendered,'<html>Container and grid</html>');
    assert.equal(page.rendered,'<html>Composed page</html>');
    assert.equal(state.components.drafts.component.controls.tab,'css');
});
test('deleting a later card leaves earlier preview documents alone and skips an unloaded preview',()=>{
    const state=workspace([{id:'component',mode:'component'},{id:'page',mode:'page',pageId:'uuid-1'},{id:'loading',mode:'component'}],'component');
    const unchanged=previewFrame('component','<html>Container</html>'), loading=previewFrame('loading','');
    state.frames=[unchanged,loading];state.pageDeleted({pageId:'uuid-1'});state.flushDom();
    assert.equal(unchanged.writes,0);assert.equal(loading.writes,0);
});
