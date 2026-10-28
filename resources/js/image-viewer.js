const viewer = document.querySelector('[data-image-viewer]');
if (viewer && typeof viewer.showModal === 'function') {
    const image = viewer.querySelector('[data-viewer-image]');
    const caption = viewer.querySelector('[data-viewer-caption]');
    const count = viewer.querySelector('[data-viewer-count]');
    const previous = viewer.querySelector('[data-viewer-prev]');
    const next = viewer.querySelector('[data-viewer-next]');
    let pictures = [];
    let index = 0;
    let trigger = null;
    let previousOverflow = '';
    const show = (position) => {
        index = (position + pictures.length) % pictures.length;
        const link = pictures[index];
        const description = link.querySelector('img')?.alt || 'Ảnh địa danh';
        image.src = link.href;
        image.alt = description;
        caption.textContent = description;
        count.textContent = `Ảnh ${index + 1} / ${pictures.length}`;
        previous.hidden = next.hidden = pictures.length < 2;
    };
    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-gallery-item]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        const group = link.closest('[data-image-gallery]') || link.parentElement;
        pictures = [...group.querySelectorAll('[data-gallery-item]')];
        trigger = link;
        show(pictures.indexOf(link));
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        viewer.showModal();
    });
    viewer.querySelector('[data-viewer-close]').addEventListener('click', () => viewer.close());
    previous.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    viewer.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            show(index + (event.key === 'ArrowLeft' ? -1 : 1));
        }
    });
    viewer.addEventListener('click', (event) => {
        if (event.target === viewer) viewer.close();
    });
    viewer.addEventListener('close', () => {
        document.body.style.overflow = previousOverflow;
        image.removeAttribute('src');
        trigger?.focus({ preventScroll: true });
    });
}
