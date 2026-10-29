import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
function load(fetch) {
    const events=[];
    const context={window:{waSessionTabsConfig:{tabs:{component_properties:'css'},csrf:'token'},dispatchEvent:event=>events.push(event)},fetch,CustomEvent:class{constructor(type,options){this.type=type;this.detail=options.detail;}}};
    vm.runInNewContext(readFileSync(new URL('../../public/js/session-tabs.js',import.meta.url),'utf8'),context);
    return {tabs:context.window.waSessionTabs,events};
}
test('session preferences restore server values and serialize rapid changes without losing the final choice',async()=>{
    const calls=[],resolves=[];
    const {tabs}=load((url,options)=>{calls.push({url,...JSON.parse(options.body)});return new Promise(resolve=>resolves.push(resolve));});
    assert.equal(tabs.get('component_properties','basic'),'css');
    assert.equal(tabs.get('domain_properties','general'),'general');
    const first=tabs.set('component_properties','bootstrap');
    tabs.set('component_properties','css');tabs.set('appearance','other');
    assert.equal(calls.length,1);
    resolves.shift()({ok:true});await new Promise(resolve=>setImmediate(resolve));
    assert.equal(calls[1].tab,'css');
    resolves.shift()({ok:true});await new Promise(resolve=>setImmediate(resolve));
    assert.equal(calls[2].panel,'appearance');
    resolves.shift()({ok:true});await first;
    assert.equal(tabs.get('component_properties'),'css');
});
test('a failed write keeps the current choice and allows a retry without native dialogs',async()=>{
    let fail=true;
    const {tabs,events}=load(async()=>({ok:!fail}));
    await tabs.set('domain_properties','bootstrap');
    assert.equal(tabs.get('domain_properties'),'bootstrap');
    assert.match(events.at(-1).detail.message,/nepodarilo/);
    fail=false;await tabs.retry();assert.equal(events.at(-1).detail.message,'');
});
test('restoring the right accordion respects its saved section even with an active editor',()=>{
    const blade=readFileSync(new URL('../../resources/views/components/ui/sidebar-accordion.blade.php',import.meta.url),'utf8');
    const expression=blade.split('x-data="')[1].split('"\n     @project-editor-focused')[0]
        .replaceAll('@js($initial)',JSON.stringify('properties'))
        .replaceAll("@js('wa.sidebar.' . $id . '.section')",JSON.stringify('wa.sidebar.right.section'))
        .replaceAll('@js($sections)',JSON.stringify(['properties','appearance']))
        .replaceAll('@js($id)',JSON.stringify('right'));
    const state=vm.runInNewContext('('+expression+')',{window:{localStorage:{getItem:()=> 'appearance',setItem(){}}}});
    state.$nextTick=callback=>callback();state.$store={projectEditor:{active:true},componentEditor:{current:{}},pagePreview:{current:null}};
    state.init();assert.equal(state.activeSection,'appearance');
    state.openSection('properties');assert.equal(state.activeSection,'properties');
});
