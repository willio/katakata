(() => {
    const editor = document.querySelector('[data-editor]');
    if (!editor) return;

    const textarea = editor.querySelector('[name="body"]');
    const title = editor.querySelector('[name="title"]');
    const status = document.querySelector('[data-save-status]');
    const panel = document.querySelector('[data-editor-panel]');
    const toggle = document.querySelector('[data-settings-toggle]');
    const close = document.querySelector('[data-settings-close]');
    const slug = editor.dataset.draftId;
    const endpoint = editor.dataset.autosaveUrl;
    const storageKey = slug ? `katakata:draft:${slug}` : null;
    let serverVersion = editor.dataset.serverVersion || '';
    let serverUpdatedAt = Date.parse(editor.dataset.serverUpdatedAt || '') || 0;
    let timer = null;
    let bufferTimer = null;
    let saving = false;

    const setStatus = (text) => {
        status.textContent = ['Saving…', 'Saved', 'Not saved'].includes(text) ? '' : text;
    };
    const readBuffer = () => {
        if (!storageKey) return null;
        try { return JSON.parse(localStorage.getItem(storageKey)); } catch { return null; }
    };
    const writeBuffer = () => {
        if (!storageKey) {
            setStatus('Not saved');
            return null;
        }
        const next = {
            body: textarea.value,
            title: title.value,
            baseVersion: serverVersion,
            clientVersion: crypto.randomUUID(),
            updatedAt: Date.now(),
        };
        localStorage.setItem(storageKey, JSON.stringify(next));
        setStatus(navigator.onLine ? 'Saving…' : 'Not saved — offline');
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
        setStatus('Saving…');
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
                setStatus('Saved');
            } else {
                setStatus('Saving…');
                schedule();
            }
        } catch {
            setStatus(navigator.onLine ? 'Save failed' : 'Not saved — offline');
        } finally {
            saving = false;
        }
    };

    const local = readBuffer();
    if (local && local.updatedAt > serverUpdatedAt && (local.body !== textarea.value || local.title !== title.value)) {
        if (window.confirm('A newer local recovery buffer exists. Restore it?')) {
            textarea.value = local.body;
            title.value = local.title;
            setStatus('Saving…');
            schedule();
        } else {
            localStorage.removeItem(storageKey);
        }
    }

    editor.addEventListener('input', () => {
        clearTimeout(bufferTimer);
        bufferTimer = setTimeout(() => {
            writeBuffer();
            schedule();
        }, 750);
    });
    editor.addEventListener('focusout', () => { writeBuffer(); void sync(); });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            writeBuffer();
            void sync();
        }
    });
    window.addEventListener('online', () => { setStatus('Saving…'); void sync(); });
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
