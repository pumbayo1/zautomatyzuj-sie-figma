(function () {
  var siteHeader = document.querySelector(".site-header");
  var headerProgressBar = document.querySelector(".site-header__progress-bar");
  var menuButton = document.querySelector(".menu-toggle");
  var mobileNav = document.getElementById("mobile-nav");
  var dropdownItem = document.querySelector(".nav__item--dropdown");
  var dropdownTrigger = dropdownItem ? dropdownItem.querySelector(".nav__trigger") : null;
  var mobileGroup = document.querySelector(".mobile-nav__group");
  var mobileToggle = mobileGroup ? mobileGroup.querySelector(".mobile-nav__toggle") : null;
  var bodyNav = document.body.getAttribute("data-nav");

  if (bodyNav) {
    document.querySelectorAll('[data-nav="' + bodyNav + '"]').forEach(function (node) {
      node.classList.add("is-current");
    });
  }

  if (dropdownTrigger && dropdownItem) {
    dropdownTrigger.addEventListener("click", function (event) {
      event.preventDefault();
      var open = dropdownItem.classList.toggle("is-open");
      dropdownTrigger.setAttribute("aria-expanded", open ? "true" : "false");
    });

    document.addEventListener("click", function (event) {
      if (!dropdownItem.contains(event.target)) {
        dropdownItem.classList.remove("is-open");
        dropdownTrigger.setAttribute("aria-expanded", "false");
      }
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        dropdownItem.classList.remove("is-open");
        dropdownTrigger.setAttribute("aria-expanded", "false");
      }
    });
  }

  if (menuButton && mobileNav) {
    menuButton.addEventListener("click", function () {
      var open = mobileNav.classList.toggle("is-open");
      menuButton.setAttribute("aria-expanded", open ? "true" : "false");
      menuButton.classList.toggle("is-open", open);
      if (siteHeader) {
        siteHeader.classList.remove("is-hidden");
      }
    });

    mobileNav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        mobileNav.classList.remove("is-open");
        menuButton.setAttribute("aria-expanded", "false");
        menuButton.classList.remove("is-open");
      });
    });
  }

  if (mobileToggle && mobileGroup) {
    mobileToggle.addEventListener("click", function () {
      var open = mobileGroup.classList.toggle("is-open");
      mobileToggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
  }

  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function animateAccordionOpen(card, body) {
    card.dataset.animating = "true";
    card.open = true;
    body.style.transition = "none";
    body.style.height = "0px";
    body.style.opacity = "0";
    body.style.transform = "translateY(-6px)";

    window.requestAnimationFrame(function () {
      var targetHeight = body.scrollHeight;
      body.style.transition = "height 260ms ease, opacity 220ms ease, transform 220ms ease";
      body.style.height = targetHeight + "px";
      body.style.opacity = "1";
      body.style.transform = "translateY(0)";
    });

    body.addEventListener("transitionend", function handleOpen(event) {
      if (event.propertyName !== "height") {
        return;
      }
      body.removeEventListener("transitionend", handleOpen);
      body.style.transition = "";
      body.style.height = "auto";
      body.style.opacity = "";
      body.style.transform = "";
      card.dataset.animating = "false";
    });
  }

  function animateAccordionClose(card, body) {
    card.dataset.animating = "true";
    body.style.transition = "none";
    body.style.height = body.scrollHeight + "px";
    body.style.opacity = "1";
    body.style.transform = "translateY(0)";

    window.requestAnimationFrame(function () {
      body.style.transition = "height 240ms ease, opacity 180ms ease, transform 180ms ease";
      body.style.height = "0px";
      body.style.opacity = "0";
      body.style.transform = "translateY(-6px)";
    });

    body.addEventListener("transitionend", function handleClose(event) {
      if (event.propertyName !== "height") {
        return;
      }
      body.removeEventListener("transitionend", handleClose);
      card.open = false;
      body.style.transition = "";
      body.style.height = "";
      body.style.opacity = "";
      body.style.transform = "";
      card.dataset.animating = "false";
    });
  }

  document.querySelectorAll(".price-card--expandable").forEach(function (card) {
    var summary = card.querySelector(".price-card__summary");
    var body = card.querySelector(".price-card__body");

    if (!summary || !body) {
      return;
    }

    if (card.open) {
      body.style.height = "auto";
    }

    summary.addEventListener("click", function (event) {
      if (reduceMotion) {
        return;
      }

      event.preventDefault();

      if (card.dataset.animating === "true") {
        return;
      }

      if (card.open) {
        animateAccordionClose(card, body);
      } else {
        animateAccordionOpen(card, body);
      }
    });
  });

  var lastScrollY = window.scrollY;
  var ticking = false;

  function updateHeaderState() {
    var currentScroll = window.scrollY;
    var scrolled = currentScroll > 24;
    var menuOpen = mobileNav && mobileNav.classList.contains("is-open");

    if (siteHeader) {
      siteHeader.classList.toggle("is-scrolled", scrolled);

      if (menuOpen || currentScroll < 120 || currentScroll < lastScrollY || Math.abs(currentScroll - lastScrollY) < 10) {
        siteHeader.classList.remove("is-hidden");
      } else if (currentScroll > lastScrollY) {
        siteHeader.classList.add("is-hidden");
      }
    }

    if (headerProgressBar) {
      var scrollable = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
      var progress = Math.min(1, Math.max(0, currentScroll / scrollable));
      headerProgressBar.style.transform = "scaleX(" + progress.toFixed(4) + ")";
    }

    lastScrollY = currentScroll;
    ticking = false;
  }

  window.addEventListener("scroll", function () {
    if (!ticking) {
      window.requestAnimationFrame(updateHeaderState);
      ticking = true;
    }
  }, { passive: true });

  updateHeaderState();

  var revealItems = document.querySelectorAll(".reveal");
  revealItems.forEach(function (item, index) {
    item.style.transitionDelay = (index % 4) * 55 + "ms";
  });

  if ("IntersectionObserver" in window) {
    var revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.18 });

    revealItems.forEach(function (item) {
      revealObserver.observe(item);
    });
  } else {
    revealItems.forEach(function (item) {
      item.classList.add("is-visible");
    });
  }
})();
