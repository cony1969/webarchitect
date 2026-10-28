    <template x-if="draft.copyOpen">
        <div class="wa-project-modal-backdrop" @keydown.escape.window="if (!draft.saving) draft.copyOpen = false" @click.self="if (!draft.saving) draft.copyOpen = false">
            <section class="wa-project-copy-dialog" role="dialog" aria-modal="true" :aria-labelledby="'project-copy-title-' + key" @keydown.tab="trapCopy($event)">
                <h2 :id="'project-copy-title-' + key" x-text="draft.kind === 'domain' ? 'Uložiť ako novú doménu' : 'Uložiť ako nový projekt'"></h2>
                <p class="wa-project-muted" x-text="draft.kind === 'domain' ? 'Nová doména preberie aktuálne nastavenia. Stránky sa nekopírujú.' : 'Vznikne nový prázdny projekt. Domény ani stránky sa nekopírujú.'"></p>
                <form @submit.prevent="store.save(key, true)">
                    <fieldset :disabled="draft.saving">
                        <label class="wa-project-field"><span x-text="draft.kind === 'domain' ? 'Nová doména / subdoména' : 'Názov nového projektu'"></span>
                            <input type="text" :maxlength="draft.kind === 'domain' ? 253 : 160" x-model="draft.copyName" x-init="$nextTick(() => $el.focus())" required>
                        </label>
                        <div x-show="draft.error" class="wa-project-message is-error" role="alert"><p x-text="draft.error"></p><template x-for="(messages, field) in draft.errors" :key="field"><p x-text="messages.join(' ')"></p></template></div>
                        <div class="wa-project-actions">
                            <button type="button" class="btn wa-project-secondary" @click="draft.copyOpen = false">Zrušiť</button>
                            <button type="submit" class="btn wa-project-save" x-text="draft.saving ? 'Ukladám…' : 'Uložiť ako nový'"></button>
                        </div>
                    </fieldset>
                </form>
            </section>
        </div>
    </template>
