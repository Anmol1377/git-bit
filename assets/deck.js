/* Interactive deck engine — vanilla + GSAP. */
const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------------------------------------------------------------- slides */
const slides = $$('.slide');
const dots = $('#dots'), bar = $('#bar');
let i = 0, busy = false;

$('#tot').textContent = slides.length;
slides.forEach((s, n) => {
  const b = document.createElement('button');
  b.title = s.dataset.title;
  b.onclick = () => go(n);
  dots.append(b);
});

function go(n, dir = n > i ? 1 : -1) {
  n = Math.max(0, Math.min(slides.length - 1, n));
  if (n === i || busy) return;
  busy = true;
  const from = slides[i], to = slides[n];
  i = n;
  to.classList.add('live');
  if (reduced) { from.classList.remove('live'); busy = false; }
  else {
    gsap.to(from, { autoAlpha: 0, y: -26 * dir, duration: .3, ease: 'power2.in',
      onComplete: () => { from.classList.remove('live'); gsap.set(from, { clearProps: 'all' }); busy = false; } });
    gsap.fromTo(to, { autoAlpha: 0, y: 34 * dir }, { autoAlpha: 1, y: 0, duration: .5, delay: .16, ease: 'power3.out' });
  }
  enter(to);
  sync();
}
function sync() {
  $('#cur').textContent = i + 1;
  bar.style.width = ((i + 1) / slides.length * 100) + '%';
  $$('button', dots).forEach((d, n) => d.classList.toggle('on', n === i));
  location.hash = i ? '#' + (i + 1) : '';
}

/* per-slide entrance + hooks */
function enter(s) {
  if (!reduced) {
    const kids = $$('.sh,.sub,.lede,.callout,.layer,.cmp-col,.stat,.node,.code,.anno,.card,.pill,.f,.run,.waste-row,.m,.def,.linkcard,.chip,.qrthanks,.qb-left,.qb-right,.mega .word,.pipe-wrap,.blast,.steps-note', s);
    gsap.fromTo(kids, { autoAlpha: 0, y: 22 }, { autoAlpha: 1, y: 0, duration: .55, stagger: .035, delay: .2, ease: 'power3.out', clearProps: 'transform' });
  }
  const t = s.dataset.title;
  if (t === 'Why GitHub') countUp($$('[data-count]', s));
  if (t === 'The waste') countUp([['#mA', 21], ['#mB', 6], ['#mC', 600]].map(([sel, v]) => { const el = $(sel); el.dataset.count = v; return el; }));
  if (t === 'Thanks') confetti();
}
function countUp(els) {
  els.forEach(el => {
    const end = +el.dataset.count;
    if (reduced) return (el.textContent = end);
    gsap.fromTo(el, { innerText: 0 }, { innerText: end, duration: 1.3, ease: 'power2.out', snap: { innerText: 1 }, delay: .3 });
  });
}

/* navigation */
$('#next').onclick = () => go(i + 1);
$('#prev').onclick = () => go(i - 1);
addEventListener('keydown', e => {
  if (e.target.isContentEditable || /input|textarea/i.test(e.target.tagName)) return;
  const k = e.key;
  if (k === 'ArrowRight' || k === 'ArrowDown' || k === ' ' || k === 'PageDown') { e.preventDefault(); go(i + 1); }
  if (k === 'ArrowLeft' || k === 'ArrowUp' || k === 'PageUp') { e.preventDefault(); go(i - 1); }
  if (k === 'Home') go(0);
  if (k === 'End') go(slides.length - 1);
});
let wheelLock = 0;
addEventListener('wheel', e => {
  const sc = e.target.closest('.slide,.code,.term');
  if (sc && sc.scrollHeight > sc.clientHeight + 4) {          // let inner scrollers scroll
    const atTop = sc.scrollTop <= 0, atEnd = sc.scrollTop + sc.clientHeight >= sc.scrollHeight - 2;
    if (!(e.deltaY > 0 ? atEnd : atTop)) return;
  }
  const now = Date.now();
  if (now - wheelLock < 700 || Math.abs(e.deltaY) < 14) return;
  wheelLock = now;
  go(i + (e.deltaY > 0 ? 1 : -1));
}, { passive: true });
let tx = 0, ty = 0;
addEventListener('touchstart', e => { tx = e.touches[0].clientX; ty = e.touches[0].clientY; }, { passive: true });
addEventListener('touchend', e => {
  const dx = e.changedTouches[0].clientX - tx, dy = e.changedTouches[0].clientY - ty;
  if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy)) go(i + (dx < 0 ? 1 : -1));
  else if (Math.abs(dy) > 70) go(i + (dy < 0 ? 1 : -1));
}, { passive: true });

/* ------------------------------------------------------------ background */
const bg = $('#bg'), bx = bg.getContext('2d');
let pts = [], W, H;
function sizeBg() {
  const d = devicePixelRatio || 1;
  W = bg.width = innerWidth * d; H = bg.height = innerHeight * d;
  bg.style.width = innerWidth + 'px'; bg.style.height = innerHeight + 'px';
  pts = Array.from({ length: innerWidth < 700 ? 26 : 60 }, () => ({
    x: Math.random() * W, y: Math.random() * H,
    vx: (Math.random() - .5) * .28 * d, vy: (Math.random() - .5) * .28 * d, r: (Math.random() * 1.7 + .7) * d
  }));
}
function drawBg() {
  bx.clearRect(0, 0, W, H);
  const link = (devicePixelRatio || 1) * 150;
  for (const p of pts) {
    p.x += p.vx; p.y += p.vy;
    if (p.x < 0 || p.x > W) p.vx *= -1;
    if (p.y < 0 || p.y > H) p.vy *= -1;
    bx.beginPath(); bx.arc(p.x, p.y, p.r, 0, 7); bx.fillStyle = 'rgba(88,166,255,.5)'; bx.fill();
  }
  for (let a = 0; a < pts.length; a++) for (let b = a + 1; b < pts.length; b++) {
    const dx = pts[a].x - pts[b].x, dy = pts[a].y - pts[b].y, d = Math.hypot(dx, dy);
    if (d < link) {
      bx.beginPath(); bx.moveTo(pts[a].x, pts[a].y); bx.lineTo(pts[b].x, pts[b].y);
      bx.strokeStyle = `rgba(88,166,255,${(1 - d / link) * .17})`; bx.stroke();
    }
  }
  requestAnimationFrame(drawBg);
}
sizeBg(); addEventListener('resize', sizeBg);
if (!reduced) drawBg();

/* ------------------------------------------------------------- confetti */
const cc = $('#confetti'), cx = cc.getContext('2d');
let bits = [];
function confetti(n = 140) {
  if (reduced) return;
  cc.width = innerWidth; cc.height = innerHeight;
  const cols = ['#58a6ff', '#3fb950', '#bc8cff', '#f778ba', '#d29922'];
  bits = bits.concat(Array.from({ length: n }, () => ({
    x: Math.random() * innerWidth, y: -20 - Math.random() * innerHeight * .4,
    w: 5 + Math.random() * 7, h: 8 + Math.random() * 9,
    vy: 2.2 + Math.random() * 3.6, vx: (Math.random() - .5) * 2.4,
    a: Math.random() * 7, va: (Math.random() - .5) * .3, c: cols[Math.random() * cols.length | 0]
  })));
  if (bits.length === n) tick();
}
function tick() {
  cx.clearRect(0, 0, cc.width, cc.height);
  bits = bits.filter(b => b.y < cc.height + 30);
  for (const b of bits) {
    b.x += b.vx; b.y += b.vy; b.a += b.va;
    cx.save(); cx.translate(b.x, b.y); cx.rotate(b.a);
    cx.fillStyle = b.c; cx.fillRect(-b.w / 2, -b.h / 2, b.w, b.h); cx.restore();
  }
  if (bits.length) requestAnimationFrame(tick); else cx.clearRect(0, 0, cc.width, cc.height);
}
$('#confettiBtn').onclick = () => confetti(200);

/* ------------------------------------------------- 02 · layers of GitHub */
$$('#layers .layer').forEach((b, n) => b.onclick = () => {
  $$('#layers .layer').forEach((x, m) => x.classList.toggle('on', m <= n));
  const msg = [
    '<b>Git</b> alone: your history is on one laptop. Lose the laptop, lose the project.',
    '<b>+ Hosting</b>: now the truth lives in one place everyone can clone. This is the "remote".',
    '<b>+ Collaboration</b>: a Pull Request is a <i>proposal</i> — discussed, reviewed, then merged.',
    '<b>+ Automation</b>: GitHub Actions. Your repo now tests, builds and deploys itself. That is the rest of this talk. →'
  ][n];
  const out = $('#layerOut'); out.innerHTML = msg;
  if (!reduced) gsap.fromTo(out, { autoAlpha: .2, x: -12 }, { autoAlpha: 1, x: 0, duration: .4 });
});

/* ------------------------------------------------------- 03 · why GitHub */
const wipe = $('#wipe');
wipe.oninput = () => {
  const v = +wipe.value;
  wipe.closest('.compare').style.gridTemplateColumns = `${Math.max(12, 100 - v)}fr ${Math.max(12, v)}fr`;
  $('.cmp-col.bad').style.opacity = 1 - v / 160;
  $('.cmp-col.good').style.opacity = .35 + v / 160;
};
wipe.oninput();

/* ------------------------------------------------------ 04 · what fires? */
const evCopy = {
  push: 'you push a commit',
  pull_request: 'someone opens / updates a PR',
  schedule: 'a cron timer fires',
  workflow_dispatch: 'you click "Run workflow"',
  issues: 'an issue is opened or labelled',
  release: 'you publish a release'
};
$$('#events .pill').forEach(p => p.onclick = () => {
  $$('#events .pill').forEach(x => x.classList.remove('on'));
  p.classList.add('on');
  const ev = p.dataset.ev;
  $('#evName').textContent = ev;
  $('#flow .ev small').textContent = evCopy[ev];
  const nodes = $$('#flow .node'), arrows = $$('#flow .arrow');
  nodes.forEach(n => n.classList.remove('fire'));
  arrows.forEach(a => a.classList.remove('hot'));
  nodes.forEach((n, k) => setTimeout(() => {
    n.classList.add('fire');
    arrows[k]?.classList.add('hot');
    setTimeout(() => n.classList.remove('fire'), 900);
  }, k * 260));
});

/* --------------------------------------------------- 05 · YAML anatomy */
const anno = $('#anno');
function showAnno(key) {
  $$('.anno-card', anno).forEach(c => c.classList.toggle('show', c.dataset.y === key));
  $$('#anatomy span').forEach(s => s.classList.toggle('hl', s.dataset.x === key));
}
showAnno('_');
$$('#anatomy span').forEach(s => {
  const on = () => showAnno(s.dataset.x);
  s.onmouseenter = on; s.onclick = on;
});
$('#anatomy').onmouseleave = () => showAnno('_');

/* ------------------------------------------------- 06 · workflow builder */
const pick = { trigger: 'push', os: 'ubuntu-latest', steps: new Set(['node', 'install', 'test']) };
$$('.builder .pills').forEach(g => $$('.pill', g).forEach(p => p.onclick = () => {
  const k = g.dataset.group, v = p.dataset.v;
  if (g.classList.contains('multi')) { pick.steps.has(v) ? pick.steps.delete(v) : pick.steps.add(v); p.classList.toggle('on'); }
  else { $$('.pill', g).forEach(x => x.classList.remove('on')); p.classList.add('on'); pick[k] = v; }
  renderYaml();
}));
const TRIG = {
  push: 'on:\n  push:\n    branches: [main]',
  pr:   'on:\n  pull_request:\n    branches: [main]',
  cron: 'on:\n  schedule:\n    - cron: "0 2 * * *"   # 02:00 UTC daily',
  manual: 'on:\n  workflow_dispatch:\n    inputs:\n      reason:\n        description: Why are you running this?\n        required: false'
};
const STEP = {
  node:    '      - uses: actions/setup-node@v4\n        with:\n          node-version: 20\n          cache: npm',
  install: '      - run: npm ci',
  lint:    '      - run: npm run lint',
  test:    '      - run: npm test',
  build:   '      - run: npm run build',
  deploy:  '      - name: Deploy\n        if: github.ref == \'refs/heads/main\'\n        run: ./scripts/deploy.sh\n        env:\n          TOKEN: ${{ secrets.DEPLOY_TOKEN }}'
};
function renderYaml() {
  const order = ['node', 'install', 'lint', 'test', 'build', 'deploy'].filter(s => pick.steps.has(s));
  const y = [
    'name: CI', '', TRIG[pick.trigger], '', 'jobs:', '  build:',
    `    runs-on: ${pick.os}`, '    steps:', '      - uses: actions/checkout@v4',
    ...order.map(s => STEP[s])
  ].join('\n');
  const el = $('#yamlOut');
  if (reduced) return (el.textContent = y);
  let n = 0;                                    // cheap typewriter
  clearInterval(el._t);
  el.textContent = '';
  el._t = setInterval(() => {
    n += 14;
    el.textContent = y.slice(0, n);
    if (n >= y.length) { clearInterval(el._t); el.textContent = y; }
  }, 8);
}
renderYaml();
$('#copyYaml').onclick = e => {
  navigator.clipboard?.writeText($('#yamlOut').textContent);
  e.target.textContent = 'copied ✓';
  setTimeout(() => e.target.textContent = 'copy', 1400);
};

/* ------------------------------------------------------ 07 · CI/CD demo */
const LOG = {
  checkout: ['$ git clone --depth 1 github.com/Anmol1377/git-bit', 'Receiving objects: 100% (412/412), done.'],
  install:  ['$ npm ci', 'added 284 packages in 11s', 'cache restored from key node-20-lock-8f3a'],
  lint:     ['$ npm run lint', '✔ 0 problems'],
  test:     ['$ npm test', 'PASS  src/button.test.tsx', 'Tests: 34 passed, 34 total'],
  build:    ['$ npm run build', 'dist/index.js  128 kB', '✔ built in 6.2s'],
  deploy:   ['$ ./scripts/deploy.sh', 'uploading…', '✔ live at https://anmol1377.github.io/git-bit/']
};
$('#runPipe').onclick = async e => {
  const btn = e.target, term = $('code', $('#term'));
  if (btn.disabled) return;
  btn.disabled = true; btn.style.opacity = .5;
  $$('#pipeline .stage').forEach(s => s.className = 'stage');
  term.textContent = '';
  const push = s => { term.textContent += s + '\n'; $('#term').scrollTop = 1e5; };
  push('$ git push origin main');
  push('→ event: push  ·  workflow: CI  ·  runner: ubuntu-latest\n');
  for (const st of $$('#pipeline .stage')) {
    st.classList.add('run');
    for (const l of LOG[st.dataset.s]) { push('  ' + l); await sleep(reduced ? 0 : 320); }
    st.classList.remove('run'); st.classList.add('done');
    await sleep(reduced ? 0 : 160);
  }
  push('\n✅ All checks have passed — merge is green.');
  confetti(60);
  btn.disabled = false; btn.style.opacity = 1;
};
const sleep = ms => new Promise(r => setTimeout(r, ms));

/* -------------------------------------------------- 10 · path filters */
const FIRES = { frontend: ['frontend'], api: ['api'], worker: ['worker'], shared: ['frontend', 'api', 'worker'], docs: ['none'] };
const FYAML = {
  frontend: "on:\n  push:\n    paths:\n      - 'frontend/**'",
  api:      "on:\n  push:\n    paths:\n      - 'api/**'",
  worker:   "on:\n  push:\n    paths:\n      - 'worker/**'",
  shared:   "# every service lists shared/ too:\non:\n  push:\n    paths:\n      - 'api/**'\n      - 'shared/**'   # ← the hard part, slide 10",
  docs:     "on:\n  push:\n    paths-ignore:\n      - '**.md'\n      - 'docs/**'"
};
$$('#filetree .f').forEach(f => f.onclick = () => {
  $$('#filetree .f').forEach(x => x.classList.remove('on'));
  f.classList.add('on');
  const hot = FIRES[f.dataset.p];
  $$('.runs .run').forEach(r => {
    const on = hot.includes(r.dataset.r);
    r.classList.toggle('hot', on);
    if (on && !reduced) gsap.fromTo(r, { x: -10 }, { x: 0, duration: .35, ease: 'back.out(3)' });
  });
  $('#filterYaml code').textContent = FYAML[f.dataset.p];
});
$('#filetree .f').click();

/* -------------------------------------------------------- 13 · quiz join */
// always the gt.tc copy: the quiz talks to MySQL and must be same-origin with the API
const quizUrl = 'https://git-vit.gt.tc/quiz/';
$('#joinUrl').textContent = quizUrl.replace(/^https?:\/\//, '');
$('#qr').innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=0&data=${encodeURIComponent(quizUrl)}" width="134" height="134" alt="QR code to open the quiz" onerror="this.parentNode.classList.remove('on')">`;
$('#qr').classList.add('on');

/* ------------------------------------------------------------- boot */
const start = Math.max(0, (parseInt(location.hash.slice(1), 10) || 1) - 1);
slides[start].classList.add('live');
i = start; sync(); enter(slides[start]);
