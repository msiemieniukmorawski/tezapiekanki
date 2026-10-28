// Local validation of structured data (JSON-LD) against Google's requirements for Product / Merchant listings and FAQPage.
// The Rich Results Test cannot open the local copy, so after deploying to staging also check the URLs in Google.
// Usage: node .claude/tests/schema-check.js  (no browser, Node 18+)
const BASE = 'http://tezapiekanki.local/';
const PRODUCTS = ['zapiekanka-tradycyjna', 'zapiekanka-wiejska', 'zapiekanka-z-boczkiem', 'zapiekanka-goralska', 'zapiekanka-mega-serowa', 'zapiekanka-z-szynka', 'zapiekanka-kebab', 'zapiekanka-na-dziko', 'zapiekanka-salami', 'zapiekanka-na-ostro', 'zapiekanka-szwedzka'];
const results = [];
const ok = (name, pass, info = '') => { results.push(pass); console.log(`${pass ? 'OK  ' : 'FAIL'}  ${name}${info ? '  | ' + info : ''}`); };

const nodes = (html) => {
  const out = [];
  let invalid = 0;
  for (const m of html.matchAll(/<script[^>]*application\/ld\+json[^>]*>([\s\S]*?)<\/script>/g)) {
    try {
      const d = JSON.parse(m[1]);
      (d['@graph'] || (Array.isArray(d) ? d : [d])).forEach((n) => out.push(n));
    } catch (e) { invalid++; }
  }
  return { out, invalid };
};
const types = (n) => [].concat(n['@type'] || []);
const missing = (obj, paths) => paths.filter((p) => p.split('.').reduce((o, k) => (o == null ? undefined : o[k]), obj) in { undefined: 1, '': 1 });

(async () => {
  for (const slug of PRODUCTS) {
    const html = await (await fetch(BASE + slug + '/')).text();
    const { out, invalid } = nodes(html);
    const products = out.filter((n) => types(n).includes('Product'));
    const p = products[0] || {};
    const offer = Array.isArray(p.offers) ? p.offers[0] : (p.offers || {});
    const required = missing(p, ['name', 'image']).concat(missing(offer, ['price', 'priceCurrency', 'availability']));
    const recommended = missing(p, ['description', 'sku', 'brand.name', 'weight.value']).concat(missing(offer, [
      'shippingDetails.shippingRate.value', 'shippingDetails.shippingRate.currency', 'shippingDetails.shippingDestination.addressCountry',
      'shippingDetails.deliveryTime.handlingTime.minValue', 'shippingDetails.deliveryTime.handlingTime.maxValue',
      'shippingDetails.deliveryTime.transitTime.minValue', 'shippingDetails.deliveryTime.transitTime.maxValue',
      'hasMerchantReturnPolicy.applicableCountry', 'hasMerchantReturnPolicy.returnPolicyCategory',
    ]));
    const faq = out.filter((n) => types(n).includes('FAQPage'));
    const faqOk = faq.length === 1 && faq[0].mainEntity.every((q) => q.name && q.acceptedAnswer && q.acceptedAnswer.text);
    ok(`${slug}: JSON-LD poprawny, 1 Product, wymagane pola`, invalid === 0 && products.length === 1 && required.length === 0, required.join(', '));
    ok(`${slug}: pola zalecane dla Merchant listings (marka, waga, wysyłka, zwroty)`, recommended.length === 0, recommended.join(', ') || `${p.name}; wysyłka ${offer.shippingDetails.shippingRate.value} ${offer.shippingDetails.shippingRate.currency}, ${offer.shippingDetails.deliveryTime.handlingTime.minValue}-${offer.shippingDetails.deliveryTime.handlingTime.maxValue} + ${offer.shippingDetails.deliveryTime.transitTime.minValue} dni`);
    ok(`${slug}: jedna FAQPage, pytania z odpowiedziami`, faqOk, `${faq.length} FAQPage, ${faq[0] ? faq[0].mainEntity.length : 0} pytań`);
  }

  const faqHtml = await (await fetch(BASE + 'faq/')).text();
  const f = nodes(faqHtml);
  const fp = f.out.filter((n) => types(n).includes('FAQPage'));
  ok('/faq/: jedna FAQPage z 11 pytaniami', f.invalid === 0 && fp.length === 1 && fp[0].mainEntity.length === 11 && fp[0].mainEntity.every((q) => q.acceptedAnswer && q.acceptedAnswer.text), `${fp.length} FAQPage, ${fp[0] ? fp[0].mainEntity.length : 0} pytań`);

  const home = await (await fetch(BASE)).text();
  const h = nodes(home);
  ok('strona główna: JSON-LD poprawny, bez FAQPage (pytania oznaczone na /faq/)', h.invalid === 0 && !h.out.some((n) => types(n).includes('FAQPage')));

  const failed = results.filter((r) => !r).length;
  console.log(`\n${results.length - failed}/${results.length} OK`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
