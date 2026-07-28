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
    const endpoint = editor.dataset.autosaveUrl;
    const storageKey = draftSlug ? `katakata:draft:${draftSlug}` : null;
    let serverVersion = editor.dataset.serverVersion || '';
    let serverUpdatedAt = Date.parse(editor.dataset.serverUpdatedAt || '') || 0;
    let timer = null;
    let bufferTimer = null;
    let saving = false;

    const firstLine = () => textarea.value
        .split(/\r?\n/, 1)[0]
        .replace(/^\s{0,3}#{1,6}\s+/, '')
        .trim();
    const slugify = (value) => value
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 120);
    const deriveMetadata = () => {
        const nextTitle = firstLine();
        title.value = nextTitle;
        if (!draftSlug) slugInput.value = slugify(nextTitle);
        return nextTitle;
    };
    const setStatus = (text) => {
        status.textContent = ['Saving…', 'Saved', 'Not saved'].includes(text) ? '' : text;
    };
    const readBuffer = () => {
        if (!storageKey) return null;
        try { return JSON.parse(localStorage.getItem(storageKey)); } catch { return null; }
    };
    const writeBuffer = () => {
        deriveMetadata();
        if (!storageKey) return null;
        const next = {
            body: textarea.value,
            title: title.value,
            baseVersion: serverVersion,
            clientVersion: crypto.randomUUID(),
            updatedAt: Date.now(),
        };
        localStorage.setItem(storageKey, JSON.stringify(next));
        if (!navigator.onLine) setStatus('Not saved — offline');
        return next;
    };

    const schedule = () => {
        clearTimeout(timer);
        timer = setTimeout(sync, 7000);
    };

    const sync = async () => {
        if (!endpoint || saving) return;
        const pending = readBuffer();
        if (!pending) return;
        if (!navigator.onLine) {
            setStatus('Not saved — offline');
            return;
        }

        saving = true;
        const form = new FormData(editor);
        form.set('client_version', pending.clientVersion);

        try {
            const response = await fetch(endpoint, { method: 'POST', body: form, headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Save failed');

            serverVersion = result.version;
            serverUpdatedAt = Date.parse(result.updated_at) || Date.now();
            editor.dataset.serverVersion = serverVersion;
            editor.dataset.serverUpdatedAt = result.updated_at;
            const current = readBuffer();
            if (current?.clientVersion === result.client_version) {
                localStorage.removeItem(storageKey);
                setStatus('');
            } else {
                schedule();
            }
        } catch {
            setStatus(navigator.onLine ? 'Save failed' : 'Not saved — offline');
        } finally {
            saving = false;
        }
    };

    deriveMetadata();
    const local = readBuffer();
    if (local && local.updatedAt > serverUpdatedAt && (local.body !== textarea.value || local.title !== title.value)) {
        if (window.confirm('A newer local recovery buffer exists. Restore it?')) {
            textarea.value = local.body;
            deriveMetadata();
            schedule();
        } else {
            localStorage.removeItem(storageKey);
        }
    }

    editor.addEventListener('input', (event) => {
        if (event.target !== textarea) return;
        deriveMetadata();
        clearTimeout(bufferTimer);
        bufferTimer = setTimeout(() => {
            writeBuffer();
            schedule();
        }, 750);
    });
    editor.addEventListener('submit', (event) => {
        deriveMetadata();
        if (!title.value || !slugInput.value) {
            event.preventDefault();
            setStatus('Begin with a title before creating this draft');
            textarea.focus();
        }
    });
    editor.addEventListener('focusout', () => { writeBuffer(); void sync(); });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            writeBuffer();
            void sync();
        }
    });
    window.addEventListener('online', () => { setStatus(''); void sync(); });
    window.addEventListener('offline', () => setStatus('Not saved — offline'));

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
