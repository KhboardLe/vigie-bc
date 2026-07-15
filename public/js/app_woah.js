// ============================================================
// VIGIE BC — shared front-end behaviour (static prototype, no backend)
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  initMobileNav();
  initSidebarToggle();
  initReveal();
  initCounters();
  initKeywordManager();
  initBcTable();
  initToggles();
  initFormGuards();
  initPasswordStrength();
  initModal();
  initOnboarding();
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

/* ---------- Onboarding steps (topics / categories / nature / cities / keywords) ---------- */
function initOnboarding(){
  const step = document.querySelector('[data-onboarding-step]');
  if(!step) return;

  // Live "N sélections" badge next to each checkbox group
  step.querySelectorAll('[data-selectable]').forEach(group => {
    const countLabel = group.parentElement.querySelector('[data-selected-count]');
    function updateCount(){
      const n = group.querySelectorAll('input[type="checkbox"]:checked').length;
      if(countLabel) countLabel.textContent = n + (n > 1 || n === 0 ? ' sélections' : ' sélection');
    }
    group.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.addEventListener('change', updateCount));
    updateCount();
  });

  // Search filter for long option lists (cities, keywords)
  step.querySelectorAll('[data-option-search]').forEach(input => {
    const list = step.querySelector(input.dataset.optionSearch);
    if(!list) return;
    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      list.querySelectorAll('[data-option-name]').forEach(item => {
        const match = item.dataset.optionName.toLowerCase().includes(q);
        item.style.display = match ? '' : 'none';
      });
    });
  });

  // Custom "add your own keyword" chips — each becomes a real hidden input so it submits with the form
  const kwInput = step.querySelector('[data-custom-keyword-input]');
  const kwAddBtn = step.querySelector('[data-custom-keyword-add]');
  const kwChipRow = step.querySelector('[data-custom-keyword-chips]');
  if(kwInput && kwAddBtn && kwChipRow){
    function addCustomKeyword(){
      const val = kwInput.value.trim();
      if(!val) return;
      const chip = document.createElement('span');
      chip.className = 'match-tag';
      chip.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:5px 10px;font-size:12px;cursor:pointer;';
      chip.innerHTML = `${escapeHtml(val)} <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18 6L6 18M6 6l12 12"/></svg>
        <input type="hidden" name="custom_keywords[]" value="${escapeHtml(val)}">`;
      chip.addEventListener('click', () => chip.remove());
      kwChipRow.appendChild(chip);
      kwInput.value = '';
      kwInput.focus();
    }
    kwAddBtn.addEventListener('click', (e) => { e.preventDefault(); addCustomKeyword(); });
    kwInput.addEventListener('keydown', (e) => { if(e.key === 'Enter'){ e.preventDefault(); addCustomKeyword(); } });
  }


// new shit

const input = document.querySelector("[data-tag-input]");
const addBtn = document.querySelector("[data-tag-add]");
const suggestions = document.querySelector("[data-tag-suggestions]");
const chips = document.querySelector("[data-tag-chips]");

if(input && addBtn && suggestions && chips){

chips.querySelectorAll(".tag-chip").forEach(chip => {

    const removeBtn = chip.querySelector(".tag-chip__remove");

    if(removeBtn){

        removeBtn.onclick = () => {

            chip.remove();

            filterSuggestions();

        };

    }

});


    function keywordExists(value){
        return [...chips.querySelectorAll(".tag-chip")]
            .some(chip => chip.dataset.value.toLowerCase() === value.toLowerCase());
    }

    function createChip(value){

        value = value.trim();

        if(!value || keywordExists(value))
            return;

        const chip = document.createElement("span");

        chip.className = "tag-chip";
        chip.dataset.value = value;

        chip.innerHTML = `
            ${value}
            <button type="button" class="tag-chip__remove">✕</button>
            <input type="hidden" name="keywords[]" value="${value}">
        `;

        chip.querySelector(".tag-chip__remove").onclick = () => {

    chip.remove();

    filterSuggestions();

};

        chips.appendChild(chip);

        input.value = "";

        filterSuggestions();
    }

    function filterSuggestions(){

        const search = input.value.trim().toLowerCase();

        if(search.length === 0){

            suggestions.classList.remove("show");

            return;

        }

        let visible = 0;

        suggestions.querySelectorAll(".tag-suggestion").forEach(btn=>{

            const word = btn.dataset.value.toLowerCase();

            const match = word.includes(search) && !keywordExists(word);

            btn.classList.toggle("hidden", !match);

            if(match) visible++;

        });

        suggestions.classList.toggle("show", visible>0);
    }

    input.addEventListener("input", filterSuggestions);

    input.addEventListener("keydown", e=>{

        if(e.key==="Enter"){

            e.preventDefault();

            createChip(input.value);

        }

    });

    addBtn.addEventListener("click", ()=>{

        createChip(input.value);

    });

    suggestions.querySelectorAll(".tag-suggestion").forEach(btn=>{

        btn.onclick = ()=>{

            createChip(btn.dataset.value);

        };

    });

}}