(() => {
    const editor = document.querySelector('[data-editor]');
    if (!editor) return;

    const textarea = editor.querySelector('[name="body"]');
    const title = editor.querySelector('[name="title"]');
    const slugInput = editor.querySelector('[name="slug"]');
    const status = document.querySelector('[data-save-status]');
    const panel = document.querySelector('[data-editor-panel]');
    const toggle = document.querySelector('[data-settings-toggle]');
    const close = document.querySelector('[data-settings-close]');
    const draftSlug = editor.dataset.draftId;
    const storageKey = draftSlug ? `katakata:draft:${draftSlug}` : null;

    const firstLine = () => textarea.value
        .split(/\r?\n/, 1)[0]
        .replace(/^\s{0,3}#{1,6}\s+/, '')
        .trim();
    const slugify = (value) => {
        const ascii = value
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 120);
        if (ascii || !value) return ascii;

        let hash = 2166136261;
        for (const character of value) {
            hash ^= character.codePointAt(0);
            hash = Math.imul(hash, 16777619);
        }
        return `post-${(hash >>> 0).toString(36)}`;
    };
    const deriveMetadata = () => {
        const nextTitle = firstLine();
        title.value = nextTitle;
        if (!draftSlug) slugInput.value = slugify(nextTitle);
    };
    const setStatus = (text) => {
        status.textContent = ['Saving…', 'Saved', 'Not saved'].includes(text) ? '' : text;
    };

    deriveMetadata();
    textarea.addEventListener('input', deriveMetadata);
    editor.addEventListener('submit', (event) => {
        deriveMetadata();
        if (!title.value || !slugInput.value) {
            event.preventDefault();
            setStatus('Begin with a title before creating this draft');
            textarea.focus();
        }
    });

    if (storageKey && window.KatakataAutosave) {
        window.KatakataAutosave.bind({
            form: editor,
            fields: ['body', 'title', 'publish_as_newsletter', 'discussion_enabled'],
            storageKey,
            status,
            onSaved: deriveMetadata,
            onConflict: () => setStatus('Changed elsewhere — reload to compare'),
        });
    }

    const setPanelOpen = (opening) => {
        panel.toggleAttribute('hidden', !opening);
        toggle?.setAttribute('aria-expanded', String(opening));
        if (opening) close?.focus();
        else toggle?.focus();
    };
    toggle?.addEventListener('click', () => setPanelOpen(panel.hasAttribute('hidden')));
    close?.addEventListener('click', () => setPanelOpen(false));
    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key === ',') {
            event.preventDefault();
            toggle?.click();
        }
        if (event.key === 'Escape' && panel && !panel.hasAttribute('hidden')) toggle?.click();
    });
})();
