(() => {
    'use strict';
    const colorProperty = name => /(?:^|-)color$|^(?:background(?:-image)?|border(?:-(?:top|right|bottom|left|inline(?:-start|-end)?|block(?:-start|-end)?))?|outline|box-shadow|text-shadow|fill|stroke|--[\w-]+)$/.test(String(name).trim().toLowerCase());
    const colorTokens = (value, valid = color => CSS.supports('color', color)) => {
        const result = []; value = String(value || '');
        const endFunction = start => {
            let depth = 0, quote = '', escaped = false;
            for (let i = start; i < value.length; i++) {
                const char = value[i];
                if (escaped) { escaped = false; continue; }
                if (char === '\\') { escaped = true; continue; }
                if (quote) { if (char === quote) quote = ''; continue; }
                if (char === '"' || char === "'") { quote = char; continue; }
                if (char === '(') depth++;
                if (char === ')' && --depth === 0) return i + 1;
            }
            return value.length;
        };
        for (let i = 0; i < value.length;) {
            if (value.slice(i, i + 2) === '/*') { const end = value.indexOf('*/', i + 2); i = end < 0 ? value.length : end + 2; continue; }
            if (value[i] === '"' || value[i] === "'") {
                const quote = value[i++]; while (i < value.length) { if (value[i] === '\\') i += 2; else if (value[i++] === quote) break; } continue;
            }
            const match = value.slice(i).match(/^(?:#[a-zA-Z0-9]+|[a-zA-Z_-][a-zA-Z0-9_-]*)/);
            if (!match) { i++; continue; }
            const name = match[0], start = i; let end = i + name.length;
            if (value[end] === '(') {
                if (/^(?:url|var|env)$/i.test(name)) { i = endFunction(end); continue; }
                if (/^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color|color-mix|light-dark)$/i.test(name)) end = endFunction(end);
                else { i = end + 1; continue; }
            }
            const token = value.slice(start, end);
            if (!/^(?:inherit|initial|unset|revert|revert-layer)$/i.test(token) && valid(token)) result.push({ start, end, color: token });
            i = end;
        }
        return result;
    };
    const rowColors = row => {
        if (!colorProperty(row.name)) return [];
        const result = colorTokens(row.value);
        if (!result.length && /^(?:\s*|\s*var\([\s\S]*\)\s*|\s*(?:inherit|initial|unset|revert|revert-layer)\s*)$/i.test(row.value)) return [{ start: 0, end: row.value.length, color: '' }];
        return result;
    };
    const replaceColor = (value, token, color) => value.slice(0, token.start) + color + value.slice(token.end);
    window.waColors = { colorProperty, colorTokens, rowColors, replaceColor };
    document.addEventListener('alpine:init', () => {
        Alpine.data('waColorPicker', options => ({
            widget: null,
            init() {
                this.$nextTick(() => {
                    this.widget = window.jQuery(this.$refs.color);
                    this.widget.spectrum({
                        type: 'color', color: options.get() || null, showInput: true, showInitial: true, showPalette: false,
                        showAlpha: options.alpha !== false, allowEmpty: false, preferredFormat: 'hex',
                        clickoutFiresChange: false, appendTo: 'body', containerClassName: 'wa-color-popup',
                        chooseText: 'Použiť', cancelText: 'Zrušiť', clearText: 'Vymazať farbu', noColorSelectedText: 'Vyberte farbu',
                        change: color => { if (color && !options.disabled()) options.set(color.getAlpha() < 1 ? color.toRgbString() : color.toHexString()); },
                        show: () => this.widget.attr('aria-expanded', 'true'),
                        hide: () => { this.widget.attr('aria-expanded', 'false'); },
                    });
                    this.widget.attr({ 'aria-label': options.label, 'aria-haspopup': 'dialog', 'aria-expanded': 'false' });
                    this.widget.spectrum('container').attr({ role: 'dialog', 'aria-label': options.label });
                    this.$watch(() => options.get(), () => this.sync());
                    this.$watch(() => options.disabled(), () => this.sync());
                    this.sync();
                });
            },
            sync() {
                if (!this.widget) return;
                this.widget.spectrum('set', options.get() || null);
                this.widget.spectrum(options.disabled() ? 'disable' : 'enable');
                const color = options.get() || '';
                this.widget[0].style.setProperty('--wa-selected-color', color && CSS.supports('color', color) ? color : 'transparent');
                this.widget.prop('disabled', options.disabled());
                this.widget.attr({ 'aria-disabled': String(options.disabled()), title: color ? options.label + ': ' + color : options.label });
            },
            destroy() { if (this.widget?.data('spectrum.id') !== undefined) this.widget.spectrum('destroy'); this.widget = null; },
        }));
    });
})();
