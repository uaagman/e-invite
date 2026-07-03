function openModal(id) {
  document.getElementById(id).classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
}

function openEdit(token) {
  const g = GUESTS[token];
  if (!g) return;
  document.getElementById('edit-token').value      = g.token;
  document.getElementById('edit-name').value       = g.name || '';
  document.getElementById('edit-salutation').value = g.salutation || '';
  document.getElementById('edit-table').value      = g.table || '';
  document.getElementById('edit-meals').value      = (g.meal_options || []).join(', ');
  document.getElementById('edit-message').value    = g.personal_message || '';
  document.getElementById('edit-plusone').checked  = !!g.plus_one_allowed;
  openModal('modal-edit');
}

function copyLink(btn, url) {
  navigator.clipboard.writeText(url).then(() => {
    btn.textContent = '✓';
    setTimeout(() => btn.textContent = '⎘', 1800);
  }).catch(() => {
    prompt('Copy this invite link:', url);
  });
}

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
      m.classList.remove('open');
      document.body.style.overflow = '';
    });
  }
});
