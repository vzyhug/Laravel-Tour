// Keep VietGo available while SePay performs the sandbox payment in another tab.
document.querySelectorAll('[data-payment-method-form]').forEach((form) => {
    if (typeof BroadcastChannel === 'undefined') return;
    const channel = new BroadcastChannel(`vietgo-sepay-${form.dataset.bookingId}`);
    let waiting = false;
    channel.addEventListener('message', ({ data }) => {
        if (!waiting || data?.type !== 'checkout-ready') return;
        try {
            const url = new URL(data.statusUrl);
            const expected = `/payments/${form.dataset.bookingId}/sepay/`;
            if (url.origin === location.origin && url.pathname.startsWith(expected)
                && /^[0-9a-z]{26}$/i.test(url.pathname.slice(expected.length)) && !url.search && !url.hash) {
                waiting = false;
                window.location.assign(url.href);
            }
        } catch { /* Ignore unrelated or invalid tab messages. */ }
    });
    form.addEventListener('submit', () => {
        waiting = form.querySelector('[name="payment_method"]:checked')?.value === 'sepay';
        form.target = waiting ? '_blank' : '_self';
        form.rel = 'noopener';
    });
    window.addEventListener('pagehide', () => channel.close());
});

document.querySelectorAll('[data-sepay-checkout-form]').forEach((form) => {
    const returnToStatus = () => window.location.replace(form.dataset.returnUrl);
    window.addEventListener('pageshow', (event) => { if (event.persisted) returnToStatus(); });
    if (performance.getEntriesByType('navigation')[0]?.type === 'back_forward') {
        returnToStatus();
    } else {
        if (typeof BroadcastChannel !== 'undefined') {
            const channel = new BroadcastChannel(`vietgo-sepay-${form.dataset.bookingId}`);
            channel.postMessage({ type: 'checkout-ready', statusUrl: form.dataset.returnUrl });
            channel.close();
        }
        form.submit();
    }
});
