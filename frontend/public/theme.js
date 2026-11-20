(() => {
  const applyTheme = (theme) => {
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.setAttribute('aria-pressed', String(theme === 'dark'));
    });
  };

  let theme = 'light';

  try {
    if (localStorage.getItem('landlord-theme') === 'dark') theme = 'dark';
  } catch {
    // Theme switching also works when browser storage is unavailable.
  }

  applyTheme(theme);

  document.addEventListener('DOMContentLoaded', () => applyTheme(theme));
  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element) || !event.target.closest('[data-theme-toggle]')) return;

    theme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(theme);

    try {
      localStorage.setItem('landlord-theme', theme);
    } catch {
      // A stored preference is optional.
    }
  });
})();
