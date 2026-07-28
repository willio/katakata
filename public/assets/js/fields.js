(() => {
    const sync = (input, clear) => {
        clear.hidden = input.disabled || input.value.length === 0;
    };

    document.querySelectorAll('[data-field-clear]').forEach((clear) => {
        const input = document.getElementById(clear.dataset.fieldClear);
        if (!(input instanceof HTMLInputElement)) return;

        sync(input, clear);
        input.addEventListener('input', () => sync(input, clear));
        clear.addEventListener('click', () => {
            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
        });
    });
})();
