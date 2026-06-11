// ============================================================
// assets/js/main.js — JavaScript principal de l'application
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

  // ---- Burger menu mobile ----
  const burger  = document.getElementById('burger');
  const mainNav = document.getElementById('main-nav');
  if (burger && mainNav) {
    burger.addEventListener('click', () => {
      const isOpen = mainNav.classList.toggle('open');
      burger.classList.toggle('open');
      burger.setAttribute('aria-expanded', isOpen);
      burger.setAttribute('aria-label', isOpen ? 'Fermer le menu' : 'Ouvrir le menu de navigation');
    });
  }

  // ---- Dropdown utilisateur ----
  const userBtn      = document.getElementById('user-btn');
  const userDropdown = document.getElementById('user-dropdown');
  if (userBtn && userDropdown) {
    userBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = userDropdown.classList.toggle('open');
      userBtn.setAttribute('aria-expanded', isOpen);
    });
    // Close on outside click or Escape
    document.addEventListener('click', () => {
      userDropdown.classList.remove('open');
      userBtn.setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        userDropdown.classList.remove('open');
        userBtn.setAttribute('aria-expanded', 'false');
        userBtn.focus();
      }
    });
    // Keyboard navigation inside dropdown (arrows)
    userDropdown.addEventListener('keydown', (e) => {
      const items = [...userDropdown.querySelectorAll('[role="menuitem"]')];
      const idx   = items.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); items[(idx + 1) % items.length]?.focus(); }
      if (e.key === 'ArrowUp')   { e.preventDefault(); items[(idx - 1 + items.length) % items.length]?.focus(); }
    });
  }

  // ---- Live region pour annonces accessibles ----
  const liveRegion = document.createElement('div');
  liveRegion.setAttribute('aria-live', 'polite');
  liveRegion.setAttribute('aria-atomic', 'true');
  liveRegion.className = 'sr-only';
  liveRegion.id = 'a11y-live';
  document.body.appendChild(liveRegion);

  // ---- Fermeture des alertes ----
  document.querySelectorAll('.btn-close').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.closest('.alert').remove();
    });
  });

  // ---- Auto-disparition flash messages (3s) ----
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity .4s';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 400);
    }, 4000);
  });

  // ---- Confirmation suppression ----
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
      if (!confirm(el.dataset.confirm || 'Confirmer cette action ?')) {
        e.preventDefault();
      }
    });
  });

  // ---- Galerie photos bien détail (accessible) ----
  const galleryThumbs = document.querySelectorAll('.gallery-thumb');
  const galleryMain   = document.querySelector('.gallery-main img');
  if (galleryMain && galleryThumbs.length) {
    galleryThumbs.forEach(thumb => {
      thumb.addEventListener('click', () => {
        galleryMain.src = thumb.dataset.full || thumb.src;
        galleryThumbs.forEach(t => t.classList.remove('active'));
        thumb.classList.add('active');
      });
    });
  }

  // ---- Graphique en barres (stats dashboard) ----
  renderBarCharts();

  // ---- Donut chart CSS pur ----
  document.querySelectorAll('.js-donut').forEach(el => {
    const pct = parseFloat(el.dataset.pct) || 0;
    const deg = Math.round((pct / 100) * 360);
    el.style.background = `conic-gradient(#C9A84C ${deg}deg, #EAE4D8 ${deg}deg)`;
  });

  // ---- Toggle filtres mobile (biens.php) ----
  const filtersToggle = document.getElementById('filters-toggle');
  const filtersForm   = document.getElementById('filters-form');
  if (filtersToggle && filtersForm) {
    // Si filtres actifs (un champ rempli), ouvrir automatiquement
    const params = new URLSearchParams(window.location.search);
    const hasFilters = ['operation','type','ville','prix_min','prix_max','surface_min','pieces_min'].some(k => params.get(k));
    if (hasFilters) {
      filtersForm.classList.add('open');
      filtersToggle.setAttribute('aria-expanded', 'true');
    }
    filtersToggle.addEventListener('click', () => {
      const isOpen = filtersForm.classList.toggle('open');
      filtersToggle.setAttribute('aria-expanded', String(isOpen));
    });
  }

  // ---- Toggle sidebar mobile ----
  const sidebarToggle  = document.getElementById('sidebar-toggle');
  const sidebar        = document.getElementById('dashboard-sidebar');
  const sidebarOverlay = document.getElementById('sidebar-overlay');

  function openSidebar() {
    sidebar.classList.add('open');
    sidebarToggle && sidebarToggle.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar && sidebar.classList.remove('open');
    sidebarToggle && sidebarToggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => {
      sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
  }
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebar);
  }
  // Close sidebar on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
      closeSidebar();
      sidebarToggle && sidebarToggle.focus();
    }
  });

  // ---- Preview image upload ----
  const imgInput   = document.getElementById('images');
  const imgPreview = document.getElementById('img-preview');
  if (imgInput && imgPreview) {
    imgInput.addEventListener('change', () => {
      imgPreview.innerHTML = '';
      Array.from(imgInput.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
          const img = document.createElement('img');
          img.src = e.target.result;
          img.className = 'preview-img';
          img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:6px;border:2px solid #C9A84C;';
          imgPreview.appendChild(img);
        };
        reader.readAsDataURL(file);
      });
    });
  }

  // ---- Graphiques barres simples ----
  function renderBarCharts() {
    document.querySelectorAll('.js-bar-chart').forEach(container => {
      const data   = JSON.parse(container.dataset.values || '[]');
      const labels = JSON.parse(container.dataset.labels || '[]');
      const colors = JSON.parse(container.dataset.colors || '[]');
      if (!data.length) return;

      const max = Math.max(...data.map(Number));
      container.innerHTML = '';

      data.forEach((val, i) => {
        const pct   = max > 0 ? (Number(val) / max) * 100 : 0;
        const color = colors[i] || '#0A1628';
        const col   = document.createElement('div');
        col.className = 'bar-col';
        col.innerHTML = `
          <span style="font-size:.75rem;font-weight:600;color:#0A1628">${val}</span>
          <div class="bar-val" style="height:${pct}%;background:${color};"></div>
          <span class="bar-label">${labels[i] || ''}</span>
        `;
        container.appendChild(col);
      });
    });
  }

});
