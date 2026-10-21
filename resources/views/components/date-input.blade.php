@props(['name', 'label', 'value' => null])

@php
    $raw = old($name, $value);
    [$d, $m, $y] = ['', '', ''];

    if (is_string($raw) && preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $raw, $p)) {
        [$d, $m, $y] = [$p[3], $p[2], $p[1]];
    } elseif (is_string($raw) && preg_match('#^(\d{0,2})/(\d{0,2})/(\d{0,4})$#', $raw, $p)) {
        [$d, $m, $y] = [$p[1], $p[2], $p[3]];
    }

    $partStyle = 'border:0;outline:0;background:transparent;text-align:center;padding:0.6rem 0;';
@endphp

<div data-date-field>
    <span class="label">{{ $label }}</span>

    <div class="field flex items-center gap-1" style="padding-top:0;padding-bottom:0;">
        <input type="text" data-part="d" inputmode="numeric" maxlength="2" placeholder="dd" autocomplete="off"
               value="{{ $d }}" style="{{ $partStyle }}width:2.6rem;">
        <span class="text-slate-400">/</span>
        <input type="text" data-part="m" inputmode="numeric" maxlength="2" placeholder="mm" autocomplete="off"
               value="{{ $m }}" style="{{ $partStyle }}width:2.6rem;">
        <span class="text-slate-400">/</span>
        <input type="text" data-part="y" inputmode="numeric" maxlength="4" placeholder="yyyy" autocomplete="off"
               value="{{ $y }}" style="{{ $partStyle }}width:4.2rem;">

        <button type="button" data-date-open title="Chọn từ lịch"
                style="margin-left:auto;background:none;border:0;cursor:pointer;font-size:1.1rem;">📅</button>
        <input type="date" data-date-picker tabindex="-1" aria-hidden="true"
               style="position:absolute;opacity:0;pointer-events:none;width:0;height:0;">
    </div>

    <input type="hidden" name="{{ $name }}" data-date-value value="">

    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

@once
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-date-field]').forEach(box => {
            const parts = [...box.querySelectorAll('[data-part]')];
            const hidden = box.querySelector('[data-date-value]');
            const picker = box.querySelector('[data-date-picker]');
            const openBtn = box.querySelector('[data-date-open]');
            const limits = { d: 31, m: 12 };

            const sync = () => {
                const [d, m, y] = parts.map(p => p.value);
                hidden.value = (d || m || y) ? `${d}/${m}/${y}` : '';
            };

            const pad = p => {
                if ((p.dataset.part === 'd' || p.dataset.part === 'm') && p.value.length === 1) {
                    p.value = p.value.padStart(2, '0');
                }
            };

            parts.forEach((p, i) => {
                p.addEventListener('input', () => {
                    p.value = p.value.replace(/\D/g, '');
                    const type = p.dataset.part;

                    if (limits[type] && p.value.length === 2 && +p.value > limits[type]) {
                        p.value = String(limits[type]);
                    }
                    if (limits[type] && p.value.length === 1 && +p.value > Math.floor(limits[type] / 10)) {
                        p.value = p.value.padStart(2, '0');
                    }

                    sync();

                    if (p.value.length >= p.maxLength && parts[i + 1]) {
                        parts[i + 1].focus();
                        parts[i + 1].select();
                    }
                });

                p.addEventListener('keydown', e => {
                    if (e.key === '/' || e.key === '.' || e.key === '-') {
                        e.preventDefault();
                        pad(p);
                        sync();
                        parts[i + 1]?.focus();
                    }
                    if (e.key === 'Backspace' && p.value === '' && parts[i - 1]) {
                        e.preventDefault();
                        parts[i - 1].focus();
                    }
                });

                p.addEventListener('focus', () => p.select());
                p.addEventListener('blur', () => { pad(p); sync(); });
            });

            openBtn.addEventListener('click', () => {
                const [d, m, y] = parts.map(p => p.value);
                if (d && m && y.length === 4) {
                    picker.value = `${y}-${m.padStart(2, '0')}-${d.padStart(2, '0')}`;
                }
                try { picker.showPicker(); } catch (e) { picker.focus(); }
            });

            picker.addEventListener('change', () => {
                const [y, m, d] = picker.value.split('-');
                if (y && m && d) {
                    parts[0].value = d;
                    parts[1].value = m;
                    parts[2].value = y;
                    sync();
                }
            });

            sync();
        });
    });
</script>
@endonce
