const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];

const initScreenLock = () => {
    const { screenLockIdleMinutes, screenLockUrl, screenLockShowUrl } = document.body.dataset;

    if (!screenLockIdleMinutes || !screenLockUrl || !screenLockShowUrl) {
        return;
    }

    if (document.body.dataset.screenLockInitialized === 'true') {
        return;
    }

    document.body.dataset.screenLockInitialized = 'true';

    const idleMs = Number(screenLockIdleMinutes) * 60000;

    if (!Number.isFinite(idleMs) || idleMs <= 0) {
        return;
    }

    let timer = null;

    const triggerLock = () => {
        const token = document.querySelector('input[name="_token"]')?.value;

        fetch(screenLockUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token ?? '',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                redirect_url: window.location.pathname + window.location.search,
            }),
        }).finally(() => {
            window.location.href = screenLockShowUrl;
        });
    };

    const resetTimer = () => {
        if (timer) {
            clearTimeout(timer);
        }

        timer = setTimeout(triggerLock, idleMs);
    };

    ACTIVITY_EVENTS.forEach((eventName) => {
        document.addEventListener(eventName, resetTimer, { passive: true });
    });

    resetTimer();
};

document.addEventListener('DOMContentLoaded', initScreenLock);
