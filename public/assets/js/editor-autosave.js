(() => {
    const json = (value) => {
        try { return JSON.parse(value); } catch { return null; }
    };

    window.KatakataAutosave = {
        bind({ form, fields, storageKey, status, onSaved, onConflict }) {
            if (!form || !storageKey) return null;

            const endpoint = form.dataset.autosaveUrl || '';
            let serverVersion = form.dataset.serverVersion || '';
            let serverUpdatedAt = Date.parse(form.dataset.serverUpdatedAt || '') || 0;
            let timer = null;
            let bufferTimer = null;
            let saving = false;

            const setStatus = (text) => {
                if (status) status.textContent = ['Saving…', 'Saved', 'Not saved'].includes(text) ? '' : text;
            };
            const read = () => json(localStorage.getItem(storageKey));
            const values = () => Object.fromEntries(fields.map((name) => {
                const input = form.elements.namedItem(name);
                if (!input) return [name, ''];
                if (input instanceof RadioNodeList) return [name, input.value];
                if (input instanceof HTMLInputElement && input.type === 'checkbox') return [name, input.checked ? input.value : ''];
                return [name, input.value];
            }));
            const write = () => {
                const next = {
                    fields: values(),
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
                if (!endpoint || saving) return false;
                const pending = read();
                if (!pending) return true;
                if (!navigator.onLine) {
                    setStatus('Not saved — offline');
                    return false;
                }

                saving = true;
                const payload = new FormData(form);
                payload.set('expected_version', pending.baseVersion);
                payload.set('client_version', pending.clientVersion);

                try {
                    const response = await fetch(endpoint, { method: 'POST', body: payload, headers: { Accept: 'application/json' } });
                    const result = await response.json();
                    if (response.status === 409) {
                        onConflict?.(result);
                        setStatus('Changed elsewhere');
                        return false;
                    }
                    if (!response.ok) throw new Error(result.error || 'Save failed');

                    serverVersion = String(result.version ?? serverVersion);
                    serverUpdatedAt = Date.parse(result.updated_at || '') || Date.now();
                    form.dataset.serverVersion = serverVersion;
                    form.dataset.serverUpdatedAt = result.updated_at || '';
                    const current = read();
                    if (current?.clientVersion === result.client_version) {
                        localStorage.removeItem(storageKey);
                        setStatus('');
                    } else {
                        schedule();
                    }
                    onSaved?.(result);
                    return true;
                } catch {
                    setStatus(navigator.onLine ? 'Save failed' : 'Not saved — offline');
                    return false;
                } finally {
                    saving = false;
                }
            };
            const restore = () => {
                const local = read();
                if (!local || local.updatedAt <= serverUpdatedAt) return;
                const current = values();
                const differs = fields.some((name) => String(local.fields?.[name] ?? '') !== String(current[name] ?? ''));
                if (!differs) return;

                if (window.confirm('A newer local recovery buffer exists. Restore it?')) {
                    fields.forEach((name) => {
                        const input = form.elements.namedItem(name);
                        if (!input || input instanceof RadioNodeList) return;
                        if (input instanceof HTMLInputElement && input.type === 'checkbox') input.checked = String(local.fields?.[name] ?? '') !== '';
                        else input.value = String(local.fields?.[name] ?? '');
                    });
                    schedule();
                } else {
                    localStorage.removeItem(storageKey);
                }
            };

            restore();
            form.addEventListener('input', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement)) return;
                if (!fields.includes(target.name)) return;
                clearTimeout(bufferTimer);
                bufferTimer = setTimeout(() => { write(); schedule(); }, 750);
            });
            form.addEventListener('focusout', () => { write(); void sync(); });
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') { write(); void sync(); }
            });
            window.addEventListener('online', () => { setStatus(''); void sync(); });
            window.addEventListener('offline', () => setStatus('Not saved — offline'));

            return { write, sync, schedule, read };
        },
    };
})();
