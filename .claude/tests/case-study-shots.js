// Pary zrzutów "przed / po" do case study z suwakiem porównania (img-comparison-slider na ms-m.pl): dla każdego
// widoku dwa osobne pliki old_<slug>.png i new_<slug>.png o identycznym wymiarze i kadrze. "Przed" domyślnie
// z produkcji https://tezapiekanki.pl (sprzed zmian), "po" z kopii lokalnej.
//
// Uruchomienie:
//   PPTR_DIR="katalog/z/node_modules" OUT="katalog/wynikowy" node .claude/tests/case-study-shots.js
//   (BEFORE i AFTER zmieniają adresy stron "przed" i "po", ONLY=desktop/produkt,telefon/hero tylko wybrane widoki)
//
// Kadry:
// - desktop: ekran 1440 x 803 (proporcja 809 x 451 jak w case study Leaf No Trace) albo wycinek wokół elementu
//   w podwójnej rozdzielczości, zaczepiony o ten sam element na obu stronach,
// - telefon: ekran 390 x 844 w podwójnej rozdzielczości (780 x 1688), tablet 820 x 1180 (1640 x 2360).
// Na obu stronach blokowane są statystyki i piksele (GTM, GA, Facebook, TikTok), chowany baner cookies i pasek
// "Dodaj do koszyka" (poza widokiem, który go pokazuje). LiteSpeed na produkcji uruchamia JS po pierwszym ruchu
// użytkownika, więc przed zrzutem jest ruch myszy i przewinięcie. Koszyk i checkout: 1 zapiekanka w koszyku, bez zamówienia.
const path = require('path');
const fs = require('fs');
const puppeteer = require(process.env.PPTR_DIR ? path.join(process.env.PPTR_DIR, 'node_modules', 'puppeteer-core') : 'puppeteer-core');

const SIDES = { old: process.env.BEFORE || 'https://tezapiekanki.pl', new: process.env.AFTER || 'http://tezapiekanki.local' };
const OUT = process.env.OUT || path.join(require('os').tmpdir(), 'tzp-case-study');
const DEVICES = {
  desktop: { width: 1440, height: 803, deviceScaleFactor: 1 },
  detal: { width: 1440, height: 900, deviceScaleFactor: 2 },
  tablet: { width: 820, height: 1180, deviceScaleFactor: 2 },
  telefon: { width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
};
const BLOCK = /googletagmanager|google-analytics|analytics\.google|doubleclick|googleadservices|facebook\.net|facebook\.com\/tr|connect\.facebook|tiktok|pinimg|pinterest|hotjar|clarity\.ms|cookieyes|cdn-cookie|snap\.licdn/i;
const HIDE = '.cky-consent-container,.cky-overlay,.cky-btn-revisit-wrapper,.cky-modal,.wcc-consent-container,.wcc-overlay,.wcc-revisit-bottom-left,#cookie-law-info-bar{display:none!important}';
const HIDE_STICKY = '.wd-sticky-btn,.scrollToTop,.wd-scroll-top{display:none!important}';
// Przyklejony nagłówek po przewinięciu zasłaniałby górę kadru (w obu wersjach jest taki sam).
const HIDE_STICKY_HEADER = '.whb-header.whb-sticked,.whb-sticked .whb-main-header{visibility:hidden!important}';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// Szukanie elementów w przeglądarce (pierwszy widoczny).
const FIND = {
  price: () => [...document.querySelectorAll('.elementor-element-1b46f25')].find((e) => e.offsetParent),
  summary: () => document.querySelector('.elementor-element-bed0dcc'),
  nutrition: () => document.querySelector('.elementor-element-1fd0c69'),
  title: () => document.querySelector('.elementor-element-c73ed9e'),
  productFaq: () => document.querySelector('.tzp-faq') || [...document.querySelectorAll('h2')].find((h) => /FAQ|pytania/i.test(h.textContent) && h.offsetParent),
  homeFlavors: () => [...document.querySelectorAll('.elementor-element-709318e, .elementor-element-391e7b3')].find((e) => e.offsetParent),
  homeFaq: () => document.querySelector('.elementor-element-2e3bb3e'),
  // Po: karta "Dostawa" z kafelkami terminów; przed: sekcja "Wybierz datę wysyłki zamówienia" z kalendarzem Iconic.
  checkoutDate: () => {
    const tiles = document.querySelector('.wc-stn-checkout');
    if (tiles) { return tiles.closest('.tzp-card') || tiles; }
    return [...document.querySelectorAll('h1,h2,h3,h4,h5,.title,.elementor-heading-title')].find((h) => /datę wysyłki/i.test(h.textContent) && h.offsetParent) || document.querySelector('#jckwds-delivery-date-wrapper');
  },
  bundleAlert: () => document.querySelector('.woosb-alert'),
  bundleWrap: () => document.querySelector('.woosb-wrap'),
};

// slug, path, device, frame: 'viewport' | { anchor, left?, w, h, dx, dy } (wycinek w px CSS względem elementu
// anchor; left = element, od którego liczyć lewą krawędź, domyślnie anchor; w: 'full' = cała szerokość),
// scroll: element, do którego przewinąć ekran (telefon/tablet), action: 'popup' | 'sticky', cart, only, note.
const VIEWS = [
  { slug: 'hero', path: '/', device: 'desktop', frame: 'viewport', note: 'Strona główna: belka o wysyłce nad nagłówkiem.' },
  { slug: 'popup', path: '/', device: 'desktop', frame: 'viewport', action: 'popup', note: 'Przed: sama strona główna. Po: popup "Dlaczego wysyłamy tylko 2 razy w tygodniu?" z najbliższym terminem.' },
  { slug: 'smaki', path: '/', device: 'desktop', frame: { anchor: 'homeFlavors', w: 'full', h: 803, dy: -140 }, note: 'Sekcja smaków: przycisk "Zobacz wszystkie smaki", zdjęcia 1:1.' },
  { slug: 'faq-strona-glowna', path: '/', device: 'desktop', frame: { anchor: 'homeFaq', w: 'full', h: 803, dy: -150 }, note: 'FAQ na stronie głównej: 4 pytania i przycisk do pełnej listy.' },
  { slug: 'sklep', path: '/zapiekanki/', device: 'desktop', frame: 'viewport', note: 'Sklep: bez filtrów, 4 w rzędzie, zdjęcia 1:1.' },
  { slug: 'produkt', path: '/zapiekanka-salami/', device: 'desktop', frame: 'viewport', note: 'Karta produktu: galeria (duże zdjęcie i siatka), piny smaków.' },
  { slug: 'produkt-zakup', path: '/zapiekanka-salami/', device: 'detal', frame: { anchor: 'price', left: 'summary', w: 650, h: 560, dx: -5, dy: -210 }, note: 'Przy cenie: termin dostawy z odliczaniem i ikony "Info praktyczne".' },
  { slug: 'piny-smakow', path: '/zapiekanka-salami/', device: 'detal', frame: { anchor: 'price', left: 'summary', w: 650, h: 300, dx: -5, dy: -30 }, note: 'Pod ceną i "Dodaj do koszyka": piny do pozostałych smaków (przed: logotypy płatności).' },
  { slug: 'wartosc-odzywcza', path: '/zapiekanka-salami/', device: 'detal', frame: { anchor: 'nutrition', left: 'summary', w: 650, h: 760, dx: -5, dy: -30 }, note: 'Wartość odżywcza z kart produktu, tabela jak na etykiecie, waga osobno, przycisk karty PDF.' },
  { slug: 'faq-produkt', path: '/zapiekanka-salami/', device: 'desktop', frame: { anchor: 'productFaq', w: 'full', h: 803, dy: -120 }, note: 'FAQ w opisie produktu: pytania rozwijane i przycisk do wszystkich pytań.' },
  { slug: 'zestaw-komunikat', path: '/zestaw-9-zapiekanek/', device: 'detal', frame: { anchor: 'bundleAlert', left: 'bundleWrap', w: 760, h: 424, dx: -30, dy: -260 }, note: 'Komunikat pustego zestawu: "Wybierz 9 zapiekanek" zamiast "Wybierz [min] zapiekanek".' },
  { slug: 'koszyk', path: '/koszyk/', cart: true, device: 'desktop', frame: 'viewport', note: 'Koszyk: termin dostawy i wartość koszyka obok produktów.' },
  { slug: 'checkout', path: '/zamowienie/', cart: true, device: 'desktop', frame: 'viewport', note: 'Checkout: kroki i podsumowanie z boku.' },
  { slug: 'checkout-termin', path: '/zamowienie/', cart: true, device: 'desktop', frame: { anchor: 'checkoutDate', w: 'full', h: 803, dy: -60 }, note: 'Termin w checkoucie: kafelki z najbliższymi terminami i metody dostawy zamiast pola z kalendarzem.' },
  { slug: 'faq-strona', path: '/faq/', device: 'desktop', frame: 'viewport', only: 'new', note: 'Nowa strona "Najczęstsze pytania" (wcześniej /faq/ przekierowywało na stronę główną).' },

  { slug: 'hero', path: '/', device: 'telefon', frame: 'viewport', note: 'Telefon: belka, bez dolnego paska nawigacji.' },
  { slug: 'produkt', path: '/zapiekanka-salami/', device: 'telefon', frame: 'viewport', note: 'Telefon: galeria pod nagłówkiem, karuzela z licznikiem, piny smaków.' },
  { slug: 'produkt-zakup', path: '/zapiekanka-salami/', device: 'telefon', frame: 'viewport', scroll: { anchor: 'price', dy: -330 }, note: 'Telefon: termin dostawy, cena, ikony.' },
  { slug: 'piny-smakow', path: '/zapiekanka-salami/', device: 'telefon', frame: { anchor: 'price', w: 'full', h: 300, dy: -30 }, note: 'Telefon: piny smaków w przewijanym wierszu pod przyciskiem.' },
  { slug: 'wartosc-odzywcza', path: '/zapiekanka-salami/', device: 'telefon', frame: 'viewport', scroll: { anchor: 'nutrition', dy: -80 }, note: 'Telefon: wartość odżywcza i karta PDF.' },
  { slug: 'pasek-dodaj-do-koszyka', path: '/zapiekanka-salami/', device: 'telefon', frame: 'viewport', action: 'sticky', note: 'Telefon: pasek "Dodaj do koszyka" po przewinięciu.' },
  { slug: 'koszyk', path: '/koszyk/', cart: true, device: 'telefon', frame: 'viewport', note: 'Telefon: koszyk.' },
  { slug: 'checkout', path: '/zamowienie/', cart: true, device: 'telefon', frame: 'viewport', note: 'Telefon: checkout, podsumowanie zwinięte w pasek.' },
  { slug: 'checkout-termin', path: '/zamowienie/', cart: true, device: 'telefon', frame: 'viewport', scroll: { anchor: 'checkoutDate', dy: -40 }, note: 'Telefon: termin dostawy w checkoucie.' },

  { slug: 'produkt', path: '/zapiekanka-salami/', device: 'tablet', frame: 'viewport', scroll: { anchor: 'title', dy: -40 }, note: 'Tablet: odstęp od krawędzi ekranu.' },
];

async function interact(page) {
  await page.mouse.move(200, 300);
  await page.mouse.move(360, 420);
  await page.evaluate(() => window.scrollBy(0, 250));
  await sleep(2500);
  await page.evaluate(() => window.scrollTo(0, 0));
  await sleep(1500);
}

async function capture(browser, side, view) {
  const ctx = await browser.createBrowserContext();
  const page = await ctx.newPage();
  page.setDefaultTimeout(90000);
  await page.setRequestInterception(true);
  page.on('request', (r) => (BLOCK.test(r.url()) ? r.abort() : r.continue()));
  await page.setViewport(DEVICES[view.device]);
  const base = SIDES[side];
  if (view.cart) {
    await page.goto(base + '/?add-to-cart=399', { waitUntil: 'networkidle2', timeout: 120000 });
  }
  await page.goto(base + view.path, { waitUntil: 'networkidle2', timeout: 120000 });
  await page.addStyleTag({ content: HIDE });
  await interact(page);

  const dir = path.join(OUT, view.device === 'detal' ? 'desktop' : view.device);
  fs.mkdirSync(dir, { recursive: true });
  const file = path.join(dir, `${side}_${view.slug}.png`);

  try {
    if (view.action !== 'sticky') {
      await page.addStyleTag({ content: HIDE_STICKY });
    }
    if (view.action === 'popup' && side === 'new') {
      await page.click('.tzp-bar-link');
      await sleep(1500);
    }
    if (view.action === 'sticky') {
      await page.evaluate(() => window.scrollTo(0, document.querySelector('form.cart').getBoundingClientRect().bottom + window.scrollY + 900));
      await sleep(1500);
    }
    if (view.scroll || view.frame !== 'viewport') {
      await page.addStyleTag({ content: HIDE_STICKY_HEADER });
    }
    if (view.scroll) {
      const ok = await page.evaluate((fn, dy) => {
        const el = new Function('return (' + fn + ')()')();
        if (!el) { return false; }
        window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY + dy);
        return true;
      }, FIND[view.scroll.anchor].toString(), view.scroll.dy);
      if (!ok) { throw new Error('brak elementu ' + view.scroll.anchor); }
      await sleep(1200);
    }

    if (view.frame === 'viewport') {
      await page.screenshot({ path: file });
    } else {
      // Wycinek: przezroczysty element w miejscu kadru, zrzut tego elementu (Puppeteer sam przewija i przycina).
      const f = view.frame;
      const box = await page.evaluate((anchorFn, leftFn, frame) => {
        const get = (fn) => (fn ? new Function('return (' + fn + ')()')() : null);
        const a = get(anchorFn);
        if (!a) { return null; }
        const l = get(leftFn) || a;
        const ra = a.getBoundingClientRect();
        const rl = l.getBoundingClientRect();
        const width = frame.w === 'full' ? document.documentElement.clientWidth : frame.w;
        const x = frame.w === 'full' ? 0 : Math.max(0, rl.left + window.scrollX + (frame.dx || 0));
        const y = Math.max(0, ra.top + window.scrollY + (frame.dy || 0));
        const d = document.createElement('div');
        d.id = 'tzp-frame';
        d.style.cssText = `position:absolute;left:${x}px;top:${y}px;width:${width}px;height:${frame.h}px;pointer-events:none;z-index:-1`;
        document.documentElement.appendChild(d);
        return { x, y, width, height: frame.h };
      }, FIND[f.anchor].toString(), f.left ? FIND[f.left].toString() : null, f);
      if (!box) { throw new Error('brak elementu ' + f.anchor); }
      await sleep(800);
      const frameEl = await page.$('#tzp-frame');
      await frameEl.screenshot({ path: file });
    }
  } catch (e) {
    console.log(`  ${side} ${view.device}/${view.slug}: ${e.message.slice(0, 100)}`);
    await ctx.close();
    return null;
  }
  await ctx.close();
  return file;
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true, args: ['--no-first-run', '--hide-scrollbars'] });
  const index = [];
  const only = (process.env.ONLY || '').split(',').filter(Boolean);
  for (const view of VIEWS) {
    if (only.length && !only.includes(view.device + '/' + view.slug)) { continue; }
    const done = {};
    for (const side of ['old', 'new']) {
      done[side] = view.only && view.only !== side ? null : await capture(browser, side, view);
    }
    const folder = view.device === 'detal' ? 'desktop' : view.device;
    console.log(`${folder}/${view.slug}: old ${done.old ? 'OK' : '-'}, new ${done.new ? 'OK' : '-'}`);
    index.push({ folder, slug: view.slug, note: view.note, old: !!done.old, new: !!done.new });
  }
  if (!only.length) { fs.writeFileSync(path.join(OUT, 'pary.json'), JSON.stringify(index, null, 2)); }
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
