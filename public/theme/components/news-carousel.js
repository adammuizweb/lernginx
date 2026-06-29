(function (global) {
  function initNewsCarousel(rootSelector = '#lpmf-carousel', opts = {}) {
    const AUTOPLAY_INTERVAL = opts.autoplayInterval || 3800;
    const TRANSITION_MS = opts.transitionMs || 500;
    const EASING = opts.easing || 'cubic-bezier(.22,.9,.35,1)';

    const root = document.querySelector(rootSelector);
    if (!root) return null;
    const wrap = root.closest('.lpmf-berita-wrap') || document;
    const dotsWrap = wrap.querySelector('.lpmf-dots') || document.getElementById('lpmf-dots');
    const track = root.querySelector('.lpmf-track');
    if (!track || !dotsWrap) return null;

    let autoplayTimer = null;
    let slidesPerView = computeSlidesPerView();
    let originals = [];       // array of original nodes
    let originalsCount = 0;
    let clonesN = 0;          // number of clones on each side
    let currentIndex = 0;     // index in track.children (after clones added)
    let isTransitioning = false;

    // dot state simplified
    // ----- utilities -----
    function computeSlidesPerView() {
      const w = window.innerWidth;
      if (w < 640) return 1;
      if (w < 1000) return 2;
      return 3;
    }

    function cards() { return Array.from(track.children); }

    function getCardWidthAndGap() {
      const c = cards();
      if (!c.length) return { w: 0, gap: 0 };
      const gap = parseFloat(getComputedStyle(track).gap) || 28;
      const w = c[0].getBoundingClientRect().width;
      return { w, gap };
    }

    // ----- cloning / rebuild logic -----
    function rebuild() {
      // take originals from current track but avoid existing clones
      let initial = Array.from(track.querySelectorAll('.lpmf-card')).filter(n => !n.classList.contains('lpmf-clone'));
      if (initial.length === 0) {
        // fallback: use all children (if no class)
        initial = Array.from(track.children);
      }
      originals = initial.map(n => n.cloneNode(true));
      originalsCount = originals.length;
      if (originalsCount === 0) return;

      slidesPerView = computeSlidesPerView();
      clonesN = Math.min(slidesPerView, originalsCount);

      // rebuild track: clonesBefore + originals + clonesAfter
      track.innerHTML = '';

      // clonesBefore: last clonesN originals (preserve order)
      const before = originals.slice(originalsCount - clonesN).map(n => {
        const c = n.cloneNode(true);
        c.classList.add('lpmf-clone');
        return c;
      });
      before.forEach(n => track.appendChild(n));

      // originals
      originals.forEach(n => {
        const c = n.cloneNode(true);
        c.classList.remove('lpmf-clone');
        track.appendChild(c);
      });

      // clonesAfter: first clonesN originals
      const after = originals.slice(0, clonesN).map(n => {
        const c = n.cloneNode(true);
        c.classList.add('lpmf-clone');
        return c;
      });
      after.forEach(n => track.appendChild(n));

      // start at first original
      currentIndex = clonesN;

      // rebuild dots
      buildDots();

      // set initial position without animation
      setPositionNoTransition(currentIndex);
    }

    // --- simplified dot logic: one dot per original slide ---
    function buildDots() {
      dotsWrap.innerHTML = '';
      for (let i = 0; i < originalsCount; i++) {
        const btn = document.createElement('button');
        btn.className = 'lpmf-dot';
        btn.setAttribute('aria-selected', 'false');
        btn.setAttribute('aria-label', 'Slide ' + (i + 1));
        (function (idx) {
          btn.addEventListener('click', function () {
            // clicking a dot jumps to that original start
            goToByOriginalStart(idx);
            resetAutoplay();
          });
        })(i);
        dotsWrap.appendChild(btn);
      }
      updateDotsImmediate();
    }

    function logicalIndex() {
      if (originalsCount === 0) return 0;
      const raw = currentIndex - clonesN;
      return ((raw % originalsCount) + originalsCount) % originalsCount;
    }

    function updateDotsImmediate() {
      const dotNodes = Array.from(dotsWrap.children);
      if (!dotNodes.length) return;
      const li = logicalIndex();
      dotNodes.forEach((d, i) => d.setAttribute('aria-selected', i === li ? 'true' : 'false'));
    }

    function updateDots() {
      // keep it immediate — we want dot state to reflect visual position
      updateDotsImmediate();
    }

    // ----- positioning helpers -----
    function setPositionNoTransition(indexToSet) {
      const { w, gap } = getCardWidthAndGap();
      const translate = - (w + gap) * indexToSet;
      const prevTrans = track.style.transition;
      track.style.transition = 'none';
      track.style.transform = `translateX(${translate}px)`;
      // force reflow then restore transition
      requestAnimationFrame(() => {
        track.getBoundingClientRect();
        track.style.transition = prevTrans || `transform ${TRANSITION_MS}ms ${EASING}`;
      });
      // after reposition without anim, sync dots immediately
      updateDotsImmediate();
    }

    function setPosition(indexToSet, animate = true) {
      const { w, gap } = getCardWidthAndGap();
      const translate = - (w + gap) * indexToSet;
      if (!animate) {
        setPositionNoTransition(indexToSet);
      } else {
        track.style.transition = `transform ${TRANSITION_MS}ms ${EASING}`;
        track.style.transform = `translateX(${translate}px)`;
      }
      // update dots to match visual
      updateDots();
    }

    // ----- navigation -----
    function next() {
      if (isTransitioning) return;
      isTransitioning = true;
      currentIndex++;
      setPosition(currentIndex, true);
    }
    function prev() {
      if (isTransitioning) return;
      isTransitioning = true;
      currentIndex--;
      setPosition(currentIndex, true);
    }

    // go to an original start index (0..originalsCount - 1)
    function goToByOriginalStart(originalStart) {
      if (originalsCount === 0) return;
      originalStart = Math.max(0, Math.min(originalStart, Math.max(0, originalsCount - 1)));
      currentIndex = clonesN + originalStart;
      setPosition(currentIndex, true);
    }

    // transitionend handler -> check clones and reset without animation
    track.addEventListener('transitionend', () => {
      isTransitioning = false;
      if (originalsCount === 0) return;
      const total = originalsCount;
      // moved beyond rightmost original set -> wrap left by subtracting total
      if (currentIndex >= clonesN + total) {
        currentIndex = currentIndex - total;
        setPositionNoTransition(currentIndex);
        updateDotsImmediate();
      } else if (currentIndex < clonesN) {
        // moved into left clones -> wrap right by adding total
        currentIndex = currentIndex + total;
        setPositionNoTransition(currentIndex);
        updateDotsImmediate();
      } else {
        // normal stop inside originals -> update dots
        updateDots();
      }
    });

    // ----- dragging (pointer events) -----
    let dragging = false, startX = 0, prevTranslate = 0;
    function getTranslateX() {
      const t = getComputedStyle(track).transform;
      if (t && t !== 'none') {
        const m = t.match(/^matrix\((.+)\)$/);
        if (m) {
          const vals = m[1].split(', ');
          return parseFloat(vals[4]) || 0;
        }
      }
      return 0;
    }

    track.addEventListener('pointerdown', (e) => {
      dragging = true;
      startX = e.clientX;
      prevTranslate = getTranslateX();
      track.style.transition = 'none';
      stopAutoplay();
      try { track.setPointerCapture(e.pointerId); } catch(e){}
    });
    window.addEventListener('pointermove', (e) => {
      if (!dragging) return;
      const dx = e.clientX - startX;
      track.style.transform = `translateX(${prevTranslate + dx}px)`;
    });
    window.addEventListener('pointerup', (e) => {
      if (!dragging) return;
      dragging = false;
      const dx = e.clientX - startX;
      track.style.transition = `transform ${TRANSITION_MS}ms ${EASING}`;
      const threshold = 60;
      if (dx < -threshold) next();
      else if (dx > threshold) prev();
      else setPosition(currentIndex, true);
      resetAutoplay();
    });
    track.addEventListener('pointercancel', () => {
      if (!dragging) return;
      dragging = false;
      setPosition(currentIndex, true);
      resetAutoplay();
    });

    // ----- autoplay and controls -----
    function startAutoplay() {
      stopAutoplay();
      autoplayTimer = setInterval(next, AUTOPLAY_INTERVAL);
    }
    function stopAutoplay() {
      if (autoplayTimer) { clearInterval(autoplayTimer); autoplayTimer = null; }
    }
    function resetAutoplay() { stopAutoplay(); startAutoplay(); }

    root.addEventListener('mouseenter', stopAutoplay);
    root.addEventListener('mouseleave', startAutoplay);
    root.addEventListener('focusin', stopAutoplay);
    root.addEventListener('focusout', startAutoplay);

    // keyboard nav
    root.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') { prev(); resetAutoplay(); }
      if (e.key === 'ArrowRight') { next(); resetAutoplay(); }
    });

    // resize -> rebuild clones when slidesPerView changed
    let resizeTimer = null;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        const prevSPV = slidesPerView;
        slidesPerView = computeSlidesPerView();
        if (prevSPV !== slidesPerView) {
          // rebuild to adjust clones and mapping
          rebuild();
        } else {
          // sizes changed, reposition without rebuild
          setPositionNoTransition(currentIndex);
        }
      }, 120);
    });

    // ----- init sequence -----
    function init() {
      // collect initial originals (prefer .lpmf-card children)
      const initial = Array.from(track.querySelectorAll('.lpmf-card')).filter(n => !n.classList.contains('lpmf-clone'));
      if (initial.length === 0) {
        const fallback = Array.from(track.children);
        if (fallback.length === 0) return;
        fallback.forEach(n => n.classList.remove('lpmf-clone'));
      }
      rebuild();
      startAutoplay();
    }

    // slight delay to allow images/layout to settle
    setTimeout(init, 20);

    // expose control handle
    return {
      next, prev, goToByOriginalStart,
      start: startAutoplay, stop: stopAutoplay,
      destroy() { stopAutoplay(); /* cleanup listeners not implemented */ }
    };
  }

  // expose globally
  global.initNewsCarousel = initNewsCarousel;
})(window);
