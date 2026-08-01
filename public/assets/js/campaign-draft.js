(() => {
    'use strict';

    const form = document.getElementById('campaign-draft-form');
    if (!(form instanceof HTMLFormElement)) return;

    const status = document.getElementById('campaign-save-state');
    const version = form.querySelector('input[name="expected_version"]');
    const clientVersion = form.querySelector('input[name="client_version"]');
    const fields = Array.from(form.querySelectorAll('input[name="subject"], input[name="preheader"], textarea[name="body"]'));
    const fullscreen = document.getElementById('campaign-fullscreen-toggle');
    let timer = null;
    let sequence = 0;

    const setStatus = (message) => {
        if (status) status.textContent = message;
    };

    const payload = () => {
        const data = new FormData(form);
        sequence += 1;
        if (clientVersion instanceof HTMLInputElement) {
            clientVersion.value = String(sequence);
            data.set('client_version', clientVersion.value);
        }
        return data;
    };

    const save = async () => {
        const url = form.dataset.autosaveUrl;
        if (!url) return;

        setStatus('Saving…');
        try {
            const response = await fetch(url, {
                method: 'POST',
                body: payload(),
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const result = await response.json();

            if (response.status === 409) {
                setStatus('Conflict detected. Server version ' + result.current.version + ' is available for recovery.');
                window.localStorage.setItem('katakata:campaign-draft:recovery:' + result.current.id, JSON.stringify({
                    server: result.current,
                    local: {
                        subject: form.elements.subject.value,
                        preheader: form.elements.preheader.value,
                        body: form.elements.body.value,
                    },
                    saved_at: new Date().toISOString(),
                }));
                return;
            }

            if (!response.ok) throw new Error(result.error || 'Unable to save campaign draft.');

            if (version instanceof HTMLInputElement) version.value = String(result.version);
            setStatus('Saved version ' + result.version + '.');
        } catch (error) {
            setStatus(error instanceof Error ? error.message : 'Unable to save campaign draft.');
        }
    };

    const schedule = () => {
        if (timer !== null) window.clearTimeout(timer);
        setStatus('Unsaved changes.');
        timer = window.setTimeout(save, 7000);
    };

    const toggleFullscreen = () => {
        const active = document.body.classList.toggle('campaign-compose-fullscreen');
        if (fullscreen instanceof HTMLButtonElement) {
            fullscreen.setAttribute('aria-pressed', active ? 'true' : 'false');
            fullscreen.textContent = active ? 'Exit fullscreen' : 'Fullscreen';
        }
    };

    fields.forEach((field) => field.addEventListener('input', schedule));
    if (fullscreen instanceof HTMLButtonElement) fullscreen.addEventListener('click', toggleFullscreen);
    window.addEventListener('blur', () => { if (timer !== null) save(); });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden' && timer !== null) save();
    });
})();
