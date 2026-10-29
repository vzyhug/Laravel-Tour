import { showFlashAlert } from './flash-alert';

document.querySelectorAll('[data-qr-payment]').forEach((root) => {
    let state = JSON.parse(root.querySelector('[data-qr-state]').textContent);
    const labels = JSON.parse(root.querySelector('[data-qr-labels]').textContent);
    const status = root.querySelector('[data-qr-status]');
    const countdown = root.querySelector('[data-qr-countdown]');
    const connection = root.querySelector('[data-qr-connection]');
    const refresh = root.querySelector('[data-qr-refresh]');
    let receivedAt = performance.now();
    let timer;
    let busy = false;
    let stopped = false;
    const announcedStatuses = new Set();
    const render = () => {
        const pending = state.status === 'pending';
        const paid = ['paid', 'superseded'].includes(state.status);
        status.textContent = labels[state.status];
        root.querySelectorAll('[data-qr-step]').forEach((step) => {
            const active = step.dataset.qrStep === 'confirmed' ? paid : !paid;
            step.classList.toggle('bg-linen', !active);
            ['bg-brand-50', 'font-semibold', 'text-brand-600'].forEach((name) => step.classList.toggle(name, active));
        });
        root.querySelector('[data-qr-active]').hidden = !pending;
        root.querySelector('[data-qr-result]').hidden = pending;
        root.querySelector('[data-qr-result-title]').textContent = labels[state.status];
        root.querySelector('[data-qr-result-description]').textContent = state.status === 'review'
            ? 'Kết quả từ SePay cần được đối soát. Vui lòng liên hệ nhân viên; chưa thể tự động xác nhận hoặc tạo phiên thanh toán mới.'
            : paid
            ? 'Đã xác nhận thanh toán. Bạn có thể xem thông tin chuyến đi và hóa đơn.'
            : 'Không ghi nhận khoản thanh toán thành công từ mã QR này. Bạn có thể xem lại đơn hoặc tạo mã mới nếu đơn vẫn đang chờ thanh toán.';
        const invoice = root.querySelector('[data-qr-invoice]');
        invoice.hidden = !state.invoice_url;
        if (state.invoice_url) invoice.href = state.invoice_url;
        const retry = root.querySelector('[data-qr-retry]');
        if (retry) retry.hidden = !state.can_retry;
        if (!pending && !announcedStatuses.has(state.status)) {
            announcedStatuses.add(state.status);
            showFlashAlert({
                variant: paid ? 'success' : state.status === 'failed' ? 'error' : 'warning',
                title: labels[state.status],
                message: root.querySelector('[data-qr-result-description]').textContent,
            });
        }
    };
    const tick = () => {
        const seconds = Math.max(0, state.seconds_remaining - Math.floor((performance.now() - receivedAt) / 1000));
        countdown.textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
    };
    const check = async () => {
        clearTimeout(timer);
        if (busy || stopped || document.hidden) return;
        busy = true;
        refresh.disabled = true;
        let delay = 3000;
        try {
            const response = await fetch(root.dataset.statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: AbortSignal.timeout(10000) });
            if (!response.ok || response.redirected) throw new Error('Status request failed');
            const next = await response.json();
            if (next.verification_available === false) throw new Error('Provider verification unavailable');
            if (!Object.hasOwn(labels, next.status)) throw new Error('Unknown payment status');
            state = next;
            receivedAt = performance.now();
            connection.hidden = true;
            render();
            tick();
        } catch {
            connection.textContent = 'Chưa kiểm tra được trạng thái. Vui lòng kiểm tra kết nối hoặc tải lại trang; hệ thống chưa xác nhận thanh toán từ thông báo này.';
            connection.hidden = false;
            delay = 10000;
        } finally {
            busy = false;
            refresh.disabled = false;
            if (!stopped && state.status === 'pending' && !document.hidden) timer = setTimeout(check, delay);
        }
    };
    refresh.addEventListener('click', check);
    document.addEventListener('visibilitychange', () => { clearTimeout(timer); if (!document.hidden) check(); });
    window.addEventListener('focus', check);
    const interval = setInterval(tick, 1000);
    window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); clearInterval(interval); });
    window.addEventListener('pageshow', (event) => { if (event.persisted) location.reload(); });
    root.querySelector('[data-copy-qr-reference]')?.addEventListener('click', async () => {
        const feedback = root.querySelector('[data-copy-qr-status]');
        try {
            await navigator.clipboard.writeText(state.reference);
            feedback.textContent = 'Đã sao chép nội dung giao dịch.';
        } catch {
            const range = document.createRange();
            range.selectNodeContents(root.querySelector('#qr-reference'));
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            feedback.textContent = 'Đã chọn nội dung. Dùng Ctrl+C hoặc chạm giữ để sao chép.';
        }
    });
    render(); tick();
    if (state.status === 'pending') timer = setTimeout(check, 3000);
});
