document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-menu-toggle]');
    if (toggle) {
        const menu = document.getElementById(toggle.dataset.menuToggle);
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        menu?.classList.toggle('hidden');
        toggle.setAttribute('aria-expanded', String(!expanded));
    }
});
