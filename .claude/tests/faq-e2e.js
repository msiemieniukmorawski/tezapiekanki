// Usage: PPTR_DIR="katalog/z/node_modules" node .claude/tests/faq-e2e.js (puppeteer-core 23.11.1, installed Chrome).
// FAQ test (tezapiekanki plugin: includes/class-faq.php, widget "FAQ (zwiń / rozwiń)", includes/class-product-faq.php):
// the /faq/ page (full list + schema), product page (4 questions + schema + link), home page (4 questions without schema + link).
// Expanding, one open at a time, keyboard, a single FAQPage matching the content. Screenshots: %TEMP%\tzp-faq-e2e.
const path = require('path');
const puppeteer = require(process.env.PPTR_DIR ? path.join(process.env.PPTR_DIR, 'node_modules', 'puppeteer-core') : 'puppeteer-core');
const OUT = path.join(require('os').tmpdir(), 'tzp-faq-e2e');
require('fs').mkdirSync(OUT, { recursive: true });
const results = [];
const ok = (name, pass, info = '') => { results.push(pass); console.log(`${pass ? 'OK  ' : 'FAIL'}  ${name}${info ? '  | ' + info : ''}`); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const BASE = 'http://tezapiekanki.local';
const DESKTOP = { width: 1440, height: 900 };
const PHONE = { width: 390, height: 844, isMobile: true, hasTouch: true };

const state = (page) => page.evaluate(() => [...document.querySelectorAll('.tzp-faq__item')].map((d) => d.open));
const norm = (s) => s.replace(/\s+/g, ' ').trim();

// FAQPage schema on the page + comparison with the visible content (answer text from the schema via the DOM, the way a search engine reads it).
const schemaInfo = (page) => page.evaluate(() => {
  const blocks = [...document.querySelectorAll('script[type="application/ld+json"]')].map((s) => { try { return JSON.parse(s.textContent); } catch (e) { return { parseError: true }; } });
  const flat = [];
  blocks.forEach((b) => { if (b['@graph']) flat.push(...b['@graph']); else flat.push(b); });
  const faqs = flat.filter((x) => x['@type'] === 'FAQPage');
  const entities = faqs.length ? faqs[0].mainEntity || [] : [];
  const visible = [...document.querySelectorAll('.tzp-faq__item')].map((d) => ({ q: d.querySelector('.tzp-faq__title').textContent, a: d.querySelector('.tzp-faq__answer').textContent }));
  const answers = entities.map((e) => { const div = document.createElement('div'); div.innerHTML = (e.acceptedAnswer || {}).text || ''; return div.textContent; });
  const more = document.querySelector('.tzp-faq-more__link');
  return { parseErrors: blocks.filter((b) => b.parseError).length, faqCount: faqs.length, entities, visible, answers, more: more ? more.getAttribute('href') : null };
});

(async () => {
  const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true, args: ['--no-first-run'] });
  const errors = [];
  const open = async (url, vp) => {
    const page = await browser.newPage();
    page.on('pageerror', (e) => { if (!/cookieyes|website URL has changed/i.test(e.message)) errors.push(url + ': ' + e.message.slice(0, 120)); });
    await page.setViewport(vp);
    await page.goto(BASE + url, { waitUntil: 'networkidle2', timeout: 120000 });
    await page.evaluate(() => { const f = document.querySelector('.tzp-faq'); if (f) f.scrollIntoView({ block: 'start' }); window.scrollBy(0, -140); });
    await sleep(500);
    return page;
  };
  const schemaMatches = (s) => s.visible.length === s.entities.length && s.visible.every((v, i) => norm(v.q) === norm(s.entities[i].name) && norm(v.a) === norm(s.answers[i]));

  // 1. FAQ page (desktop): behaviour and schema.
  let page = await open('/faq/', DESKTOP);
  let s = await state(page);
  ok('FAQ: pytań na stronie', s.length >= 8, String(s.length));
  ok('FAQ: wszystkie zamknięte na start', s.every((x) => !x));
  const groups = await page.evaluate(() => document.querySelectorAll('.tzp-faq').length);
  ok('FAQ: kilka grup pytań', groups >= 3, String(groups));
  await page.screenshot({ path: path.join(OUT, '1-faq-desktop.png') });

  await page.click('.tzp-faq:first-of-type .tzp-faq__item:nth-child(1) > summary');
  await sleep(400);
  await page.click('.tzp-faq:first-of-type .tzp-faq__item:nth-child(2) > summary');
  await sleep(500);
  // Several questions open at once (decision of 2026-10-19: "one open at a time" shifted the content on the phone).
  const g1 = await page.evaluate(() => [...document.querySelector('.tzp-faq').querySelectorAll('.tzp-faq__item')].map((d) => d.open));
  ok('FAQ: można otworzyć kilka pytań naraz', g1[0] === true && g1[1] === true, JSON.stringify(g1));
  const answerVisible = await page.evaluate(() => { const a = document.querySelector('.tzp-faq .tzp-faq__item:nth-child(2) .tzp-faq__answer'); return a.getBoundingClientRect().height > 20; });
  ok('FAQ: odpowiedź widoczna po otwarciu', answerVisible);
  await page.click('.tzp-faq .tzp-faq__item:nth-child(1) > summary');
  await sleep(300);
  await page.click('.tzp-faq .tzp-faq__item:nth-child(2) > summary');
  await sleep(500);
  ok('FAQ: drugi klik zamyka', (await state(page)).every((x) => !x));
  await page.focus('.tzp-faq .tzp-faq__item:nth-child(3) > summary');
  await page.keyboard.press('Enter');
  await sleep(400);
  ok('FAQ: Enter otwiera (klawiatura)', (await state(page))[2] === true);

  s = await schemaInfo(page);
  ok('FAQ: JSON-LD bez błędów', s.parseErrors === 0);
  ok('FAQ: dokładnie jedna FAQPage dla wszystkich grup', s.faqCount === 1 && s.entities.length === s.visible.length, `${s.faqCount} / ${s.entities.length} pytań`);
  ok('FAQ: Question + acceptedAnswer.text', s.entities.every((e) => e['@type'] === 'Question' && e.name && e.acceptedAnswer && e.acceptedAnswer.text));
  ok('FAQ: schema = treść na stronie', schemaMatches(s));
  const footer = await page.evaluate(() => [...document.querySelectorAll('footer a, .wd-footer a, [class*="footer"] a')].some((a) => /\/faq\/?$/.test(a.getAttribute('href') || '')));
  ok('FAQ: link w stopce', footer);
  for (let i = 0; i < 2; i++) await page.reload({ waitUntil: 'networkidle2' });
  ok('FAQ: schema po kolejnych odsłonach', (await page.evaluate(() => document.querySelectorAll('script.tzp-faq-schema').length)) === 1);
  await page.close();

  // 2. Product page: 4 questions, schema, link to the FAQ.
  page = await open('/zapiekanka-salami/', DESKTOP);
  s = await schemaInfo(page);
  ok('produkt: 4 pytania', s.visible.length === 4, String(s.visible.length));
  ok('produkt: pytania o ten smak', s.visible.every((v) => /zapiekank/i.test(v.q)), s.visible.map((v) => v.q).join(' / '));
  ok('produkt: bez "dodatkowych składników"', !s.visible.some((v) => /dodatkowe składniki/i.test(v.q)));
  ok('produkt: jedna FAQPage = treść', s.faqCount === 1 && schemaMatches(s));
  ok('produkt: link "Zobacz wszystkie pytania" do /faq/', /\/faq\/?$/.test(s.more || ''), s.more);
  await page.click('.tzp-faq__item:nth-child(1) > summary');
  await sleep(500);
  await page.evaluate(() => document.querySelector('.tzp-faq').scrollIntoView({ block: 'center' }));
  await page.screenshot({ path: path.join(OUT, '2-produkt-desktop.png') });
  await page.close();

  // 3. Home page: 4 questions without schema, link to the FAQ.
  page = await open('/', DESKTOP);
  s = await schemaInfo(page);
  ok('główna: 4 pytania', s.visible.length === 4, String(s.visible.length));
  ok('główna: bez schemy FAQPage (jest na stronie FAQ)', s.faqCount === 0);
  ok('główna: link do /faq/', /\/faq\/?$/.test(s.more || ''), s.more);
  ok('główna: bez paska "Napisz do nas" pod FAQ', !(await page.evaluate(() => /Nie zamieściliśmy odpowiedzi/.test(document.body.innerText))));
  await page.evaluate(() => document.querySelector('.tzp-faq-more').scrollIntoView({ block: 'center' }));
  await sleep(300);
  await page.screenshot({ path: path.join(OUT, '3-glowna-desktop.png') });
  await page.close();

  // 4. Phone.
  for (const [name, url] of [['faq', '/faq/'], ['produkt', '/zapiekanka-salami/']]) {
    page = await open(url, PHONE);
    await page.tap('.tzp-faq__item:nth-child(1) > summary');
    await sleep(500);
    const m = await page.evaluate(() => ({ open: document.querySelector('.tzp-faq__item').open, overflow: document.documentElement.scrollWidth > innerWidth }));
    ok(`telefon ${name}: dotknięcie otwiera, bez poziomego przewijania`, m.open && !m.overflow);
    await page.screenshot({ path: path.join(OUT, `4-${name}-telefon.png`) });
    await page.close();
  }

  ok('brak błędów JS', errors.length === 0, errors.join(' | '));
  await browser.close();
  const failed = results.filter((r) => !r).length;
  console.log(`\n${results.length - failed}/${results.length} OK. Zrzuty: ${OUT}`);
  process.exit(failed ? 1 : 0);
})();
