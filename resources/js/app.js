const closeMainMenu = () => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = toggle && document.getElementById(toggle.dataset.menuToggle);
    menu?.classList.add('hidden');
    toggle?.setAttribute('aria-expanded', 'false');
};

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

document.querySelectorAll('[data-price-range]').forEach((range) => {
    const minimum = range.querySelector('[data-price-min]');
    const maximum = range.querySelector('[data-price-max]');
    const ceiling = Number(maximum.max);
    const update = (changed) => {
        if (Number(minimum.value) > Number(maximum.value)) {
            changed.value = changed === minimum ? maximum.value : minimum.value;
        }
        const min = Number(minimum.value);
        const max = Number(maximum.value);
        range.style.setProperty('--price-start', `${min / ceiling * 100}%`);
        range.style.setProperty('--price-end', `${max / ceiling * 100}%`);
        minimum.style.zIndex = min === ceiling ? '3' : '2';
        for (const [input, value, label] of [[minimum, min, 'min'], [maximum, max, 'max']]) {
            const formatted = value.toLocaleString('vi-VN') + '₫';
            range.querySelector(`[data-price-${label}-label]`).textContent = formatted;
            input.setAttribute('aria-valuetext', formatted);
        }
        if (changed) {
            range.querySelector('[name="price_min"]').value = min || '';
            range.querySelector('[name="price_max"]').value = max === ceiling ? '' : max;
        }
    };
    [minimum, maximum].forEach((input) => {
        input.addEventListener('input', () => update(input));
        input.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowDown', 'ArrowRight', 'ArrowUp'].includes(event.key)) { return; }
            event.preventDefault();
            const direction = ['ArrowLeft', 'ArrowDown'].includes(event.key) ? -1 : 1;
            input.value = Math.max(0, Math.min(ceiling, Number(input.value) + direction * 100000));
            update(input);
        });
    });
    update();
});

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
