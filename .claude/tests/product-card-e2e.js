// Usage: PPTR_DIR="katalog/z/node_modules" node .claude/tests/product-card-e2e.js (puppeteer-core 23.11.1, installed Chrome).
// Test of the zapiekanka product page after importing the product cards (2026-10-20): nutrition values and PDF card ([tzp_nutrition]),
// flavour pins ([tzp_flavor_pins]), product data for Google (Rank Math + includes/class-product-schema.php), descriptions.
// Part 1 without a browser (HTML of 11 products and a bundle), part 2 in Chrome (desktop, phone). Writes nothing.
// Screenshots: %TEMP%\tzp-product-card-e2e.
const path = require('path');
const puppeteer = require(process.env.PPTR_DIR ? path.join(process.env.PPTR_DIR, 'node_modules', 'puppeteer-core') : 'puppeteer-core');
const OUT = path.join(require('os').tmpdir(), 'tzp-product-card-e2e');
require('fs').mkdirSync(OUT, { recursive: true });
const results = [];
const ok = (name, pass, info = '') => { results.push(pass); console.log(`${pass ? 'OK  ' : 'FAIL'}  ${name}${info ? '  | ' + info : ''}`); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const BASE = 'http://tezapiekanki.local/';

// From the client's cards: product slug => [card file, weight, energy, fat, saturates, carbohydrates, sugars, protein, salt].
const CARDS = {
  'zapiekanka-tradycyjna': ['tradycyjna', '319 g', '829 kJ / 198 kcal', '6,7 g', '4,4 g', '23,6 g', '2,1 g', '11,2 g', '1,3 g'],
  'zapiekanka-wiejska': ['wiejska', '449 g', '799 kJ / 191 kcal', '8,3 g', '4,4 g', '17,0 g', '1,5 g', '12,2 g', '1,4 g'],
  'zapiekanka-z-boczkiem': ['z-boczkiem', '366 g', '977 kJ / 234 kcal', '11,4 g', '5,8 g', '20,7 g', '2,0 g', '12,5 g', '1,4 g'],
  'zapiekanka-goralska': ['goralska', '405 g', '943 kJ / 225 kcal', '10,0 g', '6,0 g', '20,7 g', '3,4 g', '13,4 g', '1,4 g'],
  'zapiekanka-mega-serowa': ['mega-serowa', '413 g', '969 kJ / 231 kcal', '11,1 g', '7,0 g', '18,5 g', '1,7 g', '14,8 g', '1,4 g'],
  'zapiekanka-z-szynka': ['z-szynka', '374 g', '821 kJ / 196 kcal', '6,7 g', '4,1 g', '20,2 g', '1,8 g', '14,1 g', '1,5 g'],
  'zapiekanka-kebab': ['kebab', '389 g', '783 kJ / 187 kcal', '6,9 g', '4,0 g', '19,4 g', '1,8 g', '12,2 g', '1,5 g'],
  'zapiekanka-na-dziko': ['na-dziko', '366 g', '928 kJ / 222 kcal', '7,9 g', '5,4 g', '20,7 g', '1,9 g', '13,0 g', '1,5 g'],
  'zapiekanka-salami': ['salami', '349 g', '880 kJ / 211 kcal', '8,5 g', '5,0 g', '21,6 g', '1,9 g', '12,2 g', '1,5 g'],
  'zapiekanka-na-ostro': ['na-ostro', '404 g', '863 kJ / 206 kcal', '9,5 g', '4,9 g', '19,5 g', '2,2 g', '10,9 g', '1,5 g'],
  'zapiekanka-szwedzka': ['szwedzka', '379 g', '818 kJ / 196 kcal', '7,4 g', '4,5 g', '22,7 g', '3,5 g', '9,7 g', '1,2 g'],
};
const LABELS = ['Wartość energetyczna', 'Tłuszcz', 'w tym kwasy tłuszczowe nasycone', 'Węglowodany', 'w tym cukry', 'Białko', 'Sól'];

const strip = (s) => s.replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
const products = (html) => {
  const out = [];
  for (const m of html.matchAll(/<script[^>]*application\/ld\+json[^>]*>([\s\S]*?)<\/script>/g)) {
    try {
      const d = JSON.parse(m[1]);
      (d['@graph'] || [d]).forEach((n) => { if (n['@type'] === 'Product') out.push(n); });
    } catch (e) { /* other scripts */ }
  }
  return out;
};

(async () => {
  /*
   * Part 1: HTML of all 11 flavours.
   */
  const pdfChecked = new Set();
  for (const [slug, card] of Object.entries(CARDS)) {
    const res = await fetch(BASE + slug + '/');
    const html = await res.text();
    const section = (html.match(/<section class="tzp-nutrition"[\s\S]*?<\/section>/) || [''])[0];
    const rows = [...section.matchAll(/<tr[^>]*><th scope="row">([^<]+)<\/th><td>([^<]+)<\/td><\/tr>/g)].map((m) => [m[1], m[2]]);
    const expected = LABELS.map((l, i) => [l, card[2 + i]]);
    ok(`${slug}: tabela jak na karcie (7 wierszy, w tym 2 "w tym")`, JSON.stringify(rows) === JSON.stringify(expected), rows.length ? '' : 'brak tabeli');
    ok(`${slug}: waga zapiekanki ${card[1]}`, strip(section).includes('Waga zapiekanki: ' + card[1]));

    const pdf = (section.match(/class="tzp-card-link" href="([^"]+)"/) || [])[1] || '';
    ok(`${slug}: przycisk karty PDF prowadzi do własnej karty`, pdf.endsWith(`/karta-produktu-zapiekanka-${card[0]}.pdf`), pdf);
    if (pdf && !pdfChecked.has(pdf)) {
      pdfChecked.add(pdf);
      const r = await fetch(pdf, { method: 'HEAD' });
      ok(`${slug}: PDF dostępny`, r.status === 200 && /pdf/.test(r.headers.get('content-type') || ''), `${r.status} ${r.headers.get('content-type')}`);
    }

    // Old Woodmart table (class tabelkakal; the .tabelkakal rule in Additional CSS stays), post-info widget with the link from the field.
    ok(`${slug}: bez starej tabeli i przycisku "Pobierz kartę produktu"`, !/class="[^"]*tabelkakal|Wartości odżywcze na 100g|elementor-widget-post-info|POBIERZ KARTĘ PRODUKTU/.test(html));

    const pins = [...html.matchAll(/<a class="tzp-flavors__pin([^"]*)" href="([^"]+)"/g)];
    const current = pins.filter((p) => /is-current/.test(p[1]));
    ok(`${slug}: 11 pinów, bieżący smak wyróżniony`, pins.length === 11 && current.length === 1 && current[0][2].includes('/' + slug + '/'), `${pins.length} pinów, bieżący: ${current.map((p) => p[2]).join()}`);

    const h1 = (html.match(/<h1[\s>]/g) || []).length;
    ok(`${slug}: jeden nagłówek H1`, h1 === 1, `H1: ${h1}`);
    ok(`${slug}: opis bez "dwóch serów"`, !/dwóch serów|dwa rodzaje sera|To duet/.test(html));

    const p = products(html)[0] || {};
    const offer = p.offers || {};
    ok(`${slug}: schema Product z wysyłką i zwrotami`,
      p.name && !p.name.includes(' - ') && p.weight && p.weight.unitCode === 'GRM' && String(p.weight.value) + ' g' === card[1]
        && offer.shippingDetails && offer.shippingDetails.shippingRate && offer.shippingDetails.deliveryTime
        && offer.hasMerchantReturnPolicy && /MerchantReturnNotPermitted/.test(offer.hasMerchantReturnPolicy.returnPolicyCategory)
        && (p.additionalProperty || []).every((a) => !/^pa_/.test(a.name)),
      `name=${p.name}`);
  }

  // Pins in shop order (Tradycyjna first), unavailable flavour greyed out.
  const salami = await (await fetch(BASE + 'zapiekanka-salami/')).text();
  const names = [...salami.matchAll(/<a class="tzp-flavors__pin[^"]*"[^>]*>([^<]+)</g)].map((m) => m[1]);
  ok('piny w kolejności sklepu (pierwsza Tradycyjna)', names[0] === 'Tradycyjna', names.join(', '));
  ok('niedostępny smak (na dziko) wyszarzony', /tzp-flavors__pin is-out" href="[^"]*zapiekanka-na-dziko/.test(salami));

  // Bundle (layout 6533) unchanged.
  const zestaw = await (await fetch(BASE + 'zestaw-9-zapiekanek/')).text();
  ok('zestaw: bez tabeli wartości i pinów', !/tzp-nutrition|tzp-flavors/.test(zestaw.replace(/<link[^>]+>/g, '')));

  /*
   * Part 2: browser.
   */
  const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true, args: ['--no-first-run'] });
  const errors = [];
  const open = async (slug, vp) => {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    page.on('pageerror', (e) => { if (!/cookieyes/i.test(e.message)) errors.push(slug + ': ' + e.message.slice(0, 120)); });
    await page.setViewport(vp);
    await page.goto(BASE + slug + '/', { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(1000);
    return { ctx, page };
  };

  // Desktop
  let { ctx, page } = await open('zapiekanka-salami', { width: 1440, height: 900 });
  let info = await page.evaluate(() => {
    const t = document.querySelector('.tzp-nutrition');
    const link = document.querySelector('.tzp-card-link');
    const sub = document.querySelector('.tzp-nutrition__sub th');
    const row = document.querySelector('.tzp-nutrition__table tr:not(.tzp-nutrition__sub) th');
    const pins = document.querySelector('.tzp-flavors');
    const atc = document.querySelector('form.cart');
    const info = document.querySelector('.tzp-product-info');
    return {
      table: !!t && t.getBoundingClientRect().height > 200,
      linkVisible: !!link && link.getBoundingClientRect().width > 150 && getComputedStyle(link).borderTopColor === 'rgb(140, 50, 21)',
      indent: sub && row ? parseFloat(getComputedStyle(sub).paddingLeft) - parseFloat(getComputedStyle(row).paddingLeft) : 0,
      // Pins below the purchase form (price and "Dodaj do koszyka"), above the icon block (decision of 2026-10-20).
      pinsUnderForm: !!pins && !!atc && !!info && pins.getBoundingClientRect().top >= atc.getBoundingClientRect().bottom - 1 && pins.getBoundingClientRect().top - atc.getBoundingClientRect().bottom < 60 && pins.getBoundingClientRect().bottom <= info.getBoundingClientRect().top + 1,
      tableUnderInfo: !!t && !!info && t.getBoundingClientRect().top > info.getBoundingClientRect().bottom - 1,
      uppercaseValues: t ? getComputedStyle(t.querySelector('td')).textTransform : '',
    };
  });
  ok('desktop: tabela widoczna, przycisk karty w kolorze marki', info.table && info.linkVisible, JSON.stringify(info));
  ok('desktop: wiersze "w tym" wcięte', info.indent >= 10, `wcięcie ${info.indent}px`);
  ok('desktop: wartości bez wielkich liter ("g", "kcal")', info.uppercaseValues === 'none', info.uppercaseValues);
  ok('desktop: piny pod formularzem zakupu, tabela pod blokiem ikon', info.pinsUnderForm && info.tableUnderInfo);
  await page.evaluate(() => document.querySelector('.tzp-nutrition').scrollIntoView({ block: 'center' }));
  await sleep(500);
  await page.screenshot({ path: path.join(OUT, 'desk-tabela.png') });
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 120000 }), page.evaluate(() => [...document.querySelectorAll('.tzp-flavors__pin')].find((a) => a.textContent.trim() === 'Kebab').click())]);
  ok('desktop: klik w pin "Kebab" otwiera kartę Kebab', /zapiekanka-kebab/.test(page.url()) && await page.$eval('.tzp-flavors__pin.is-current', (a) => a.textContent.trim()) === 'Kebab', page.url());
  await ctx.close();

  // Phone
  ({ ctx, page } = await open('zapiekanka-szwedzka', { width: 390, height: 844, isMobile: true, hasTouch: true }));
  info = await page.evaluate(() => {
    const list = document.querySelector('.tzp-flavors__list');
    const cur = list && list.querySelector('.is-current');
    const lr = list.getBoundingClientRect();
    const cr = cur.getBoundingClientRect();
    return {
      oneRow: getComputedStyle(list).flexWrap === 'nowrap' && list.scrollWidth > list.clientWidth,
      currentVisible: cr.left >= lr.left - 1 && cr.right <= lr.right + 1,
      scrolled: list.scrollLeft > 0,
      pageOverflow: document.documentElement.scrollWidth - window.innerWidth,
      btnFull: (() => { const b = document.querySelector('.tzp-card-link'); const f = document.querySelector('.tzp-nutrition__foot'); return b && f ? Math.round(f.getBoundingClientRect().width - b.getBoundingClientRect().width) : -1; })(),
    };
  });
  ok('telefon: piny w jednym przewijanym wierszu', info.oneRow, JSON.stringify(info));
  ok('telefon: bieżący smak (Szwedzka, 10. na liście) przewinięty do widoku', info.currentVisible && info.scrolled);
  ok('telefon: strona bez przewijania w poziomie', info.pageOverflow <= 0, `nadmiar ${info.pageOverflow}px`);
  ok('telefon: przycisk karty na całą szerokość', info.btnFull === 0, `różnica ${info.btnFull}px`);
  await page.screenshot({ path: path.join(OUT, 'tel-gora.png') });
  await page.evaluate(() => document.querySelector('.tzp-nutrition').scrollIntoView({ block: 'center' }));
  await sleep(500);
  await page.screenshot({ path: path.join(OUT, 'tel-tabela.png') });
  await ctx.close();

  ok('brak błędów JS', errors.length === 0, errors.join(' || '));
  await browser.close();

  const failed = results.filter((r) => !r).length;
  console.log(`\n${results.length - failed}/${results.length} OK. Zrzuty: ${OUT}`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
