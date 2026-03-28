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

        if (message && !window.confirm(message)) {
            event.preventDefault();
            event.stopPropagation();
        }
    });
};

document.addEventListener('DOMContentLoaded', initConfirmActions);