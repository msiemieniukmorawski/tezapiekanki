// Test of passing the cutoff time in the browser (the way it works on a page cached by LiteSpeed): the page clock is
// shifted so that the nearest cutoff from the wc-ship-today-notice engine (dates from WP-CLI) passes 60 s after
// the test starts. Checks that once it has passed, without reloading the page, these change: the date box on the product
// page, the date tiles in the checkout (the closed slot disappears, the next one gets selected, a notice appears)
// and the bar popup. Writes nothing.
// Server-side logic (holidays, excluded dates, validation) is covered by wp-content/plugins/wc-ship-today-notice/tests/cli-schedule-test.php.
//
// Usage: PPTR_DIR="katalog/z/node_modules" TZP_SHOTS="katalog/na/zrzuty" node .claude/tests/cutoff-e2e.js
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const puppeteer = require(process.env.PPTR_DIR ? path.join(process.env.PPTR_DIR, 'node_modules', 'puppeteer-core') : 'puppeteer-core');

const BASE = 'http://tezapiekanki.local';
const OUT = process.env.TZP_SHOTS || path.join(require('os').tmpdir(), 'tzp-cutoff-e2e');
fs.mkdirSync(OUT, { recursive: true });
const WP = {
  php: process.env.WPPHP || 'C:/Users/msiem/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe',
  ini: process.env.WPINI || 'C:/Users/msiem/AppData/Roaming/Local/run/B6RiQ0Is4/conf/php/php.ini',
  phar: 'C:/tools/wp-cli/wp.phar',
  root: 'C:/Users/msiem/Documents/local site 2/tezapiekanki/app/public',
};
const wp = (...args) => execFileSync(WP.php, ['-c', WP.ini, WP.phar, '--path=' + WP.root, '--exec=define("DB_HOST","127.0.0.1:10061");', ...args], { encoding: 'utf8' }).trim();
const results = [];
const ok = (name, pass, info = '') => { results.push(pass); console.log(`${pass ? 'OK  ' : 'FAIL'}  ${name}${info ? '  | ' + info : ''}`); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// The two nearest slots: [cutoff (s), ship date, delivery date].
const slots = JSON.parse(wp('eval', 'echo wp_json_encode(array_map(function ($s) { return array($s["cutoff"]->getTimestamp(), $s["ship"]->format("Y-m-d"), $s["delivery"] ? $s["delivery"]->format("Y-m-d") : ""); }, wc_stn_get_slots(2)));'));
const [first, second] = slots;

// One clock shift for all tabs: the page clock passes the cutoff LEAD seconds after the test starts
// (time to load the product page and the checkout).
const LEAD = 60;
const OFFSET = (first[0] - LEAD) * 1000 - Date.now();
const cutoffAt = Date.now() + LEAD * 1000;
const fakeClock = `(() => {
  const RealDate = Date;
  const OFFSET = ${OFFSET};
  class FakeDate extends RealDate {
    constructor(...a) { if (a.length === 0) { super(RealDate.now() + OFFSET); } else { super(...a); } }
    static now() { return RealDate.now() + OFFSET; }
  }
  FakeDate.UTC = RealDate.UTC;
  FakeDate.parse = RealDate.parse;
  window.Date = FakeDate;
})();`;

(async () => {
  const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true, args: ['--no-first-run', '--hide-scrollbars'] });
  const errors = [];
  const open = async (url, vp = { width: 1440, height: 900 }) => {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    page.setDefaultTimeout(90000);
    page.on('pageerror', (e) => { if (!/cookieyes/i.test(e.message)) errors.push(e.message.slice(0, 120)); });
    await page.emulateTimezone('Europe/Warsaw');
    await page.evaluateOnNewDocument(fakeClock);
    await page.setViewport(vp);
    await page.goto(url, { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(1000);
    return { ctx, page };
  };
  const waitPast = async () => { const left = cutoffAt - Date.now() + 2500; if (left > 0) { await sleep(left); } };

  try {
    console.log(`Najbliższy termin: godzina graniczna ${new Date(first[0] * 1000).toISOString()}, wysyłka ${first[1]}, dostawa ${first[2]}. Następny: wysyłka ${second[1]}, dostawa ${second[2]}.`);

    const prod = await open(BASE + '/zapiekanka-salami/');
    await prod.page.addStyleTag({ content: '.wd-sticky-btn{display:none!important}' });
    const box = () => prod.page.$eval('.tzp-delivery-box', (el) => el.innerText.replace(/\s+/g, ' ').trim());
    const beforeText = await box();
    await prod.page.$eval('.tzp-delivery-box', (el) => el.scrollIntoView({ block: 'center' }));
    await (await prod.page.$('.tzp-delivery-box')).screenshot({ path: path.join(OUT, '01-karta-produktu-przed-godzina-graniczna.png') });

    const chk = await open(BASE + '/?add-to-cart=274');
    await chk.page.goto(BASE + '/zamowienie/', { waitUntil: 'networkidle2', timeout: 120000 });
    await chk.page.waitForSelector('.wc-stn-tile');
    await chk.page.waitForFunction(() => !document.querySelector('.blockUI'), { timeout: 60000 });
    const tiles = () => chk.page.evaluate(() => ({
      values: [...document.querySelectorAll('.wc-stn-tile__input')].map((i) => i.value),
      checked: (document.querySelector('.wc-stn-tile__input:checked') || {}).value || '',
      notice: (() => { const n = document.querySelector('.wc-stn-checkout__notice'); return n && !n.hidden ? n.textContent.trim() : ''; })(),
    }));
    const t1 = await tiles();
    await chk.page.$eval('.wc-stn-checkout', (el) => el.scrollIntoView({ block: 'center' }));
    await (await chk.page.$('.wc-stn-checkout')).screenshot({ path: path.join(OUT, '03-checkout-przed-godzina-graniczna.png') });
    ok('przed: strony załadowane przed godziną graniczną zegara stron', Date.now() < cutoffAt - 3000, `zapas ${Math.round((cutoffAt - Date.now()) / 1000)} s`);
    ok('przed: box na karcie produktu z najbliższym terminem i odliczaniem', /Zostało/.test(beforeText), beforeText);
    ok('przed: w checkoucie zaznaczony najbliższy termin', t1.checked === first[1] && t1.values[0] === first[1], JSON.stringify(t1));

    await waitPast();
    const afterText = await box();
    ok('po: box sam przeszedł na kolejny termin (bez przeładowania)', afterText !== beforeText && /Zostało/.test(afterText), afterText);
    await (await prod.page.$('.tzp-delivery-box')).screenshot({ path: path.join(OUT, '02-karta-produktu-po-godzinie-granicznej.png') });

    await chk.page.waitForFunction(() => !document.querySelector('.blockUI'), { timeout: 60000 });
    await sleep(800);
    const t2 = await tiles();
    ok('po: zamknięty termin zniknął z kafelków, zaznaczony kolejny', !t2.values.includes(first[1]) && t2.checked === second[1], JSON.stringify(t2));
    ok('po: komunikat o zamkniętym terminie', /zamknął/.test(t2.notice), t2.notice);
    await (await chk.page.$('.wc-stn-checkout')).screenshot({ path: path.join(OUT, '04-checkout-po-godzinie-granicznej.png') });
    await prod.ctx.close();
    await chk.ctx.close();

    // Bar popup: a new page with the same clock (the cutoff has already passed).
    const home = await open(BASE + '/');
    await home.page.click('.tzp-bar-link');
    await sleep(1500);
    const popup = await home.page.evaluate(() => { const d = document.querySelector('.mfp-wrap .tzp-why-dates'); return d ? d.innerText.replace(/\s+/g, ' ').trim() : ''; });
    ok('popup belki po godzinie granicznej: termin kolejnej wysyłki', /Zamów/.test(popup), popup);
    await home.page.screenshot({ path: path.join(OUT, '05-popup-belki-po-godzinie-granicznej.png') });
    await home.ctx.close();
  } catch (e) {
    ok('wyjątek w teście', false, e.message);
  } finally {
    ok('brak błędów JS', errors.length === 0, errors.slice(0, 3).join(' || '));
    await browser.close();
    const failed = results.filter((r) => !r).length;
    console.log(`\n${results.length - failed}/${results.length} OK. Zrzuty: ${OUT}`);
    process.exit(failed ? 1 : 0);
  }
})();
