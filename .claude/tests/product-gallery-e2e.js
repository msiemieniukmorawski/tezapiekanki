// Usage: PPTR_DIR="katalog/z/node_modules" node .claude/tests/<plik>.js (puppeteer-core 23.11.1, installed Chrome).
// Screenshots: %TEMP%\tzp-gallery-e2e. Places no orders. Gallery description: CLAUDE.md, section 7 "Zdjęcia i galeria".
// Product gallery test: desktop and tablet (large first image, 4:3 tiles two per row, with more than 6 images
// the "Zobacz więcej zdjęć" button below the grid expands and collapses the rest, lightbox), phone (carousel, peeking next image, counter, lightbox on tap).
const path = require('path');
const puppeteer = require(process.env.PPTR_DIR ? path.join(process.env.PPTR_DIR, 'node_modules', 'puppeteer-core') : 'puppeteer-core');
const OUT = path.join(require('os').tmpdir(), 'tzp-gallery-e2e');
require('fs').mkdirSync(OUT, { recursive: true });
const results = [];
const ok = (name, pass, info = '') => { results.push(pass); console.log(`${pass ? 'OK  ' : 'FAIL'}  ${name}${info ? '  | ' + info : ''}`); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const BASE = 'http://tezapiekanki.local/produkt/';

(async () => {
  const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true, args: ['--no-first-run'] });
  const errors = [];
  const open = async (slug, vp) => {
    const page = await browser.newPage();
    page.on('pageerror', (e) => { if (!/cookieyes/i.test(e.message)) errors.push(slug + ': ' + e.message.slice(0, 120)); });
    await page.setViewport(vp);
    await page.goto(BASE + slug + '/', { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(1500);
    return page;
  };
  const gridState = (page) => page.evaluate(() => {
    const items = [...document.querySelectorAll('.tzp-gallery .wd-carousel-wrap > .wd-carousel-item')];
    const visible = items.filter((i) => i.getClientRects().length > 0);
    const tiles = visible.slice(1).map((i) => { const im = i.querySelector('img').getBoundingClientRect(); return Math.round((im.width / im.height) * 100) / 100; });
    const rows = [...new Set(visible.map((i) => Math.round(i.getBoundingClientRect().top)))].length;
    const btn = document.querySelector('.tzp-gallery__toggle');
    const grid = document.querySelector('.tzp-gallery .wd-gallery-images');
    return {
      total: items.length,
      visible: visible.length,
      rows,
      firstW: items[0] ? Math.round(items[0].getBoundingClientRect().width) : 0,
      tileW: items[1] ? Math.round(items[1].getBoundingClientRect().width) : 0,
      tiles,
      button: btn && btn.getClientRects().length ? btn.innerText.replace(/\s+/g, ' ').trim() : null,
      expanded: btn ? btn.getAttribute('aria-expanded') : null,
      buttonBelowGrid: !!(btn && grid && btn.getBoundingClientRect().top >= grid.getBoundingClientRect().bottom - 1),
      overlayOnTiles: items.some((i) => i.querySelector('button')),
      swiper: !!document.querySelector('.tzp-gallery .woocommerce-product-gallery__wrapper').swiper,
    };
  });
  const pswp = (page) => page.evaluate(() => ({
    open: !!document.querySelector('.pswp--open'),
    counter: ((document.querySelector('.pswp__counter') || {}).textContent || '').trim(),
  }));

  // Desktop: Kebab (8 images)
  let page = await open('zapiekanka-kebab', { width: 1440, height: 1000 });
  let s = await gridState(page);
  ok('desktop: duże pierwsze zdjęcie, pod nim 2 kolumny, widać 5 z 8 (1 + 2 + 2)', !s.swiper && s.total === 8 && s.visible === 5 && s.rows === 3 && s.firstW > s.tileW * 1.9, JSON.stringify({ visible: s.visible, rows: s.rows, firstW: s.firstW, tileW: s.tileW }));
  ok('desktop: kafelki 4:3', s.tiles.every((r) => Math.abs(r - 1.33) < 0.02), s.tiles.join(', '));
  ok('desktop: przycisk "Zobacz więcej zdjęć" pod siatką, bez nakładki na zdjęciach', s.button === 'Zobacz więcej zdjęć' && s.expanded === 'false' && s.buttonBelowGrid && !s.overlayOnTiles, JSON.stringify({ button: s.button, expanded: s.expanded, below: s.buttonBelowGrid, overlay: s.overlayOnTiles }));
  const g = await page.$('.tzp-gallery .woocommerce-product-gallery');
  await g.screenshot({ path: path.join(OUT, 'test-desk-gallery.png') });

  await page.click('.tzp-gallery .wd-carousel-wrap > .wd-carousel-item:nth-child(1) .woocommerce-product-gallery__image > a');
  await sleep(1200);
  let p = await pswp(page);
  ok('klik w duże zdjęcie: lightbox 1/8 (z ukrytymi zdjęciami)', p.open && /^1\s*\/\s*8$/.test(p.counter), JSON.stringify(p));
  await page.keyboard.press('Escape');
  await sleep(700);

  await page.click('.tzp-gallery__toggle');
  await sleep(900);
  p = await pswp(page);
  s = await gridState(page);
  ok('"Zobacz więcej zdjęć" rozwija w dół (bez lightboxa): 8 zdjęć, przycisk "Zwiń zdjęcia" pod siatką', !p.open && s.visible === 8 && s.rows === 5 && s.button === 'Zwiń zdjęcia' && s.expanded === 'true' && s.buttonBelowGrid, JSON.stringify({ lightbox: p.open, visible: s.visible, rows: s.rows, button: s.button, below: s.buttonBelowGrid }));
  await g.screenshot({ path: path.join(OUT, 'test-desk-gallery-open.png') });
  await page.click('.tzp-gallery .wd-carousel-wrap > .wd-carousel-item:nth-child(5) .woocommerce-product-gallery__image > a');
  await sleep(1200);
  p = await pswp(page);
  ok('po rozwinięciu klik w 5. zdjęcie otwiera lightbox 5/8', p.open && /^5\s*\/\s*8$/.test(p.counter), p.counter);
  await page.keyboard.press('Escape');
  await sleep(700);
  await page.click('.tzp-gallery__toggle');
  await sleep(900);
  s = await gridState(page);
  ok('"Zwiń zdjęcia" zwija z powrotem do 5 zdjęć, przycisk widoczny na ekranie', s.visible === 5 && s.rows === 3 && s.button === 'Zobacz więcej zdjęć' && s.expanded === 'false', JSON.stringify({ visible: s.visible, rows: s.rows, button: s.button }));
  const btnTop = await page.$eval('.tzp-gallery__toggle', (b) => Math.round(b.getBoundingClientRect().top));
  ok('po zwinięciu przycisk w widoku', btnTop >= 0 && btnTop < 1000, `top=${btnTop}`);
  await page.close();

  // Desktop: 6 and 7 images
  for (const [slug, total, visible, button] of [['zapiekanka-z-szynka', 6, 6, null], ['zapiekanka-goralska', 7, 5, 'Zobacz więcej zdjęć']]) {
    page = await open(slug, { width: 1440, height: 1000 });
    s = await gridState(page);
    ok(`desktop ${slug}: ${total} zdjęć, widać ${visible}, przycisk: ${button || 'brak'}`, s.total === total && s.visible === visible && s.button === button, JSON.stringify({ total: s.total, visible: s.visible, button: s.button }));
    await page.close();
  }

  // Tablet 900 px: grid with the button
  page = await open('zapiekanka-kebab', { width: 900, height: 1100 });
  s = await gridState(page);
  ok('tablet 900 px: duże + 4 kafelki, "Zobacz więcej zdjęć", bez karuzeli', !s.swiper && s.visible === 5 && s.rows === 3 && s.button === 'Zobacz więcej zdjęć', JSON.stringify({ visible: s.visible, rows: s.rows, button: s.button, swiper: s.swiper }));
  await page.close();

  // Phone
  page = await open('zapiekanka-kebab', { width: 390, height: 844, isMobile: true, hasTouch: true });
  let m = await page.evaluate(() => {
    const gal = document.querySelector('.tzp-gallery .woocommerce-product-gallery__wrapper');
    const sw = gal.swiper;
    const c = document.querySelector('.tzp-gallery__counter');
    const items = gal.querySelectorAll('.wd-carousel-item');
    const r2 = items[1].getBoundingClientRect();
    const img = items[0].querySelector('img').getBoundingClientRect();
    const btn = document.querySelector('.tzp-gallery__toggle');
    return {
      swiper: !!sw,
      spv: sw && sw.params.slidesPerView,
      counter: c && c.textContent,
      peekPx: Math.round(window.innerWidth - r2.left),
      ratio: Math.round((img.width / img.height) * 100) / 100,
      allShown: [...items].every((i) => getComputedStyle(i).display !== 'none'),
      button: !!(btn && btn.getClientRects().length),
    };
  });
  ok('telefon: karuzela, licznik "1 / 8", wystaje kolejne zdjęcie', m.swiper && m.counter === '1 / 8' && m.peekPx > 20 && m.peekPx < 90, JSON.stringify(m));
  ok('telefon: wszystkie 8 zdjęć w karuzeli, bez przycisku, slajdy 4:3', m.allShown && !m.button && Math.abs(m.ratio - 1.33) < 0.03, JSON.stringify({ allShown: m.allShown, button: m.button, ratio: m.ratio }));
  await page.evaluate(() => window.scrollTo(0, 0));
  await sleep(300);
  await page.screenshot({ path: path.join(OUT, 'test-mob-top.png') });
  await page.evaluate(() => document.querySelector('.tzp-gallery .woocommerce-product-gallery__wrapper').swiper.slideNext());
  await sleep(700);
  m = await page.evaluate(() => document.querySelector('.tzp-gallery__counter').textContent);
  ok('telefon: po przesunięciu licznik "2 / 8"', m === '2 / 8', m);
  // Tap in the visible part of the active slide, below the header (the header overlaps the gallery).
  const pt = await page.evaluate(() => {
    const r = document.querySelector('.tzp-gallery .wd-carousel-item.wd-active').getBoundingClientRect();
    return { x: Math.round(Math.max(r.left, 0) + 60), y: Math.round(Math.max(r.top, 0) + r.height * 0.75) };
  });
  await page.touchscreen.tap(pt.x, pt.y);
  await sleep(1200);
  p = await pswp(page);
  ok('telefon: dotknięcie otwiera lightbox na tym zdjęciu', p.open && /^2\s*\/\s*8$/.test(p.counter), JSON.stringify(p));
  await page.screenshot({ path: path.join(OUT, 'test-mob-lightbox.png') });
  await page.close();

  ok('brak błędów JS', errors.length === 0, errors.slice(0, 3).join(' | '));
  await browser.close();
  console.log(`\n${results.filter(Boolean).length}/${results.length} OK`);
})();
