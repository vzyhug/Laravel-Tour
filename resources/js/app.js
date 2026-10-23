const closeMainMenu = () => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = toggle && document.getElementById(toggle.dataset.menuToggle);
    menu?.classList.add('hidden');
    toggle?.setAttribute('aria-expanded', 'false');
};

document.querySelectorAll('[data-copy-coupon]').forEach((button) => {
    button.addEventListener('click', async () => {
        const status = document.querySelector('[data-copy-status]');
        try {
            await navigator.clipboard.writeText(button.dataset.copyCoupon);
            status.textContent = `Đã sao chép ${button.dataset.copyCoupon}. Nhập mã tại bước đặt tour để kiểm tra điều kiện.`;
        } catch {
            status.textContent = `Bạn có thể nhập mã ${button.dataset.copyCoupon} tại bước đặt tour.`;
        }
    });
});

document.querySelectorAll('[data-payment-countdown]').forEach((timer) => {
    const deadline = Date.parse(timer.dataset.paymentCountdown);
    const offset = Date.parse(timer.dataset.serverNow) - Date.now();
    const tick = () => {
        const seconds = Math.max(0, Math.ceil((deadline - Date.now() - offset) / 1000));
        timer.textContent = seconds > 0 ? `${Math.floor(seconds / 60)} phút ${seconds % 60} giây` : 'Đã hết hạn; vui lòng chọn lại lịch';
        if (seconds === 0) {
            const formId = timer.dataset.paymentForm || 'payment-form';
            document.querySelectorAll(`button[form="${formId}"], #${formId} button`).forEach((button) => { button.disabled = true; });
        }
    };
    tick();
    setInterval(tick, 1000);
});

document.querySelectorAll('[data-bus-form]').forEach((form) => {
    const trips = JSON.parse(form.dataset.trips);
    const tripSelect = form.querySelector('[data-bus-trip]');
    const from = form.querySelector('[data-bus-from]');
    const to = form.querySelector('[data-bus-to]');
    const seats = form.querySelector('[data-bus-seats]');
    const status = form.querySelector('[data-bus-status]');
    const submit = form.querySelector('button:not([type]), button[type="submit"]');
    let version = 0;
    let selected = JSON.parse(form.dataset.oldSeats || '[]');
    const refreshSeats = async () => {
        const current = ++version;
        submit.disabled = true;
        const trip = trips.find((item) => String(item.id) === tripSelect.value);
        const start = trip?.stops.findIndex((stop) => String(stop.id) === from.value) ?? -1;
        const end = trip?.stops.findIndex((stop) => String(stop.id) === to.value) ?? -1;
        if (start < 0 || end <= start) { seats.replaceChildren(); status.textContent = 'Chọn điểm xuống sau điểm lên trên cùng chuyến.'; return; }
        status.textContent = 'Đang kiểm tra ghế trên đoạn tuyến...';
        try {
            const response = await fetch(`${form.dataset.seatsBase}/${trip.id}/seats?from=${from.value}&to=${to.value}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) { throw new Error('Seats unavailable'); }
            const result = await response.json();
            if (current !== version) { return; }
            seats.replaceChildren();
            for (const seat of result.seats) {
                const label = document.createElement('label');
                label.className = 'flex min-h-12 items-center gap-2 rounded-lg border border-slate-200 p-3 text-xs';
                const input = document.createElement('input');
                input.type = 'checkbox'; input.name = 'seats[]'; input.value = seat.label;
                input.disabled = seat.status !== 'available'; input.checked = !input.disabled && selected.includes(seat.label);
                const text = document.createElement('span');
                text.textContent = `${seat.label} · ${({ available: 'Trống', held: 'Đang giữ', sold: 'Đã bán' })[seat.status]}`;
                if (input.disabled) { label.classList.add('bg-linen', 'text-muted'); }
                input.addEventListener('change', () => { selected = [...seats.querySelectorAll('input:checked')].map((element) => element.value); tripSelect.setCustomValidity(''); });
                label.append(input, text); seats.append(label);
            }
            status.textContent = 'Ghế được giữ sau khi xác nhận báo giá. Tối đa 8 ghế/đơn.';
            submit.disabled = false;
        } catch {
            if (current === version) { status.textContent = 'Chưa tải được ghế. Hãy chọn lại chuyến hoặc tải lại trang.'; seats.replaceChildren(); }
        }
    };
    const selectTrip = () => {
        const trip = trips.find((item) => String(item.id) === tripSelect.value);
        from.replaceChildren(); to.replaceChildren();
        for (const stop of trip?.stops || []) {
            const text = `${stop.name} · ${stop.time} · ${stop.address}`;
            from.add(new Option(text, stop.id)); to.add(new Option(text, stop.id));
        }
        if (trip?.stops.length) { to.value = trip.stops.at(-1).id; }
        if (form.dataset.oldFrom) { from.value = form.dataset.oldFrom; delete form.dataset.oldFrom; }
        if (form.dataset.oldTo) { to.value = form.dataset.oldTo; delete form.dataset.oldTo; }
        refreshSeats();
    };
    tripSelect.addEventListener('change', selectTrip);
    [from, to].forEach((select) => select.addEventListener('change', refreshSeats));
    form.addEventListener('submit', (event) => {
        tripSelect.setCustomValidity(seats.querySelectorAll('input:checked').length ? '' : 'Vui lòng chọn ít nhất một ghế.');
        if (!form.reportValidity()) { event.preventDefault(); }
    });
    selectTrip();
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || form.method.toLowerCase() !== 'post') { return; }
    const buttons = [...form.querySelectorAll('button:not([type]), button[type="submit"]'),
        ...document.querySelectorAll(`button[type="submit"][form="${form.id}"]`)];
    buttons.forEach((button) => { button.disabled = true; button.dataset.submitting = 'true'; });
    form.setAttribute('aria-busy', 'true');
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-submitting]').forEach((button) => { button.disabled = false; delete button.dataset.submitting; });
    document.querySelectorAll('form[aria-busy]').forEach((form) => form.removeAttribute('aria-busy'));
});

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-menu-toggle]');
    if (toggle) {
        const menu = document.getElementById(toggle.dataset.menuToggle);
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        menu?.classList.toggle('hidden', expanded);
        toggle.setAttribute('aria-expanded', String(!expanded));
        return;
    }
    if (event.target.closest('#main-menu a') || !event.target.closest('header')) {
        closeMainMenu();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        const toggle = document.querySelector('[data-menu-toggle][aria-expanded="true"]');
        closeMainMenu();
        toggle?.focus();
    }
});

document.querySelectorAll('[data-money]').forEach((input) => {
    const formatMoney = () => {
        const negative = input.value.includes('-');
        const digits = input.value.replace(/\D/g, '');
        input.value = (negative ? '-' : '') + (digits === '' ? '' : Number(digits).toLocaleString('vi-VN'));
    };
    input.addEventListener('input', formatMoney);
    formatMoney();
});

const tourFilters = document.getElementById('tour-filters');
if (tourFilters) {
    const minPrice = tourFilters.querySelector('[name="price_min"]');
    const maxPrice = tourFilters.querySelector('[name="price_max"]');
    const validatePrices = () => {
        minPrice.setCustomValidity(minPrice.value.includes('-') ? 'Giá từ không được là số âm.' : '');
        maxPrice.setCustomValidity(maxPrice.value.includes('-') ? 'Giá đến không được là số âm.' : '');
    };
    [minPrice, maxPrice].forEach((input) => input.addEventListener('input', validatePrices));
    tourFilters.addEventListener('submit', (event) => {
        validatePrices();
        if (!tourFilters.reportValidity()) { event.preventDefault(); }
    });
    validatePrices();
}

document.querySelector('[data-sort-tours]')?.addEventListener('change', (event) => {
    event.target.form?.requestSubmit();
});

document.querySelector('[data-expand-itinerary]')?.addEventListener('click', (event) => {
    const days = [...document.querySelectorAll('[data-itinerary] details')];
    const shouldOpen = days.some((day) => !day.open);
    days.forEach((day) => { day.open = shouldOpen; });
    const button = event.currentTarget;
    button.firstChild.textContent = shouldOpen ? 'Thu gọn tất cả ' : 'Mở tất cả ';
});

const bookingForm = document.querySelector('[data-booking-form]');
if (bookingForm) {
    const passengers = document.getElementById('passengers');
    const template = document.getElementById('passenger-template');
    const addButton = document.getElementById('add-passenger');
    const maximum = Number(bookingForm.dataset.maxPassengers);
    const adultPrice = Number(bookingForm.dataset.adultPrice);
    const childPrice = Number(bookingForm.dataset.childPrice);
    const couponInput = document.getElementById('coupon-code');
    const applyCouponButton = document.getElementById('apply-coupon');
    const couponMessage = document.getElementById('coupon-message');
    const discountRow = document.querySelector('[data-discount-row]');
    let discount = 0;
    let couponRequestVersion = 0;
    const money = (value) => value.toLocaleString('vi-VN') + '₫';
    const setText = (selector, value) => {
        const element = document.querySelector(selector);
        if (element) { element.textContent = value; }
    };
    const updateSummary = () => {
        const types = [...passengers.querySelectorAll('[data-name="passenger_type"]')];
        [...passengers.children].forEach((row) => {
            const isChild = row.querySelector('[data-name="passenger_type"]').value === 'child';
            const date = row.querySelector('[data-name="date_of_birth"]');
            const identity = row.querySelector('[data-name="id_card"]');
            date.required = isChild;
            if (isChild) { date.min = date.dataset.childMin; } else { date.removeAttribute('min'); }
            identity.required = !isChild;
        });
        const adults = types.filter((input) => input.value === 'adult').length;
        const children = types.filter((input) => input.value === 'child').length;
        setText('[data-adult-count]', adults);
        setText('[data-child-count]', children);
        setText('[data-adult-total]', money(adults * adultPrice));
        setText('[data-child-total]', money(children * childPrice));
        setText('[data-booking-total]', money(Math.max(0, adults * adultPrice + children * childPrice - discount)));
        const expected = bookingForm.querySelector('[name="expected_total"]');
        if (expected) { expected.value = Math.max(0, adults * adultPrice + children * childPrice - discount); }
    };
    const resetCoupon = (message = '') => {
        discount = 0;
        couponRequestVersion++;
        discountRow?.classList.add('hidden');
        discountRow?.classList.remove('flex');
        if (couponMessage) {
            couponMessage.textContent = message;
            couponMessage.classList.remove('text-sage', 'text-red-700');
        }
        if (applyCouponButton) { applyCouponButton.disabled = false; }
    };
    const passengerChanges = () => {
        resetCoupon(couponInput?.value.trim() ? 'Hành khách đã thay đổi. Vui lòng áp dụng lại mã giảm giá.' : '');
        updateSummary();
    };
    const renumberPassengers = () => {
        [...passengers.children].forEach((row, index) => {
            row.querySelector('[data-number]').textContent = index + 1;
            row.querySelectorAll('[data-name]').forEach((input) => {
                input.name = 'passengers[' + index + '][' + input.dataset.name + ']';
            });
            row.querySelector('[data-remove]').hidden = passengers.children.length === 1;
        });
        addButton.disabled = passengers.children.length >= maximum;
        passengerChanges();
    };
    addButton.addEventListener('click', () => {
        if (passengers.children.length >= maximum) { return; }
        const row = template.content.cloneNode(true);
        passengers.append(row);
        renumberPassengers();
        passengers.lastElementChild.querySelector('input')?.focus();
    });
    passengers.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove]');
        if (removeButton && passengers.children.length > 1) {
            const row = removeButton.closest('[data-passenger]');
            const nextRow = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            renumberPassengers();
            nextRow?.querySelector('input')?.focus();
        }
    });
    passengers.addEventListener('change', passengerChanges);
    const pickupFields = bookingForm.querySelector('[data-pickup-fields]');
    const updatePickup = () => {
        const doorPickup = bookingForm.querySelector('[name="pickup_type"]:checked')?.value === 'door';
        pickupFields?.classList.toggle('hidden', !doorPickup);
        const address = pickupFields?.querySelector('[name="pickup_address"]');
        if (address) { address.required = doorPickup; }
    };
    bookingForm.querySelectorAll('[name="pickup_type"]').forEach((input) => input.addEventListener('change', updatePickup));
    updatePickup();

    const checkCoupon = async () => {
        resetCoupon();
        updateSummary();
        const code = couponInput.value.trim();
        if (!code) { return; }
        const requestVersion = ++couponRequestVersion;
        const types = [...passengers.querySelectorAll('[data-name="passenger_type"]')];
        applyCouponButton.disabled = true;
        couponMessage.textContent = 'Đang kiểm tra mã giảm giá…';
        try {
            const response = await fetch(bookingForm.dataset.couponUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': bookingForm.querySelector('[name="_token"]').value,
                },
                body: JSON.stringify({
                    departure_id: bookingForm.querySelector('[name="departure_id"]').value,
                    adults: types.filter((input) => input.value === 'adult').length,
                    children: types.filter((input) => input.value === 'child').length,
                    code,
                }),
            });
            const result = await response.json();
            if (requestVersion !== couponRequestVersion) { return; }
            if (!response.ok || !result.valid) {
                couponMessage.textContent = result.message || 'Không thể áp dụng mã giảm giá.';
                couponMessage.classList.add('text-red-700');
                return;
            }
            discount = Number(result.discount);
            setText('[data-discount-amount]', '−' + money(discount));
            setText('[data-applied-coupon]', code.toUpperCase());
            discountRow.classList.remove('hidden');
            discountRow.classList.add('flex');
            couponMessage.textContent = result.message;
            couponMessage.classList.add('text-sage');
            updateSummary();
        } catch {
            if (requestVersion === couponRequestVersion) {
                couponMessage.textContent = 'Không kiểm tra được mã giảm giá. Vui lòng thử lại.';
                couponMessage.classList.add('text-red-700');
            }
        } finally {
            if (requestVersion === couponRequestVersion) { applyCouponButton.disabled = false; }
        }
    };
    if (couponInput && applyCouponButton) {
        applyCouponButton.addEventListener('click', checkCoupon);
        couponInput.addEventListener('input', () => { resetCoupon(); updateSummary(); });
        couponInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') { event.preventDefault(); checkCoupon(); }
        });
        bookingForm.querySelectorAll('[data-coupon-code]').forEach((button) => {
            button.addEventListener('click', () => { couponInput.value = button.dataset.couponCode; checkCoupon(); });
        });
    }
    renumberPassengers();
    if (couponInput?.value.trim()) { checkCoupon(); }
}
