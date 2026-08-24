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
      e.preventDefault();
      e.stopPropagation();
      const willOpen = !wrap.classList.contains("is-open");
      document.querySelectorAll(".nav-dropdown").forEach(function (other) {
        other.classList.remove("is-open");
        const t = other.querySelector(".nav-dropdown-toggle");
        if (t) t.setAttribute("aria-expanded", "false");
      });
      if (willOpen) {
        wrap.classList.add("is-open");
        toggle.setAttribute("aria-expanded", "true");
      }
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

  const board = document.querySelector(".kanban-board");
  if (board) {
    const endpoint = base + "/actions/update_application_stage.php";
    let dragging = null;

    function refreshCounts() {
      board.querySelectorAll(".kanban-col").forEach(function (col) {
        const badge = col.querySelector(".kanban-count");
        if (badge) badge.textContent = String(col.querySelectorAll(".kanban-card").length);
      });
    }

    function clearDropTargets() {
      board.querySelectorAll(".kanban-col.is-drop-target").forEach(function (col) {
        col.classList.remove("is-drop-target");
      });
    }

    board.addEventListener("dragstart", function (e) {
      const card = e.target.closest(".kanban-card");
      if (!card || !board.contains(card)) return;
      dragging = card;
      card.classList.add("is-dragging");
      e.dataTransfer.effectAllowed = "move";
      e.dataTransfer.setData("text/plain", card.getAttribute("data-id") || "");
    });

    board.addEventListener("dragend", function () {
      if (dragging) dragging.classList.remove("is-dragging");
      dragging = null;
      clearDropTargets();
    });

    board.addEventListener("dragover", function (e) {
      const col = e.target.closest(".kanban-col");
      if (!col || !board.contains(col)) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = "move";
      if (!col.classList.contains("is-drop-target")) {
        clearDropTargets();
        col.classList.add("is-drop-target");
      }
    });

    board.addEventListener("drop", function (e) {
      e.preventDefault();
      const col = e.target.closest(".kanban-col");
      const card = dragging || board.querySelector(".kanban-card.is-dragging");
      clearDropTargets();
      if (!col || !card) return;
      const id = card.getAttribute("data-id");
      const stage = col.getAttribute("data-stage");
      const from = card.closest(".kanban-col");
      if (!id || !stage) return;
      if (from === col) return;

      col.appendChild(card);
      refreshCounts();

      const body = new URLSearchParams();
      body.set("id", id);
      body.set("stage", stage);
      fetch(endpoint, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: body.toString(),
      })
        .then(function (res) {
          return res.json().then(function (data) {
            if (!res.ok || !data.ok) throw new Error(data.message || "No se pudo actualizar el estado");
          });
        })
        .catch(function () {
          if (from) from.appendChild(card);
          refreshCounts();
        });
    });
  }
})();
