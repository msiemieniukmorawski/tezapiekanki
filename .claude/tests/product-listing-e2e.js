// Uruchomienie: PPTR_DIR="katalog/z/node_modules" node .claude/tests/<plik>.js (puppeteer-core 23.11.1, zainstalowany Chrome).
// Zrzuty ekranu: %TEMP%\tzp-gallery-e2e. Nie składa zamówień. Opis galerii: CLAUDE.md, sekcja 7 "Zdjęcia i galeria".
// Format 1:1 w listingach: sklep, kategoria zestawów, strona główna, "podobne" na karcie produktu.
const path = require('path');
const puppeteer = require(process.env.PPTR_DIR ? path.join(process.env.PPTR_DIR, 'node_modules', 'puppeteer-core') : 'puppeteer-core');
const OUT = path.join(require('os').tmpdir(), 'tzp-gallery-e2e');
require('fs').mkdirSync(OUT, { recursive: true });
const results = [];
const ok = (name, pass, info = '') => { results.push(pass); console.log(`${pass ? 'OK  ' : 'FAIL'}  ${name}${info ? '  | ' + info : ''}`); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true, args: ['--no-first-run'] });
  const pages = [
    ['sklep', '/sklep/', '.products .wd-product'],
    ['zestawy', '/kategoria-produktu/zestawy-zapiekanek/', '.products .wd-product'],
    ['glowna', '/', '.wd-product'],
    ['karta-podobne', '/produkt/zapiekanka-kebab/', '.elementor-element-94d33da .wd-product'],
  ];
  for (const vp of [{ n: 'desk', width: 1440, height: 1000 }, { n: 'mob', width: 390, height: 844, isMobile: true, hasTouch: true }]) {
    for (const [name, url, sel] of pages) {
      const page = await browser.newPage();
      await page.setViewport(vp);
      const res = await page.goto('http://tezapiekanki.local' + url, { waitUntil: 'networkidle2', timeout: 120000 });
      await sleep(1200);
      const d = await page.evaluate((sel) => {
        const prods = [...document.querySelectorAll(sel)].filter((p) => p.getClientRects().length);
        const imgs = prods.map((p) => p.querySelector('.product-image-link img, .wd-product-thumb img, img')).filter(Boolean);
        const ratios = imgs.map((i) => { const r = i.getBoundingClientRect(); return r.height ? Math.round((r.width / r.height) * 100) / 100 : 0; }).filter(Boolean);
        const srcs = imgs.map((i) => (i.currentSrc || i.src || '').split('/').pop());
        return { count: prods.length, ratios: [...new Set(ratios)], sample: srcs.slice(0, 3), full: srcs.filter((s) => !/-\d+x\d+\./.test(s)).length };
      }, sel);
      const square = d.ratios.length > 0 && d.ratios.every((r) => Math.abs(r - 1) < 0.03);
      ok(`${vp.n} ${name}: ${d.count} produktów, zdjęcia 1:1, miniatury zamiast oryginałów`, res.status() === 200 && d.count > 0 && square && d.full === 0, JSON.stringify(d));
      if (vp.n === 'desk' && name !== 'karta-podobne') {
        const first = await page.$(sel);
        if (first) { await first.evaluate((el) => el.scrollIntoView({ block: 'start' })); await sleep(500); await page.screenshot({ path: path.join(OUT, `listing-${name}.png`) }); }
      }
      await page.close();
    }
  }
  await browser.close();
  console.log(`\n${results.filter(Boolean).length}/${results.length} OK`);
})();
