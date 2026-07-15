// ============================================================
// VIGIE BC — shared front-end behaviour (static prototype, no backend)
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  initMobileNav();
  initSidebarToggle();
  initReveal();
  initCounters();
  initKeywordManager();
     initOnboardingKeywords();   // <-- ADD THIS
  initBcTable();
  initToggles();
  initFormGuards();
  initPasswordStrength();
  initModal();
});

/* ---------- Mobile nav (public pages) ---------- */
function initMobileNav(){
  const toggle = document.querySelector('.nav__toggle');
  const links = document.querySelector('.nav__links');
  if(!toggle || !links) return;
  toggle.addEventListener('click', () => {
    const open = links.style.display === 'flex';
    links.style.display = open ? 'none' : 'flex';
    links.style.cssText += open ? '' : 'position:absolute;top:100%;left:0;right:0;background:#fff;flex-direction:column;padding:20px 24px;border-bottom:1px solid var(--line);gap:18px;';
  });
}

/* ---------- Sidebar toggle (app pages, mobile) ---------- */
function initSidebarToggle(){
  const btn = document.querySelector('[data-sidebar-toggle]');
  const sidebar = document.querySelector('.sidebar');
  if(!btn || !sidebar) return;
  btn.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.addEventListener('click', (e) => {
    if(sidebar.classList.contains('open') && !sidebar.contains(e.target) && !btn.contains(e.target)){
      sidebar.classList.remove('open');
    }
  });
}

/* ---------- Scroll reveal ---------- */
function initReveal(){
  const items = document.querySelectorAll('.reveal');
  if(!items.length) return;
  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if(entry.isIntersecting){
        entry.target.classList.add('is-visible');
        io.unobserve(entry.target);
      }
    });
  }, {threshold:0.15});
  items.forEach(el => io.observe(el));
}

/* ---------- Animated stat counters ---------- */
function initCounters(){
  const counters = document.querySelectorAll('[data-count]');
  if(!counters.length) return;
  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if(!entry.isIntersecting) return;
      const el = entry.target;
      const target = parseInt(el.dataset.count, 10);
      const suffix = el.dataset.suffix || '';
      const duration = 1200;
      const start = performance.now();
      function tick(now){
        const p = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(eased * target).toLocaleString('fr-FR') + suffix;
        if(p < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
      io.unobserve(el);
    });
  }, {threshold:0.4});
  counters.forEach(el => io.observe(el));
}

/* ---------- Toast helper ---------- */
function showToast(message){
  let toast = document.querySelector('.toast');
  if(!toast){
    toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg><span></span>`;
    document.body.appendChild(toast);
  }
  toast.querySelector('span').textContent = message;
  toast.classList.add('show');
  clearTimeout(window.__toastTimer);
  window.__toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}
window.showToast = showToast;

/* ---------- Keyword manager (keywords.html) ---------- */
function initKeywordManager(){
  const grid = document.querySelector('[data-chip-grid]');
  const input = document.querySelector('[data-keyword-input]');
  const addBtn = document.querySelector('[data-keyword-add]');
  if(!grid || !input || !addBtn) return;

  function bindDelete(card){
    const del = card.querySelector('[data-delete]');
    del.addEventListener('click', () => {
      card.classList.add('removing');
      setTimeout(() => { card.remove(); showToast('Mot-clé supprimé'); }, 250);
    });
  }
  grid.querySelectorAll('.chip-card').forEach(bindDelete);

  function addKeyword(){
    const val = input.value.trim();
    if(!val) { input.focus(); return; }
    const card = document.createElement('div');
    card.className = 'chip-card reveal';
    card.innerHTML = `
      <div class="chip-card__top">
        <span class="chip-card__word">${escapeHtml(val)}</span>
        <span class="chip-card__count">0 correspondance</span>
      </div>
      <div class="chip-card__foot">
        <small>Ajouté à l'instant</small>
        <a href="#" class="icon-link" data-delete title="Supprimer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6h16z"/></svg>
        </a>
      </div>`;
    grid.prepend(card);
    requestAnimationFrame(() => card.classList.add('is-visible'));
    bindDelete(card);
    input.value = '';
    showToast('Mot-clé ajouté à la surveillance');
  }
  addBtn.addEventListener('click', addKeyword);
  input.addEventListener('keydown', (e) => { if(e.key === 'Enter'){ e.preventDefault(); addKeyword(); } });
}

function escapeHtml(str){
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

/* ---------- BC table: search / filter / sort (bc-list.html) ---------- */
function initBcTable(){
  const table = document.querySelector('[data-bc-table]');
  if(!table) return;
  const tbody = table.querySelector('tbody');
  const rows = Array.from(tbody.querySelectorAll('tr'));
  const search = document.querySelector('[data-bc-search]');
  const statusFilter = document.querySelector('[data-bc-status]');
  const countLabels = document.querySelectorAll('[data-bc-count]');

  function applyFilters(){
    const q = (search?.value || '').toLowerCase();
    const status = statusFilter?.value || '';
    let visible = 0;
    rows.forEach(row => {
      const text = row.dataset.search || row.textContent.toLowerCase();
      const rowStatus = row.dataset.status || '';
      const matchesQ = text.toLowerCase().includes(q);
      const matchesStatus = !status || rowStatus === status;
      const show = matchesQ && matchesStatus;
      row.style.display = show ? '' : 'none';
      if(show) visible++;
    });
    countLabels.forEach(label => label.textContent = visible);
  }
  search?.addEventListener('input', applyFilters);
  statusFilter?.addEventListener('change', applyFilters);
  applyFilters();

  table.querySelectorAll('thead th[data-sort]').forEach(th => {
    th.addEventListener('click', () => {
      const key = th.dataset.sort;
      const asc = th.dataset.asc !== 'true';
      table.querySelectorAll('thead th').forEach(t => t.removeAttribute('data-asc'));
      th.dataset.asc = asc;
      const sorted = rows.slice().sort((a,b) => {
        const av = a.dataset[key] || '';
        const bv = b.dataset[key] || '';
        return asc ? av.localeCompare(bv, 'fr', {numeric:true}) : bv.localeCompare(av, 'fr', {numeric:true});
      });
      sorted.forEach(r => tbody.appendChild(r));
    });
  });
}

/* ---------- Toggle switches (alerts.html) ---------- */
function initToggles(){
  document.querySelectorAll('.switch input').forEach(input => {
    input.addEventListener('change', () => {
      showToast(input.checked ? 'Notification activée' : 'Notification désactivée');
    });
  });
}

/* ---------- Form guards: prevent real submit, fake redirect ---------- */
function initFormGuards(){
  document.querySelectorAll('form[data-demo-form]').forEach(form => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const redirect = form.dataset.demoForm;
      const btn = form.querySelector('button[type="submit"]');
      if(btn){
        btn.dataset.label = btn.textContent;
        btn.textContent = 'Un instant…';
        btn.disabled = true;
      }
      setTimeout(() => {
        if(redirect && redirect !== 'none'){
          window.location.href = redirect;
        } else {
          if(btn){ btn.textContent = btn.dataset.label; btn.disabled = false; }
          showToast('Modifications enregistrées');
        }
      }, 650);
    });
  });
}

/* ---------- Password strength (register.html) ---------- */
function initPasswordStrength(){
  const input = document.querySelector('[data-password-strength]');
  const bar = document.querySelector('[data-strength-bar]');
  if(!input || !bar) return;
  input.addEventListener('input', () => {
    const val = input.value;
    let score = 0;
    if(val.length >= 8) score++;
    if(/[A-Z]/.test(val)) score++;
    if(/[0-9]/.test(val)) score++;
    if(/[^A-Za-z0-9]/.test(val)) score++;
    const pct = (score/4)*100;
    const colors = ['#D64545','#DE8A1B','#2F8AE0','#1FA97A'];
    bar.style.width = pct + '%';
    bar.style.background = colors[Math.max(0,score-1)] || colors[0];
  });
}

/* ---------- Add-keyword modal (dashboard quick action, optional) ---------- */
function initModal(){
  const openers = document.querySelectorAll('[data-modal-open]');
  const modal = document.querySelector('[data-modal]');
  if(!modal) return;
  const closers = modal.querySelectorAll('[data-modal-close]');
  openers.forEach(o => o.addEventListener('click', () => modal.classList.add('show')));
  closers.forEach(c => c.addEventListener('click', () => modal.classList.remove('show')));
  modal.addEventListener('click', (e) => { if(e.target === modal) modal.classList.remove('show'); });
}


function initOnboardingKeywords() {

    const input = document.querySelector("[data-tag-input]");
    const addBtn = document.querySelector("[data-tag-add]");
    const chips = document.querySelector("[data-tag-chips]");
    const suggestions = document.querySelector("[data-tag-suggestions]");

    if (!input || !addBtn || !chips) {
        return;
    }

    function addKeyword(keyword, existing = false) {

        keyword = keyword.trim();

        if (keyword === "") return;

        // avoid duplicates
        if (chips.querySelector('[data-value="' + keyword + '"]')) {
            input.value = "";
            return;
        }

        const chip = document.createElement("span");
        chip.className = "tag-chip";
        chip.dataset.value = keyword;

        chip.innerHTML = `
            ${keyword}
            <button type="button" class="tag-chip__remove">✕</button>
        `;

        let hidden = document.createElement("input");
        hidden.type = "hidden";

        if(existing){
            hidden.name = "keywords[]";
        }else{
            hidden.name = "custom_keywords[]";
        }

        hidden.value = keyword;

        chip.appendChild(hidden);

        chips.appendChild(chip);

        chip.querySelector("button").onclick = () => {
            chip.remove();
        };

        input.value = "";
    }

    addBtn.onclick = () => {
        addKeyword(input.value, false);
    };

    input.addEventListener("keydown", function(e){

        if(e.key === "Enter"){
            e.preventDefault();
            addKeyword(input.value, false);
        }

    });

    suggestions.querySelectorAll("[data-tag-suggestion]").forEach(btn => {

        btn.onclick = () => {
            addKeyword(btn.dataset.value, true);
        };

    });

}

