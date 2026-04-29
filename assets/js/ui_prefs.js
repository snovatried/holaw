(() => {
  const body = document.body;
  if (!body) return;

  const get = (k) => {
    try { return window.localStorage.getItem(k); } catch (e) { return null; }
  };

  const tema = get('dispensador_tema');
  if (tema === 'claro' || tema === 'oscuro') {
    body.classList.remove('theme-light', 'theme-dark');
    body.classList.add(tema === 'oscuro' ? 'theme-dark' : 'theme-light');
  }

  if (get('dispensador_dislexia') === '1') {
    body.classList.add('dyslexia-mode');
  }
})();
