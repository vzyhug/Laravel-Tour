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
        message('CĂ³ thay Ä‘á»•i chÆ°a lÆ°u.');
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
            throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'KhĂ´ng lÆ°u Ä‘Æ°á»£c. PhiĂªn Ä‘Äƒng nháº­p cĂ³ thá»ƒ Ä‘Ă£ háº¿t háº¡n; hĂ£y táº£i láº¡i trang.');
        }
        return result;
    };
    const renderRequests = () => {
        const container = root.querySelector('[data-itinerary-requests]');
        if (!container) return;
        container.replaceChildren();
        state.consultations.forEach((request) => {
            const card = el('article', 'rounded-lg border border-[#e5d9cf] bg-white p-4');
            const labels = { submitted: 'Äang chá» tÆ° váº¥n', quoted: 'ÄĂ£ cĂ³ bĂ¡o giĂ¡', closed: 'ÄĂ£ Ä‘Ă³ng' };
            card.append(el('p', 'font-semibold text-sm', `YĂªu cáº§u #${request.id} Â· ${labels[request.status] || request.status} Â· Lá»‹ch phiĂªn báº£n ${request.itinerary_version}`));
            const link = el('a', 'text-link mt-3 inline-block', 'Xem lá»‹ch Ä‘Ă£ gá»­i vĂ  pháº£n há»“i');
            link.href = request.url;
            card.append(link);
            container.append(card);
        });
    };
    const render = () => {
        daysNode.replaceChildren();
        state.days.forEach((day) => {
            const section = el('section', 'rounded-lg border border-[#e5d9cf] bg-white p-4');
            section.append(el('h4', 'font-serif text-xl', `NgĂ y ${day.number} Â· ${day.title}`));
            const indices = items.map((item, index) => ({ item, index })).filter(({ item }) => Number(item.day_number) === Number(day.number));
            if (!indices.length) section.append(el('p', 'mt-4 text-sm text-muted', 'KĂ©o hoáº¡t Ä‘á»™ng vĂ o Ä‘Ă¢y hoáº·c Ä‘á»ƒ trá»‘ng ngĂ y nĂ y Ä‘á»ƒ nghá»‰ ngÆ¡i.'));
            const list = el('div', 'itinerary-dropzone');
            list.dataset.dayDropzone = String(day.number);
            list.setAttribute('role', 'list');
            list.setAttribute('aria-label', `Hoáº¡t Ä‘á»™ng ngĂ y ${day.number}`);
            section.append(list);
            indices.forEach(({ item, index }) => {
                const place = state.places.find((point) => Number(point.id) === Number(item.place_id));
                const card = el('article', 'overflow-hidden rounded-lg border border-[#e5d9cf] bg-white');
                card.setAttribute('role', 'listitem');
                if (place?.image) {
                    const figure = el('figure');
                    const img = el('img', 'aspect-[16/7] w-full object-cover');
                    img.src = place.image.url;
                    img.alt = place.image.caption;
                    img.loading = 'lazy';
                    img.draggable = false;
                    const caption = el('figcaption', 'p-3 text-[10px] leading-5 text-muted');
                    caption.append(document.createTextNode(`${place.image.caption} Â· ${place.image.author} Â· `));
                    const source = el('a', 'underline', place.image.license);
                    source.href = place.image.source_url;
                    source.target = '_blank';
                    source.rel = 'noopener noreferrer';
                    caption.append(source);
                    figure.append(img, caption);
                    card.append(figure);
                }
                const body = el('div', 'p-4');
                body.append(el('h5', 'font-semibold text-sm', item.title));
                if (place) {
                    const source = el('a', 'mt-2 inline-block text-[10px] text-brand-600 underline', `Nguá»“n Ä‘á»‹a danh Â· Äá»‘i chiáº¿u ${place.verified_on}`);
                    source.href = place.source_url;
                    source.target = '_blank';
                    source.rel = 'noopener noreferrer';
                    body.append(source);
                }
                const fields = el('div', 'mt-3 grid gap-3 sm:grid-cols-3');
                const field = (label, control) => {
                    const wrapper = el('label');
                    wrapper.append(el('span', 'label', label), control);
                    fields.append(wrapper);
                };
                const daySelect = el('select', 'field');
                state.days.forEach((other) => {
                    const option = el('option', '', `NgĂ y ${other.number}`);
                    option.value = other.number;
                    option.selected = Number(item.day_number) === Number(other.number);
                    daySelect.append(option);
                });
                daySelect.addEventListener('change', () => { item.day_number = Number(daySelect.value); changed(); render(); });
                field('NgĂ y tham quan', daySelect);
                const time = el('input', 'field');
                time.type = 'time';
                time.value = item.start_time || '';
                time.addEventListener('input', () => { item.start_time = time.value || null; changed(); });
                field('Giá» dá»± kiáº¿n', time);
                const duration = el('input', 'field');
                duration.type = 'number';
                duration.min = 5;
                duration.max = 720;
                duration.value = item.duration_minutes ?? '';
                duration.placeholder = 'ChÆ°a chá»n';
                duration.addEventListener('input', () => { item.duration_minutes = duration.value ? Number(duration.value) : null; changed(); });
                field('Thá»i lÆ°á»£ng (phĂºt)', duration);
                body.append(fields);
                if (!place) {
                    const title = el('input', 'field mt-3');
                    title.value = item.title;
                    title.maxLength = 160;
                    title.setAttribute('aria-label', 'TĂªn hoáº¡t Ä‘á»™ng tá»± ghi');
                    title.addEventListener('input', () => { item.title = title.value; changed(); });
                    body.append(title);
                }
                const notesLabel = el('label', 'mt-3 block');
                const notes = el('textarea', 'field');
                notes.rows = 2;
                notes.maxLength = 1000;
                notes.value = item.notes || '';
                notes.addEventListener('input', () => { item.notes = notes.value; changed(); });
                notesLabel.append(el('span', 'label', 'Ghi chĂº cá»§a báº¡n'), notes);
                body.append(notesLabel);
                const controls = el('div', 'mt-3 flex flex-wrap gap-3');
                const button = (label, action) => {
                    const control = el('button', 'btn-secondary !px-3 !py-2 !text-xs', label);
                    control.type = 'button';
                    control.addEventListener('click', action);
                    controls.append(control);
                };
                button('Bá» khá»i lá»‹ch', () => { items.splice(index, 1); changed(); render(); });
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
        if (!title) { message('Nháº­p tĂªn hoáº¡t Ä‘á»™ng hoáº·c chá»n Ä‘á»‹a danh.', true); return; }
        items.push({ place_id: point?.id ?? null, day_number: Number(form.elements.day_number.value), title, notes: '', start_time: null, duration_minutes: null });
        form.elements.custom_title.value = '';
        changed(); render();
    });
    saveButton?.addEventListener('click', async () => {
        if (busy) return;
        lock(true); message('Äang lÆ°u lá»‹ch trĂ¬nhâ€¦');
        try {
            state = await api(state.save_url, 'PUT', { version: state.version, items });
            items = clone(state.items);
            requestKey = uuid();
            dirty = false;
            render();
            message('ÄĂ£ lÆ°u lá»‹ch trĂ¬nh riĂªng cá»§a báº¡n. CĂ³ thá»ƒ gá»­i nhĂ¢n viĂªn tÆ° váº¥n bĂªn dÆ°á»›i.');
        } catch (error) { message(error.message, true); }
        finally { lock(false); }
    });
    consultationForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) return;
        if (dirty || !state.version) { message('HĂ£y lÆ°u lá»‹ch trĂ¬nh trÆ°á»›c khi gá»­i tÆ° váº¥n.', true); return; }
        const data = Object.fromEntries(new FormData(consultationForm));
        lock(true); message('Äang gá»­i yĂªu cáº§u tÆ° váº¥nâ€¦');
        try {
            const result = await api(state.consult_url, 'POST', { ...data, version: state.version, request_key: requestKey });
            window.location.assign(result.url);
        } catch (error) { message(error.message, true); lock(false); }
    });
    window.addEventListener('beforeunload', (event) => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
    render();
    if (state.version) message(`ÄĂ£ táº£i lá»‹ch cá»§a báº¡n Â· PhiĂªn báº£n ${state.version}.`);
});
