import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const named = new Set(['red','blue','teal','transparent','currentcolor']);
const valid = value => named.has(value.toLowerCase()) || /^#(?:[a-f0-9]{3,4}|[a-f0-9]{6}|[a-f0-9]{8})$/i.test(value) || /^(?:rgba?|hsla?|oklch)\(.+\)$/i.test(value);
const context = { window: {}, document: { addEventListener() {} }, CSS: { supports: (_, value) => valid(value) } };
vm.runInNewContext(readFileSync(new URL('../../public/js/color-picker.js', import.meta.url), 'utf8'), context);
const c = context.window.waColors;
const plain = value => JSON.parse(JSON.stringify(value));
test('finds every color in borders shadows and gradients while retaining other values', () => {
    for (const [name, value, expected] of [
        ['border','1px solid #008080 !important',['#008080']],
        ['box-shadow','0 2px 5px rgba(0, 0, 0, .4), 0 0 1px red',['rgba(0, 0, 0, .4)','red']],
        ['background-image','linear-gradient(45deg, #fff 0%, rgba(10, 20, 30, .5) 100%)',['#fff','rgba(10, 20, 30, .5)']],
        ['border-color','red blue transparent currentColor',['red','blue','transparent','currentColor']],
    ]) {
        const tokens=c.rowColors({name,value}); assert.deepEqual(plain(tokens).map(token=>token.color), expected);
        assert.equal(c.replaceColor(value,tokens[0],'#123456'),value.slice(0,tokens[0].start)+'#123456'+value.slice(tokens[0].end));
    }
});
test('ignores colors inside quoted content URLs comments and variable references', () => {
    const value='url("red/#fff.svg") /* blue */ linear-gradient(red, var(--blue, #fff)) "teal"';
    assert.deepEqual(plain(c.colorTokens(value)).map(token=>token.color), ['red']);
    assert.equal(c.rowColors({name:'content',value:'"red"'}).length,0);
    assert.equal(c.rowColors({name:'padding',value:'red'}).length,0);
});
test('offers a picker for empty color fields and variables without converting their text', () => {
    for(const name of ['color','background-color','border-left-color','accent-color','fill','stroke','--brand']) {
        assert.equal(c.rowColors({name,value:''}).length,1);
        const original='var(--brand)'; const token=c.rowColors({name,value:original})[0];
        assert.equal(c.replaceColor(original,token,'rgba(1, 2, 3, 0.5)'),'rgba(1, 2, 3, 0.5)');
    }
});
test('keeps modern CSS color functions as one editable color', () => {
    const value='oklch(65% .2 220 / .5) !important';
    const tokens=c.colorTokens(value);assert.equal(tokens.length,1);
    assert.equal(c.replaceColor(value,tokens[0],'#00ffff'),'#00ffff !important');
});

test('color-only properties use button values while composite CSS keeps editable dimensions', () => {
    for(const name of ['color','background-color','border-inline-start-color','accent-color','scrollbar-color','fill','stroke']) {
        assert.equal(c.colorOnly({name,value:''}),true,name);
        assert.equal(c.rowColors({name,value:'auto'}).length,1,name);
    }
    assert.equal(c.colorOnly({name:'background',value:'#fff !important'}),true);
    assert.equal(c.colorOnly({name:'--brand',value:'teal'}),true);
    assert.equal(c.colorOnly({name:'--spacing',value:'20px'}),false);
    for(const [name,value] of [['border','1px solid red'],['box-shadow','0 0 4px #fff'],['background','linear-gradient(red,blue)']]) assert.equal(c.colorOnly({name,value}),false);
});

test('replacing a variable color keeps important priority', () => {
    const row={name:'color',value:'var(--brand) !important'};
    assert.equal(c.replaceColor(row.value,c.rowColors(row)[0],'#123456'),'#123456 !important');
});
