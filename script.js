/* ==========================================================
   AdAge — Interactive Behaviors
   ========================================================== */

// ---------- Loader ----------
window.addEventListener('load', () => {
  setTimeout(() => {
    document.getElementById('loader').classList.add('hide');
  }, 1800);
});

// ---------- Custom Cursor ----------
// const cursor = document.getElementById('cursor');
// const follower = document.getElementById('cursorFollower');
// let mouseX = 0, mouseY = 0;
// let followerX = 0, followerY = 0;
// 
// document.addEventListener('mousemove', (e) => {
//   mouseX = e.clientX;
//   mouseY = e.clientY;
//   cursor.style.left = mouseX + 'px';
//   cursor.style.top = mouseY + 'px';
// });
// 
// function animateFollower() {
//   followerX += (mouseX - followerX) * 0.15;
//   followerY += (mouseY - followerY) * 0.15;
//   follower.style.left = followerX + 'px';
//   follower.style.top = followerY + 'px';
//   requestAnimationFrame(animateFollower);
// }
// animateFollower();
// 
// const growTargets = document.querySelectorAll('a, button, .work-item, .service-card, input, textarea, .filter-btn, .socials a, .testimonial');
// growTargets.forEach(el => {
//   el.addEventListener('mouseenter', () => {
//     cursor.classList.add('grow');
//     follower.classList.add('grow');
//   });
//   el.addEventListener('mouseleave', () => {
//     cursor.classList.remove('grow');
//     follower.classList.remove('grow');
//   });
// });

// ---------- Nav Scroll ----------
const nav = document.getElementById('nav');
window.addEventListener('scroll', () => {
  if (window.scrollY > 40) {
    nav.classList.add('scrolled');
  } else {
    nav.classList.remove('scrolled');
  }
});

// ---------- Mobile Menu ----------
const burger = document.getElementById('navBurger');
const mobileMenu = document.getElementById('mobileMenu');

burger.addEventListener('click', () => {
  burger.classList.toggle('open');
  mobileMenu.classList.toggle('open');
});

mobileMenu.querySelectorAll('a').forEach(a => {
  a.addEventListener('click', () => {
    burger.classList.remove('open');
    mobileMenu.classList.remove('open');
  });
});

// ---------- Reveal on Scroll ----------
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('in');
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });

document.querySelectorAll('.reveal, .reveal-line').forEach(el => observer.observe(el));

// ---------- Counter ----------
const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const el = entry.target;
      const target = parseInt(el.getAttribute('data-count'), 10);
      const duration = 1600;
      const start = performance.now();

      function step(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target);
        if (progress < 1) requestAnimationFrame(step);
        else el.textContent = target;
      }
      requestAnimationFrame(step);
      counterObserver.unobserve(el);
    }
  });
}, { threshold: 0.4 });

document.querySelectorAll('.stat-num').forEach(el => counterObserver.observe(el));

// ---------- Work Filters ----------
const filterBtns = document.querySelectorAll('.filter-btn');
const workItems = document.querySelectorAll('.work-item');

filterBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    filterBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const filter = btn.dataset.filter;
    workItems.forEach(item => {
      if (filter === 'all' || item.dataset.cat === filter) {
        item.classList.remove('hidden');
      } else {
        item.classList.add('hidden');
      }
    });
  });
});

// ---------- Contact Form ----------
const form = document.getElementById('contactForm');
const status = document.getElementById('formStatus');

/* Messages are delivered straight to info@adageuniverse.com — no server,
   no API keys, no hosting config. Works on Vercel's free plan as-is.
   (A self-hosted PHP alternative lives in php-alternative/ if ever needed.) */
const FORM_ENDPOINT = 'https://formsubmit.co/ajax/info@adageuniverse.com';

form.addEventListener('submit', async (e) => {
  e.preventDefault();

  const submitBtn = form.querySelector('button[type="submit"]');
  const btn = submitBtn.querySelector('span');
  const original = btn.textContent;

  status.textContent = '';
  status.classList.remove('error');

  const data = Object.fromEntries(new FormData(form).entries());

  if (!data.name.trim() || !data.email.trim() || !data.message.trim()) {
    status.textContent = 'Please fill in your name, email, and message.';
    status.classList.add('error');
    return;
  }

  btn.textContent = 'Sending...';
  submitBtn.disabled = true;

  try {
    const res = await fetch(FORM_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(data),
    });

    let result = {};
    try { result = await res.json(); } catch (_) { /* non-JSON response */ }

    const sent = res.ok && (result.success === true || result.success === 'true' || result.ok === true);

    if (sent) {
      status.textContent = '✓ Message sent. We\'ll get back to you within 24 hours.';
      form.reset();
    } else {
      status.textContent = result.error || 'Something went wrong. Please email info@adageuniverse.com directly.';
      status.classList.add('error');
    }
  } catch (err) {
    status.textContent = 'Network error. Please email info@adageuniverse.com directly.';
    status.classList.add('error');
  } finally {
    btn.textContent = original;
    submitBtn.disabled = false;
    setTimeout(() => {
      status.textContent = '';
      status.classList.remove('error');
    }, 8000);
  }
});

// ---------- Smooth Scroll (with offset for fixed nav) ----------
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      e.preventDefault();
      const offset = 80;
      const top = target.getBoundingClientRect().top + window.scrollY - offset;
      window.scrollTo({ top, behavior: 'smooth' });
    }
  });
});

// ---------- Parallax orbs (subtle) ----------
const orbs = document.querySelectorAll('.hero-orb');
window.addEventListener('scroll', () => {
  const y = window.scrollY;
  orbs.forEach((orb, i) => {
    orb.style.transform = `translateY(${y * (0.2 + i * 0.1)}px)`;
  });
});
