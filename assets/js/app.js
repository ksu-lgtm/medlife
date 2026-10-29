// МедЛайф — клиентская часть
(function () {
  // меню на мобильных
  var b = document.getElementById('burger'), n = document.getElementById('nav');
  if (b && n) b.addEventListener('click', function () { n.classList.toggle('open'); });

  // модальные окна
  function openM(m) { m.classList.add('open'); m.setAttribute('aria-hidden', 'false'); }
  function closeM(m) { m.classList.remove('open'); m.setAttribute('aria-hidden', 'true'); }
  document.querySelectorAll('.modal').forEach(function (m) {
    m.addEventListener('click', function (e) { if (e.target === m || e.target.hasAttribute('data-close')) closeM(m); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.querySelectorAll('.modal.open').forEach(closeM);
  });
  document.querySelectorAll('[data-open]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      var m = document.getElementById(el.getAttribute('data-open'));
      if (!m) return;
      if (el.dataset.doctorId) {
        document.getElementById('bookDoctorId').value = el.dataset.doctorId;
        document.getElementById('bookDoctorName').value = el.dataset.doctorName;
      }
      openM(m);
    });
  });
  if (location.hash === '#book' ) { var bm = document.getElementById('bookModal'); }
  document.querySelectorAll('.modal[data-autoopen]').forEach(openM);

  // маска телефона +7 (___) ___-__-__
  document.querySelectorAll('[data-phone]').forEach(function (inp) {
    function fmt() {
      var d = inp.value.replace(/\D/g, '');
      if (d[0] === '8' || d[0] === '7') d = d.slice(1);
      d = d.slice(0, 10);
      var s = '+7';
      if (d.length) s += ' (' + d.slice(0, 3);
      if (d.length >= 3) s += ')';
      if (d.length > 3) s += ' ' + d.slice(3, 6);
      if (d.length > 6) s += '-' + d.slice(6, 8);
      if (d.length > 8) s += '-' + d.slice(8, 10);
      inp.value = d.length ? s : '';
    }
    inp.addEventListener('input', fmt);
    inp.addEventListener('focus', function () { if (!inp.value) inp.value = '+7 ('; });
    inp.addEventListener('blur', function () { if (inp.value === '+7 (' ) inp.value = ''; });
  });
})();
