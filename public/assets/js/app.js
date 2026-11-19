/**
 * Module générique de pagination AJAX (20 lignes maximum par page).
 * Utilisé par toutes les listes : stagiaires, candidatures, conventions, stages, livrables, attestations, utilisateurs.
 */
function DataTable(options) {
  const {
    endpoint,          // ex: "index.php?action=stagiaires/apiListe"
    tableBody,         // sélecteur du <tbody>
    pagination,        // sélecteur du conteneur de pagination
    renderRow,         // fonction(row) -> html <tr>
    filtersSelector,   // sélecteur du formulaire de filtres (optionnel)
    colSpan,           // nombre de colonnes pour l'état vide
    extraParams,       // fonction() -> objet de paramètres additionnels (filtres fixes, ex: stage_id)
  } = options;

  let page = 1;

  function buildUrl(p) {
    const params = new URLSearchParams();
    params.set('page', p);
    if (filtersSelector) {
      const form = document.querySelector(filtersSelector);
      if (form) {
        new FormData(form).forEach((v, k) => { if (v !== '') params.set(k, v); });
      }
    }
    if (typeof extraParams === 'function') {
      const extra = extraParams() || {};
      Object.keys(extra).forEach((k) => params.set(k, extra[k]));
    }
    return endpoint + (endpoint.includes('?') ? '&' : '?') + params.toString();
  }

  function load(p) {
    page = p;
    const tbody = document.querySelector(tableBody);
    tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="empty-state">Chargement...</td></tr>';

    fetch(buildUrl(p), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then((r) => r.json().then((data) => ({ ok: r.ok, data })))
      .then(({ ok, data: res }) => {
        if (!ok || res.error) {
          const el = document.querySelector(pagination);
          if (el) el.innerHTML = '';
          tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="empty-state">' + escapeHtml(res.error || 'Erreur de chargement.') + '</td></tr>';
          return;
        }
        if (!res.data || res.data.length === 0) {
          tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="empty-state">Aucun résultat.</td></tr>';
        } else {
          tbody.innerHTML = res.data.map(renderRow).join('');
        }
        renderPagination(res);
      })
      .catch(() => {
        tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="empty-state">Erreur de chargement. Vérifiez votre connexion et réessayez.</td></tr>';
      });
  }

  function renderPagination(res) {
    const el = document.querySelector(pagination);
    if (!el) return;
    let html = '';
    html += '<button ' + (res.page <= 1 ? 'disabled' : '') + ' data-page="' + (res.page - 1) + '">&laquo; Préc.</button>';

    const maxButtons = 6;
    let start = Math.max(1, res.page - 2);
    let end = Math.min(res.total_pages, start + maxButtons - 1);
    start = Math.max(1, end - maxButtons + 1);

    for (let i = start; i <= end; i++) {
      html += '<button class="' + (i === res.page ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
    }
    html += '<button ' + (res.page >= res.total_pages ? 'disabled' : '') + ' data-page="' + (res.page + 1) + '">Suiv. &raquo;</button>';
    html += '<span class="info">' + res.total + ' résultat(s) - page ' + res.page + '/' + res.total_pages + ' (20 lignes / page)</span>';
    el.innerHTML = html;

    el.querySelectorAll('button[data-page]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const p = parseInt(btn.getAttribute('data-page'), 10);
        if (p >= 1 && p <= res.total_pages) load(p);
      });
    });
  }

  if (filtersSelector) {
    const form = document.querySelector(filtersSelector);
    if (form) {
      form.addEventListener('input', debounce(() => load(1), 350));
      form.addEventListener('change', () => load(1));
      form.addEventListener('submit', (e) => { e.preventDefault(); load(1); });
    }
  }

  function debounce(fn, delay) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
  }

  load(1);
  return { reload: () => load(page) };
}

/**
 * Pagination côté navigateur d'un tableau déjà rendu (statistiques du tableau de bord) :
 * même présentation que DataTable, 20 lignes maximum par page.
 */
function PaginationTableau(tbodySelector, paginationSelector, parPage = 20) {
  const tbody = document.querySelector(tbodySelector);
  const el = document.querySelector(paginationSelector);
  if (!tbody || !el) return;
  const lignes = Array.from(tbody.rows);
  const totalPages = Math.max(1, Math.ceil(lignes.length / parPage));

  function afficher(page) {
    lignes.forEach((tr, i) => { tr.hidden = i < (page - 1) * parPage || i >= page * parPage; });

    let html = '<button type="button" ' + (page <= 1 ? 'disabled' : '') + ' data-page="' + (page - 1) + '">&laquo; Préc.</button>';
    const maxButtons = 6;
    let start = Math.max(1, page - 2);
    const end = Math.min(totalPages, start + maxButtons - 1);
    start = Math.max(1, end - maxButtons + 1);
    for (let i = start; i <= end; i++) {
      html += '<button type="button" class="' + (i === page ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
    }
    html += '<button type="button" ' + (page >= totalPages ? 'disabled' : '') + ' data-page="' + (page + 1) + '">Suiv. &raquo;</button>';
    html += '<span class="info">' + lignes.length + ' résultat(s) - page ' + page + '/' + totalPages + ' (' + parPage + ' lignes / page)</span>';
    el.innerHTML = html;
    el.querySelectorAll('button[data-page]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const p = parseInt(btn.getAttribute('data-page'), 10);
        if (p >= 1 && p <= totalPages) afficher(p);
      });
    });
  }
  afficher(1);
}

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function badge(statut) {
  const labels = {
    en_attente: 'En attente', validee: 'Validée', rejetee: 'Rejetée', terminee: 'Terminée',
    acceptee: 'Acceptée', refusee: 'Refusée', desistee: 'Désistée',
    actif: 'Actif', desiste: 'Désisté', archive: 'Archivé',
    a_venir: 'À venir', en_cours: 'En cours', termine: 'Terminé', interrompu: 'Interrompu',
    valide: 'Validé', rejete: 'Rejeté',
  };
  return '<span class="badge badge-' + statut + '">' + (labels[statut] || statut) + '</span>';
}

/**
 * Notifications en application (remplace les alertes natives du navigateur).
 * Le serveur dépose un message dans la session ; le layout le rend sous forme de toast
 * (haut à droite), qui se ferme automatiquement ou via le bouton de fermeture.
 */
function fermerNotification() {
  const toast = document.getElementById('toast-notification');
  if (!toast) return;
  toast.classList.add('toast-sortie');
  setTimeout(() => toast.remove(), 220);
}
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('toast-notification')) {
    setTimeout(fermerNotification, 5000);
  }
});

/**
 * Boîte de confirmation en application (remplace window.confirm()).
 * Utilisation : onclick="return confirmerPuisSoumettre(event, 'Message à afficher')"
 * sur un bouton de soumission, ou onsubmit="return confirmerPuisSoumettre(event, '...')" sur un <form>.
 */
function confirmerPuisSoumettre(evt, message) {
  evt.preventDefault();
  const cible = evt.target;
  const form = cible.closest('form');
  if (!form) return false;
  afficherConfirmation(message, () => {
    // form.submit() (et non requestSubmit()) : ne redéclenche pas l'évènement "submit",
    // ce qui éviterait sinon une boucle avec les formulaires utilisant onsubmit="...confirmerPuisSoumettre...".
    form.submit();
  });
  return false;
}

function afficherConfirmation(message, onConfirm) {
  let overlay = document.getElementById('modal-confirmation');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'modal-confirmation';
    overlay.className = 'modal-overlay';
    overlay.innerHTML =
      '<div class="modal-box" role="alertdialog" aria-modal="true">' +
        '<p id="modal-confirmation-texte"></p>' +
        '<div class="modal-actions">' +
          '<button type="button" class="btn btn-outline btn-sm" data-role="annuler">Annuler</button>' +
          '<button type="button" class="btn btn-danger btn-sm" data-role="confirmer">Confirmer</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(overlay);
  }
  overlay.querySelector('#modal-confirmation-texte').textContent = message;
  overlay.classList.add('actif');

  function fermer() {
    overlay.classList.remove('actif');
    overlay.removeEventListener('click', clicHorsBoite);
    document.removeEventListener('keydown', toucheEchap);
  }
  function clicHorsBoite(e) { if (e.target === overlay) fermer(); }
  function toucheEchap(e) { if (e.key === 'Escape') fermer(); }

  overlay.querySelector('[data-role="confirmer"]').onclick = () => { fermer(); onConfirm(); };
  overlay.querySelector('[data-role="annuler"]').onclick = fermer;
  overlay.addEventListener('click', clicHorsBoite);
  document.addEventListener('keydown', toucheEchap);
}

// Bascule l'affichage en clair / masqué de tout champ mot de passe muni d'un
// bouton "œil" (icône .password-toggle à l'intérieur d'un conteneur .password-field).
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.password-toggle').forEach((bouton) => {
    const champ = bouton.closest('.password-field')?.querySelector('input');
    if (!champ) return;
    const oeilOuvert = bouton.querySelector('.icon-oeil-ouvert');
    const oeilBarre = bouton.querySelector('.icon-oeil-barre');
    bouton.addEventListener('click', () => {
      const vaDevenirVisible = champ.type === 'password';
      champ.type = vaDevenirVisible ? 'text' : 'password';
      if (oeilOuvert) oeilOuvert.style.display = vaDevenirVisible ? 'none' : '';
      if (oeilBarre) oeilBarre.style.display = vaDevenirVisible ? '' : 'none';
      bouton.setAttribute('aria-label', vaDevenirVisible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
      bouton.setAttribute('aria-pressed', vaDevenirVisible ? 'true' : 'false');
    });
  });
});

/**
 * Petites icônes SVG pour les lignes de tableau générées côté navigateur
 * (mêmes tracés que app/core/Icones.php).
 */
function iconeJs(nom) {
  const chemins = {
    eye: '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/>',
    pencil: '<path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    external: '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
    user: '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
  };
  return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (chemins[nom] || '') + '</svg>';
}

/** Bouton-icône de ligne de tableau (lien) : <a class="icon-btn-table" title="…">. */
function lienIcone(href, icone, titre, extra) {
  return '<a class="icon-btn-table" href="' + href + '" title="' + escapeHtml(titre) + '" aria-label="' + escapeHtml(titre) + '"' + (extra || '') + '>' + iconeJs(icone) + '</a>';
}

/** Coque de l'application : menu latéral repliable, menu mobile, menu du profil. */
document.addEventListener('DOMContentLoaded', () => {
  const shell = document.getElementById('app-shell');
  if (!shell) return;

  const toggle = document.getElementById('sidebar-toggle');
  if (toggle) toggle.addEventListener('click', () => {
    const replie = shell.classList.toggle('collapsed');
    // L'état du menu est mémorisé dans un cookie : le serveur l'applique dès le chargement (pas de clignotement).
    document.cookie = 'itcorp_menu=' + (replie ? 'repli' : 'deplie') + '; path=/; max-age=31536000; SameSite=Lax';
  });

  const burger = document.getElementById('topbar-burger');
  const fond = document.getElementById('sidebar-backdrop');
  if (burger) burger.addEventListener('click', () => shell.classList.toggle('nav-open'));
  if (fond) fond.addEventListener('click', () => shell.classList.remove('nav-open'));

  const profil = document.getElementById('profile');
  const bouton = document.getElementById('profile-btn');
  if (profil && bouton) {
    bouton.addEventListener('click', (e) => {
      e.stopPropagation();
      const ouvert = profil.classList.toggle('open');
      bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    });
    document.addEventListener('click', (e) => {
      if (!profil.contains(e.target)) { profil.classList.remove('open'); bouton.setAttribute('aria-expanded', 'false'); }
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { profil.classList.remove('open'); shell.classList.remove('nav-open'); }
    });
  }
});
