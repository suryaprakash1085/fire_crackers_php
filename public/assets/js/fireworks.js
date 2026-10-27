/* Fire-blast / fireworks engine — dependency-free, tuned for LIGHT backgrounds.
   Public API (window.FW):
     FW.hero(canvas)         ambient rockets that keep launching while the canvas is on screen
     FW.burst(x, y, opts)    one burst at viewport coordinates
     FW.burstAt(el, opts)    burst centred on an element (used by "Add to cart")
     FW.celebrate(n)         n rockets across the screen (order placed, voice order done …)
   Honors prefers-reduced-motion, pauses on hidden tabs / off-screen canvases, caps particles. */
(function () {
  'use strict';
  const reduce = () => !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
  const PAL = [
    ['#ff3d00', '#ff9100', '#ffc400'], ['#7c4dff', '#b388ff', '#ff4081'], ['#0091ea', '#00b8d4', '#2979ff'],
    ['#00c853', '#76d200', '#ffab00'], ['#ff1744', '#ff6090', '#ff9100'], ['#d500f9', '#7c4dff', '#00b0ff'],
  ];
  const SOFT = [['#ffb38a', '#ffd48a', '#ffe9a8'], ['#c9b8ff', '#e3b8ff', '#ffb8d9'], ['#a8dcff', '#b8f0ff', '#c2d4ff'], ['#b8f0c8', '#e0f5a8', '#ffe0a8']];
  const rnd = (a, b) => a + Math.random() * (b - a);
  const pick = (a) => a[(Math.random() * a.length) | 0];
  const MAX = 900;

  class Stage {
    constructor(canvas, opt = {}) {
      this.c = canvas; this.g = null; try { this.g = canvas.getContext('2d'); } catch (e) {} this.ps = []; this.rk = []; this.fl = [];
      this.auto = !!opt.auto; this.fixed = !!opt.fixed; this.scale = opt.scale || 1; this.iv = opt.interval || [1300, 2500]; this.ymax = opt.ymax || .5; this.soft = !!opt.soft; this.cannons = !!opt.cannons; this.nextCannon = 0;
      this.raf = 0; this.last = 0; this.nextAuto = 0; this.onscreen = true; this.dead = !this.g;
      if (this.dead) return;   // no canvas support: effects silently disabled, the shop keeps working
      this.resize();
      if (this.fixed) addEventListener('resize', () => this.resize());
      else if (window.ResizeObserver) new ResizeObserver(() => this.resize()).observe(canvas.parentElement || canvas);
      if (this.auto) {
        if ('IntersectionObserver' in window) new IntersectionObserver((e) => { this.onscreen = e[0].isIntersecting; if (this.onscreen) this.start(); }).observe(canvas);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) this.start(); });
        this.start();
      }
    }
    resize() {
      const dpr = Math.min(2, window.devicePixelRatio || 1), r = this.c.getBoundingClientRect();
      this.w = Math.max(1, r.width); this.h = Math.max(1, r.height);
      this.c.width = Math.round(this.w * dpr); this.c.height = Math.round(this.h * dpr); this.g.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    start() { if (!this.raf && !this.dead) { this.last = performance.now(); this.raf = requestAnimationFrame((t) => this.frame(t)); } }

    /* rocket flies up, then explodes */
    launch(x, y, o = {}) {
      if (this.dead || reduce()) return;
      const sx = (this.fixed ? x : x) + rnd(-40, 40);
      this.rk.push({ sx, sy: this.h + 10, x: sx, y: this.h + 10, tx: x, ty: y, t: 0, dur: rnd(.75, 1.05), o });
      this.start();
    }
    burst(x, y, o = {}) {
      if (this.dead || reduce()) return;
      if (this.ps.length > MAX) return;
      const pal = o.palette || pick(PAL), type = o.type || pick(['peony', 'peony', 'ring', 'willow', 'heart', 'star']);
      const n = Math.round((o.count || rnd(60, 90)) * this.scale * (innerWidth < 640 ? .7 : 1));
      const power = o.power || rnd(3.4, 5.4);
      for (let i = 0; i < n; i++) {
        const t = (i / n) * Math.PI * 2; let vx, vy, life = rnd(1.0, 1.6), g = .05, drag = .962, size = rnd(1.4, 2.6);
        if (type === 'ring') { vx = Math.cos(t) * power; vy = Math.sin(t) * power; }
        else if (type === 'heart') { const k = power / 17; vx = 16 * Math.pow(Math.sin(t), 3) * k; vy = -(13 * Math.cos(t) - 5 * Math.cos(2 * t) - 2 * Math.cos(3 * t) - Math.cos(4 * t)) * k; }
        else if (type === 'star') { const r = power * (.5 + .5 * Math.abs(Math.cos(2.5 * t))); vx = Math.cos(t - Math.PI / 2) * r; vy = Math.sin(t - Math.PI / 2) * r; }
        else if (type === 'willow') { const a = rnd(0, Math.PI * 2), s = power * .75 * Math.sqrt(Math.random()); vx = Math.cos(a) * s; vy = Math.sin(a) * s; life = rnd(1.9, 2.7); g = .028; drag = .975; }
        else { const a = rnd(0, Math.PI * 2), s = power * (.25 + .75 * Math.sqrt(Math.random())); vx = Math.cos(a) * s; vy = Math.sin(a) * s; }
        this.ps.push({ x, y, vx, vy, life, max: life, g, drag, size, col: type === 'willow' ? '#f5a300' : pick(pal), tw: Math.random() < .35 });
      }
      this.fl.push({ x, y, r: 4, max: 70 * (power / 4.4), a: .5, col: pal[0] });
      this.start();
    }
    /* a "cannon" fires a fan of sparks from a bottom corner toward the middle */
    cannon(side) {
      if (this.dead || reduce() || this.ps.length > MAX) return;
      const left = side === 'l', x = left ? 14 : this.w - 14, y = this.h - 8, pal = pick(PAL), n = innerWidth < 640 ? 22 : 38;
      for (let i = 0; i < n; i++) {
        const a = (left ? -1 : -1) * rnd(.95, 1.65) * (left ? 1 : 1), ang = left ? -rnd(.55, 1.25) : -Math.PI + rnd(.55, 1.25), sp = rnd(5, 11);
        this.ps.push({ x, y, vx: Math.cos(ang) * sp, vy: Math.sin(ang) * sp, life: rnd(1.1, 1.9), max: 1.9, g: .11, drag: .985, size: rnd(1.6, 2.8), col: pick(pal), tw: Math.random() < .5 });
      }
      this.fl.push({ x, y, r: 3, max: 46, a: .55, col: pal[0] }); this.start();
    }
    finale(n = 5) { for (let i = 0; i < n; i++) setTimeout(() => this.launch(rnd(.12, .88) * this.w, rnd(.12, .45) * this.h), i * 260); }

    frame(now) {
      this.raf = 0; if (this.dead) return;
      try { this.step(now); } catch (e) { this.ps.length = 0; this.rk.length = 0; this.fl.length = 0; if (this.auto) this.raf = requestAnimationFrame((t) => this.frame(t)); }
    }
    step(now) {
      const dt = Math.max(.001, Math.min(.05, (now - this.last) / 1000 || .016)), k = dt * 60, g = this.g; this.last = now;   // never negative (first rAF timestamp can precede performance.now())
      g.clearRect(0, 0, this.w, this.h);
      if (this.auto && this.onscreen && !document.hidden && !reduce() && now >= this.nextAuto) {
        this.launch(rnd(.06, .94) * this.w, rnd(.12, this.ymax) * this.h, this.soft ? { palette: pick(SOFT), power: rnd(2.6, 3.8), count: rnd(34, 52) } : undefined); this.nextAuto = now + rnd(this.iv[0], this.iv[1]);
      }
      if (this.cannons && this.onscreen && !document.hidden && !reduce() && now >= this.nextCannon) { this.cannon(Math.random() < .5 ? 'l' : 'r'); this.nextCannon = now + rnd(2600, 4800); }
      /* rockets */
      for (let i = this.rk.length - 1; i >= 0; i--) {
        const r = this.rk[i]; r.t += dt / r.dur; const e = 1 - Math.pow(1 - Math.min(1, r.t), 2.2);
        const px = r.x, py = r.y; r.x = r.sx + (r.tx - r.sx) * e; r.y = r.sy + (r.ty - r.sy) * e;
        g.strokeStyle = 'rgba(255,140,0,.9)'; g.lineWidth = 2.2; g.lineCap = 'round'; g.beginPath(); g.moveTo(px - (r.x - px) * 1.2, py - (r.y - py) * 1.2); g.lineTo(r.x, r.y); g.stroke();
        g.fillStyle = '#ff6d00'; g.beginPath(); g.arc(r.x, r.y, 2.4, 0, 6.283); g.fill();
        if (this.ps.length < MAX) this.ps.push({ x: r.x, y: r.y, vx: rnd(-.4, .4), vy: rnd(.3, 1.2), life: .45, max: .45, g: .02, drag: .95, size: 1.3, col: '#ff9100', tw: false });
        if (r.t >= 1) { this.rk.splice(i, 1); this.burst(r.tx, r.ty, r.o); }
      }
      /* flashes (expanding soft ring) */
      for (let i = this.fl.length - 1; i >= 0; i--) {
        const f = this.fl[i]; f.r = Math.max(1, f.r + (f.max - f.r) * .18 * k + .6 * k); f.a -= .035 * k;
        if (f.a <= 0) { this.fl.splice(i, 1); continue; }
        g.globalAlpha = f.a; g.strokeStyle = f.col; g.lineWidth = 2; g.beginPath(); g.arc(f.x, f.y, f.r, 0, 6.283); g.stroke();
        g.globalAlpha = f.a * .35; g.fillStyle = f.col; g.beginPath(); g.arc(f.x, f.y, f.r * .35, 0, 6.283); g.fill();
      }
      /* particles */
      g.lineCap = 'round';
      for (let i = this.ps.length - 1; i >= 0; i--) {
        const p = this.ps[i]; p.life -= dt;
        if (p.life <= 0) { this.ps.splice(i, 1); continue; }
        const d = Math.pow(p.drag, k); p.vx *= d; p.vy = p.vy * d + p.g * k; const ox = p.x, oy = p.y; p.x += p.vx * k; p.y += p.vy * k;
        let a = Math.pow(p.life / p.max, .8); if (p.tw && a < .5 && Math.random() < .3) a *= .3;
        g.globalAlpha = a * .22; g.fillStyle = p.col; g.beginPath(); g.arc(p.x, p.y, p.size * 2.6, 0, 6.283); g.fill();   // soft glow
        g.globalAlpha = a; g.strokeStyle = p.col; g.lineWidth = p.size; g.beginPath(); g.moveTo(ox - p.vx * 1.6, oy - p.vy * 1.6); g.lineTo(p.x, p.y); g.stroke();  // tail
        g.fillStyle = p.col; g.beginPath(); g.arc(p.x, p.y, p.size * .75, 0, 6.283); g.fill();
      }
      g.globalAlpha = 1;
      if (this.ps.length || this.rk.length || this.fl.length || (this.auto && this.onscreen && !document.hidden)) this.raf = requestAnimationFrame((t) => this.frame(t));
    }
  }

  let G = null;
  const global = () => {
    if (G) return G;
    const c = document.createElement('canvas'); c.id = 'fw-global'; c.setAttribute('aria-hidden', 'true'); document.body.appendChild(c);
    return (G = new Stage(c, { fixed: true }));
  };
  const FW = (window.FW = {
    hero: (canvas) => { try { return new Stage(canvas, { auto: true }); } catch (e) { return null; } },
    burst: (x, y, o) => { try { global().burst(x, y, o); } catch (e) {} },
    burstAt: (el, o) => { try { if (!el) return; const r = el.getBoundingClientRect(); global().burst(r.left + r.width / 2, r.top + r.height / 2, Object.assign({ count: 46, power: 3.3, type: 'peony' }, o || {})); } catch (e) {} },
    ambient: () => {   // soft, slow, always-on blasts behind the whole page
      if (reduce() || document.getElementById('fw-ambient')) return;
      const c = document.createElement('canvas'); c.id = 'fw-ambient'; c.setAttribute('aria-hidden', 'true'); document.body.insertBefore(c, document.body.firstChild);
      try { new Stage(c, { auto: true, fixed: true, soft: true, cannons: true, scale: 1, ymax: .62, interval: [2000, 3800] }); } catch (e) {}
    },
    celebrate: (n = 5) => { try { global().finale(n); } catch (e) {} },
  });
  document.addEventListener('DOMContentLoaded', () => { document.querySelectorAll('canvas[data-fw-hero]').forEach((c) => FW.hero(c)); if (!document.body.hasAttribute('data-no-ambient')) FW.ambient(); });
})();
