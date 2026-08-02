(() => {
    const form = document.querySelector('[data-mail-draft-editor]');
    if (!form || !window.KatakataAutosave) return;

    const status = document.querySelector('[data-save-status]');
    const version = form.querySelector('[name="expected_version"]');
    const draftId = form.action.split('/').filter(Boolean).pop() || '';

    window.KatakataAutosave.bind({
        form,
        fields: ['to', 'subject', 'text'],
        storageKey: `katakata:mail-draft:${draftId}`,
        status,
        onSaved(result) {
            if (version) version.value = String(result.version ?? version.value);
        },
        onConflict(result) {
            const current = result.current || {};
            if (version && current.version) version.value = String(current.version);
            window.alert('This draft changed elsewhere. Your local recovery copy has been preserved. Reload to review the current server version.');
        },
    });
})();
