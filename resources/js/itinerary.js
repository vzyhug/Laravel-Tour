const clone = (value) => JSON.parse(JSON.stringify(value));
// Laragon's http://*.test is not a secure context, where randomUUID is absent.
const uuid = () => globalThis.crypto?.randomUUID?.() || '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (digit) =>
    (Number(digit) ^ (globalThis.crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (Number(digit) / 4)))).toString(16));
const el = (tag, className = '', text = '') => {
    const node = document.createElement(tag);
    node.className = className;
    node.textContent = text;
    return node;
};

document.querySelectorAll('[data-personal-itinerary]').forEach((root) => {
    let state = JSON.parse(root.querySelector('[data-itinerary-json]').textContent);
    let items = clone(state.items);
    let dirty = false;
    let busy = false;
    let requestKey = uuid();
    const status = root.querySelector('[data-itinerary-status]');
    const daysNode = root.querySelector('[data-itinerary-days]');
    const saveButton = root.querySelector('[data-itinerary-save]');
    const consultationForm = root.querySelector('[data-itinerary-consult]');
    const message = (text, error = false) => {
        status.textContent = text;
        status.className = `mt-3 min-h-6 text-sm ${error ? 'text-red-700' : 'text-sage'}`;
    };
    const changed = () => {
        dirty = true;
        message('Có thay đổi chưa lưu.');
    };
    const lock = (value) => {
        busy = value;
        root.querySelectorAll('button, input, select, textarea').forEach((control) => { control.disabled = value; });
    };
    const api = async (url, method, data) => {
        const response = await fetch(url, {
            method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || consultationForm?.querySelector('[name="_token"]')?.value || '' },
            body: JSON.stringify(data),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || response.redirected) {
            throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Không lưu được. Phiên đăng nhập có thể đã hết hạn; hãy tải lại trang.');
        }
        return result;
    };
    const renderRequests = () => {
        const container = root.querySelector('[data-itinerary-requests]');
        if (!container) return;
        container.replaceChildren();
        state.consultations.forEach((request) => {
            const card = el('article', 'rounded-lg border border-[#e5d9cf] bg-white p-4');
            const labels = { submitted: 'Đang chờ tư vấn', quoted: 'Đã có báo giá', closed: 'Đã đóng' };
            card.append(el('p', 'font-semibold text-sm', `Yêu cầu #${request.id} · ${labels[request.status] || request.status} · Lịch phiên bản ${request.itinerary_version}`));
            const link = el('a', 'text-link mt-3 inline-block', 'Xem lịch đã gửi và phản hồi');
            link.href = request.url;
            card.append(link);
            container.append(card);
        });
    };
    const render = () => {
        daysNode.replaceChildren();
        state.days.forEach((day) => {
            const section = el('section', 'rounded-lg border border-[#e5d9cf] bg-white p-4');
            section.append(el('h4', 'font-serif text-xl', `Ngày ${day.number} · ${day.title}`));
            const indices = items.map((item, index) => ({ item, index })).filter(({ item }) => Number(item.day_number) === Number(day.number));
            if (!indices.length) section.append(el('p', 'mt-4 text-sm text-muted', 'Kéo hoạt động vào đây hoặc để trống ngày này để nghỉ ngơi.'));
            const list = el('div', 'itinerary-dropzone');
            list.dataset.dayDropzone = String(day.number);
            list.setAttribute('role', 'list');
            list.setAttribute('aria-label', `Hoạt động ngày ${day.number}`);
            section.append(list);
            indices.forEach(({ item, index }) => {
                const place = state.places.find((point) => Number(point.id) === Number(item.place_id));
                const card = el('article', 'overflow-hidden rounded-lg border border-[#e5d9cf] bg-white');
                card.setAttribute('role', 'listitem');
                if (place?.image) {
                    const figure = el('a', 'block focus-visible:ring-2 focus-visible:ring-brand-500');
                    figure.href = place.image.url;
                    figure.target = '_blank';
                    figure.rel = 'noopener';
                    figure.dataset.galleryItem = '';
                    figure.setAttribute('aria-label', `Phóng to ảnh: ${place.name}`);
                    const img = el('img', 'aspect-[16/7] w-full object-cover');
                    img.src = place.image.url;
                    img.alt = place.image.caption;
                    img.loading = 'lazy';
                    img.draggable = false;
                    figure.append(img);
                    card.append(figure);
                }
                const body = el('div', 'p-4');
                body.append(el('h5', 'font-semibold text-sm', item.title));
                const fields = el('div', 'mt-3 grid gap-3 sm:grid-cols-3');
                const field = (label, control) => {
                    const wrapper = el('label');
                    wrapper.append(el('span', 'label', label), control);
                    fields.append(wrapper);
                };
                const daySelect = el('select', 'field');
                state.days.forEach((other) => {
                    const option = el('option', '', `Ngày ${other.number}`);
                    option.value = other.number;
                    option.selected = Number(item.day_number) === Number(other.number);
                    daySelect.append(option);
                });
                daySelect.addEventListener('change', () => { item.day_number = Number(daySelect.value); changed(); render(); });
                field('Ngày tham quan', daySelect);
                const time = el('input', 'field');
                time.type = 'time';
                time.value = item.start_time || '';
                time.addEventListener('input', () => { item.start_time = time.value || null; changed(); });
                field('Giờ dự kiến', time);
                const duration = el('input', 'field');
                duration.type = 'number';
                duration.min = 5;
                duration.max = 720;
                duration.value = item.duration_minutes ?? '';
                duration.placeholder = 'Chưa chọn';
                duration.addEventListener('input', () => { item.duration_minutes = duration.value ? Number(duration.value) : null; changed(); });
                field('Thời lượng (phút)', duration);
                body.append(fields);
                if (!place) {
                    const title = el('input', 'field mt-3');
                    title.value = item.title;
                    title.maxLength = 160;
                    title.setAttribute('aria-label', 'Tên hoạt động tự ghi');
                    title.addEventListener('input', () => { item.title = title.value; changed(); });
                    body.append(title);
                }
                const notesLabel = el('label', 'mt-3 block');
                const notes = el('textarea', 'field');
                notes.rows = 2;
                notes.maxLength = 1000;
                notes.value = item.notes || '';
                notes.addEventListener('input', () => { item.notes = notes.value; changed(); });
                notesLabel.append(el('span', 'label', 'Ghi chú của bạn'), notes);
                body.append(notesLabel);
                const controls = el('div', 'mt-3 flex flex-wrap gap-3');
                const button = (label, action) => {
                    const control = el('button', 'btn-secondary !px-3 !py-2 !text-xs', label);
                    control.type = 'button';
                    control.addEventListener('click', action);
                    controls.append(control);
                };
                button('Bỏ khỏi lịch', () => { items.splice(index, 1); changed(); render(); });
                body.append(controls);
                card.append(body);
                list.append(card);
            });
            daysNode.append(section);
        });
        renderRequests();
    };
    root.querySelector('[data-editor-toggle]').addEventListener('click', (event) => {
        const panel = root.querySelector('#personal-itinerary-panel');
        const expanded = event.currentTarget.getAttribute('aria-expanded') === 'true';
        panel.classList.toggle('hidden', expanded);
        event.currentTarget.setAttribute('aria-expanded', String(!expanded));
    });
    root.querySelector('[data-itinerary-reset]').addEventListener('click', () => { if (busy) return; items = clone(state.defaults); changed(); render(); });
    root.querySelector('[data-itinerary-add]').addEventListener('submit', (event) => {
        event.preventDefault();
        if (busy) return;
        const form = event.currentTarget;
        const point = state.places.find((place) => Number(place.id) === Number(form.elements.place_id.value));
        const title = point?.name || form.elements.custom_title.value.trim();
        if (!title) { message('Nhập tên hoạt động hoặc chọn địa danh.', true); return; }
        items.push({ place_id: point?.id ?? null, day_number: Number(form.elements.day_number.value), title, notes: '', start_time: null, duration_minutes: null });
        form.elements.custom_title.value = '';
        changed(); render();
    });
    saveButton?.addEventListener('click', async () => {
        if (busy) return;
        lock(true); message('Đang lưu lịch trình…');
        try {
            state = await api(state.save_url, 'PUT', { version: state.version, items });
            items = clone(state.items);
            requestKey = uuid();
            dirty = false;
            render();
            message('Đã lưu lịch trình riêng của bạn. Có thể gửi nhân viên tư vấn bên dưới.');
        } catch (error) { message(error.message, true); }
        finally { lock(false); }
    });
    consultationForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) return;
        if (dirty || !state.version) { message('Hãy lưu lịch trình trước khi gửi tư vấn.', true); return; }
        const data = Object.fromEntries(new FormData(consultationForm));
        lock(true); message('Đang gửi yêu cầu tư vấn…');
        try {
            const result = await api(state.consult_url, 'POST', { ...data, version: state.version, request_key: requestKey });
            window.location.assign(result.url);
        } catch (error) { message(error.message, true); lock(false); }
    });
    window.addEventListener('beforeunload', (event) => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
    render();
    if (state.version) message(`Đã tải lịch của bạn · Phiên bản ${state.version}.`);
});
