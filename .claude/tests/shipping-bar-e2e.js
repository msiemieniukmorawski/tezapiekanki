// Usage: PPTR_DIR="katalog/z/node_modules" node .claude/tests/shipping-bar-e2e.js (puppeteer-core 23.11.1, installed Chrome).
// Test of the shipping bar (tezapiekanki plugin, includes/class-shipping-bar.php) and the Woodmart popup with delivery dates.
// No orders: the checkout is checked with a product in the cart in a separate context. Screenshots: %TEMP%\tzp-bar-e2e.
const path = require('path');
const puppeteer = require(process.env.PPTR_DIR ? path.join(process.env.PPTR_DIR, 'node_modules', 'puppeteer-core') : 'puppeteer-core');
const OUT = path.join(require('os').tmpdir(), 'tzp-bar-e2e');
require('fs').mkdirSync(OUT, { recursive: true });
const results = [];
const ok = (name, pass, info = '') => { results.push(pass); console.log(`${pass ? 'OK  ' : 'FAIL'}  ${name}${info ? '  | ' + info : ''}`); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const BASE = 'http://tezapiekanki.local';
const DESKTOP = { width: 1440, height: 900 };
const TABLET = { width: 820, height: 1180, isMobile: true, hasTouch: true };
const PHONE = { width: 390, height: 844, isMobile: true, hasTouch: true };
const SMALL = { width: 320, height: 640, isMobile: true, hasTouch: true };

const barState = (page) => page.evaluate(() => {
  const bar = document.querySelector('.tzp-bar');
  const header = document.querySelector('.whb-main-header');
  const link = bar && bar.querySelector('.tzp-bar__link');
  const r = bar && bar.getBoundingClientRect();
  const lcs = link && getComputedStyle(link);
  return {
    exists: !!bar,
    shown: !!bar && getComputedStyle(bar).display !== 'none' && r.height > 0,
    height: r ? Math.round(r.height) : 0,
    lines: link ? Math.round((link.getBoundingClientRect().height - parseFloat(lcs.paddingTop) - parseFloat(lcs.paddingBottom)) / parseFloat(lcs.lineHeight)) : 0,
    headerTop: header ? Math.round(header.getBoundingClientRect().top) : null,
    off: document.documentElement.classList.contains('tzp-bar-off'),
    overflowX: document.documentElement.scrollWidth > window.innerWidth,
    text: link ? link.textContent.replace(/\s+/g, ' ').trim() : '',
  };
});

const popupState = (page) => page.evaluate(() => {
  const wrap = document.querySelector('.mfp-wrap.wd-popup-builder-wrap');
  const popup = wrap && wrap.querySelector('.wd-popup');
  const dates = popup && popup.querySelector('.tzp-why-dates');
  const items = dates ? [dates.textContent.trim()] : [];
  const datesBorder = dates ? getComputedStyle(dates).borderLeftWidth : null;
  const r = popup && popup.getBoundingClientRect();
  const btn = popup && popup.querySelector('a.btn, .wd-button-wrapper a');
  return {
    open: !!popup && r.height > 0,
    title: popup ? (popup.querySelector('.wd-title, .title, h3') || {}).textContent : '',
    items,
    datesBorder,
    width: r ? Math.round(r.width) : 0,
    left: r ? Math.round(r.left) : 0,
    button: btn ? { text: btn.textContent.trim(), href: btn.getAttribute('href') } : null,
  };
});

(async () => {
  const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true, args: ['--no-first-run'] });
  const errors = [];
  const open = async (url, vp, ctx) => {
    ctx = ctx || await browser.createBrowserContext();
    const page = await ctx.newPage();
    page.on('pageerror', (e) => { if (!/cookieyes/i.test(e.message)) errors.push(url + ': ' + e.message.slice(0, 120)); });
    await page.setViewport(vp);
    await page.goto(BASE + url, { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(800);
    return { ctx, page };
  };

  // 1. Desktop: bar above the header, popup.
  {
    const { ctx, page } = await open('/', DESKTOP);
    let s = await barState(page);
    ok('desktop: belka widoczna', s.shown, `wys. ${s.height}px, linie ${s.lines}`);
    ok('desktop: tekst belki', /Wysyłamy tylko 2 razy w tygodniu\. Sprawdź dlaczego/.test(s.text), s.text);
    ok('desktop: nagłówek pod belką', s.headerTop === s.height, `nagłówek top ${s.headerTop}`);
    ok('desktop: jedna linia', s.lines === 1);
    await page.screenshot({ path: path.join(OUT, '1-desktop.png') });

    const urlBefore = page.url();
    await page.click('.tzp-bar-link');
    await sleep(900);
    let p = await popupState(page);
    ok('desktop: popup otwiera się z belki', p.open && page.url() === urlBefore, `szer. ${p.width}px`);
    ok('desktop: tytuł popupu', /Dlaczego wysyłamy tylko 2 razy w tygodniu\?/.test(p.title || ''));
    ok('desktop: najbliższy termin w popupie, bez lewej ramki', p.datesBorder === '0px' && p.items.length === 1 && p.items.every((t) => /^Zamów ((dziś|jutro) do|do \S+) \d{2}:\d{2}, a dostaniesz ją (w|we) \S+\.$/.test(t)), p.items.join(' / '));
    ok('desktop: przycisk do sklepu', !!p.button && /Wybieram zapiekanki/i.test(p.button.text) && /\/sklep\/?$/.test(p.button.href), JSON.stringify(p.button));
    await page.screenshot({ path: path.join(OUT, '2-desktop-popup.png') });
    await page.keyboard.press('Escape');
    await sleep(900);
    ok('desktop: ESC zamyka popup', !(await popupState(page)).open);
    await page.click('.tzp-bar-link');
    await sleep(900);
    ok('desktop: popup otwiera się ponownie (bez "persistent close")', (await popupState(page)).open);
    await page.keyboard.press('Escape');
    await sleep(900);

    // Closing the bar until the end of the day.
    await page.click('.tzp-bar__close');
    await sleep(500);
    s = await barState(page);
    const stored = await page.evaluate(() => localStorage.getItem('tzp_bar_closed'));
    const today = await page.evaluate(() => new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Warsaw', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date()).reduce((o, x) => ((o[x.type] = x.value), o), {}));
    ok('desktop: X chowa belkę', !s.shown && s.off && s.headerTop === 0, `nagłówek top ${s.headerTop}`);
    ok('desktop: zapisana dzisiejsza data', stored === `${today.year}-${today.month}-${today.day}`, stored);

    // After a reload the class is already set by the script in <head> (no flicker).
    await page.evaluateOnNewDocument(() => {
      document.addEventListener('DOMContentLoaded', () => { window.__tzpOffAtDCL = document.documentElement.classList.contains('tzp-bar-off'); });
    });
    await page.goto(BASE + '/sklep/', { waitUntil: 'networkidle2', timeout: 120000 });
    s = await barState(page);
    const offAtDCL = await page.evaluate(() => window.__tzpOffAtDCL);
    ok('desktop: po przeładowaniu belka nadal zamknięta', !s.shown && offAtDCL === true);

    // Next day: yesterday's entry does not hide the bar.
    await page.evaluate(() => localStorage.setItem('tzp_bar_closed', '2020-01-01'));
    await page.reload({ waitUntil: 'networkidle2' });
    s = await barState(page);
    ok('desktop: następnego dnia belka wraca', s.shown);

    // Scrolling: sticky header at the top of the screen.
    await page.goto(BASE + '/', { waitUntil: 'networkidle2', timeout: 120000 });
    await page.evaluate(() => window.scrollTo(0, 900));
    await sleep(500);
    await page.evaluate(() => window.scrollTo(0, 700));
    await sleep(700);
    const sticky = await page.evaluate(() => {
      const h = document.querySelector('.whb-header');
      const m = document.querySelector('.whb-main-header').getBoundingClientRect();
      return { sticked: h.classList.contains('whb-sticked'), top: Math.round(m.top) };
    });
    ok('desktop: nagłówek przyklejony przy przewijaniu w górę', sticky.sticked && sticky.top === 0, JSON.stringify(sticky));
    await page.screenshot({ path: path.join(OUT, '3-desktop-sticky.png') });
    await ctx.close();
  }

  // 2. Product page (desktop and phone).
  for (const [name, vp] of [['desktop', DESKTOP], ['telefon', PHONE]]) {
    const { ctx, page } = await open('/zapiekanka-tradycyjna/', vp);
    const s = await barState(page);
    ok(`produkt ${name}: belka i nagłówek pod nią`, s.shown && s.headerTop === s.height && !s.overflowX, `wys. ${s.height}, nagłówek ${s.headerTop}`);
    await page.screenshot({ path: path.join(OUT, `4-produkt-${name}.png`) });
    await ctx.close();
  }

  // 3. Phone and tablet: look, full-width popup.
  for (const [name, vp] of [['telefon', PHONE], ['tablet', TABLET], ['320px', SMALL]]) {
    const { ctx, page } = await open('/', vp);
    const s = await barState(page);
    ok(`${name}: belka widoczna, bez poziomego przewijania`, s.shown && !s.overflowX, `wys. ${s.height}px, linie ${s.lines}`);
    if (name !== '320px') ok(`${name}: jedna linia`, s.lines === 1);
    const closeBox = await page.evaluate(() => { const r = document.querySelector('.tzp-bar__close').getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height)]; });
    ok(`${name}: pole dotyku X 44 px`, closeBox[0] >= 44 && closeBox[1] >= 40, closeBox.join('x'));
    await page.screenshot({ path: path.join(OUT, `5-${name}.png`) });
    await page.tap('.tzp-bar-link');
    await sleep(1000);
    const p = await popupState(page);
    ok(`${name}: popup mieści się na ekranie`, p.open && p.width <= vp.width && p.left >= 0, `szer. ${p.width}, left ${p.left}`);
    await page.screenshot({ path: path.join(OUT, `6-${name}-popup.png`) });
    await ctx.close();
  }

  // 4. Checkout: no bar and no popup.
  {
    const ctx = await browser.createBrowserContext();
    const { page } = await open('/?add-to-cart=274', DESKTOP, ctx);
    await page.goto(BASE + '/zamowienie/', { waitUntil: 'networkidle2', timeout: 120000 });
    const onCheckout = await page.evaluate(() => document.body.classList.contains('woocommerce-checkout'));
    const s = await barState(page);
    const popup = await page.evaluate(() => !!document.querySelector('.wd-popup-builder .tzp-why-dates'));
    ok('checkout: brak belki i popupu', onCheckout && !s.exists && !popup, `checkout ${onCheckout}`);
    await page.goto(BASE + '/koszyk/', { waitUntil: 'networkidle2', timeout: 120000 });
    ok('koszyk: belka jest', (await barState(page)).shown);
    await ctx.close();
  }

  ok('brak błędów JS', errors.length === 0, errors.join(' | '));
  await browser.close();
  const failed = results.filter((r) => !r).length;
  console.log(`\n${results.length - failed}/${results.length} OK. Zrzuty: ${OUT}`);
  process.exit(failed ? 1 : 0);
})();
