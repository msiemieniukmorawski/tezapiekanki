// Uruchomienie: PPTR_DIR="katalog/z/node_modules" node .claude/tests/product-sticky-cart-e2e.js (puppeteer-core 23.11.1, zainstalowany Chrome).
// Test paska "Dodaj do koszyka" przy przewijaniu (Woodmart + poprawki z inc/product-sticky-cart.php).
// Dodaje produkty do koszyka w osobnych kontekstach przeglądarki (bez zamówień). Zrzuty: %TEMP%\tzp-sticky-e2e.
const path = require('path');
const puppeteer = require(process.env.PPTR_DIR ? path.join(process.env.PPTR_DIR, 'node_modules', 'puppeteer-core') : 'puppeteer-core');
const OUT = path.join(require('os').tmpdir(), 'tzp-sticky-e2e');
require('fs').mkdirSync(OUT, { recursive: true });
const results = [];
const ok = (name, pass, info = '') => { results.push(pass); console.log(`${pass ? 'OK  ' : 'FAIL'}  ${name}${info ? '  | ' + info : ''}`); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const BASE = 'http://tezapiekanki.local/produkt/';

const cartCount = (page) => page.evaluate(() => {
  const el = document.querySelector('.wd-cart-number .wd-tools-count, .wd-cart-number, .wd-tools-count');
  return el ? parseInt(el.textContent.replace(/\D+/g, ''), 10) || 0 : -1;
});
const waitCount = async (page, expected, ms = 20000) => {
  const end = Date.now() + ms;
  let c = await cartCount(page);
  while (c !== expected && Date.now() < end) { await sleep(300); c = await cartCount(page); }
  return c;
};
const sticky = (page) => page.evaluate(() => {
  const s = document.querySelector('.wd-sticky-btn');
  const b = s && s.querySelector('.single_add_to_cart_button, .wd-sticky-add-to-cart');
  return { shown: !!s && s.classList.contains('wd-sticky-btn-shown'), button: b ? b.textContent.trim() : null };
});

(async () => {
  const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true, args: ['--no-first-run'] });
  const errors = [];
  const open = async (slug, vp) => {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    page.on('pageerror', (e) => { if (!/cookieyes/i.test(e.message)) errors.push(slug + ': ' + e.message.slice(0, 120)); });
    await page.setViewport(vp);
    await page.goto(BASE + slug + '/', { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(1200);
    return { ctx, page };
  };

  // 1. Desktop, zapiekanka
  let { ctx, page } = await open('zapiekanka-kebab', { width: 1440, height: 900 });
  let st = await sticky(page);
  ok('desktop: pasek ukryty na górze strony', !st.shown, JSON.stringify(st));
  await page.evaluate(() => window.scrollTo(0, 2500));
  await sleep(800);
  st = await sticky(page);
  ok('desktop: po przewinięciu pasek widoczny', st.shown && st.button === 'Dodaj do koszyka', JSON.stringify(st));
  await page.evaluate(() => { const q = document.querySelector('.wd-sticky-btn .qty'); q.value = 2; jQuery(q).trigger('change'); });
  const mainQty = await page.evaluate(() => [...document.querySelectorAll('form.cart .qty')].find((q) => !q.closest('.wd-sticky-btn')).value);
  ok('desktop: ilość w pasku przenosi się do formularza na karcie', mainQty === '2', `ilość=${mainQty}`);
  let before = await cartCount(page);
  await page.click('.wd-sticky-btn .single_add_to_cart_button');
  let after = await waitCount(page, before + 2);
  ok('desktop: "Dodaj do koszyka" w pasku dodaje 2 szt. (AJAX, bez przeładowania)', after === before + 2 && /zapiekanka-kebab/.test(page.url()), `${before} -> ${after}`);
  await page.screenshot({ path: path.join(OUT, 'desk-po-dodaniu.png') });
  await ctx.close();

  // 2. Telefon, zapiekanka
  ({ ctx, page } = await open('zapiekanka-kebab', { width: 390, height: 844, isMobile: true, hasTouch: true }));
  await page.evaluate(() => window.scrollTo(0, 3000));
  await sleep(800);
  const m = await page.evaluate(() => {
    const s = document.querySelector('.wd-sticky-btn');
    const tb = document.querySelector('.wd-toolbar');
    const vis = (sel) => { const el = s.querySelector(sel); return !!el && el.getClientRects().length > 0 && el.getBoundingClientRect().width > 0; };
    const r = s.getBoundingClientRect();
    return {
      shown: s.classList.contains('wd-sticky-btn-shown'),
      thumb: vis('.wd-sticky-btn-thumbnail img'),
      title: vis('.wd-entities-title'),
      price: vis('.price'),
      qty: vis('.qty'),
      button: vis('.single_add_to_cart_button'),
      height: Math.round(r.height),
      aboveToolbar: tb ? Math.round(r.bottom) <= Math.round(tb.getBoundingClientRect().top) + 1 : true,
      overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
    };
  });
  ok('telefon: w pasku tylko ilość i przycisk (bez zdjęcia, nazwy i ceny)', m.shown && m.qty && m.button && !m.thumb && !m.title && !m.price, JSON.stringify(m));
  ok('telefon: pasek nad dolną nawigacją, bez poziomego przewijania', m.aboveToolbar && !m.overflow, JSON.stringify({ above: m.aboveToolbar, height: m.height, overflow: m.overflow }));
  await page.screenshot({ path: path.join(OUT, 'mob-pasek.png') });
  before = await cartCount(page);
  await page.tap('.wd-sticky-btn .single_add_to_cart_button');
  after = await waitCount(page, before + 1);
  ok('telefon: dotknięcie "Dodaj do koszyka" dodaje produkt', after === before + 1, `${before} -> ${after}`);
  await ctx.close();

  // 3. Zestaw 9: "Wybierz smaki" przewija do listy, po skompletowaniu dodaje zestaw
  ({ ctx, page } = await open('zestaw-9-zapiekanek', { width: 1440, height: 900 }));
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight * 0.6));
  await sleep(800);
  st = await sticky(page);
  ok('zestaw: w pasku "Wybierz smaki", dopóki zestaw nie jest skompletowany', st.shown && st.button === 'Wybierz smaki', JSON.stringify(st));
  await page.click('.wd-sticky-btn .wd-sticky-add-to-cart');
  await sleep(1500);
  const pos = await page.evaluate(() => ({ list: Math.round(document.querySelector('.woosb-wrap').getBoundingClientRect().top), header: Math.round(document.querySelector('.whb-main-header').getBoundingClientRect().bottom), url: location.pathname }));
  ok('zestaw: "Wybierz smaki" przewija do listy smaków, poniżej przyklejonego nagłówka', pos.list >= pos.header && pos.list < 300, JSON.stringify(pos));
  // Wybór 9 sztuk jednego smaku tak, jak robi to klient (pole ilości przy smaku).
  await page.evaluate(() => {
    const input = [...document.querySelectorAll('.woosb-wrap .woosb-product input.qty, .woosb-wrap input.qty')].find((i) => !i.disabled);
    input.value = 9;
    jQuery(input).trigger('change');
  });
  await sleep(800);
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight * 0.6));
  await sleep(800);
  st = await sticky(page);
  ok('zestaw: po wybraniu 9 sztuk w pasku "Dodaj do koszyka"', st.button === 'Dodaj do koszyka', JSON.stringify(st));
  before = await cartCount(page);
  await page.click('.wd-sticky-btn .wd-sticky-add-to-cart');
  after = await waitCount(page, before + 1);
  ok('zestaw: przycisk w pasku dodaje zestaw do koszyka', after > before, `${before} -> ${after}`);
  await page.screenshot({ path: path.join(OUT, 'zestaw-po-dodaniu.png') });
  await ctx.close();

  ok('brak błędów JS', errors.length === 0, errors.slice(0, 3).join(' | '));
  await browser.close();
  console.log(`\n${results.filter(Boolean).length}/${results.length} OK`);
})();
