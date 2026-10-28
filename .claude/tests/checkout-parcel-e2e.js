// Test of parcel locker selection in the checkout (variant B) on the local copy. Described in CLAUDE.md, section 8 "Układ checkoutu".
//
// The map cannot be clicked in a test (external widget), so point selection is simulated by calling the same callback
// the map calls: window.apaczka_mp_map_callback (Apaczka) or inpost_pl_select_point_callback (InPost PL).
//
// Usage (BACS enabled for the duration of the test, as in checkout-e2e.js):
//   PPTR_DIR="katalog/z/node_modules" PROVIDER=apaczka node .claude/tests/checkout-parcel-e2e.js
//   PROVIDER=inpost requires the active InPost PL plugin and the Paczkomat method linked to "InPost Paczkomat 24/7".
// After the test: disable BACS, cancel and delete the test order (number in parcel-order-id.txt).
const path = require('path');
const fs = require('fs');
const OUT = path.join(require('os').tmpdir(), 'tzp-checkout-e2e-parcel');
fs.rmSync(path.join(OUT, 'profile'), { recursive: true, force: true });
fs.mkdirSync(OUT, { recursive: true });
const puppeteer = require(process.env.PPTR_DIR ? path.join(process.env.PPTR_DIR, 'node_modules', 'puppeteer-core') : 'puppeteer-core');

const PROVIDER = process.env.PROVIDER || 'apaczka';
const BASE = 'http://tezapiekanki.local';
const results = [];
const ok = (name, pass, info = '') => { results.push({ name, pass, info }); console.log(`${pass ? 'OK  ' : 'FAIL'}  ${name}${info ? '  | ' + info : ''}`); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function waitUpdated(page) {
  await page.evaluate(() => new Promise((resolve) => {
    let done = false;
    const finish = () => { if (!done) { done = true; setTimeout(resolve, 400); } };
    jQuery(document.body).one('updated_checkout', finish);
    setTimeout(finish, 30000);
  }));
}

async function clickAndWait(page, selector) {
  const wait = waitUpdated(page);
  await page.$eval(selector, (el) => el.scrollIntoView({ block: 'center' }));
  await page.click(selector);
  await wait;
}

// The same data shape that the given plugin's map passes.
async function pickPoint(page) {
  await page.evaluate((provider) => {
    if (provider === 'apaczka') {
      window.apaczka_mp_map_callback({ code: 'WAW01M', operator: 'INPOST', brand: 'INPOST', description: 'Przy sklepie Żabka', street: 'Prosta 18', city: 'Warszawa', postalCode: '00-850' });
    } else {
      // The "map button via JS" mode (easypack_js_map_button) loads front.js with a different callback.
      const cb = window.easypack_front_map ? window.inpost_pl_select_point_callback_js_mode : window.inpost_pl_select_point_callback;
      cb({ name: 'PL_WAW01M', address: { line1: 'Prosta 18', line2: '00-850 Warszawa' }, location_description: 'Przy sklepie Żabka' });
    }
  }, PROVIDER);
  await sleep(400);
}

const state = (page) => page.evaluate(() => {
  const box = document.querySelector('.tzp-parcel');
  const q = (sel) => (document.querySelector(sel) || {}).textContent || '';
  return {
    provider: box ? box.getAttribute('data-provider') : null,
    visible: !!box && !document.querySelector('.tzp-parcel__selected').hidden,
    code: q('.tzp-parcel__code'),
    address: q('.tzp-parcel__address'),
    desc: q('.tzp-parcel__desc'),
    button: q('.tzp-parcel__button'),
    summary: q('.tzp-summary-parcel'),
  };
});

(async () => {
  const browser = await puppeteer.launch({
    executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    headless: true,
    userDataDir: path.join(OUT, 'profile'),
    args: ['--no-first-run', '--no-default-browser-check'],
  });

  try {
    const page = await browser.newPage();
    page.setDefaultTimeout(90000);
    const jsErrors = [];
    page.on('pageerror', (e) => { if (!/cookieyes/i.test(e.message)) jsErrors.push(e.message); });
    await page.setViewport({ width: 1300, height: 900 });

    await page.goto(BASE + '/?add-to-cart=274', { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.goto(BASE + '/zamowienie/', { waitUntil: 'networkidle2', timeout: 120000 });
    await page.waitForSelector('.tzp-method');
    await page.waitForFunction(() => !document.querySelector('.blockUI'), { timeout: 60000 });
    await sleep(1500);

    // 1. Start: Paczkomat, the right plugin's button, nothing selected
    let s = await state(page);
    ok(`start: przycisk paczkomatu obsługuje ${PROVIDER}`, s.provider === PROVIDER && !s.visible && s.button === 'Wybierz paczkomat', JSON.stringify(s));

    // 2. Our button opens the plugin's map
    await page.click('.tzp-parcel__button');
    await sleep(1500);
    const opened = await page.evaluate((provider) => {
      if (provider === 'apaczka') {
        const m = document.getElementById('apaczka_pl_geowidget_modal_dynamic');
        return m ? getComputedStyle(m).display : 'brak';
      }
      const m = document.querySelector('.jBox-Modal');
      return m ? getComputedStyle(m).display : 'brak';
    }, PROVIDER);
    ok('przycisk otwiera mapę wtyczki', opened !== 'none' && opened !== 'brak', `display=${opened}`);
    await page.evaluate((provider) => {
      if (provider === 'apaczka') { const x = document.getElementById('apaczka_pl_geowidget_modal_cross'); if (x) x.click(); }
      else if (window.inpostplGeowidgetModal) { window.inpostplGeowidgetModal.close(); }
      else if (window.inpostjsGeowidgetModal) { window.inpostjsGeowidgetModal.close(); }
    }, PROVIDER);
    await sleep(600);

    // 3. Picking a point on the map
    await pickPoint(page);
    s = await state(page);
    ok('po wyborze: kod, adres i opis w sekcji Dostawa', s.visible && s.code === 'WAW01M' && /Prosta 18/.test(s.address) && /00-850 Warszawa/.test(s.address), JSON.stringify(s));
    ok('po wyborze: przycisk "Zmień paczkomat", numer w podsumowaniu', s.button === 'Zmień paczkomat' && s.summary === 'Paczkomat WAW01M', `${s.button} / ${s.summary}`);
    await page.$eval('.tzp-parcel', (el) => el.scrollIntoView({ block: 'center' }));
    await page.screenshot({ path: path.join(OUT, `${PROVIDER}-desk-selected.png`) });

    // 4. Changing the delivery date refreshes the summary: the selection must stay
    await clickAndWait(page, '.wc-stn-tile:not(.is-checked)');
    await sleep(300);
    s = await state(page);
    ok('po zmianie terminu (update_checkout): paczkomat dalej widoczny', s.visible && s.code === 'WAW01M' && s.summary === 'Paczkomat WAW01M', JSON.stringify(s));

    // 5. Changing the city also refreshes the checkout
    const wait = waitUpdated(page);
    await page.$eval('#billing_city', (el) => { el.value = 'Warszawa'; jQuery(el).trigger('change'); });
    await page.evaluate(() => jQuery(document.body).trigger('update_checkout'));
    await wait;
    await sleep(300);
    s = await state(page);
    ok('po kolejnym odświeżeniu: paczkomat dalej widoczny', s.visible && s.code === 'WAW01M', JSON.stringify(s));

    // 6. Courier and back: the plugin clears the selection, and so do we
    await clickAndWait(page, 'label.tzp-method[for$="single12"]');
    s = await state(page);
    ok('Kurier: bez paczkomatu w Dostawie i w podsumowaniu', s.provider === null && s.summary === '', JSON.stringify(s));
    await clickAndWait(page, 'label.tzp-method[for$="single10"]');
    await sleep(300);
    s = await state(page);
    ok('powrót na Paczkomat: wybór wyczyszczony, jak w wtyczce', s.provider === PROVIDER && !s.visible && s.button === 'Wybierz paczkomat', JSON.stringify(s));

    // 7. Picking again and placing an order with a parcel locker
    await pickPoint(page);
    await clickAndWait(page, '.wc-stn-tile:not(.is-checked)');
    s = await state(page);
    ok('ponowny wybór przetrwał zmianę terminu', s.visible && s.code === 'WAW01M', JSON.stringify(s));

    const fill = async (sel, val) => { await page.$eval(sel, (el) => { el.value = ''; }); await page.type(sel, val); };
    await fill('#billing_email', 'test@example.com');
    await fill('#billing_phone', '500600700');
    await fill('#billing_first_name', 'Test');
    await fill('#billing_last_name', 'Claude');
    await fill('#billing_address_1', 'Testowa 1');
    await fill('#billing_postcode', '00-001');
    await fill('#billing_city', 'Warszawa');
    await clickAndWait(page, '#payment_method_bacs');
    await page.waitForFunction(() => !document.querySelector('.blockUI'), { timeout: 30000 });
    s = await state(page);
    ok('po uzupełnieniu danych i zmianie płatności: paczkomat dalej widoczny', s.visible && s.code === 'WAW01M', JSON.stringify(s));
    if (!(await page.$eval('#terms', (el) => el.checked))) { await page.click('#terms'); }
    await page.click('#place_order');
    const outcome = await Promise.race([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 150000 }).then(() => 'nav'),
      page.waitForSelector('.woocommerce-NoticeGroup-checkout .woocommerce-error, ul.woocommerce-error', { timeout: 150000 }).then(() => 'error'),
    ]);
    if (outcome === 'error') {
      const text = await page.evaluate(() => (document.querySelector('.woocommerce-NoticeGroup-checkout, ul.woocommerce-error') || {}).innerText || '');
      ok('zamówienie z paczkomatem złożone', false, 'błąd: ' + text.replace(/\s+/g, ' ').trim().slice(0, 200));
    } else {
      const url = page.url();
      const orderId = (url.match(/zamowienie-otrzymane\/(\d+)/) || [])[1] || '';
      ok('zamówienie z paczkomatem złożone', !!orderId, `order=${orderId}`);
      fs.writeFileSync(path.join(OUT, 'parcel-order-id.txt'), orderId);
    }
    ok('brak błędów JS', jsErrors.length === 0, jsErrors.slice(0, 3).join(' | '));

    // 8. Phone: how the selected parcel locker looks
    const mob = await browser.newPage();
    await mob.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
    await mob.goto(BASE + '/?add-to-cart=274', { waitUntil: 'domcontentloaded', timeout: 120000 });
    await mob.goto(BASE + '/zamowienie/', { waitUntil: 'networkidle2', timeout: 120000 });
    await mob.waitForSelector('.tzp-parcel');
    await mob.waitForFunction(() => !document.querySelector('.blockUI'), { timeout: 60000 });
    await sleep(1000);
    await pickPoint(mob);
    s = await state(mob);
    const overflow = await mob.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    ok('telefon: wybrany paczkomat widoczny, bez poziomego przewijania', s.visible && s.code === 'WAW01M' && !overflow, JSON.stringify({ code: s.code, overflow }));
    await mob.$eval('.tzp-parcel', (el) => el.scrollIntoView({ block: 'center' }));
    await mob.screenshot({ path: path.join(OUT, `${PROVIDER}-mob-selected.png`) });
  } catch (e) {
    ok('wyjątek w teście', false, e.message);
  } finally {
    await browser.close();
    const failed = results.filter((r) => !r.pass).length;
    console.log(`\n${results.length - failed}/${results.length} OK`);
  }
})();
