const initialized = new WeakSet();

const initialize = (dialog) => {
    if (initialized.has(dialog)) return;
    initialized.add(dialog);
    dialog.querySelectorAll('[data-flash-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
};

document.querySelectorAll('[data-flash-alert]').forEach((dialog) => {
    if (!(dialog instanceof HTMLDialogElement)) return;
    initialize(dialog);
    dialog.showModal();
});

export const showFlashAlert = ({ variant = 'success', title, message }) => {
    if (!['success', 'error', 'warning'].includes(variant)) return;
    let dialog = document.querySelector('[data-dynamic-flash-alert]');
    if (!dialog) {
        const template = document.querySelector('[data-flash-alert-template]');
        if (!template) return;
        dialog = template.content.querySelector('[data-flash-alert]').cloneNode(true);
        dialog.dataset.dynamicFlashAlert = '';
        document.body.append(dialog);
        initialize(dialog);
    }
    document.querySelectorAll('[data-flash-alert][open]').forEach((open) => open.close());
    ['success', 'error', 'warning'].forEach((name) => {
        dialog.classList.toggle(`flash-alert--${name}`, name === variant);
    });
    if (variant === 'success') dialog.removeAttribute('role');
    else dialog.setAttribute('role', 'alertdialog');
    dialog.querySelectorAll('[data-flash-icon]').forEach((icon) => {
        icon.hidden = icon.dataset.flashIcon !== variant;
    });
    dialog.querySelector('[data-flash-eyebrow]').textContent = `VietGo · ${{ success: 'Thông báo', error: 'Có lỗi xảy ra', warning: 'Lưu ý' }[variant]}`;
    dialog.querySelector('[data-flash-title]').textContent = title;
    const paragraph = document.createElement('p');
    paragraph.textContent = message;
    dialog.querySelector('[data-flash-message]').replaceChildren(paragraph);
    dialog.showModal();
};
