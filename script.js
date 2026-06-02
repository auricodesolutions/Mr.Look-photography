// ------------------------------
// Mr.Look Photography — Scripts
// ------------------------------
(function () {
  "use strict";

  // ===== Year =====
  const yearEl = document.getElementById("year");
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  // ===== Hero image fade-in =====
  (function heroFade() {
    const heroImg = document.querySelector(".hero-media .fade-in");
    if (!heroImg) return;
    const on = () => heroImg.classList.add("in");
    if (heroImg.complete && heroImg.naturalWidth) on();
    else heroImg.addEventListener("load", on, { once: true });
  })();

  // ===== Scroll-aware header (shrink + shadow) =====
  (function headerScroll() {
    const header = document.querySelector(".header");
    if (!header) return;
    const onScroll = () => {
      if (window.scrollY > 8) header.classList.add("scrolled");
      else header.classList.remove("scrolled");
    };
    onScroll();
    addEventListener("scroll", onScroll, { passive: true });
  })();

  // ===== Mobile nav toggle + smooth scroll + auto-close =====
  (function navMenu() {
    const nav = document.getElementById("nav");
    const burger = document.querySelector(".hamburger");
    if (burger && nav) {
      burger.addEventListener("click", () => {
        const open = nav.classList.toggle("open");
        burger.setAttribute("aria-expanded", String(open));
        burger.classList.toggle("active", open);
      });
    }
    
    
    
    // Smooth-scroll for in-page links & close mobile nav
    document.querySelectorAll('.nav a[href^="#"], .scroll-down[href^="#"]').forEach((a) => {
      a.addEventListener("click", (e) => {
        const hash = a.getAttribute("href");
        if (!hash || hash === "#") return;
        const target = document.querySelector(hash);
        if (!target) return;
        e.preventDefault();
        target.scrollIntoView({ behavior: "smooth", block: "start" });
        if (nav?.classList.contains("open")) {
          nav.classList.remove("open");
          burger?.setAttribute("aria-expanded", "false");
          burger?.classList.remove("active");
        }
      });
    });
  })();

  // ===== Progress bar (works with width or transform) =====
  (function progressBar() {
    const bar = document.querySelector(".progress-bar");
    if (!bar) return;
    const setBar = () => {
      const h = document.documentElement;
      const p = (h.scrollTop / (h.scrollHeight - h.clientHeight)) || 0;
      bar.style.width = (p * 100).toFixed(2) + "%";
      bar.style.transform = `scaleX(${p})`; // also set transform just in case your CSS uses it
      bar.style.transformOrigin = "left center";
    };
    setBar();
    addEventListener("scroll", setBar, { passive: true });
    addEventListener("resize", setBar, { passive: true });
  })();

  // ===== Scroll reveal: auto-annotate & stagger; toggles .show on [data-animate] =====
  (function reveal() {
    const auto = [
      ".section-header",
      ".masonry .card-img",
      ".service-card",
      ".pkg-card",          // packages carousel cards
      ".review-card",       // reviews cards
      ".accordion details",
      ".footer .map-card",
      ".footer .btn-icon",
    ];
    auto.forEach((sel) => {
      document.querySelectorAll(sel).forEach((el) => el.setAttribute("data-animate", ""));
    });

    const staggerSets = [
      [".masonry", ".card-img", 60],
      [".service-grid", ".service-card", 80],
      [".ctrack", ".pkg-card", 80],
      [".rtrack", ".review-card", 80],
    ];
    staggerSets.forEach(([rootSel, childSel, step]) => {
      const root = document.querySelector(rootSel);
      if (!root) return;
      root.querySelectorAll(childSel).forEach((el, i) => {
        el.style.setProperty("--delay", i * (step / 1000) + "s");
      });
    });

    const els = document.querySelectorAll("[data-animate]");
    if (!els.length) return;
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            e.target.classList.add("show");
            io.unobserve(e.target);
          }
        });
      },
      { threshold: 0.18, rootMargin: "0px 0px -8% 0px" }
    );
    els.forEach((el) => io.observe(el));
  })();

  // ===== Lightbox for masonry gallery + packages =====
  (function lightbox() {
    const lb = document.getElementById("lightbox");
    if (!lb) return; // ensure the single global lightbox exists
    const lbImg  = lb.querySelector(".lightbox-img");
    const lbCap  = lb.querySelector(".lightbox-cap");
    const closeBtn = lb.querySelector(".lightbox-close");
    const prevBtn  = lb.querySelector(".lightbox-prev");
    const nextBtn  = lb.querySelector(".lightbox-next");

    // Collect both gallery and package images
    const imgs = Array.from(document.querySelectorAll(".masonry img, #packages .pkg-img img"));
    if (!imgs.length) return;

    let idx = 0;

    function srcFor(el){
      // Prefer high-res if provided via data-large
      return el.getAttribute("data-large") || el.currentSrc || el.src;
    }
    function show(i){
      const im = imgs[i];
      if (!im) return;
      lbImg.src = srcFor(im);
      lbImg.alt = im.alt || "Preview";
      lbCap.textContent = im.alt || "";
    }
    function open(i){
      idx = i;
      show(idx);
      lb.classList.add("open");
    }
    function step(dir){
      idx = (idx + dir + imgs.length) % imgs.length;
      show(idx);
    }
    function close(){
      lb.classList.remove("open");
    }

    // Open on click (only when clicking the image, buttons remain normal)
    imgs.forEach((im, i) => {
      im.style.cursor = "zoom-in";
      im.addEventListener("click", () => open(i), { passive: true });
    });

    // Controls & interactions
    closeBtn?.addEventListener("click", close);
    prevBtn?.addEventListener("click", () => step(-1));
    nextBtn?.addEventListener("click", () => step(1));
    lb.addEventListener("click", (e) => { if (e.target === lb) close(); });
    addEventListener("keydown", (e) => {
      if (!lb.classList.contains("open")) return;
      if (e.key === "Escape") close();
      if (e.key === "ArrowLeft") step(-1);
      if (e.key === "ArrowRight") step(1);
    });

    // Basic swipe support
    let startX = 0;
    lb.addEventListener("touchstart", (e) => { startX = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener("touchend", (e) => {
      const dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 40) step(dx > 0 ? -1 : 1);
    }, { passive: true });
  })();

  // ===== Packages carousel (buttons + dots) =====
  (function packagesCarousel() {
    const wrap = document.getElementById("pkgCarousel");
    if (!wrap) return;

    const view = wrap.querySelector(".cview");
    const track = wrap.querySelector(".ctrack");
    const prev = wrap.querySelector(".cprev");
    const next = wrap.querySelector(".cnext");
    const dotsWrap = document.getElementById("pkgDots");
    if (!view || !track) return;

    const step = () => Math.max(260, Math.round(view.clientWidth * 0.88));
    const go = (dir) => view.scrollBy({ left: dir * step(), behavior: "smooth" });

    prev?.addEventListener("click", () => go(-1));
    next?.addEventListener("click", () => go(1));

    // Horizontal wheel scrolling
    view.addEventListener(
      "wheel",
      (e) => {
        if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
          e.preventDefault();
          view.scrollBy({ left: e.deltaY, behavior: "auto" });
        }
      },
      { passive: false }
    );

    function setActiveDot() {
      if (!dotsWrap) return;
      const dots = [...dotsWrap.children];
      if (!dots.length) return;
      const cards = [...track.children];
      let index = 0,
        min = Infinity;
      const vrect = view.getBoundingClientRect();
      cards.forEach((card, i) => {
        const dx = Math.abs(card.getBoundingClientRect().left - vrect.left);
        if (dx < min) {
          min = dx;
          index = i;
        }
      });
      dots.forEach((d, i) => d.classList.toggle("active", i === index));
    }

    function buildDots() {
      if (!dotsWrap) return;
      dotsWrap.innerHTML = "";
      const items = track.children.length;
      for (let i = 0; i < items; i++) {
        const d = document.createElement("span");
        d.className = "dot";
        d.addEventListener("click", () => {
          const card = track.children[i];
          if (!card) return;
          const rect = card.getBoundingClientRect();
          const vrect = view.getBoundingClientRect();
          const offset = rect.left - vrect.left;
          view.scrollBy({ left: offset, behavior: "smooth" });
        });
        dotsWrap.appendChild(d);
      }
      setActiveDot();
    }

    buildDots();
    view.addEventListener("scroll", () => requestAnimationFrame(setActiveDot));
    addEventListener("resize", () => requestAnimationFrame(setActiveDot));
  })();

  // ===== Reviews slider + Add Review (localStorage) =====
  (function reviews() {
    const view = document.getElementById("revView");
    const track = document.getElementById("reviewsTrack");
    if (!view || !track) return;

    const prev = document.querySelector(".rprev");
    const next = document.querySelector(".rnext");
    const form = document.getElementById("reviewForm");
    const msg = document.getElementById("reviewMsg");

    const step = () => Math.max(260, Math.round(view.clientWidth * 0.9));

    prev?.addEventListener("click", () => view.scrollBy({ left: -step(), behavior: "smooth" }));
    next?.addEventListener("click", () => view.scrollBy({ left: step(), behavior: "smooth" }));

    view.addEventListener(
      "wheel",
      (e) => {
        if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
          e.preventDefault();
          view.scrollBy({ left: e.deltaY, behavior: "auto" });
        }
      },
      { passive: false }
    );

    // Storage helpers
    const KEY = "mrlook_reviews_v1";
    const getStored = () => {
      try {
        return JSON.parse(localStorage.getItem(KEY) || "[]");
      } catch {
        return [];
      }
    };
    const setStored = (arr) => localStorage.setItem(KEY, JSON.stringify(arr));

    function escapeHtml(s) {
      return (s || "").replace(/[&<>"']/g, (m) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[m]));
    }
    function renderItem({ name, rating, text }) {
      const fig = document.createElement("figure");
      fig.className = "review-card";
      const r = Math.max(1, Math.min(5, Number(rating) || 5));
      const stars = "★★★★★".slice(0, r);
      fig.innerHTML = `
        <div class="review-stars" aria-label="${r} out of 5">${stars}</div>
        <blockquote>${escapeHtml(text)}</blockquote>
        <figcaption>— ${escapeHtml(name)}</figcaption>
      `;
      track.prepend(fig); // newest first
    }

    // Seed from storage
    getStored().forEach(renderItem);

    // Form handling
    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        if (!form.reportValidity?.()) {
          // basic fallback
        }
        const data = new FormData(form);
        const name = (data.get("name") || "").toString().trim() || "Anonymous";
        const rating = Number(data.get("rating") || 5);
        const text = (data.get("text") || "").toString().trim();
        if (!text) return;

        const payload = { name, rating, text };
        const cur = getStored();
        cur.push(payload);
        setStored(cur);
        renderItem(payload);

        form.reset();
        const r5 = form.querySelector("#r5");
        if (r5) r5.checked = true;
        if (msg) {
          msg.textContent = "Thanks! Your review was added.";
          setTimeout(() => (msg.textContent = ""), 3000);
        }
      });
    }
  })();

  // ===== Testimonials gentle auto scroll (kept for backward-compat; safe if absent) =====
  (function testiAuto() {
    const row = document.getElementById("testiRow");
    if (!row) return;
    let raf;
    function loop() {
      row.scrollLeft += 0.3;
      if (row.scrollLeft + row.clientWidth >= row.scrollWidth - 1) row.scrollLeft = 0;
      raf = requestAnimationFrame(loop);
    }
    loop();
    row.addEventListener("mouseenter", () => cancelAnimationFrame(raf));
    row.addEventListener("mouseleave", loop);
  })();

  // ===== Booking form helpers (remember contact fields) =====
  (function booking() {
    const form = document.getElementById("bookForm");
    const toast = document.getElementById("toast");
    if (!form) return;

    ["name", "email", "phone"].forEach((key) => {
      const el = form.querySelector(`[name="${key}"]`);
      if (!el) return;
      try {
        el.value = localStorage.getItem("mrlook_" + key) || "";
      } catch {}
      el.addEventListener("input", () => {
        try {
          localStorage.setItem("mrlook_" + key, el.value);
        } catch {}
      });
    });

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (form.reportValidity && !form.reportValidity()) return;

      const payload = Object.fromEntries(new FormData(form).entries());
      console.log("Booking request", payload);

      // Example POST
      // await fetch('https://your-backend/booking', {
      //   method:'POST',
      //   headers:{ 'Content-Type':'application/json' },
      //   body: JSON.stringify(payload)
      // });

      if (toast) {
        toast.textContent = "Request sent! We’ll confirm availability shortly.";
        toast.classList.add("show");
        setTimeout(() => toast.classList.remove("show"), 3500);
      }
      form.reset();
    });
  })();

  // ===== Reel modal =====
  (function reelModal() {
    const modal = document.getElementById("reelModal");
    const openBtn = document.getElementById("openReel");
    openBtn?.addEventListener("click", () => modal?.showModal?.());
    modal?.querySelector(".reel-close")?.addEventListener("click", () => modal?.close?.());
  })();
  
  
  

  // ===== Back-to-top button =====
  (function toTopBtn() {
    if (document.getElementById("toTop")) return; // don't duplicate
    const btn = document.createElement("button");
    btn.id = "toTop";
    btn.type = "button";
    btn.setAttribute("aria-label", "Back to top");
    btn.innerHTML = "↑";
    document.body.appendChild(btn);
    const onScroll = () => btn.classList.toggle("show", scrollY > 700);
    addEventListener("scroll", onScroll, { passive: true });
    onScroll();
    btn.addEventListener("click", () => scrollTo({ top: 0, behavior: "smooth" }));
  })();
  
  
/* Optional: make the accordion behave "one-open-at-a-time" */
document.addEventListener('click', (e) => {
  const sm = e.target.closest('.faq-item > summary');
  if (!sm) return;
  const cur = sm.parentElement;
  document.querySelectorAll('.faq-item[open]').forEach(d => {
    if (d !== cur) d.removeAttribute('open');
  });
});


  // ===== Image loading animation (blur-up + shimmer skeleton) =====
  (function imageLoading() {
    const addSkeleton = (img) => {
      const p = img.closest("figure.card-img, .hero-card-media, .pkg-img");
      if (p) p.classList.add("skeleton");
      img.classList.add("is-loading");
    };
    const removeSkeleton = (img) => {
      const p = img.closest("figure.card-img, .hero-card-media, .pkg-img");
      if (p) p.classList.remove("skeleton");
      img.classList.remove("is-loading");
      img.classList.add("is-loaded");
    };
    document.querySelectorAll("img").forEach((img) => {
      if (!img.closest(".hero-media")) {
        img.loading = img.getAttribute("loading") || "lazy";
        img.decoding = "async";
      }
      if (img.complete && img.naturalWidth) {
        img.classList.add("is-loaded");
      } else {
        addSkeleton(img);
        img.addEventListener("load", () => removeSkeleton(img), { once: true });
        img.addEventListener("error", () => removeSkeleton(img), { once: true });
      }
    });
  })();
})();
