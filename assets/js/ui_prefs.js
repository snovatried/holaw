(() => {
  const body = document.body;
  if (!body) return;

  const THEME_KEY = 'dispensador_tema';
  const DYS_KEY = 'dispensador_dislexia';

  const get = (k) => { try { return window.localStorage.getItem(k); } catch (_) { return null; } };
  const set = (k, v) => { try { window.localStorage.setItem(k, v); } catch (_) {} };

  // Limpia controles legado (claro/oscuro + etiqueta "Tema actual") si existen.
  const legacyLight = document.getElementById('modo-claro');
  const legacyDark = document.getElementById('modo-oscuro');
  if (legacyLight && legacyDark) {
    const wrapper = legacyLight.closest('.theme-switcher');
    if (wrapper) {
      wrapper.remove();
    } else {
      legacyLight.remove();
      legacyDark.remove();
    }
  }
  const legacyLabel = Array.from(document.querySelectorAll('span, p, div'))
    .find((el) => (el.textContent || '').trim().startsWith('Tema actual:'));
  if (legacyLabel) {
    legacyLabel.remove();
  }
  // Elimina cualquier control legacy visible dentro de topbar/actions.
  document.querySelectorAll('.topbar-actions .theme-switcher, .topbar .theme-switcher').forEach((el) => el.remove());
  Array.from(document.querySelectorAll('.topbar-actions *, .topbar *'))
    .filter((el) => ['claro', 'oscuro'].includes((el.textContent || '').trim().toLowerCase()))
    .forEach((el) => el.remove());

  if (!document.querySelector('.ui-tools')) {
    const panel = document.createElement('aside');
    panel.className = 'ui-tools';
    panel.setAttribute('aria-label', 'Herramientas visuales');
    panel.innerHTML = `
      <div class="theme-switcher" role="group" aria-label="Selector de tema">
        <button type="button" class="theme-btn" id="modo-tema" aria-pressed="false">🌓 Cambiar tema</button>
      </div>
      <button type="button" class="theme-btn dyslexia-btn" id="modo-dislexia" aria-pressed="false">🅰️ Modo dislexia</button>
    `;
    body.appendChild(panel);
  }

  const btnTema = document.getElementById('modo-tema');
  const btnDis = document.getElementById('modo-dislexia');

  const applyTheme = (mode) => {
    body.classList.remove('theme-light', 'theme-dark');
    body.classList.add(mode === 'oscuro' ? 'theme-dark' : 'theme-light');
    set(THEME_KEY, mode === 'oscuro' ? 'oscuro' : 'claro');
    if (btnTema) {
      const dark = mode === 'oscuro';
      btnTema.classList.toggle('is-active', dark);
      btnTema.setAttribute('aria-pressed', dark ? 'true' : 'false');
    }
  };

  const savedTheme = get(THEME_KEY);
  if (savedTheme === 'claro' || savedTheme === 'oscuro') {
    applyTheme(savedTheme);
  } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    applyTheme('oscuro');
  } else {
    applyTheme('claro');
  }

  btnTema?.addEventListener('click', () => {
    applyTheme(body.classList.contains('theme-dark') ? 'claro' : 'oscuro');
  });

  const applyDys = (enabled) => {
    body.classList.toggle('dyslexia-mode', enabled);
    set(DYS_KEY, enabled ? '1' : '0');
    if (btnDis) {
      btnDis.classList.toggle('is-active', enabled);
      btnDis.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    }
  };

  applyDys(get(DYS_KEY) === '1');
  btnDis?.addEventListener('click', () => applyDys(!body.classList.contains('dyslexia-mode')));
})();
