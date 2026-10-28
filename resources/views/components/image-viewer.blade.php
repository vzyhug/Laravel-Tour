<dialog data-image-viewer aria-labelledby="image-viewer-title" class="image-viewer rounded-2xl border-0 bg-[#171411] p-4 text-white sm:p-6">
    <div class="flex items-start justify-between gap-4">
        <p id="image-viewer-title" class="min-w-0 pt-2 text-sm leading-6" data-viewer-caption></p>
        <button type="button" data-viewer-close class="shrink-0 rounded-full border border-white/30 px-4 py-2 text-sm hover:bg-white/15" aria-label="Đóng ảnh">Đóng ✕</button>
    </div>
    <div class="mt-4 grid min-h-40 place-items-center"><img data-viewer-image alt="" class="max-h-[72dvh] max-w-full object-contain"></div>
    <div class="mt-4 flex items-center justify-between gap-3">
        <button type="button" data-viewer-prev class="rounded-full border border-white/30 px-4 py-2 text-sm hover:bg-white/15" aria-label="Ảnh trước">← Trước</button>
        <p data-viewer-count class="text-xs text-white/80" role="status" aria-live="polite"></p>
        <button type="button" data-viewer-next class="rounded-full border border-white/30 px-4 py-2 text-sm hover:bg-white/15" aria-label="Ảnh tiếp theo">Sau →</button>
    </div>
</dialog>
