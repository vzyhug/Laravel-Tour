import './itinerary';
import Sortable from 'sortablejs';

document.querySelectorAll('[data-auto-filter]').forEach((form) => {
    form.addEventListener('keydown', (event) => {
        if (event.target.matches('input[name="q"]') && event.key === 'Enter') {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    form.addEventListener('change', (event) => {
        if (event.target.matches('input[name="q"]') || event.target.matches('input[type="date"], select')) {
            form.requestSubmit();
        }
    });
});

const flashAlert = document.querySelector('[data-flash-alert]');
if (flashAlert instanceof HTMLDialogElement) {
    flashAlert.showModal();
    flashAlert.querySelectorAll('[data-flash-close]').forEach((button) => {
        button.addEventListener('click', () => flashAlert.close());
    });
    flashAlert.addEventListener('click', (event) => {
        if (event.target === flashAlert) flashAlert.close();
    });
}

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
        if (/[^0-9.,\s-]/u.test(input.value)) { return; }
        const negative = input.value.includes('-');
        const digits = input.value.replace(/\D/g, '');
        input.value = (negative ? '-' : '') + (digits === '' ? '' : Number(digits).toLocaleString('vi-VN'));
    };
    input.addEventListener('input', formatMoney);
    formatMoney();
});

const tourFilters = document.getElementById('tour-filters');
if (tourFilters) {
    const province = tourFilters.querySelector('[name="province"]');
    const region = tourFilters.querySelector('[name="region"]');
    const regionHelp = document.getElementById('province-region-help');
    const syncRegion = (provinceChanged = false) => {
        const selectedProvince = province.selectedOptions[0];
        const hasProvince = Boolean(selectedProvince?.value);
        region.disabled = hasProvince;
        if (hasProvince) {
            region.value = selectedProvince.dataset.region;
        } else if (provinceChanged) {
            region.value = '';
        }
        regionHelp.hidden = !hasProvince;
        if (provinceChanged) {
            tourFilters.querySelector('[data-region-error]')?.setAttribute('hidden', '');
        }
    };
    province.addEventListener('change', () => syncRegion(true));
    window.addEventListener('pageshow', () => syncRegion());
    syncRegion();

    const minPrice = tourFilters.querySelector('[name="price_min"]');
    const maxPrice = tourFilters.querySelector('[name="price_max"]');
    const validatePrices = () => {
        const readPrice = (input, label) => {
            const raw = input.value.trim();
            const digits = raw.replace(/[.,\s]/gu, '');
            if (raw.includes('-')) return { error: `${label} không được là số âm.` };
            if (raw && !/^[0-9][0-9.,\s]*$/u.test(raw)) return { error: `${label} phải là số tiền nguyên, ví dụ 5.000.000.` };
            if (digits.replace(/^0+/, '').length > 13) return { error: `${label} không được vượt quá 9.999.999.999.999₫.` };
            return { value: raw === '' ? null : Number(digits), error: '' };
        };
        const min = readPrice(minPrice, 'Giá từ');
        const max = readPrice(maxPrice, 'Giá đến');
        if (!min.error && !max.error && min.value !== null && max.value !== null && min.value > max.value) {
            max.error = 'Giá đến phải lớn hơn hoặc bằng giá từ.';
        }
        [[minPrice, min.error], [maxPrice, max.error]].forEach(([input, error]) => {
            input.setCustomValidity(error);
            input.setAttribute('aria-invalid', String(Boolean(error)));
            input.classList.toggle('border-red-500', Boolean(error));
            const feedback = document.getElementById(`${input.name}-error`);
            feedback.textContent = error;
            feedback.hidden = !error;
        });
    };
    [minPrice, maxPrice].forEach((input) => input.addEventListener('input', validatePrices));
    tourFilters.addEventListener('submit', (event) => {
        syncRegion();
        validatePrices();
        if (!tourFilters.reportValidity()) { event.preventDefault(); }
    });
    validatePrices();
}

document.querySelector('[data-sort-tours]')?.addEventListener('change', (event) => {
    event.target.form?.requestSubmit();
});

const hotelStayForm = document.querySelector('[data-hotel-search]');
if (hotelStayForm) {
    const checkIn = hotelStayForm.querySelector('[data-hotel-check-in]');
    const checkOut = hotelStayForm.querySelector('[data-hotel-check-out]');
    const stayLength = hotelStayForm.querySelector('[data-hotel-stay-length]');
    const submitButton = hotelStayForm.querySelector('[data-hotel-search-submit]');
    const nextDay = (value) => {
        if (!value) return '';
        const date = new Date(`${value}T00:00:00`);
        date.setDate(date.getDate() + 1);
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    };
    const updateStay = (adjustCheckout = false) => {
        const minimumCheckout = nextDay(checkIn.value);
        checkOut.min = minimumCheckout;
        if (adjustCheckout && minimumCheckout && (!checkOut.value || checkOut.value < minimumCheckout)) {
            checkOut.value = minimumCheckout;
        }
        if (!stayLength) return;
        if (!checkIn.value || !checkOut.value || checkOut.value <= checkIn.value) {
            stayLength.textContent = 'Chọn ngày nhận và trả phòng để xem giá theo từng đêm.';
            return;
        }
        const start = new Date(`${checkIn.value}T00:00:00`);
        const end = new Date(`${checkOut.value}T00:00:00`);
        const nights = Math.round((end - start) / 86400000);
        stayLength.textContent = `${nights} đêm lưu trú · Giá tính theo từng đêm`;
    };
    checkIn.addEventListener('change', () => updateStay(true));
    checkOut.addEventListener('change', () => updateStay());
    hotelStayForm.addEventListener('submit', (event) => {
        updateStay();
        if (checkIn.value && checkOut.value && checkOut.value <= checkIn.value) {
            event.preventDefault();
            checkOut.setCustomValidity('Ngày trả phòng cần sau ngày nhận phòng.');
            checkOut.reportValidity();
            checkOut.addEventListener('change', () => checkOut.setCustomValidity(''), { once: true });
            return;
        }
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="hotel-spinner" aria-hidden="true"></span> Đang tìm phòng…';
        }
    });
    updateStay();
}

document.querySelectorAll('[data-hotel-gallery]').forEach((gallery) => {
    const mainImage = gallery.querySelector('[data-gallery-main]');
    const caption = gallery.querySelector('[data-gallery-caption]');
    if (!mainImage) return;
    gallery.querySelectorAll('[data-gallery-pick]').forEach((button) => {
        button.addEventListener('click', () => {
            mainImage.src = button.dataset.src;
            mainImage.alt = button.dataset.alt || '';
            if (caption) caption.textContent = button.dataset.caption || '';
            gallery.querySelectorAll('[data-gallery-pick]').forEach((thumb) => thumb.setAttribute('aria-pressed', String(thumb === button)));
        });
    });
});

document.querySelector('[data-expand-itinerary]')?.addEventListener('click', (event) => {
    const days = [...document.querySelectorAll('[data-itinerary] details')];
    const shouldOpen = days.some((day) => !day.open);
    days.forEach((day) => { day.open = shouldOpen; });
    const button = event.currentTarget;
    button.firstChild.textContent = shouldOpen ? 'Thu gọn tất cả ' : 'Mở tất cả ';
});

const tourItinerary = document.querySelector('[data-itinerary]');
if (tourItinerary) {
    const renumberDays = () => {
        [...tourItinerary.querySelectorAll('[data-itinerary-day]')].forEach((day, index) => {
            const number = index + 1;
            day.querySelector('[data-day-ordinal]').textContent = String(number).padStart(2, '0');
            day.querySelector('[data-day-label]').textContent = `Ngày ${number}`;
            day.querySelector('[data-day-drag-handle]').setAttribute('aria-label', `Kéo để sắp xếp Ngày ${number}`);
        });
    };
    Sortable.create(tourItinerary, {
        draggable: '[data-itinerary-day]',
        handle: '[data-day-drag-handle]',
        animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 180,
        ghostClass: 'itinerary-drag-ghost',
        chosenClass: 'itinerary-drag-chosen',
        delay: 120,
        delayOnTouchOnly: true,
        touchStartThreshold: 5,
        scrollSensitivity: 80,
        scrollSpeed: 12,
        onEnd: () => { renumberDays(); window.dispatchEvent(new Event('itinerary:order-changed')); },
    });
    const renumberPlaces = (day) => {
        const names = [...day.querySelectorAll('[data-day-place-list] [data-itinerary-place]')]
            .map((place) => place.dataset.placeName)
            .filter(Boolean);
        const label = day.querySelector('[data-day-places]');
        if (label) label.textContent = names.join(' - ') || '—';
    };
    tourItinerary.querySelectorAll('[data-day-place-list]').forEach((list) => {
        Sortable.create(list, {
            draggable: '[data-itinerary-place]',
            handle: '[data-place-drag-handle]',
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 180,
            ghostClass: 'place-drag-ghost',
            chosenClass: 'place-drag-chosen',
            delay: 120,
            delayOnTouchOnly: true,
            touchStartThreshold: 5,
            scrollSensitivity: 80,
            scrollSpeed: 12,
            onEnd: () => { renumberPlaces(list.closest('[data-itinerary-day]')); window.dispatchEvent(new Event('itinerary:order-changed')); },
        });
    });
    tourItinerary.addEventListener('keydown', (event) => {
        if (!event.altKey || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
        const place = event.target.closest('[data-itinerary-place]');
        if (place && event.target.closest('[data-place-drag-handle]')) {
            const siblingPlace = event.key === 'ArrowUp' ? place.previousElementSibling : place.nextElementSibling;
            if (!siblingPlace?.matches('[data-itinerary-place]')) return;
            event.preventDefault();
            if (event.key === 'ArrowUp') siblingPlace.before(place); else siblingPlace.after(place);
            renumberPlaces(place.closest('[data-itinerary-day]'));
            window.dispatchEvent(new Event('itinerary:order-changed'));
            place.querySelector('[data-place-drag-handle]')?.focus();
            return;
        }
        const day = event.target.closest('[data-itinerary-day]');
        if (!day) return;
        const sibling = event.key === 'ArrowUp' ? day.previousElementSibling : day.nextElementSibling;
        if (!sibling?.matches('[data-itinerary-day]')) return;
        event.preventDefault();
        if (event.key === 'ArrowUp') sibling.before(day); else sibling.after(day);
        renumberDays();
        window.dispatchEvent(new Event('itinerary:order-changed'));
        day.querySelector('[data-day-drag-handle]')?.focus();
    });
    tourItinerary.addEventListener('click', (event) => {
        if (event.target.closest('[data-day-drag-handle]')) event.preventDefault();
    });
}

const itineraryMapRoot = document.querySelector('[data-tour-route-map]');
if (itineraryMapRoot && tourItinerary) {
    Promise.all([
        import('leaflet'),
        import('@maplibre/maplibre-gl-leaflet'),
        import('maplibre-gl'),
        import('maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'),
        import('leaflet/dist/leaflet.css'),
        import('maplibre-gl/dist/maplibre-gl.css'),
    ]).then(([leafletModule, leafletMapLibreModule, mapLibreModule, workerModule]) => {
    const L = leafletModule.default;
    const { maplibreGL } = leafletMapLibreModule;
    mapLibreModule.setWorkerUrl(workerModule.default);
    const mapCanvas = itineraryMapRoot.querySelector('[data-map-canvas]');
    const filterBar = itineraryMapRoot.querySelector('[data-map-filters]');
    const routeList = itineraryMapRoot.querySelector('[data-map-route-list]');
    const status = itineraryMapRoot.querySelector('[data-map-status]');
    const dayCount = itineraryMapRoot.querySelector('[data-map-days]');
    const stopCount = itineraryMapRoot.querySelector('[data-map-stops]');
    const poiStatus = itineraryMapRoot.querySelector('[data-map-poi-status]');
    const poiButton = itineraryMapRoot.querySelector('[data-map-search-pois]');
    const map = L.map(mapCanvas, { scrollWheelZoom: false }).setView([16.2, 106.8], 5.4);
    const baseMap = maplibreGL({ style: 'https://tiles.openfreemap.org/styles/bright' }).addTo(map);
    const vectorMap = baseMap.getMaplibreMap();
    const hideNonVietnameseLabels = () => {
        const poiLayerIds = new Set(['poi_r20', 'poi_r7', 'poi_r1']);
        vectorMap.getStyle().layers
            .filter((layer) => layer.type === 'symbol')
            .forEach((layer) => {
                vectorMap.setLayoutProperty(layer.id, 'visibility', poiLayerIds.has(layer.id) ? 'visible' : 'none');
                if (!poiLayerIds.has(layer.id)) return;
                vectorMap.setFilter(layer.id, [
                    'all',
                    layer.filter || ['has', 'class'],
                    ['match', ['get', 'class'], ['cafe', 'restaurant', 'fast_food', 'food_court', 'bar', 'pub'], true, false],
                ]);
                vectorMap.setLayoutProperty(layer.id, 'text-field', [
                    'coalesce', ['get', 'name:vi'], ['get', 'name:latin'], ['get', 'name_en'],
                ]);
                vectorMap.setLayerZoomRange(layer.id, 13.5, 24);
            });
    };
    if (vectorMap.isStyleLoaded()) hideNonVietnameseLabels();
    else vectorMap.once('style.load', hideNonVietnameseLabels);

    const islandIcon = (html) => L.divIcon({ className: 'island-map-label-wrap', html, iconSize: [0, 0] });
    const islands = [
        { name: 'Ho&agrave;ng Sa', point: [16.5, 112.0] },
        { name: 'Tr&#x1B0;&#x1EDD;ng Sa', point: [9.0, 112.0] },
    ].map((island) => L.marker(island.point, { icon: islandIcon(`<span class="island-map-label">${island.name}</span>`), keyboard: false, interactive: false, zIndexOffset: 1000 }).addTo(map));

    const palette = ['#b9472f', '#1f7463', '#a66b13', '#6752a3', '#147b9d', '#8e385f'];
    let activeFilter = 'all';
    let hasInitializedBounds = false;
    let renderedRouteLayers = [];
    let routeRenderVersion = 0;
    const roadRouteCache = new Map();
    const poiLayer = L.layerGroup().addTo(map);
    let mapPoiResults = [];
    const clearPoiResults = () => {
        poiLayer.clearLayers();
        mapPoiResults = [];
        poiStatus.textContent = 'K\u1EBFt qu\u1EA3 SerpApi \u0111\u01B0\u1EE3c l\u01B0u t\u1EA1m 1 gi\u1EDD.';
    };
    const islandsButton = itineraryMapRoot.querySelector('[data-map-islands]');
    islandsButton?.addEventListener('click', () => map.fitBounds(L.latLngBounds([[8.5, 110.0], [17.0, 114.0]]).pad(0.1)));
    const foodCategoryLabel = (type) => {
        if (/coffee|cafe/i.test(type)) return 'Qu\u00E1n c\u00E0 ph\u00EA';
        if (/bakery|dessert|tea/i.test(type)) return 'Ti\u1EC7m b\u00E1nh & \u0111\u1ED3 ng\u1ECDt';
        if (/bar|pub/i.test(type)) return 'Qu\u00E1n bar';
        if (/fast food/i.test(type)) return '\u0110\u1ED3 \u0103n nhanh';
        return 'Nh\u00E0 h\u00E0ng';
    };
    const renderPoiList = () => {
        routeList.querySelector('[data-map-poi-list]')?.remove();
        if (!mapPoiResults.length) return;
        const section = document.createElement('section');
        section.className = 'map-poi-list';
        section.dataset.mapPoiList = '';
        const heading = document.createElement('div');
        heading.className = 'map-route-panel-heading';
        heading.innerHTML = '<span>SERPAPI \u00B7 GOOGLE MAPS</span><strong>Qu\u00E1n quanh h\u00E0nh tr\u00ECnh</strong>';
        section.append(heading);
        mapPoiResults.forEach((poi, index) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'map-poi-result';
            const number = document.createElement('i');
            number.textContent = String(index + 1);
            const details = document.createElement('span');
            const name = document.createElement('strong');
            name.textContent = poi.name;
            const type = document.createElement('small');
            type.textContent = `${foodCategoryLabel(poi.type)}${poi.rating ? ` \u00B7 \u2605 ${poi.rating.toFixed(1)}` : ''}${poi.distance_m ? ` \u00B7 ${(poi.distance_m / 1000).toFixed(1)} km` : ''}`;
            details.append(name, type);
            item.append(number, details);
            item.addEventListener('click', () => {
                const point = [poi.latitude, poi.longitude];
                map.setView(point, Math.max(map.getZoom(), 14));
                poiLayer.eachLayer((marker) => {
                    if (marker.options.poiId === poi.id) marker.openPopup();
                });
            });
            section.append(item);
        });
        routeList.append(section);
    };
    const searchNearbyPlaces = async () => {
        const days = [...tourItinerary.querySelectorAll('[data-itinerary-day]')];
        const selectedDays = activeFilter === 'all' ? days : [days[Number(activeFilter)]].filter(Boolean);
        const points = selectedDays.flatMap((day) => [...day.querySelectorAll('[data-itinerary-place]')]
            .map((stop) => [Number(stop.dataset.latitude), Number(stop.dataset.longitude)])
            .filter((point) => Number.isFinite(point[0]) && Number.isFinite(point[1]) && point[0] !== 0 && point[1] !== 0));
        if (!points.length) {
            poiStatus.textContent = 'L\u1ECBch tr\u00ECnh ch\u01B0a c\u00F3 t\u1ECDa \u0111\u1ED9 \u0111\u1EC3 t\u00ECm quanh.';
            return;
        }

        poiButton.disabled = true;
        poiButton.textContent = '\u0110ang t\u00ECm qu\u00E1n g\u1EA7n \u0111\u00E2y\u2026';
        poiStatus.textContent = 'SerpApi \u0111ang t\u00ECm \u0111\u1ECBa \u0111i\u1EC3m quanh c\u00E1c \u0111i\u1EC3m d\u1EEBng.';
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const response = await fetch(itineraryMapRoot.dataset.mapPoiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
                body: JSON.stringify({ points }),
            });
            if (!response.ok) throw new Error('SerpApi search failed.');
            const data = await response.json();
            poiLayer.clearLayers();
            mapPoiResults = data.places || [];
            mapPoiResults.forEach((poi, index) => {
                const glyph = /coffee|cafe/i.test(poi.type) ? '\u2615' : '\u{1F37D}';
                const icon = L.divIcon({ className: 'map-poi-marker-wrap', html: `<span class="map-poi-marker">${glyph}</span>`, iconSize: [32, 32], iconAnchor: [16, 16] });
                const marker = L.marker([poi.latitude, poi.longitude], { icon, title: poi.name, alt: poi.name });
                marker.options.poiId = poi.id;
                const tooltip = document.createElement('span');
                tooltip.textContent = poi.name;
                marker.bindTooltip(tooltip, { permanent: true, direction: 'top', offset: [0, -13], className: 'map-poi-name' });
                const popup = document.createElement('div');
                popup.className = 'map-poi-popup';
                const title = document.createElement('strong');
                title.textContent = poi.name;
                const category = document.createElement('p');
                category.textContent = foodCategoryLabel(poi.type);
                const address = document.createElement('p');
                address.textContent = poi.address || '';
                const rating = document.createElement('p');
                rating.textContent = poi.rating ? `\u2605 ${poi.rating.toFixed(1)}${poi.reviews ? ` (${poi.reviews.toLocaleString('vi-VN')} \u0111\u00E1nh gi\u00E1)` : ''}` : '';
                popup.append(title, category, address, rating);
                marker.bindPopup(popup).addTo(poiLayer);
            });
            renderPoiList();
            poiStatus.textContent = mapPoiResults.length
                ? `${mapPoiResults.length} qu\u00E1n/\u0111\u1ECBa \u0111i\u1EC3m t\u00ECm th\u1EA5y. K\u1EBFt qu\u1EA3 \u0111\u01B0\u1EE3c l\u01B0u t\u1EA1m 1 gi\u1EDD.`
                : 'SerpApi ch\u01B0a t\u00ECm th\u1EA5y qu\u00E1n g\u1EA7n c\u00E1c \u0111i\u1EC3m n\u00E0y.';
        } catch {
            poiStatus.textContent = 'Kh\u00F4ng t\u1EA3i \u0111\u01B0\u1EE3c \u0111\u1ECBa \u0111i\u1EC3m. H\u00E3y th\u1EED l\u1EA1i sau.';
        } finally {
            poiButton.disabled = false;
            poiButton.innerHTML = '&#x2315; T\u00ECm l\u1EA1i qu\u00E1n quanh \u0111\u00E2y';
        }
    };
    poiButton?.addEventListener('click', searchNearbyPlaces);
    if (poiButton && 'IntersectionObserver' in window) {
        const poiObserver = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return;
            poiObserver.disconnect();
            searchNearbyPlaces();
        }, { threshold: 0.2 });
        poiObserver.observe(itineraryMapRoot);
    }
    const getRoadRoute = (points) => {
        const cacheKey = JSON.stringify(points);
        if (!roadRouteCache.has(cacheKey)) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const request = fetch(itineraryMapRoot.dataset.roadRouteUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
                body: JSON.stringify({ points }),
            }).then(async (response) => {
                if (!response.ok) throw new Error('Road route could not be calculated.');
                const result = await response.json();
                if (!Array.isArray(result.coordinates) || result.coordinates.length < 2) throw new Error('Road route geometry is missing.');
                return result;
            });
            roadRouteCache.set(cacheKey, request);
        }
        return roadRouteCache.get(cacheKey);
    };
    const makePopup = (dayNumber, placeName) => {
        const content = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = `Ng\u00e0y ${dayNumber}: ${placeName}`;
        content.append(title);
        return content;
    };
    const renderFilters = (days) => {
        filterBar.replaceChildren();
        const filters = [{ key: 'all', text: 'T\u1EA5t c\u1EA3 ng\u00e0y' }, ...days.map((day, index) => ({ key: String(index), text: `Ng\u00e0y ${index + 1}` }))];
        filters.forEach(({ key, text }) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = text;
            button.dataset.mapFilter = key;
            button.className = key === activeFilter ? 'map-filter map-filter-active' : 'map-filter';
            button.setAttribute('aria-pressed', String(key === activeFilter));
            button.addEventListener('click', () => {
                if (activeFilter !== key && mapPoiResults.length) clearPoiResults();
                activeFilter = key;
                renderMap();
            });
            filterBar.append(button);
        });
    };
    const renderRouteList = (days) => {
        routeList.replaceChildren();
        const heading = document.createElement('div');
        heading.className = 'map-route-panel-heading';
        heading.innerHTML = '<span>H\u00c0NH TR\u00ccNH C\u1ee6A B\u1ea0N</span><strong>C\u00e1c \u0111i\u1ec3m d\u1eebng</strong>';
        routeList.append(heading);
        days.forEach((day, dayIndex) => {
            const color = palette[dayIndex % palette.length];
            const places = [...day.querySelectorAll('[data-itinerary-place]')];
            const card = document.createElement('button');
            card.type = 'button';
            card.className = activeFilter === String(dayIndex) ? 'map-route-day map-route-day-active' : 'map-route-day';
            card.dataset.routeDay = String(dayIndex);
            card.style.setProperty('--route-color', color);
            card.setAttribute('aria-pressed', String(activeFilter === String(dayIndex)));
            const header = document.createElement('span');
            header.className = 'map-route-day-header';
            const title = document.createElement('strong');
            title.textContent = `Ng\u00e0y ${dayIndex + 1}`;
            const count = document.createElement('small');
            count.textContent = `${places.length} \u0111i\u1ec3m d\u1eebng`;
            header.append(title, count);
            const stops = document.createElement('span');
            stops.className = 'map-route-stops';
            if (places.length) {
                places.forEach((place, placeIndex) => {
                    const stop = document.createElement('span');
                    stop.className = 'map-route-stop';
                    const number = document.createElement('i');
                    number.textContent = String(placeIndex + 1);
                    const name = document.createElement('span');
                    name.textContent = place.dataset.placeName;
                    stop.append(number, name);
                    stops.append(stop);
                });
            } else {
                const empty = document.createElement('span');
                empty.className = 'map-route-empty';
                empty.textContent = 'Ch\u01b0a c\u00f3 \u0111i\u1ec3m d\u1eebng';
                stops.append(empty);
            }
            card.append(header, stops);
            card.addEventListener('click', () => {
                if (activeFilter !== String(dayIndex) && mapPoiResults.length) clearPoiResults();
                activeFilter = String(dayIndex);
                renderMap();
            });
            routeList.append(card);
        });
        renderPoiList();
    };
    const renderMap = () => {
        const renderVersion = ++routeRenderVersion;
        const days = [...tourItinerary.querySelectorAll('[data-itinerary-day]')];
        if (activeFilter !== 'all' && Number(activeFilter) >= days.length) activeFilter = 'all';
        renderFilters(days);
        renderRouteList(days);
        const routeLayers = [];
        const routeBounds = [];
        const allBounds = [];
        const roadRequests = [];
        let missingCoordinates = 0;
        days.forEach((day, dayIndex) => {
            const color = palette[dayIndex % palette.length];
            const group = L.layerGroup();
            const stops = [...day.querySelectorAll('[data-itinerary-place]')];
            const coordinates = [];
            stops.forEach((stop, stopIndex) => {
                const lat = Number(stop.dataset.latitude);
                const lon = Number(stop.dataset.longitude);
                if (!Number.isFinite(lat) || !Number.isFinite(lon) || !stop.dataset.latitude || !stop.dataset.longitude) {
                    missingCoordinates += 1;
                    return;
                }
                const point = [lat, lon];
                coordinates.push(point);
                allBounds.push(point);
                const markerIcon = L.divIcon({
                    className: 'itinerary-route-marker-wrap',
                    html: `<span class="itinerary-route-marker" style="--route-color:${color}">${stopIndex + 1}</span>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15],
                });
                const marker = L.marker(point, { icon: markerIcon, title: stop.dataset.placeName, alt: stop.dataset.placeName });
                const placeLabel = document.createElement('span');
                placeLabel.textContent = stop.dataset.placeName;
                marker.bindTooltip(placeLabel, { permanent: true, direction: 'top', offset: [0, -13], className: 'itinerary-marker-name' });
                marker.bindPopup(makePopup(dayIndex + 1, stop.dataset.placeName));
                group.addLayer(marker);
            });
            if (coordinates.length > 1) {
                roadRequests.push(getRoadRoute(coordinates).then((roadRoute) => {
                    group.addLayer(L.polyline(roadRoute.coordinates, { color, weight: 5, opacity: 0.92, lineCap: 'round', lineJoin: 'round' }));
                    return { ...roadRoute, dayIndex };
                }));
            }
            routeLayers.push(group);
            routeBounds.push(coordinates);
        });
        renderedRouteLayers.forEach((layer) => map.removeLayer(layer));
        renderedRouteLayers = routeLayers;
        routeLayers.forEach((group, dayIndex) => {
            if (activeFilter === 'all' || activeFilter === String(dayIndex)) group.addTo(map);
        });
        const totalStops = days.reduce((count, day) => count + day.querySelectorAll('[data-itinerary-place]').length, 0);
        const mappedStops = totalStops - missingCoordinates;
        dayCount.textContent = String(days.length);
        stopCount.textContent = String(totalStops);
        const locationStatus = missingCoordinates
            ? `${mappedStops} / ${totalStops} \u0111i\u1EC3m \u0111\u00E3 c\u00F3 t\u1ECDa \u0111\u1ED9 ${missingCoordinates} \u0111i\u1EC3m ch\u01B0a hi\u1EC3n tr\u00EAn b\u1EA3n \u0111\u1ED3.`
            : `${totalStops} \u0111i\u1EC3m \u0111\u01B0\u1EE3c hi\u1EC3n theo th\u1EE9 t\u1EF1 trong l\u1ECBch tr\u00ECnh.`;
        status.textContent = roadRequests.length ? `\u0110ang t\u00EDnh tuy\u1EBFn \u0111\u01B0\u1EDDng th\u1EF1c t\u1EBF\u2026 ${locationStatus}` : locationStatus;
        const selectedBounds = activeFilter === 'all' ? allBounds : (routeBounds[Number(activeFilter)] || []);
        if (selectedBounds.length && !hasInitializedBounds) {
            map.fitBounds(L.latLngBounds(selectedBounds).pad(0.2), { maxZoom: 12 });
            hasInitializedBounds = true;
        } else if (activeFilter !== 'all' && selectedBounds.length) {
            map.fitBounds(L.latLngBounds(selectedBounds).pad(0.2), { maxZoom: 12 });
        }
        Promise.allSettled(roadRequests).then((results) => {
            if (renderVersion !== routeRenderVersion) return;
            const successfulRoutes = results.filter((result) => result.status === 'fulfilled').map((result) => result.value);
            const failedRoutes = results.length - successfulRoutes.length;
            const visibleRoutes = activeFilter === 'all'
                ? successfulRoutes
                : successfulRoutes.filter((route) => String(route.dayIndex) === activeFilter);
            const totalDistance = visibleRoutes.reduce((sum, route) => sum + route.distance_m, 0);
            const totalDurationMinutes = Math.round(visibleRoutes.reduce((sum, route) => sum + route.duration_ms, 0) / 60000);
            const durationLabel = totalDurationMinutes >= 60
                ? `${Math.floor(totalDurationMinutes / 60)} gi\u1EDD ${totalDurationMinutes % 60} ph\u00FAt`
                : `${totalDurationMinutes} ph\u00FAt`;
            const routeSummary = visibleRoutes.length
                ? `${visibleRoutes.length} ch\u1EB7ng \u00B7 ${(totalDistance / 1000).toLocaleString('vi-VN', { maximumFractionDigits: 0 })} km \u00B7 kho\u1EA3ng ${durationLabel}`
                : '';
            const fallbackSummary = failedRoutes ? 'GraphHopper ch\u01B0a tr\u1EA3 v\u1EC1 tuy\u1EBFn; b\u1EA3n \u0111\u1ED3 kh\u00F4ng v\u1EBD \u0111\u01B0\u1EDDng \u01B0\u1EDBc l\u01B0\u1EE3ng thay th\u1EBF.' : '';
            status.textContent = [routeSummary, fallbackSummary, locationStatus].filter(Boolean).join(' \u00B7 ');
            const routePoints = visibleRoutes.flatMap((route) => route.coordinates);
            if (routePoints.length && (activeFilter !== 'all' || !hasInitializedBounds)) {
                map.fitBounds(L.latLngBounds(routePoints).pad(0.12), { maxZoom: 11 });
                hasInitializedBounds = true;
            }
        });
    };
    renderMap();
    window.addEventListener('itinerary:order-changed', renderMap);
    }).catch(() => {
        const status = itineraryMapRoot.querySelector('[data-map-status]');
        if (status) status.textContent = 'Bản đồ hiện chưa tải được. Vui lòng tải lại trang.';
    });
}

const bookingForm = document.querySelector('[data-booking-form]');
if (bookingForm) {
    const passengers = document.getElementById('passengers');
    const template = document.getElementById('passenger-template');
    const addButton = document.getElementById('add-passenger');
    const maximum = Number(bookingForm.dataset.maxPassengers);
    const adultPrice = Number(bookingForm.dataset.adultPrice);
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
        const adults = passengers.children.length;
        setText('[data-adult-count]', adults);
        setText('[data-adult-total]', money(adults * adultPrice));
        setText('[data-booking-total]', money(Math.max(0, adults * adultPrice - discount)));
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
                    adults: passengers.children.length,
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
