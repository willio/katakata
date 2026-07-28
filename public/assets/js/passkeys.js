(() => {
    'use strict';

    const decode = value => {
        const padded = value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4);
        return Uint8Array.from(atob(padded), character => character.charCodeAt(0));
    };
    const encode = value => {
        const bytes = new Uint8Array(value);
        let binary = '';
        bytes.forEach(byte => binary += String.fromCharCode(byte));
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    };
    const post = async (url, values) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: new URLSearchParams(values),
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Passkey operation failed.');
        return data;
    };
    const passkeysSupported = Boolean(window.isSecureContext && window.PublicKeyCredential && navigator.credentials);
    const outputFor = control => control.closest('form, .editor-panel')?.querySelector('[data-passkey-status]');
    const message = (control, text, error = false) => {
        const output = outputFor(control);
        if (!output) return;
        output.textContent = text;
        output.setAttribute('role', error ? 'alert' : 'status');
    };

    document.querySelectorAll('[data-passkey-register]').forEach(control => {
        if (!passkeysSupported) {
            control.hidden = true;
            return;
        }
        const eventName = control.tagName === 'FORM' ? 'submit' : 'click';
        control.addEventListener(eventName, async event => {
            event.preventDefault();
            if (!passkeysSupported) return;
            const csrf = control.closest('form')?.elements.csrf?.value
                || document.querySelector('input[name="csrf"]')?.value;
            try {
                message(control, 'Waiting for your device…');
                const options = await post('/passkeys/register/options', {csrf});
                options.challenge = decode(options.challenge);
                options.user.id = decode(options.user.id);
                options.excludeCredentials = options.excludeCredentials.map(item => ({...item, id: decode(item.id)}));
                const credential = await navigator.credentials.create({publicKey: options});
                const payload = {
                    id: credential.id,
                    clientDataJSON: encode(credential.response.clientDataJSON),
                    attestationObject: encode(credential.response.attestationObject),
                };
                await post('/passkeys/register', {csrf, credential: JSON.stringify(payload)});
                message(control, 'Passkey added.');
            } catch (error) {
                message(control, error.message || 'Passkey registration failed.', true);
            }
        });
    });

    document.querySelectorAll('[data-passkey-login]').forEach(form => {
        if (!passkeysSupported) {
            form.hidden = true;
            return;
        }
        form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!passkeysSupported) return;
        const csrf = form.elements.csrf.value;
        const email = form.elements.email.value;
        try {
            message(form, 'Waiting for your device…');
            const options = await post('/passkeys/login/options', {csrf, email});
            options.challenge = decode(options.challenge);
            options.allowCredentials = options.allowCredentials.map(item => ({...item, id: decode(item.id)}));
            const credential = await navigator.credentials.get({publicKey: options});
            const payload = {
                id: credential.id,
                clientDataJSON: encode(credential.response.clientDataJSON),
                authenticatorData: encode(credential.response.authenticatorData),
                signature: encode(credential.response.signature),
                userHandle: credential.response.userHandle ? encode(credential.response.userHandle) : '',
            };
            const result = await post('/passkeys/login', {csrf, credential: JSON.stringify(payload)});
            window.location.assign(result.redirect || '/editor');
        } catch (error) {
            message(form, error.message || 'Passkey authentication failed.', true);
        }
        });
    });
})();
