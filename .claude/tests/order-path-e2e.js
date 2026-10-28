// Test pełnej ścieżki zamówienia ze zrzutami ekranu: strona główna i popup belki → sklep → karta produktu → zestaw 9
// → koszyk → checkout (Paczkomat, przelew) → strona podziękowania → panel (lista, zamówienie, zmiana statusu na
// "W trakcie realizacji") → raport "Zestawienie produktów" (CSV) → maile w Mailpit. Do tego telefon: karta, koszyk,
// checkout (bez zamówienia).
//
// Uruchomienie (kopia lokalna, Mailpit na http://localhost:10054, przelew BACS włączony na czas testu jak w
// checkout-e2e.js):
//   PPTR_DIR="katalog/z/node_modules" TZP_SHOTS="katalog/na/zrzuty" node .claude/tests/order-path-e2e.js
// Logowanie do panelu: test tworzy przez WP-CLI sesję administratora (TZP_ADMIN, domyślnie "Marcin") i usuwa ją na końcu.
// Po teście: wyłącz BACS, zamówienie testowe (sciezka-test@example.com, numer w order-path-id.txt) anuluj i usuń.
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const puppeteer = require(process.env.PPTR_DIR ? path.join(process.env.PPTR_DIR, 'node_modules', 'puppeteer-core') : 'puppeteer-core');

const BASE = 'http://tezapiekanki.local';
const MAILPIT = 'http://localhost:10054';
const OUT = process.env.TZP_SHOTS || path.join(require('os').tmpdir(), 'tzp-order-path-e2e');
const EMAIL = 'sciezka-test@example.com';
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
let shotNo = 0;
const shot = async (page, name, opts = {}) => {
  shotNo += 1;
  const file = `${String(shotNo).padStart(2, '0')}-${name}.png`;
  await page.screenshot({ path: path.join(OUT, file), ...opts });
  return file;
};
const hideSticky = (page) => page.addStyleTag({ content: '.wd-sticky-btn{display:none!important}' });
const cartCount = (page) => page.evaluate(() => {
  const el = document.querySelector('.wd-cart-number .wd-tools-count, .wd-tools-count');
  return el ? parseInt(el.textContent.replace(/\D+/g, ''), 10) || 0 : -1;
});
const waitCount = async (page, expected, ms = 30000) => {
  const end = Date.now() + ms;
  let c = await cartCount(page);
  while (c < expected && Date.now() < end) { await sleep(300); c = await cartCount(page); }
  return c;
};
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
const pickPoint = async (page) => {
  await page.evaluate(() => window.apaczka_mp_map_callback({ code: 'WAW01M', operator: 'INPOST', brand: 'INPOST', description: 'Przy sklepie Żabka', street: 'Prosta 18', city: 'Warszawa', postalCode: '00-850' }));
  await sleep(500);
};
const mailpit = async (p) => (await fetch(MAILPIT + p)).json();
const slug = (s) => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ł/g, 'l').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 50);

(async () => {
  const started = new Date();
  const before = await mailpit('/api/v1/messages?limit=200');
  const knownMails = new Set((before.messages || []).map((m) => m.ID));
  const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true, args: ['--no-first-run', '--hide-scrollbars'] });
  const errors = [];
  const track = (page, label) => page.on('pageerror', (e) => { if (!/cookieyes/i.test(e.message)) errors.push(label + ': ' + e.message.slice(0, 120)); });
  let orderId = '';
  let session = null;

  try {
    /*
     * 1. Klient na desktopie.
     */
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    page.setDefaultTimeout(90000);
    track(page, 'desktop');
    await page.setViewport({ width: 1440, height: 900 });

    await page.goto(BASE + '/', { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(1200);
    await shot(page, 'desktop-strona-glowna');
    await page.click('.tzp-bar-link');
    await sleep(1500);
    const popup = await page.evaluate(() => {
      const p = document.querySelector('.mfp-wrap.wd-popup-builder-wrap .wd-popup');
      const d = p && p.querySelector('.tzp-why-dates');
      return { title: p ? p.innerText.replace(/\s+/g, ' ').slice(0, 50) : '', dates: d ? d.innerText.replace(/\s+/g, ' ').trim() : '' };
    });
    ok('belka: popup "Dlaczego 2 razy w tygodniu" z najbliższym terminem', /Dlaczego wysyłamy/.test(popup.title) && /Zamów .*23:59/.test(popup.dates), popup.dates);
    await shot(page, 'desktop-popup-belki');

    await page.goto(BASE + '/zapiekanki/', { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(800);
    await shot(page, 'desktop-sklep-zapiekanki', { fullPage: true });

    await page.goto(BASE + '/zapiekanka-salami/', { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(1200);
    await hideSticky(page);
    await shot(page, 'desktop-karta-produktu', { fullPage: true });
    const notice = await page.$eval('.tzp-delivery-box', (el) => el.innerText.replace(/\s+/g, ' ').trim());
    ok('karta produktu: termin dostawy z odliczaniem', /Zamów .*dostarczymy .*Zostało/.test(notice), notice);
    await page.$eval('form.cart .qty', (q) => { q.value = 2; jQuery(q).trigger('change'); });
    let count = await cartCount(page);
    await page.click('form.cart .single_add_to_cart_button');
    let after = await waitCount(page, count + 2);
    ok('karta produktu: "Dodaj do koszyka" dodaje 2 szt. Salami', after === count + 2, `${count} -> ${after}`);
    await sleep(1500);
    await shot(page, 'desktop-dodano-do-koszyka');

    await page.goto(BASE + '/zestaw-9-zapiekanek/', { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(1200);
    const picked = await page.evaluate(() => {
      const inputs = [...document.querySelectorAll('.woosb-wrap .woosb-product input.qty')].filter((i) => !i.disabled).slice(0, 3);
      inputs.forEach((i) => { i.value = 3; jQuery(i).trigger('change'); });
      return inputs.map((i) => ((i.closest('.woosb-product').querySelector('.woosb-title-inner, .woosb-title a, .woosb-title') || {}).textContent || '').replace(/\s+/g, ' ').trim().replace(/\s*\d.*$/, ''));
    });
    await sleep(1000);
    await hideSticky(page);
    await page.$eval('.woosb-wrap', (el) => el.scrollIntoView({ block: 'start' }));
    await shot(page, 'desktop-zestaw-9-wybrane-smaki');
    count = await cartCount(page);
    await page.click('form.cart .single_add_to_cart_button');
    after = await waitCount(page, count + 1);
    ok('zestaw 9: 3 × 3 smaki dodane do koszyka', after > count, `${picked.join(', ')}; ${count} -> ${after}`);
    await sleep(1500);

    await page.goto(BASE + '/koszyk/', { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(1200);
    const cart = await page.evaluate(() => ({ rows: document.querySelectorAll('.woocommerce-cart-form .cart_item').length, notice: (document.querySelector('.tzp-delivery-box') || {}).innerText || '' }));
    ok('koszyk: Salami i zestaw, termin dostawy w prawej kolumnie', cart.rows >= 2 && /dostarczymy/.test(cart.notice), JSON.stringify(cart).slice(0, 160));
    await shot(page, 'desktop-koszyk', { fullPage: true });

    await page.goto(BASE + '/zamowienie/', { waitUntil: 'networkidle2', timeout: 120000 });
    await page.waitForSelector('.tzp-method');
    await page.waitForFunction(() => !document.querySelector('.blockUI'), { timeout: 60000 });
    await sleep(1500);
    await shot(page, 'desktop-checkout-pusty', { fullPage: true });
    const fill = async (sel, val) => { await page.$eval(sel, (el) => { el.value = ''; }); await page.type(sel, val); };
    await fill('#billing_email', EMAIL);
    await fill('#billing_phone', '500600700');
    await fill('#billing_first_name', 'Test');
    await fill('#billing_last_name', 'Ścieżka');
    await fill('#billing_address_1', 'Testowa 1');
    await fill('#billing_postcode', '00-001');
    await fill('#billing_city', 'Warszawa');
    await pickPoint(page);
    await clickAndWait(page, '#payment_method_bacs');
    await page.waitForFunction(() => !document.querySelector('.blockUI'), { timeout: 30000 });
    const tile = await page.$eval('.wc-stn-tile.is-checked', (el) => el.innerText.replace(/\s+/g, ' ').trim());
    const parcel = await page.$eval('.tzp-parcel__code', (el) => el.textContent.trim());
    ok('checkout: zaznaczony najbliższy termin i paczkomat WAW01M', !!tile && parcel === 'WAW01M', `${tile} / ${parcel}`);
    await shot(page, 'desktop-checkout-wypelniony', { fullPage: true });
    if (!(await page.$eval('#terms', (el) => el.checked))) { await page.click('#terms'); }
    await page.click('#place_order');
    const outcome = await Promise.race([
      page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 150000 }).then(() => 'nav'),
      page.waitForSelector('.woocommerce-NoticeGroup-checkout .woocommerce-error, ul.woocommerce-error', { timeout: 150000 }).then(() => 'error'),
    ]);
    if (outcome === 'error') {
      const text = await page.evaluate(() => (document.querySelector('.woocommerce-NoticeGroup-checkout, ul.woocommerce-error') || {}).innerText || '');
      throw new Error('checkout: ' + text.replace(/\s+/g, ' ').trim().slice(0, 200));
    }
    orderId = (page.url().match(/zamowienie-otrzymane\/(\d+)/) || [])[1] || '';
    fs.writeFileSync(path.join(OUT, 'order-path-id.txt'), orderId);
    await sleep(1000);
    const thanks = await page.evaluate(() => document.body.innerText);
    ok('strona podziękowania: numer zamówienia, data wysyłki i planowana dostawa', !!orderId && /Data wysyłki/.test(thanks) && /Planowana dostawa/.test(thanks), `zamówienie ${orderId}`);
    ok('strona podziękowania: bez pustych "Nazwa firmy:" i "Numer NIP:" (zamówienie bez faktury)', !/Nazwa firmy:|Numer NIP:/.test(thanks));
    await shot(page, 'desktop-strona-podziekowania', { fullPage: true });
    await ctx.close();

    const meta = JSON.parse(wp('eval', `$o = wc_get_order(${orderId}); echo wp_json_encode(array("status" => $o->get_status(), "ship" => $o->get_meta("_wc_stn_ship_date"), "delivery" => $o->get_meta("_wc_stn_delivery_date"), "iconic" => $o->get_meta("jckwds_date_ymd"), "point" => ($o->get_meta("apaczka_delivery_point")["apm_access_point_id"] ?? ""), "items" => count($o->get_items())));`));
    ok('zamówienie: daty z naszego modułu, bez Iconic, paczkomat zapisany', meta.ship && meta.delivery && !meta.iconic && meta.point === 'WAW01M', JSON.stringify(meta));

    /*
     * 2. Panel administratora.
     */
    session = JSON.parse(wp('eval', `$u = get_user_by("login", "${process.env.TZP_ADMIN || 'Marcin'}"); $exp = time() + 3 * HOUR_IN_SECONDS; $t = WP_Session_Tokens::get_instance($u->ID)->create($exp); echo wp_json_encode(array("uid" => $u->ID, "token" => $t, "auth" => array(AUTH_COOKIE, wp_generate_auth_cookie($u->ID, $exp, "auth", $t)), "li" => array(LOGGED_IN_COOKIE, wp_generate_auth_cookie($u->ID, $exp, "logged_in", $t))));`));
    const actx = await browser.createBrowserContext();
    const admin = await actx.newPage();
    admin.setDefaultTimeout(90000);
    track(admin, 'panel');
    await admin.setViewport({ width: 1440, height: 1000 });
    await admin.setCookie(
      { name: session.auth[0], value: session.auth[1], domain: 'tezapiekanki.local', path: '/' },
      { name: session.li[0], value: session.li[1], domain: 'tezapiekanki.local', path: '/' },
    );
    await admin.goto(BASE + '/wp-admin/admin.php?page=wc-orders', { waitUntil: 'networkidle2', timeout: 120000 });
    const listRow = await admin.evaluate((id) => { const r = document.querySelector(`#order-${id}`); return r ? r.innerText.replace(/\s+/g, ' ') : ''; }, orderId);
    ok('panel: zamówienie na liście z kolumną "Wysyłka"', /Wysyłka/.test(await admin.evaluate(() => document.querySelector('.wp-list-table thead').innerText)) && listRow.length > 0, listRow.slice(0, 140));
    await shot(admin, 'panel-lista-zamowien');

    await admin.goto(BASE + `/wp-admin/admin.php?page=wc-orders&action=edit&id=${orderId}`, { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(800);
    const edit = await admin.evaluate(() => document.querySelector('#order_data').innerText.replace(/\s+/g, ' '));
    ok('panel: zamówienie z datą wysyłki i dostawy', /Wysyłka/.test(edit) && /dostawa/i.test(edit), edit.slice(0, 160));
    await shot(admin, 'panel-zamowienie', { fullPage: true });

    // Zmiana statusu jak w panelu: wysyła klientowi mail "w trakcie realizacji", zamówienie trafia do raportu.
    await admin.evaluate(() => { jQuery('#order_status').val('wc-processing').trigger('change'); });
    await Promise.all([admin.waitForNavigation({ waitUntil: 'networkidle2', timeout: 120000 }), admin.click('button.save_order')]);
    const status = wp('eval', `echo wc_get_order(${orderId})->get_status();`);
    ok('panel: status zmieniony na "W trakcie realizacji"', status === 'processing', status);
    await shot(admin, 'panel-zamowienie-w-realizacji');

    // Raport produkcyjny na dzień wysyłki tego zamówienia.
    await admin.goto(BASE + '/wp-admin/admin.php?page=wc-product-summary', { waitUntil: 'networkidle2', timeout: 120000 });
    await admin.$eval('input[name="date_from"]', (el, v) => { el.value = v; }, meta.ship);
    await admin.$eval('input[name="date_to"]', (el, v) => { el.value = v; }, meta.ship);
    await shot(admin, 'panel-raport-zestawienie-produktow');
    const csv = await admin.evaluate(async () => {
      const form = document.querySelector('input[name="action"][value="generate_product_summary"]').form;
      // form.action zwróciłby pole <input name="action">, nie adres formularza.
      const res = await fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
      return res.text();
    });
    fs.writeFileSync(path.join(OUT, `raport-zestawienie-${meta.ship}.csv`), csv);
    ok('raport "Zestawienie produktów": CSV na dzień wysyłki z Salami i smakami z zestawu', /Salami/.test(csv) && picked.every((n) => csv.includes(n.replace(/^Zapiekanka\s+/, ''))), csv.replace(/\uFEFF/, '').split('\n').slice(0, 6).join(' / '));
    await actx.close();

    /*
     * 3. Telefon (bez zamówienia).
     */
    const mctx = await browser.createBrowserContext();
    const mob = await mctx.newPage();
    mob.setDefaultTimeout(90000);
    track(mob, 'telefon');
    await mob.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
    await mob.goto(BASE + '/zapiekanka-kebab/', { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(1200);
    await shot(mob, 'telefon-karta-produktu-gora');
    await hideSticky(mob);
    await shot(mob, 'telefon-karta-produktu-cala', { fullPage: true });
    count = await cartCount(mob);
    await mob.click('form.cart .single_add_to_cart_button');
    after = await waitCount(mob, count + 1);
    ok('telefon: dodanie do koszyka', after === count + 1, `${count} -> ${after}`);
    await mob.goto(BASE + '/koszyk/', { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(1000);
    await shot(mob, 'telefon-koszyk', { fullPage: true });
    await mob.goto(BASE + '/zamowienie/', { waitUntil: 'networkidle2', timeout: 120000 });
    await mob.waitForFunction(() => !document.querySelector('.blockUI'), { timeout: 60000 });
    await sleep(1200);
    await shot(mob, 'telefon-checkout-gora');
    await shot(mob, 'telefon-checkout-caly', { fullPage: true });
    await mctx.close();

    /*
     * 4. Maile (Mailpit).
     */
    let mails = [];
    for (let i = 0; i < 30; i++) {
      const list = await mailpit('/api/v1/messages?limit=200');
      mails = (list.messages || []).filter((m) => !knownMails.has(m.ID) && ((m.To || []).some((t) => t.Address === EMAIL) || new RegExp(`${orderId}\\b`).test(m.Subject)));
      if (mails.length >= 3) { break; }
      if (i === 10) { try { wp('action-scheduler', 'run'); wp('cron', 'event', 'run', '--due-now'); } catch (e) { /* bez zadań */ } }
      await sleep(1000);
    }
    mails.sort((a, b) => new Date(a.Created) - new Date(b.Created));
    const mailPage = await browser.newPage();
    await mailPage.setViewport({ width: 760, height: 900 });
    const mailList = [];
    for (const m of mails) {
      const full = await mailpit(`/api/v1/message/${m.ID}`);
      const text = (full.Text || '') + ' ' + (full.HTML || '').replace(/<[^>]+>/g, ' ');
      await mailPage.goto(`${MAILPIT}/view/${m.ID}.html`, { waitUntil: 'networkidle2', timeout: 60000 });
      const file = await shot(mailPage, `mail-${slug(m.Subject)}`, { fullPage: true });
      mailList.push({
        file,
        subject: m.Subject,
        to: (m.To || []).map((t) => t.Address).join(', '),
        replyTo: (full.ReplyTo || []).map((t) => t.Address).join(', '),
        dates: /Data wysyłki/.test(text) && /Planowana dostawa/.test(text),
        emptyLabels: /Nazwa firmy:|Numer NIP:/.test(text),
        noreply: /noreply@/.test(text),
      });
    }
    fs.writeFileSync(path.join(OUT, 'maile.json'), JSON.stringify(mailList, null, 2));
    const toCustomer = mailList.filter((m) => m.to.includes(EMAIL));
    const toShop = mailList.filter((m) => !m.to.includes(EMAIL));
    ok('maile: do klienta (przyjęte + w trakcie realizacji) i do sklepu (nowe zamówienie)', toCustomer.length >= 2 && toShop.length >= 1, mailList.map((m) => `${m.subject} → ${m.to}`).join(' | '));
    ok('maile: w każdym data wysyłki i planowana dostawa', mailList.length > 0 && mailList.every((m) => m.dates), mailList.map((m) => `${m.subject}: ${m.dates}`).join(' | '));
    ok('maile: bez pustych "Nazwa firmy:" i "Numer NIP:"', mailList.length > 0 && mailList.every((m) => !m.emptyLabels));
    // Gdy w WooCommerce włączony jest adres odpowiedzi (Reply-to), klient widzi go w stopce zamiast adresu nadawcy noreply@.
    const replyTo = wp('eval', 'echo "yes" === get_option("woocommerce_email_reply_to_enabled") ? get_option("woocommerce_email_reply_to_address") : "";');
    if (replyTo) {
      ok(`maile do klienta: kontakt i odpowiedź na ${replyTo}, bez noreply@ w treści`, toCustomer.every((m) => !m.noreply && m.replyTo === replyTo), toCustomer.map((m) => `reply-to ${m.replyTo}, noreply w treści: ${m.noreply}`).join(' | '));
      ok('mail do sklepu: odpowiedź do klienta', toShop.every((m) => m.replyTo === EMAIL), toShop.map((m) => m.replyTo).join(', '));
    }
  } catch (e) {
    ok('wyjątek w teście', false, e.message);
  } finally {
    if (session) {
      try { wp('eval', `WP_Session_Tokens::get_instance(${session.uid})->destroy("${session.token}");`); } catch (e) { /* sesja wygaśnie sama */ }
    }
    ok('brak błędów JS', errors.length === 0, errors.slice(0, 3).join(' || '));
    await browser.close();
    const failed = results.filter((r) => !r).length;
    console.log(`\n${results.length - failed}/${results.length} OK. Zamówienie: ${orderId}. Start: ${started.toISOString()}. Zrzuty: ${OUT}`);
    process.exit(failed ? 1 : 0);
  }
})();
