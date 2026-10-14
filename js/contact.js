/* Строителна Компания БГ — offer/contact form submission (progressive enhancement).
   Works on the published PHP host; on a static-only preview it shows a graceful fallback. */
(function () {
  "use strict";
  var form = document.getElementById("offer-form");
  if (!form) return;
  var status = document.getElementById("form-status");

  function show(msg, ok) {
    if (!status) return;
    status.textContent = msg;
    status.className = "form-status show " + (ok ? "ok" : "err");
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    // Honeypot: if filled, silently drop (likely a bot).
    var hp = form.querySelector('[name="website"]');
    if (hp && hp.value) { return; }

    if (!form.checkValidity()) { form.reportValidity(); return; }

    var btn = form.querySelector('button[type="submit"]');
    var data = new FormData(form);
    if (btn) { btn.disabled = true; }
    show("Изпращане…", true);

    fetch("api/contact.php", {
      method: "POST",
      body: data,
      headers: { "X-Requested-With": "fetch" }
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.ok) {
          show("Благодарим! Съобщението е изпратено. Ще се свържем с вас скоро.", true);
          form.reset();
        } else {
          show((res && res.error) || "Възникна грешка. Опитайте отново или ни пишете на имейл.", false);
        }
      })
      .catch(function () {
        show("Формата се обработва на публикувания сайт. Моля, пишете ни на " +
             "stroitelna.compania.bg@gmail.com или се обадете на 0700 50 507.", false);
      })
      .finally(function () { if (btn) { btn.disabled = false; } });
  });
})();
