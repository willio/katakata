(() => {
    'use strict';

    const form = document.querySelector('[data-campaign-draft]');
    if (!(form instanceof HTMLFormElement) || !window.KatakataAutosave) return;

    const status = document.querySelector('[data-save-status]');
    const expectedVersion = form.querySelector('input[name="expected_version"]');

    window.KatakataAutosave.bind({
        form,
        fields: ['subject', 'preheader', 'body'],
        storageKey: `katakata:campaign-draft:${form.action.split('/').pop()}`,
        status,
        onSaved(result) {
            if (expectedVersion instanceof HTMLInputElement) {
                expectedVersion.value = String(result.version ?? expectedVersion.value);
            }
            if (status) status.textContent = `Saved version ${result.version}.`;
        },
        onConflict(result) {
            const current = result.current || {};
            if (status) status.textContent = `Conflict detected. Server version ${current.version ?? 'unknown'} is available for recovery.`;
        },
    });
})();
