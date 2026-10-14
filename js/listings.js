/* Строителна Компания БГ — public property listings renderer.
   Reads data/listings.json and renders cards into #listings.
   Managed from the private admin panel; no build step. */
(function () {
  "use strict";
  var mount = document.getElementById("listings");
  if (!mount) return;

  var STATUS = {
    available:   { label: "Свободен",  cls: "tag--success" },
    in_progress: { label: "В строеж",  cls: "tag--warning" },
    sold:        { label: "Продаден",  cls: "tag--muted" }
  };
  var TYPE = { jil: "Жилищен", biznes: "Бизнес" };

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function card(it) {
    var st = STATUS[it.status] || { label: it.status || "", cls: "tag--muted" };
    var cat = [it.type || "", it.status || ""].join(" ").trim();
    var media = it.image
      ? '<img src="' + esc(it.image) + '" alt="' + esc(it.title) + '" loading="lazy">'
      : '<div class="ph ph--3x2" data-label="Снимка: ' + esc(it.title) + '"></div>';
    return '<article class="tile" data-cat="' + esc(cat) + '">' + media +
      '<div class="tile__body">' +
        '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:2px;">' +
          '<span class="tag ' + st.cls + '">' + esc(st.label) + '</span>' +
          (it.type ? '<span class="tag tag--muted">' + esc(TYPE[it.type] || it.type) + '</span>' : '') +
        '</div>' +
        '<h3 class="tile__title">' + esc(it.title) + '</h3>' +
        (it.location ? '<p class="tile__meta">' + esc(it.location) + '</p>' : '') +
        (it.area ? '<p class="tile__role">' + esc(it.area) + '</p>' : '') +
        (it.description ? '<p class="muted" style="font-size:14px;">' + esc(it.description) + '</p>' : '') +
        '<p class="tile__meta"><strong>Цена:</strong> ' + esc(it.price || "при запитване") + '</p>' +
        '<a class="btn btn--primary btn--sm" href="kontakti.html#oferta">Запитване</a>' +
      '</div></article>';
  }

  function render(items) {
    if (!items || !items.length) {
      mount.innerHTML = '<p class="muted">Към момента няма активни обяви.</p>';
      return;
    }
    mount.innerHTML = items.map(card).join("");
  }

  fetch("data/listings.json", { cache: "no-store" })
    .then(function (r) { if (!r.ok) throw new Error("no data"); return r.json(); })
    .then(function (d) { render((d && d.listings) || []); })
    .catch(function () {
      mount.innerHTML = '<p class="muted">Обявите се зареждат на публикувания сайт. ' +
        '(Прегледът директно от локален файл може да не зареди данните.)</p>';
    });
})();
