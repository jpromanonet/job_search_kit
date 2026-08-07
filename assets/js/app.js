(function () {
  const base = window.JOBKIT_BASE || "";

  const jumpInput = document.getElementById("jumpDay");
  const jumpBtn = document.getElementById("jumpDayBtn");

  function jumpToDay() {
    if (!jumpInput) return;
    const day = Math.min(100, Math.max(1, parseInt(jumpInput.value, 10) || 1));
    const el = document.getElementById("day-" + day);
    if (el) {
      el.scrollIntoView({ behavior: "smooth", block: "start" });
      el.classList.add("is-today");
      history.replaceState(null, "", "#day-" + day);
    } else {
      window.location.href = base + "/index.php?tab=plan&filter=all#day-" + day;
    }
  }

  if (jumpBtn) jumpBtn.addEventListener("click", jumpToDay);
  if (jumpInput) {
    jumpInput.addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        jumpToDay();
      }
    });
  }

  if (location.hash && location.hash.indexOf("#day-") === 0) {
    const target = document.querySelector(location.hash);
    if (target) {
      setTimeout(function () {
        target.scrollIntoView({ behavior: "smooth", block: "start" });
      }, 80);
    }
  }

  document.querySelectorAll(".btn-copy").forEach(function (btn) {
    btn.addEventListener("click", async function () {
      let text = btn.getAttribute("data-copy-text") || "";
      if (!text) {
        const id = btn.getAttribute("data-copy-target");
        const el = id ? document.getElementById(id) : null;
        if (!el) return;
        text = el.value || el.textContent || "";
      }
      try {
        await navigator.clipboard.writeText(text);
        const prev = btn.innerHTML;
        btn.innerHTML = "Copiado";
        setTimeout(function () {
          btn.innerHTML = prev;
        }, 1200);
      } catch (err) {
        // ignore
      }
    });
  });

  document.querySelectorAll(".nav-dropdown").forEach(function (wrap) {
    const toggle = wrap.querySelector(".nav-dropdown-toggle");
    if (!toggle) return;
    toggle.addEventListener("click", function (e) {
      e.stopPropagation();
      const open = wrap.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      document.querySelectorAll(".nav-dropdown").forEach(function (other) {
        if (other !== wrap) {
          other.classList.remove("is-open");
          const t = other.querySelector(".nav-dropdown-toggle");
          if (t) t.setAttribute("aria-expanded", "false");
        }
      });
    });
  });

  const navToggle = document.getElementById("navToggle");
  const navBackdrop = document.getElementById("navBackdrop");
  const siteNav = document.getElementById("siteNav");

  function setNavOpen(open) {
    document.body.classList.toggle("nav-open", !!open);
    if (navToggle) {
      navToggle.setAttribute("aria-expanded", open ? "true" : "false");
      navToggle.setAttribute("aria-label", open ? "Cerrar menú" : "Abrir menú");
    }
    if (navBackdrop) {
      if (open) navBackdrop.removeAttribute("hidden");
      else navBackdrop.setAttribute("hidden", "");
    }
    if (!open) {
      document.querySelectorAll(".nav-dropdown.is-open").forEach(function (wrap) {
        wrap.classList.remove("is-open");
        const t = wrap.querySelector(".nav-dropdown-toggle");
        if (t) t.setAttribute("aria-expanded", "false");
      });
    }
  }

  if (navToggle) {
    navToggle.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      setNavOpen(!document.body.classList.contains("nav-open"));
    });
  }
  if (navBackdrop) {
    navBackdrop.addEventListener("click", function (e) {
      e.preventDefault();
      setNavOpen(false);
    });
  }
  if (siteNav) {
    siteNav.addEventListener("click", function (e) {
      const link = e.target.closest("a");
      if (link) setNavOpen(false);
    });
  }

  document.addEventListener("click", function () {
    document.querySelectorAll(".nav-dropdown.is-open").forEach(function (wrap) {
      wrap.classList.remove("is-open");
      const t = wrap.querySelector(".nav-dropdown-toggle");
      if (t) t.setAttribute("aria-expanded", "false");
    });
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      setNavOpen(false);
      document.querySelectorAll(".nav-dropdown.is-open").forEach(function (wrap) {
        wrap.classList.remove("is-open");
        const t = wrap.querySelector(".nav-dropdown-toggle");
        if (t) t.setAttribute("aria-expanded", "false");
      });
    }
  });
  window.addEventListener("resize", function () {
    if (window.innerWidth > 960) setNavOpen(false);
  });
})();
