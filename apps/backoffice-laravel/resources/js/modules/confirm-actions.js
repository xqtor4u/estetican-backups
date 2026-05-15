const initConfirmActions = () => {
    if (document.body.dataset.confirmActionsEnabled === 'false') {
        return;
    }

    if (document.body.dataset.confirmActionsInitialized === 'true') {
        return;
    }

    document.body.dataset.confirmActionsInitialized = 'true';

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-confirm]');

        if (!trigger) {
            return;
        }

        const message = trigger.getAttribute('data-confirm')?.trim();

        if (message) {
            // Prevent default immediately
            event.preventDefault();
            event.stopPropagation();

            // Trigger premium confirmation
            if (window.premiumConfirm) {
                window.premiumConfirm(message, () => {
                    // If it's a form button inside a form, submit the form
                    if (trigger.type === 'submit' && trigger.form) {
                        trigger.form.submit();
                        return;
                    }
                    // If it's a link, navigate
                    if (trigger.tagName === 'A' && trigger.href) {
                        window.location.href = trigger.href;
                        return;
                    }
                    // Generic fallback: click it again but without confirmation data to avoid loop
                    const originalConfirm = trigger.getAttribute('data-confirm');
                    trigger.removeAttribute('data-confirm');
                    trigger.click();
                    // Restore after a tick if needed, but usually we navigate away
                });
            } else {
                // Fallback to native if JS hasn't loaded
                if (window.confirm(message)) {
                    const originalConfirm = trigger.getAttribute('data-confirm');
                    trigger.removeAttribute('data-confirm');
                    trigger.click();
                }
            }
        }
    });
};

document.addEventListener('DOMContentLoaded', initConfirmActions);