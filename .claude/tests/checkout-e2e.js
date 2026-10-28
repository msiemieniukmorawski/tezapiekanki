// Test E2E checkoutu (wariant B) na kopii lokalnej http://tezapiekanki.local. Opis w CLAUDE.md, sekcja 8 "Układ checkoutu".
//
// Uruchomienie:
//   1. raz: w dowolnym katalogu `npm i puppeteer-core@23.11.1` (używa zainstalowanego Chrome),
//   2. włącz BACS: wp eval '$b = get_option("woocommerce_bacs_settings"); $b["enabled"] = "yes"; update_option("woocommerce_bacs_settings", $b);'
//   3. PPTR_DIR="ścieżka/do/katalogu/z/node_modules" node .claude/tests/checkout-e2e.js
//   4. po teście: wyłącz BACS, a zamówienie testowe (test@example.com, numer w e2e-order-id.txt) anuluj i usuń.
// Zrzuty ekranu i profil przeglądarki: %TEMP%\tzp-checkout-e2e. Każde uruchomienie zaczyna z pustym koszykiem.
const path = require('path');
const fs = require('fs');
const SCRATCH = path.join(require('os').tmpdir(), 'tzp-checkout-e2e');
fs.rmSync(path.join(SCRATCH, 'pptr-profile'), { recursive: true, force: true });
fs.mkdirSync(SCRATCH, { recursive: true });
const puppeteer = require(process.env.PPTR_DIR ? path.join(process.env.PPTR_DIR, 'node_modules', 'puppeteer-core') : 'puppeteer-core');

const BASE = 'http://tezapiekanki.local';
const results = [];
const ok = (name, pass, info = '') => { results.push({ name, pass, info }); console.log(`${pass ? 'OK  ' : 'FAIL'}  ${name}${info ? '  | ' + info : ''}`); };

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
  await page.click(selector);
  await wait;
}

(async () => {
  const browser = await puppeteer.launch({
    executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    headless: true,
    userDataDir: path.join(SCRATCH, 'pptr-profile'),
    args: ['--no-first-run', '--no-default-browser-check'],
  });

  try {
    const page = await browser.newPage();
    page.setDefaultTimeout(90000);
    const jsErrors = [];
    // CookieYes zgłasza błąd, bo domena tezapiekanki.local nie jest u nich zarejestrowana (tylko lokalnie).
    page.on('pageerror', (e) => { if (!/cookieyes/i.test(e.message)) jsErrors.push(e.message); });
    await page.setViewport({ width: 1300, height: 900 });

    await page.goto(BASE + '/?add-to-cart=274', { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.goto(BASE + '/zamowienie/', { waitUntil: 'networkidle2', timeout: 120000 });
    await page.waitForSelector('.tzp-method');
    await new Promise((r) => setTimeout(r, 1500));

    // 1. Stan początkowy
    let s = await page.evaluate(() => ({
      tiles: document.querySelectorAll('.tzp-method').length,
      checkedTile: (document.querySelector('.tzp-method.is-checked .tzp-method__name') || {}).textContent,
      native: (document.querySelector('input[name="shipping_method[0]"]:checked') || {}).value,
      parcelBtn: !!document.querySelector('.tzp-parcel__button'),
      termTiles: document.querySelectorAll('.wc-stn-tile').length,
      button: (document.getElementById('place_order') || {}).textContent,
    }));
    ok('start: 2 kafelki metod, zaznaczony = oryginalne pole', s.tiles === 2 && s.checkedTile === 'InPost Paczkomat' && s.native === 'flexible_shipping_single:10', JSON.stringify({ tiles: s.tiles, checked: s.checkedTile, native: s.native }));
    ok('start: przycisk paczkomatu i 6 kafelków terminu', s.parcelBtn && s.termTiles === 6, `termTiles=${s.termTiles}`);
    ok('start: przycisk z kwotą', /Kupuję i płacę · 39,99/.test(s.button || ''), s.button);

    // 2. Kurier
    await clickAndWait(page, 'label.tzp-method[for$="single12"]');
    s = await page.evaluate(() => ({
      native: (document.querySelector('input[name="shipping_method[0]"]:checked') || {}).value,
      checkedTile: (document.querySelector('.tzp-method.is-checked .tzp-method__name') || {}).textContent,
      parcelBtn: !!document.querySelector('.tzp-parcel__button'),
      summary: (document.querySelector('.tzp-checkout-side .woocommerce-shipping-totals td') || {}).innerText,
    }));
    ok('klik Kurier: oryginalne pole i kafelek przełączone', s.native === 'flexible_shipping_single:12' && s.checkedTile === 'InPost Kurier', JSON.stringify(s));
    ok('klik Kurier: bez przycisku paczkomatu, podsumowanie pokazuje Kuriera', !s.parcelBtn && /Kurier/.test(s.summary || ''), (s.summary || '').trim());

    // 3. Paczkomat i mapa Apaczki
    await clickAndWait(page, 'label.tzp-method[for$="single10"]');
    s = await page.evaluate(() => ({ native: (document.querySelector('input[name="shipping_method[0]"]:checked') || {}).value, parcelBtn: !!document.querySelector('.tzp-parcel__button') }));
    ok('powrót na Paczkomat: przycisk paczkomatu wraca', s.native === 'flexible_shipping_single:10' && s.parcelBtn, JSON.stringify(s));
    await page.click('.tzp-parcel__button');
    await new Promise((r) => setTimeout(r, 1500));
    const modal = await page.evaluate(() => { const m = document.getElementById('apaczka_pl_geowidget_modal_dynamic'); return m ? getComputedStyle(m).display : 'brak'; });
    ok('przycisk paczkomatu otwiera mapę Apaczki', modal !== 'none' && modal !== 'brak', `modal display=${modal}`);
    await page.evaluate(() => { const x = document.getElementById('apaczka_pl_geowidget_modal_cross'); if (x) x.click(); });
    await new Promise((r) => setTimeout(r, 500));
    const modalAfter = await page.evaluate(() => { const m = document.getElementById('apaczka_pl_geowidget_modal_dynamic'); return m ? getComputedStyle(m).display : 'brak'; });
    ok('mapa Apaczki zamyka się krzyżykiem', modalAfter === 'none', `modal display=${modalAfter}`);

    // 4. Faktura na firmę
    s = await page.evaluate(() => ({ hidden: document.getElementById('billing_nazwa_firmy_field').classList.contains('tzp-is-hidden') && document.getElementById('billing_numer_nip_field').classList.contains('tzp-is-hidden') }));
    await page.click('#tzp_want_invoice');
    const shown = await page.evaluate(() => !document.getElementById('billing_nazwa_firmy_field').classList.contains('tzp-is-hidden'));
    ok('faktura: pola firmy ukryte, po zaznaczeniu widoczne', s.hidden && shown);
    await page.click('#tzp_want_invoice');

    // 5. Kupon: zły kod
    await page.click('.tzp-coupon__box summary');
    await page.type('#tzp_coupon_code', 'NIEMATAKIEGO');
    await clickAndWait(page, '.tzp-coupon__apply');
    s = await page.evaluate(() => ({ open: document.querySelector('.tzp-coupon__box').open, msg: document.querySelector('.tzp-coupon__msg').innerText.trim(), value: document.getElementById('tzp_coupon_code').value }));
    ok('kupon: zły kod pokazuje błąd pod polem, pole zostaje otwarte z wpisanym kodem', s.open && /nie istnieje|nieprawid|not exist/i.test(s.msg) && s.value === 'NIEMATAKIEGO', s.msg);

    // 6. Kupon: dobry kod (Enter nie może wysłać zamówienia)
    await page.evaluate(() => { document.getElementById('tzp_coupon_code').value = ''; });
    await page.type('#tzp_coupon_code', 'HEJ10');
    const wait = waitUpdated(page);
    await page.keyboard.press('Enter');
    await wait;
    s = await page.evaluate(() => ({
      url: location.pathname,
      discount: !!document.querySelector('.tzp-checkout-side .cart-discount'),
      total: (document.querySelector('.tzp-checkout-side .order-total .woocommerce-Price-amount') || {}).innerText,
      button: (document.getElementById('place_order') || {}).textContent,
      msg: document.querySelector('.tzp-coupon__msg') ? document.querySelector('.tzp-coupon__msg').innerText.trim() : '',
    }));
    ok('kupon HEJ10 przez Enter: rabat w podsumowaniu, bez wysłania zamówienia', s.url === '/zamowienie/' && s.discount, JSON.stringify({ total: s.total, msg: s.msg }));
    ok('kupon: kwota na przycisku zaktualizowana', /37,49/.test(s.button || ''), s.button);

    // 7. Usunięcie kuponu oryginalnym linkiem WooCommerce
    await clickAndWait(page, '.tzp-checkout-side .woocommerce-remove-coupon');
    s = await page.evaluate(() => ({ discount: !!document.querySelector('.tzp-checkout-side .cart-discount'), button: (document.getElementById('place_order') || {}).textContent }));
    ok('usunięcie kuponu oryginalnym linkiem WooCommerce', !s.discount && /39,99/.test(s.button || ''), s.button);

    // 8. Przyklejone podsumowanie
    await page.evaluate(() => window.scrollTo(0, 1400));
    await new Promise((r) => setTimeout(r, 500));
    const sideTop = await page.evaluate(() => Math.round(document.querySelector('.tzp-checkout-side').getBoundingClientRect().top));
    ok('podsumowanie przyklejone przy przewijaniu (top ~30 px)', sideTop >= 0 && sideTop <= 60, `top=${sideTop}`);
    await page.screenshot({ path: path.join(SCRATCH, 'e2e-desk-scrolled.png') });
    await page.evaluate(() => window.scrollTo(0, 0));

    // 9. Zamówienie z Paczkomatem bez wybranego punktu: walidacja Apaczki
    const fill = async (sel, val) => { await page.$eval(sel, (el) => { el.value = ''; }); await page.type(sel, val); };
    await fill('#billing_email', 'test@example.com');
    await fill('#billing_phone', '500600700');
    await fill('#billing_first_name', 'Test');
    await fill('#billing_last_name', 'Claude');
    await fill('#billing_address_1', 'Testowa 1');
    await fill('#billing_postcode', '00-001');
    await fill('#billing_city', 'Warszawa');
    await clickAndWait(page, '#payment_method_bacs');
    if (!(await page.$eval('#terms', (el) => el.checked))) { await page.click('#terms'); }
    await page.click('#place_order');
    await page.waitForSelector('.woocommerce-error, .woocommerce-NoticeGroup-checkout', { timeout: 60000 });
    const err = await page.evaluate(() => (document.querySelector('.woocommerce-NoticeGroup-checkout, .woocommerce-error') || {}).innerText || '');
    ok('Paczkomat bez punktu: zamówienie zablokowane walidacją Apaczki', /paczkomat|parcel|punkt/i.test(err), err.replace(/\s+/g, ' ').trim().slice(0, 120));

    // 10. Kurier: zamówienie przechodzi (najpierw znika nakładka blockUI po błędzie)
    await page.waitForFunction(() => !document.querySelector('.blockUI'), { timeout: 30000 });
    await new Promise((r) => setTimeout(r, 800));
    await page.$eval('label.tzp-method[for$="single12"]', (el) => el.scrollIntoView({ block: 'center' }));
    await clickAndWait(page, 'label.tzp-method[for$="single12"]');
    const before = await page.evaluate(() => ({
      native: (document.querySelector('input[name="shipping_method[0]"]:checked') || {}).value,
      inForm: !!document.querySelector('form.checkout input[name="shipping_method[0]"]'),
    }));
    ok('przed zamówieniem: wybrany Kurier w oryginalnym polu, pole w formularzu', before.native === 'flexible_shipping_single:12' && before.inForm, JSON.stringify(before));
    if (!(await page.$eval('#terms', (el) => el.checked))) { await page.click('#terms'); }
    await page.evaluate(() => document.querySelectorAll('.woocommerce-NoticeGroup-checkout, .woocommerce-error').forEach((el) => el.remove()));
    await page.click('#place_order');
    const outcome = await Promise.race([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 150000 }).then(() => 'nav'),
      page.waitForSelector('.woocommerce-NoticeGroup-checkout .woocommerce-error, ul.woocommerce-error', { timeout: 150000 }).then(() => 'error'),
    ]);
    if (outcome === 'error') {
      const text = await page.evaluate(() => (document.querySelector('.woocommerce-NoticeGroup-checkout, ul.woocommerce-error') || {}).innerText || '');
      ok('Kurier: zamówienie złożone', false, 'błąd: ' + text.replace(/\s+/g, ' ').trim().slice(0, 200));
      await page.screenshot({ path: path.join(SCRATCH, 'e2e-order-error.png'), fullPage: true });
    }
    s = await page.evaluate(() => ({ url: location.href, text: document.body.innerText }));
    const orderId = (s.url.match(/zamowienie-otrzymane\/(\d+)/) || [])[1] || '';
    ok('Kurier: zamówienie złożone, strona podziękowania z datami', !!orderId && /Data wysyłki/.test(s.text) && /Planowana dostawa/.test(s.text), `order=${orderId}`);
    require('fs').writeFileSync(path.join(SCRATCH, 'e2e-order-id.txt'), orderId);

    ok('brak błędów JS na stronie', jsErrors.length === 0, jsErrors.slice(0, 3).join(' | '));

    // 11. Telefon
    const mob = await browser.newPage();
    await mob.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 1 });
    await mob.setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1');
    await mob.goto(BASE + '/?add-to-cart=274', { waitUntil: 'domcontentloaded', timeout: 120000 });
    await mob.goto(BASE + '/zamowienie/', { waitUntil: 'networkidle2', timeout: 120000 });
    await mob.waitForSelector('.tzp-summary-toggle');
    await mob.waitForFunction(() => !document.querySelector('.blockUI'), { timeout: 60000 });
    await new Promise((r) => setTimeout(r, 1500));
    const mobState = () => mob.evaluate(() => {
      const shown = (sel) => { const el = document.querySelector(sel); return !!el && el.getClientRects().length > 0; };
      const summary = document.querySelector('.tzp-coupon__box summary');
      const r = summary ? summary.getBoundingClientRect() : null;
      return {
        overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
        toggleTotal: document.querySelector('.tzp-summary-toggle__total').textContent,
        orderTotal: document.querySelector('.tzp-checkout-side .order-total .woocommerce-Price-amount').textContent.trim(),
        itemsShown: shown('.tzp-checkout-side .woocommerce-checkout-review-order-table > tbody'),
        totalRowShown: shown('.tzp-checkout-side tr.order-total'),
        discountShown: shown('.tzp-checkout-side tr.cart-discount'),
        couponOnFirstScreen: !!r && r.height > 0 && r.bottom <= window.innerHeight,
        fieldLeft: Math.round(document.getElementById('billing_email').getBoundingClientRect().left),
        order: ['.tzp-summary-toggle', '.tzp-coupon__box summary', '#billing_email', '.tzp-method', '.wc-stn-tile', '#place_order'].map((sel) => Math.round(document.querySelector(sel).getBoundingClientRect().top + window.scrollY)),
      };
    });
    s = await mobState();
    ok('telefon: bez poziomego przewijania', !s.overflow);
    ok('telefon: pasek z kwotą "Łącznie", produkty i sumy zwinięte', s.toggleTotal !== '' && s.toggleTotal === s.orderTotal && !s.itemsShown && !s.totalRowShown, `${s.toggleTotal} / Łącznie ${s.orderTotal}`);
    ok('telefon: "Masz kod rabatowy?" widoczny od razu, na pierwszym ekranie', s.couponOnFirstScreen);
    ok('telefon: boczny odstęp pola najwyżej 32 px', s.fieldLeft <= 32, `left=${s.fieldLeft}`);
    ok('telefon: kolejność pasek > kupon > dane > dostawa > termin > przycisk', s.order.every((v, i, a) => i === 0 || v > a[i - 1]), s.order.join(' < '));
    await mob.screenshot({ path: path.join(SCRATCH, 'e2e-mob-top.png'), fullPage: false });

    // Kupon na telefonie bez rozwijania podsumowania
    await mob.click('.tzp-coupon__box summary');
    await mob.type('#tzp_coupon_code', 'HEJ10');
    const mobWait = waitUpdated(mob);
    await mob.keyboard.press('Enter');
    await mobWait;
    const mobBefore = s.toggleTotal;
    s = await mobState();
    ok('telefon: kupon bez rozwijania, użyty kupon widoczny, kwota w pasku niższa', s.discountShown && !s.itemsShown && s.toggleTotal !== mobBefore && s.toggleTotal === s.orderTotal, `${mobBefore} -> ${s.toggleTotal}`);
    await mob.screenshot({ path: path.join(SCRATCH, 'e2e-mob-coupon.png'), fullPage: false });
    await clickAndWait(mob, '.tzp-checkout-side .woocommerce-remove-coupon');
    s = await mobState();
    ok('telefon: usunięcie kuponu z zwiniętego podsumowania', !s.discountShown && s.toggleTotal === mobBefore, s.toggleTotal);

    await mob.click('.tzp-summary-toggle');
    await new Promise((r) => setTimeout(r, 300));
    s = await mobState();
    ok('telefon: pasek rozwija podsumowanie', s.itemsShown && s.totalRowShown);
    await mob.screenshot({ path: path.join(SCRATCH, 'e2e-mob-open.png'), fullPage: false });
    await mob.screenshot({ path: path.join(SCRATCH, 'e2e-mob-full.png'), fullPage: true });
  } catch (e) {
    ok('wyjątek w teście', false, e.message);
  } finally {
    await browser.close();
    const failed = results.filter((r) => !r.pass).length;
    console.log(`\n${results.length - failed}/${results.length} OK`);
  }
})();
