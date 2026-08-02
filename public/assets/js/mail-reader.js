(() => {
    'use strict';

    const reader = document.querySelector('[data-mail-reader]');
    const links = Array.from(document.querySelectorAll('[data-mail-message-link]'));
    if (!(reader instanceof HTMLElement) || links.length === 0) return;

    const select = async (link, pushHistory = true) => {
        if (!(link instanceof HTMLAnchorElement)) return;
        links.forEach((item) => item.removeAttribute('aria-current'));
        link.setAttribute('aria-current', 'page');
        reader.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(link.dataset.detailUrl || '', {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Unable to load message.');
            reader.innerHTML = await response.text();
            if (pushHistory) history.pushState({ mailMessage: link.href }, '', link.href);
            reader.querySelector('h2')?.focus({ preventScroll: true });
        } catch {
            reader.innerHTML = '<p class="quiet" role="alert">Message could not be loaded.</p>';
        } finally {
            reader.removeAttribute('aria-busy');
        }
    };

    links.forEach((link) => link.addEventListener('click', (event) => {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        void select(link);
    }));

    const initial = links.find((link) => link.getAttribute('aria-current') === 'page');
    if (initial) void select(initial, false);

    window.addEventListener('popstate', () => {
        const url = new URL(window.location.href);
        const account = url.searchParams.get('account');
        const message = url.searchParams.get('message');
        const match = links.find((link) => link.dataset.account === account && link.dataset.message === message);
        if (match) void select(match, false);
        else {
            links.forEach((item) => item.removeAttribute('aria-current'));
            reader.innerHTML = '<p class="quiet">Select a message.</p>';
        }
    });
})();
