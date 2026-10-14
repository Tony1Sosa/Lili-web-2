/* Строителна Компания БГ — site interactions (no dependencies) */
(function () {
  "use strict";

  // --- Mobile navigation toggle ---
  var toggle = document.querySelector(".nav-toggle");
  var links = document.getElementById("primary-nav");
  if (toggle && links) {
    toggle.addEventListener("click", function () {
      var open = links.classList.toggle("open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    // Close the menu when a link is tapped (mobile)
    links.addEventListener("click", function (e) {
      if (e.target.closest("a") && links.classList.contains("open")) {
        links.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
      }
    });
    // Close on Escape
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && links.classList.contains("open")) {
        links.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
        toggle.focus();
      }
    });
  }

  // --- Mark the current page in the nav (fallback if aria-current not set) ---
  try {
    var here = location.pathname.split("/").pop() || "index.html";
    document.querySelectorAll(".nav-links a[href]").forEach(function (a) {
      var target = a.getAttribute("href").split("#")[0];
      if (target === here && !a.hasAttribute("aria-current")) {
        a.setAttribute("aria-current", "page");
      }
    });
  } catch (err) { /* no-op */ }

  // --- Generic category filter (used on Projects; reusable) ---
  document.querySelectorAll("[data-filter-group]").forEach(function (group) {
    var chips = group.querySelectorAll(".chip");
    var targetSel = group.getAttribute("data-filter-target");
    var scope = targetSel ? document.querySelector(targetSel) : null;
    if (!scope) return;
    group.addEventListener("click", function (e) {
      var chip = e.target.closest(".chip");
      if (!chip) return;
      var f = chip.getAttribute("data-filter");
      chips.forEach(function (c) { c.setAttribute("aria-pressed", c === chip ? "true" : "false"); });
      scope.querySelectorAll("[data-cat]").forEach(function (it) {
        var cats = (it.getAttribute("data-cat") || "").split(/\s+/);
        it.style.display = (f === "all" || cats.indexOf(f) !== -1) ? "" : "none";
      });
    });
  });

  // --- Current year in footer ---
  var year = document.getElementById("year");
  if (year) { year.textContent = String(new Date().getFullYear()); }
})();
