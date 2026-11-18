const sidebarToggle = document.querySelector('[data-sidebar-toggle]');

function syncSidebarToggle() {
    if (!sidebarToggle) return;
    const compact = document.documentElement.dataset.sidebar === 'compact';
    const label = compact ? 'Expand sidebar' : 'Collapse sidebar';
    sidebarToggle.setAttribute('aria-expanded', String(!compact));
    sidebarToggle.setAttribute('aria-label', label);
    sidebarToggle.title = label;
    sidebarToggle.querySelector('[data-sidebar-toggle-label]').textContent = label;
}

syncSidebarToggle();
sidebarToggle?.addEventListener('click', () => {
    const preference = document.documentElement.dataset.sidebar === 'compact' ? 'expanded' : 'compact';
    document.documentElement.dataset.sidebar = preference;
    try { localStorage.setItem('inventory.sidebar', preference); } catch { /* Navigation still works without storage. */ }
    syncSidebarToggle();
});

const navigation = document.getElementById('mobile-navigation');
const navigationButton = document.querySelector('[data-open-navigation]');

if (navigation && navigationButton) {
    navigationButton.addEventListener('click', () => {
        navigation.showModal();
        navigationButton.setAttribute('aria-expanded', 'true');
    });
    navigation.addEventListener('close', () => navigationButton.setAttribute('aria-expanded', 'false'));
    navigation.addEventListener('click', (event) => {
        if (event.target === navigation) navigation.close();
    });
    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
        if (event.matches && navigation.open) navigation.close();
    });
}

document.querySelector('[data-password-toggle]')?.addEventListener('click', (event) => {
    const button = event.currentTarget;
    const password = document.getElementById(button.getAttribute('aria-controls'));
    const isVisible = password.type === 'password';
    password.type = isVisible ? 'text' : 'password';
    button.textContent = isVisible ? 'Hide' : 'Show';
    button.setAttribute('aria-pressed', String(isVisible));
});
