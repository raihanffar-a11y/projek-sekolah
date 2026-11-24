// Konfirmasi tindakan (atribut data-confirm)
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', ev => {
    if (!confirm(el.dataset.confirm || 'Lanjutkan tindakan ini?')) ev.preventDefault();
  });
});

// Tampilkan / sembunyikan password
const EYE_ON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
const EYE_OFF = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

document.querySelectorAll('.toggle-password').forEach(btn => {
  btn.addEventListener('click', () => {
    const wrap = btn.closest('.input-wrap') || btn.closest('.field-toggle');
    const input = wrap && wrap.querySelector('input');
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.innerHTML = show ? EYE_OFF : EYE_ON;
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
  });
});

// Konfirmasi password harus sama (halaman daftar)
const pw = document.querySelector('input[name="password"]');
const pw2 = document.querySelector('input[name="password2"]');
if (pw && pw2) {
  const check = () => pw2.setCustomValidity(pw2.value && pw.value !== pw2.value ? 'Konfirmasi password tidak sama.' : '');
  pw.addEventListener('input', check);
  pw2.addEventListener('input', check);
}

// Cegah klik ganda pada form login/daftar
document.querySelectorAll('form[data-auth]').forEach(form => {
  const btn = form.querySelector('button[type="submit"]');
  if (!btn) return;
  btn.dataset.label = btn.textContent;
  form.addEventListener('submit', () => {
    if (!form.checkValidity()) return;
    btn.disabled = true;
    btn.textContent = 'Memproses...';
  });
  // tombol dipulihkan bila halaman ditampilkan lagi lewat tombol Back
  window.addEventListener('pageshow', () => {
    btn.disabled = false;
    btn.textContent = btn.dataset.label;
  });
});
