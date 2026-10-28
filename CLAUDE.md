# CLAUDE.md - TeZapiekanki (WordPress + WooCommerce)

Sklep internetowy z zapiekankami (PL, PLN, ceny brutto z VAT). Wysyłka InPost (Paczkomat / Kurier), klient wybiera dzień dostawy w kalendarzu (pn i śr). Sprzedaż pojedynczych zapiekanek i zestawów 9 / 12 sztuk.

Ten plik opisuje stan z 2026-10-18 (kopia z produkcji przez Duplicator). ID postów, ustawienia i wersje mogą się zmienić, więc przed użyciem konkretnego ID sprawdź je w bazie.

Otwarte pytania do klienta (terminy, teksty, karty produktów, wysyłka, kalendarz w checkoucie) są zebrane w `.claude/doc/pytania-do-klienta.md`. Nowe pytania dopisuj tam.

---

## 1. Stack

| Element | Wersja / wartość |
|---|---|
| WordPress | 7.1.3, język `pl_PL`, permalinki `/%postname%/` |
| PHP / MySQL (lokalnie) | 8.2.29 / 8.4.0 |
| Motyw | **Woodmart Child - TeZapiekanki** (`themes/woodmart-child`, aktywny lokalnie od 2026-10-18, na produkcji do wdrożenia) na **Woodmart 8.5.5** + wtyczka **Woodmart Core 1.1.8** |
| Page builder | **Elementor 4.3.3 + Elementor Pro 4.3.0** (`current_builder = external` w opcjach Woodmarta) |
| WooCommerce | 11.1.2, **HPOS włączone** (`woocommerce_custom_orders_table_enabled = yes`) |
| Prefiks tabel | `wpiq_` |

### Aktywne wtyczki i ich rola

| Wtyczka (slug) | Do czego służy w tym sklepie |
|---|---|
| `woocommerce` | sklep |
| `woodmart-core` | CPT motywu (layouty, HTML blocki, popupy), shortcody, widgety |
| `elementor`, `elementor-pro` | wszystkie strony, layouty Woodmarta, bloki HTML |
| `iconic-woo-delivery-slots` (Iconic WDS, prefiks `jckwds`) | wybór **daty dostawy** w checkoucie |
| `wc-ship-today-notice` (2.1, własna wtyczka MS-M/Zaiste, repo git `studiozaiste/wc-ship-today-notice`, gałąź `feature/next-slot-engine`) | silnik terminów wysyłki/dostawy, odliczanie `[ship_today_notice]`, **wybór terminu w checkoucie** `[ship_today_checkout]` (zastępuje kalendarz Iconic), API `wc_stn_*`. **Aktywna tylko lokalnie** (od 2026-10-18), na produkcji do wdrożenia według `.claude/doc/zastapienie-iconic-plan.md`. Testy: `wp eval-file wp-content/plugins/wc-ship-today-notice/tests/cli-schedule-test.php` |
| `tezapiekanki` (1.2, własna, MS-M, lokalne repo git `plugins/tezapiekanki`, gałąź `main`) | funkcje sklepu niezależne od motywu: widget Elementora "FAQ (zwiń / rozwiń)" ze schemą FAQPage (sekcja 5, "FAQ"), belka o wysyłce nad nagłówkiem z popupem "Dlaczego 2 razy w tygodniu" (`includes/class-shipping-bar.php`, ustawienia w Wygląd → Dostosuj → "Belka o wysyłce", sekcja 8 "Belka o wysyłce"), od 2026-10-20 wartość odżywcza z kartą PDF i piny smaków na karcie produktu, dane produktu dla Google i komunikat pustego zestawu (sekcja 7, "Karty produktów"), kontakt w mailach i puste pola adresu (sekcja 8, "Zamówienia"). **Aktywna tylko lokalnie** (od 2026-10-19). Testy: `.claude/tests/shipping-bar-e2e.js`, `faq-e2e.js`, `product-card-e2e.js` |
| `flexible-shipping` | metody wysyłki (InPost Paczkomat, InPost Kurier) |
| `apaczka-pl` | integracja z Apaczka.pl, wybór paczkomatu (geowidget), nadawanie przesyłek |
| `inpost-for-woocommerce` (InPost PL 1.9.9, iLabs) | **wgrana, nieaktywna** (od 2026-10-18). Ewentualny zamiennik Apaczki do wyboru paczkomatu. Checkout jest na nią gotowy (sekcja 8, "Układ checkoutu") |
| `flexible-checkout-fields` (FCF) | własne pola checkoutu: firma, NIP, "zamrożone" |
| `woo-product-bundle-premium` (WPC Bundles, typ `woosb`) | zestawy 9 i 12 zapiekanek |
| `pay-by-paynow-pl` | płatności Paynow (mBank): BLIK, PBL, karta, portfele |
| `print-invoices-packing-slip-labels-for-woocommerce` (WebToffee, prefiks `wf_pklist`) | faktury, listy przewozowe |
| `omnibus` | najniższa cena z 30 dni (dyrektywa Omnibus), CPT `iw_omnibus_price_log` |
| `rearrange-woocommerce-products` | ręczna kolejność produktów (meta `rwpp_sortorder_{term_id}`) |
| `duracelltomi-google-tag-manager` (GTM4WP) | GTM `GTM-WPPLQT33`, dataLayer e-commerce |
| `seo-by-rank-math` | SEO |
| `insert-headers-and-footers` (WPCode Lite) | **snippety PHP/JS z istniejącymi customizacjami** (patrz sekcja 4) |
| `litespeed-cache` | cache i optymalizacja na produkcji (serwer LiteSpeed) |
| `webp-converter-for-media` | WebP/AVIF przez reguły w `wp-content/.htaccess` |
| `loco-translate` | własne tłumaczenia w `wp-content/languages/loco/` |
| `wpforms-lite` | formularz kontaktowy |
| `wp-mail-smtp` | wysyłka maili |
| `duplicate-page`, `duplicator` | narzędzia |

Nieaktywna: `webtoffee-cookie-consent` (lokalnie). Na produkcji sprawdź, czym obsługiwana jest zgoda na cookies.

---

## 2. Środowisko lokalne

- Local (Flywheel), strona `tezapiekanki`, URL: http://tezapiekanki.local
- Katalog WP: `C:\Users\msiem\Documents\local site 2\tezapiekanki\app\public`
- Mailpit (przechwycone maile): http://localhost:10054
- Od 2026-10-28 katalog WP to **repozytorium git** (gałąź `main`, remote `origin` = `github.com:msiemieniukmorawski/tezapiekanki`). `.gitignore` to biała lista: śledzone są tylko motyw potomny, `CLAUDE.md`, `.claude/doc` i `.claude/tests`. Wtyczki `tezapiekanki` i `wc-ship-today-notice` mają własne repozytoria (commituj w ich katalogach). Nowy katalog z własnym kodem dopisz do `.gitignore`.
- `WP_DEBUG` = false. Do debugowania zaproponuj włączenie `WP_DEBUG` + `WP_DEBUG_LOG` (log w `wp-content/debug.log`) i wyłącz po skończeniu.
- Porty i ID instancji Local (`B6RiQ0Is4`) mogą się zmienić po restarcie. Aktualne wartości są w `%APPDATA%\Local\sites.json`.

### Optymalizacja lokalna (2026-10-18, tylko ta kopia)

- `wp-config.php`: `WP_ENVIRONMENT_TYPE = local`, `DISABLE_WP_CRON = true`, `AUTOMATIC_UPDATER_DISABLED = true`. Tych stałych nie przenosimy na produkcję.
- **Cron i Action Scheduler nie uruchamiają się same.** Gdy testujesz coś zależnego od zadań w tle (maile odroczone, Action Scheduler, cron Iconic/Woo), odpal ręcznie: `wp cron event run --due-now` oraz `wp action-scheduler run`.
- `wp-content/mu-plugins/tzp-local-dev.php` (działa tylko przy `WP_ENVIRONMENT_TYPE = local`): heartbeat co 60 s, wyłączony asynchroniczny runner Action Scheduler, blokada zapytań `browse-happy`/`serve-happy`, obejście błędu Elementora 4.3.x (Design System Sync przy pustym CSS kasuje plik i generuje go od nowa przy każdym żądaniu; pomijamy to przez transient `tzp_elementor_dss_empty` na 12 h).
- PHP strony: szablon `C:\Users\msiem\Documents\local site 2\tezapiekanki\conf\php\php.ini.hbs` (Local generuje z niego `run/.../php.ini` przy starcie strony). Zmienione: OPcache 512 MB / 64 MB interned strings / 100k plików, `realpath_cache_size=16M`, `pcre.jit=1` na Windows, Xdebug ładowany tylko po włączeniu go w Local. Zmiany w szablonie wymagają restartu strony w Local.
- **Maile lokalnie nie wychodzą na zewnątrz:** `wp-content/mu-plugins/tzp-local-mail-guard.php` kieruje każdego maila do Mailpit (http://localhost:10054), a gdy Mailpit nie działa, blokuje wysyłkę (wpis `[tzp-mail-guard]` w logu PHP). Bez tego WP Mail SMTP wysyłałby przez produkcyjny SMTP hostido z hasłem zapisanym w bazie, czyli do prawdziwych klientów. Działa przy `WP_ENVIRONMENT_TYPE = local` albo domenie `*.local`. W pasku admina jest pozycja "Maile: Mailpit".
- Ostrzeżenia PHP nie są wyświetlane na stronie (`display_errors` = 0 w wp-config), trafiają do `local site 2/tezapiekanki/logs/php/error.log`. Znane: Elementor `core/base/document.php:356` ("property ID on null"), niegroźne.
- Lokalnie stronę obsługują tylko **2 procesy php-cgi** (porty 10056/10057). Każde otwarte okno panelu (heartbeat, AJAX) zabiera jeden z nich, więc przy pomiarach zamknij zbędne karty.
- Wyniki pełnych testów ze zrzutami (także maili) i raportem: `local site 2/tezapiekanki/testy/RRRR-MM-DD/RAPORT.md` (poza katalogiem strony; ostatnie: 2026-10-20, 366 sprawdzeń OK). Zrzuty panelu pokazują prawdziwe zamówienia z kopii produkcji.
- Testy E2E w `.claude/tests/` (puppeteer-core 23.11.1 + zainstalowany Chrome): `PPTR_DIR="/c/Users/msiem/Documents/local site 2/.moduły/delivery-table-for-flexible-shipping" node .claude/tests/<test>.js` (tam leży `node_modules/puppeteer-core`). Adresy produktów są bez `/produkt/` (np. `/zapiekanka-tradycyjna/`).

### WP-CLI (działa, bash)

Samo `DB_HOST=localhost` z wp-config nie łączy się stabilnie z CLI, dlatego wymuszamy `127.0.0.1:10061`:

```bash
WPPHP="/c/Users/msiem/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe"
WPINI="/c/Users/msiem/AppData/Roaming/Local/run/B6RiQ0Is4/conf/php/php.ini"
wp() { "$WPPHP" -c "$WPINI" /c/tools/wp-cli/wp.phar --path="C:/Users/msiem/Documents/local site 2/tezapiekanki/app/public" --exec='define("DB_HOST","127.0.0.1:10061");' "$@"; }

wp plugin list
wp eval 'echo wc_get_order(123)->get_meta("jckwds_date");'
```

### MySQL bezpośrednio

```bash
"/c/Users/msiem/AppData/Roaming/Local/lightning-services/mysql-8.4.0/bin/win64/bin/mysql.exe" \
  -h 127.0.0.1 -P 10061 -uroot -proot --default-character-set=utf8mb4 local
```

Bez `--default-character-set=utf8mb4` polskie znaki się psują.

---

## 3. Gdzie dodawać kod (najważniejsze zasady)

1. **Nigdy nie edytuj** plików w `themes/woodmart`, `plugins/woodmart-core` ani innych wtyczek. Aktualizacja je nadpisze. `themes/woodmart/functions.php` jest od 2026-10-18 znowu oryginalny (56 linii).
2. **Motyw potomny `themes/woodmart-child`:** kod związany z motywem i panelem, który nie może zniknąć przy aktualizacji Woodmarta (obecnie raport "Zestawienie produktów" w `inc/product-summary.php`, układ checkoutu w `inc/checkout.php` + `js/checkout.js` galeria produktu w `inc/product-gallery.php` + `js/product-gallery.js` i poprawki paska "Dodaj do koszyka" w `inc/product-sticky-cart.php` + `js/product-sticky-cart.js`, poprawka rejestracji popupów Woodmarta w Elementorze w `inc/woodmart-popups.php`), override'y szablonów (`woocommerce/...` skopiowane z wersji Woodmarta, `header-elements/...`) i CSS sklepu w `style.css` (ładowany po stylach Woodmarta, prio 10010). Nowe moduły dodawaj jako pliki w `inc/` dołączane z `functions.php`.
3. **Funkcje sklepu** (terminy dostawy, belka, piny smaków, karta produktu, schema) → wtyczki według planu w `.claude/doc/tezapiekanki-plan-wykonania.md`: `wc-ship-today-notice` (silnik terminów) i `tezapiekanki` (integracja ze sklepem, od 2026-10-19 w `plugins/tezapiekanki`: moduły jako klasy `TZP\...` w `includes/`, dołączane z `tezapiekanki.php`, assety w `assets/`, skrypty WP-CLI do jednorazowych zmian w panelu w `tools/`). Nie zależą od motywu.
   W `mu-plugins` są `tzp-local-dev.php` i `tzp-local-mail-guard.php` (tylko kopia lokalna, nie trafiają na produkcję) oraz `tzp-staging-mail-guard.php` (od 20.10): na kopii testowej (np. tezapiekanki.ms-m.pl, każdy adres inny niż tezapiekanki.pl, albo `WP_ENVIRONMENT_TYPE` = staging/development) blokuje wszystkie maile w `pre_wp_mail` i dodatkowo czyści odbiorców w `phpmailer_init` (kolejka WP Mail SMTP przy wysyłce zdejmuje cudze filtry `pre_wp_mail`). Do testów maili na stagingu stała `TZP_STAGING_MAIL_TO` w wp-config (wszystko na ten jeden adres, bez DW i UDW, pierwotni odbiorcy w temacie). Na produkcji i lokalnie nic nie robi, więc może tam leżeć. Uwaga przy stagingu z bazą produkcyjną: Paynow ma klucze produkcyjne (`sandbox = no`), GTM `GTM-WPPLQT33` liczy wejścia do GA4 klienta, a `blog_public = 1` pozwala indeksować kopię.
4. **Nie dokładaj nowych snippetów do WPCode.** Kod w bazie nie jest wersjonowany. Jeśli zmieniasz zachowanie z istniejącego snippetu, przenieś go do mu-pluginu i wyłącz snippet (nie dubluj hooków).
5. Zmiany wizualne w layoutach i stronach robi się w Elementorze (dane w `_elementor_data`). Nie edytuj `_elementor_data` SQL-em. Jeśli musisz, użyj API Elementora i potem `wp elementor flush-css`.
6. Teksty dla klienta po polsku. Do zmiany tłumaczeń wtyczek/motywu używaj Loco Translate, nie filtra `gettext`.

### Konwencje kodu

- Prefiks funkcji/hooków: `tzp_`, klasy w przestrzeni nazw `TZP\`, text domain `tezapiekanki`.
- Zamówienia **tylko przez API WooCommerce** (HPOS!): `wc_get_order()`, `wc_get_orders()`, `$order->get_meta()`, `$order->update_meta_data()` + `$order->save()`. **Nigdy** `get_post_meta()` / `update_post_meta()` / `WP_Query` na zamówieniach. Dane leżą w `wpiq_wc_orders` i `wpiq_wc_orders_meta`, a w `wpiq_posts` są tylko placeholdery `shop_order_placehold`.
- Bezpieczeństwo: nonce + `current_user_can()` w każdym handlerze admin/AJAX, `sanitize_*` na wejściu, `esc_*` na wyjściu, `$wpdb->prepare()` dla SQL.
- Żadnych sekretów (tokeny API, hasła) w kodzie. Trzymaj je w stałych w `wp-config.php` albo w opcjach.
- JS na froncie rejestruj przez `wp_enqueue_script()` z zależnościami `['jquery', 'woodmart-theme']`, nie inline. Powód: LiteSpeed na produkcji ma JS defer + minify (patrz sekcja 9).

---

## 4. Istniejące customizacje (inwentarz)

### 4.1 Raport "Zestawienie produktów" (motyw potomny)

`themes/woodmart-child/inc/product-summary.php` (przeniesione 1:1 z `functions.php` Woodmarta 2026-10-18, CSV zweryfikowany bajt w bajt ze starym kodem):

- Strona admina **WooCommerce → Zestawienie produktów** (slug `wc-product-summary`, funkcja `tzp_product_summary_page()`).
- Handler `admin_post_generate_product_summary` → `tzp_generate_product_summary()`, nonce `wc_product_summary_nonce`. Generuje CSV (separator `;`, BOM UTF-8) z sumą sztuk per produkt z zamówień w statusie `processing`, filtrowanych po dacie wysyłki `jckwds_date_ymd` (format `Ymd`) w zakresie od-do. Wyklucza ID 6420 (już nie istnieje), 6417 i 6412 (zestawy).
- To narzędzie produkcyjne (ile czego upiec na dany dzień wysyłki).
- Zabezpieczenie na czas wdrożenia: jeśli w `functions.php` Woodmarta nadal istnieje stara funkcja `wc_product_summary_page()` (produkcja przed usunięciem starego kodu), moduł w motywie potomnym się nie rejestruje. Dzięki temu nie ma zdublowanego menu ani błędu krytycznego.
- Kopia zapasowa sprzed zmiany (baza, stary `functions.php`, HTML stron, CSV): `C:\Users\msiem\Documents\local site 2\tezapiekanki\backups\2026-10-18-child-theme\`.

### 4.2 Snippety WPCode (CPT `wpcode`, wszystkie "everywhere")

| ID | Nazwa | Status | Co robi |
|---|---|---|---|
| 5901 | FIX TRANSLATE | aktywny | filtr `gettext`/`ngettext`: zamienia "Cart" → "Koszyk" **we wszystkich** tłumaczeniach (zbyt szeroki) |
| 6396 | Walidacja NIP | aktywny | dodaje walidator `nip` do FCF (`flexible_checkout_fields_custom_validation`), funkcja `wpdesk_fcf_validate_nip()` |
| 6983 | GTM ADMIN EXCL | aktywny | ustawia cookie `is_admin=true` dla administratora (wykluczenie z GTM) |
| 29726 | DATALAYER FIX | aktywny | `gtm4wp_order_datalayer`: `event_id = order_{ID}` |
| 29828 | DL FIX2 | aktywny | `gtm4wp_product_datalayer` (item_id = SKU, kategorie "A > B") + **duplikat** filtra z 29726 |
| 29829 | DL3 | aktywny | `gtm4wp_product_datalayer` ponownie (kategorie po przecinku). **Konflikt z 29828**, oba na priorytecie 10 |
| 30284 | DS | aktywny | w adminie ukrywa wiersz wtyczki `iconic-wds` i **wszystkie powiadomienia o aktualizacjach wtyczek** |
| 30668 | PRZYCISKI | aktywny | usuwa akcje "Zrealizowane" i "Drukuj" (WebToffee) z listy zamówień |
| 30864 | KALENDARZ TEST | aktywny (JS, site-wide header) | patch jQuery UI datepickera Iconic: kalendarz daty dostawy zawsze otwarty i osadzony w `#jckwds-delivery-date-wrapper` |
| 5705 | FIX | szkic | ukrywa `yith-activator` |
| 29729 | PURCHASE FIX 1 | szkic | server-side Meta CAPI + GA4 MP. **Zawiera zahardkodowany token Meta i sekret GA4**, używa `get_post_meta` na zamówieniach (niezgodne z HPOS). Nie aktywować w tej formie |

### 4.3 CSS

- **Customizer → Dodatkowy CSS** (post `custom_css` ID 31245 dla `woodmart-child`; stara kopia 6458 dla `woodmart` jest nieaktywna, nie edytuj jej): logo, nagłówek sticky, karta produktu, tabelka atrybutów (`.tabelkakal`), przycisk geowidgetu Apaczki, WPForms, cały blok stylów kalendarza Iconic (`#jckwds-delivery-date-wrapper`). Lokalnie od 2026-10-18 bez reguły `.wd-product img {max-height:200px!Important;object-fit:cover!important;}`, która ucinała zdjęcia w listingach.
- Opcje Woodmarta (Custom CSS/JS) są puste. Elementor nie ma custom CSS na poziomie kitu.
- Kolor marki: `rgb(140, 50, 21)` (`#8C3215`), hover czarny.
- Własne klasy CSS ustawione w Elementorze: `herohp`, `tabelkakal`, `odstep`, `odstepfloat`, `przepis`.

### 4.4 Tłumaczenia (Loco)

`wp-content/languages/loco/plugins/jckwds-pl_PL.po` (Iconic, polskie etykiety kalendarza), `.../themes/woodmart-pl_PL.po`, `.../plugins/webtoffee-cookie-consent-pl_PL.po`.

---

## 5. Struktura frontu: Elementor + Woodmart Layouts

Strony sklepu (koszyk, checkout, archiwa, karta produktu) **nie używają szablonów WooCommerce z motywu**. Renderują je layouty Woodmarta (CPT `woodmart_layout`) zbudowane w Elementorze. Warunki są w meta `wd_layout_type` + `wd_layout_conditions`, a cache warunków w opcji `wd_layouts_conditions`.

| ID | Layout | Typ | Warunek |
|---|---|---|---|
| 6480 | ZAPIEKANKI CARD | `single_product` | kategoria `zapiekanki` (18); lokalnie od 2026-10-18 pod krótkim opisem widget Shortcode `[ship_today_notice class="tzp-delivery-box"]`, nowa galeria (widget 3123348, sekcja 7 "Zdjęcia i galeria"), "podobne" (94d33da) w rozmiarze `woocommerce_thumbnail`. Góra na tablecie i telefonie: czarny pasek 1d4fc61 (60 px, margines -100) pod przezroczystym nagłówkiem i sekcja produktu c9ab516 bez ujemnego marginesu, więc galeria zaczyna się pod nagłówkiem (wcześniej marginesy -80 / -160 wsuwały ją pod nagłówek, na telefonie górne 60 px zdjęcia było ucięte). Sekcja opisu b931d83: padding na tablecie 10/10/40/10 (był literówką 10/109/40/10, za duży odstęp z prawej). Od 2026-10-19 pod kontenerem z ceną i przyciskiem (1b46f25) kontener 34b5aa4 z widgetem Shortcode `[html_block id="31279"]` (blok "Info praktyczne", klasa `tzp-product-info`, cache elementu wyłączony). Od 2026-10-20 (sekcja 7, "Karty produktów"): pod kontenerem z ceną i przyciskiem (1b46f25) kontener a8f6ab7 (klasa `tzp-flavor-pins-box`, odstępy jak w 1b46f25) z widgetem Shortcode 9d1f242 `[tzp_flavor_pins]` (decyzja Marcina z 20.10, wcześniej piny były pod tytułem); kontener 1fd0c69 (dawniej tekst "Wartości odżywcze na 100g", przycisk "Pobierz kartę produktu" `post-info` i tabela `wd_single_product_additional_info_table` z klasą `tabelkakal`, na końcu kolumny) ma jeden widget Shortcode d6f6f2a `[tzp_nutrition]` i stoi zaraz pod 34b5aa4, nad logotypami płatności. Kopia sprzed: `backups/2026-10-20-karty-produktow/layout-6480-elementor-data.json`. Kolumna z tytułem i ceną (bed0dcc) ma od 20.10 na tablecie padding 0/20/0/20 (wcześniej 0, tekst dotykał krawędzi ekranu; na telefonie zostaje 0, bo kontenery w środku mają tam własne 15 px) |
| 6533 | ZESTAWY CARD | `single_product` | kategoria `zestawy-zapiekanek` (34); lokalnie ten sam widget pod krótkim opisem, "podobne" w `woocommerce_thumbnail`, te same poprawki góry na tablecie i telefonie oraz paddingu opisu na tablecie. Od 2026-10-19: pod "Dodaj do koszyka" (w c73ed9e) widget eaff1b9 z `[html_block id="31279"]` (blok "Info praktyczne"); galeria 3123348 z ustawieniami jak w 6480 (klasa `tzp-gallery`, wcześniej slider bez miniatur), pierwsze zdjęcie przycięte CSS-em do 1:1 (zdjęcia zestawów są pionowe 2:3). W obu kartach i w checkoucie (6470) logotypy płatności (`wd_images_gallery`) mają akcję kliknięcia "None" (wcześniej lightbox) |
| 6473 | ARCHIVE CARD | `shop_archive` | wszystkie; lokalnie zdjęcia w `woocommerce_thumbnail` (wcześniej `full`). Od 2026-10-19 bez filtrów (wariant A): usunięte kolumna sidebara 683d76f (`wd_sidebar` z `sidebar-shop`, na telefonie off-canvas), kontener eb460da z przyciskiem "FILTRUJ OFERTĘ" i widget aktywnych filtrów 6211c0b; kolumna produktów 6573530 ma 100% szerokości; widget produktów f61d9bf: 4 w rzędzie na desktopie, 3 na tablecie, 1 na telefonie (decyzja Marcina z 19.10). Widgety z `sidebar-shop` przeniesione do nieaktywnych (Wygląd → Widżety, do przywrócenia) |
| 6868 | ZESTAWY I DODATKI ARCHIVE CARD | `shop_archive` | term 34 i term 17 (term 17 już nie istnieje); lokalnie zdjęcia w `woocommerce_thumbnail` |
| 6460 | CART CARD | `cart` | koszyk; lokalnie od 2026-10-18 nowy układ: lewa kolumna `.tzp-cart-left` (60%) = komunikaty, hook `woocommerce_before_cart`, tabela produktów, "Dobierz do zamówienia" (`.tzp-cart-cross`); prawa `.tzp-cart-right` (40%) = `[ship_today_notice class="tzp-delivery-box"]`, "Wartość koszyka", suma. Kolejność na tablecie/telefonie w `style.css` motywu potomnego. Kopia sprzed zmiany: `backups/2026-10-18-child-theme/layout-6460-before-restructure.json` |
| 6676 | PUSTY KOSZTY CARD | `empty_cart` | pusty koszyk |
| 6467 | CHECKOUT TOP | `checkout_content` | checkout ("Powracający klient?" i komunikaty WooCommerce); lokalnie od 2026-10-18 bez boxu kuponu (kupon jest w podsumowaniu), szerokość 1240, formularz logowania na 100%, klasy `tzp-checkout-top` (237ffa4, bez dolnego paddingu) i `tzp-checkout-top__login` (674a7e9). Gdy nie ma czego pokazać (zalogowany klient, brak komunikatów), sekcja jest ukryta CSS-em (`:has()` w `style.css`). Wcześniej zostawał pusty szary box i około 225 px odstępu |
| 6470 | CHECKOUT MAIN | `checkout_form` | checkout; lokalnie od 2026-10-18 układ "wariant B" (opis w sekcji 8, "Układ checkoutu"). Na produkcji nadal stary układ z `[iconic-wds-fields]`. Kopie: `backups/2026-10-18-child-theme/layout-6470-elementor-data.json` (oryginał), `layout-6470-before-checkout-b.json` (z `[ship_today_checkout]`, przed przebudową), `layout-6470-checkout-b.json` (obecny) |

**Konsekwencja dla developera:** standardowe hooki WooCommerce (`woocommerce_single_product_summary`, `woocommerce_before_cart`, ...) odpalają się tylko tam, gdzie layout ma widget Woodmarta **"WooCommerce hook"** (`wd_wc_hook`). Obecnie jest on tylko w CART CARD (`woocommerce_before_cart`, `woocommerce_cart_collaterals`). Żeby wyświetlić coś własnego na karcie produktu lub w checkoucie, wybierz jedno z trzech:
- dodaj w Elementorze do layoutu widget `wd_wc_hook` z wybranym hookiem i podepnij się pod ten hook w PHP,
- zarejestruj shortcode i wstaw widget "Shortcode" do layoutu,
- zarejestruj własny widget Elementora (kategoria `wd-elements` albo `wd-single-product-elements`).

Hooki checkoutu związane z formularzem i zamówieniem (`woocommerce_checkout_process`, `woocommerce_checkout_create_order`, `woocommerce_checkout_update_order_meta`, `woocommerce_review_order_*`) działają normalnie, bo layout używa widgetów `wd_checkout_billing_details_form`, `wd_checkout_order_review`, `wd_checkout_payment_methods`.

### Strony

| ID | Strona | Uwagi |
|---|---|---|
| 11 | TE Zapiekanki (`strona-glowna`) | Elementor. Od 2026-10-19 FAQ (2e3bb3e) to widget "FAQ (zwiń / rozwiń)" zamiast akordeonu Woodmarta: 4 pytania, których nie powtarza tekst SEO niżej (wysyłka, bezpieczna dostawa, świeżość, pieczenie; mrożenie, farsz i Paczkomat są tylko na `/faq/`), pierwsze otwarte, **bez schemy** (te pytania są oznaczone na `/faq/`), przycisk "Zobacz wszystkie pytania" na środku. Pasek "Nie zamieściliśmy odpowiedzi… / Napisz do nas / Odpowiemy…" pod FAQ usunięty (kontenery e197f0d desktop i bbf69ab tablet/telefon, decyzja Marcina z 19.10), sekcja FAQ 265972d ma na telefonie 40 px dolnego odstępu (było 20). Tekst SEO (ae0ec9e) bez zmian. Kopie sprzed: `backups/2026-10-19-faq/`. Pod "Poznaj 6 smaków" wypełniony przycisk "Zobacz wszystkie smaki" → `/zapiekanki/` (3af6d90 desktop, 84a99ee tablet/telefon; wcześniej "Sprawdzam pozostałe" z obramowaniem). Meta description w Rank Math (sekcja 9, "Wdrożenie belki") |
| 221 | Sklep | strona sklepu WC |
| 222 | Koszyk | |
| 223 | Zamówienie (`zamowienie`) | checkout |
| 224 | Moje konto | |
| 411 | Regulamin sklepu | terms page |
| 23, 409, 5957, 6571, 6576, 6582, 6681, 29660, 30870 | Kontakt, Polityka prywatności, Polityka cookies, Metody płatności, Zwroty i reklamacje, Sposoby dostawy, Błąd 404, Preferencje marketingowe, Klienci B2B | |

### Inne elementy Woodmarta / Elementora

- Nagłówek: Header Builder, jeden header `header_686073` (opcje `whb_main_header`, `whb_header_686073`). Logo od 20.10 lokalnie (decyzja Marcina): wersja "TE Zapiekanki Skalbmierskie od 1987" z materiałów klienta, w nagłówku białe `uploads/2024/06/Light.png` (ID 214, desktop i telefon, zwykły i przyklejony nagłówek), w stopce (HTML Block 6541, widget 2186a26), bloku "POP" 29869 i szkicach 29481, 29880 czarne `Black.png` (ID 213, rozmiar pełny, szerokość ustawia widget). Wcześniej SVG "est 1987" z 01.2026 (`tezapiekankibialelogo.svg` 6873, `tezapiekankiczarnelogo.svg` 6874, zostały w mediach). Maile miały już `Black.png`. Bez zmian: ikona strony i logo organizacji w Rank Math (okrągły znak `cropped-LOGO-OKRAGLE.png`). Podmienia `wp eval-file wp-content/plugins/tezapiekanki/tools/replace-logo.php [apply] --user=<admin>` (nagłówek zapisuje przez Header Builder, więc CSS nagłówka się przebudowuje; gdy plików nie ma w mediach, wgrywa je z `tools/logo/`). Nagłówek jest przezroczysty i leży nad treścią (`whb-overcontent`), czarne tło dają kontenery-przekładki w layoutach (np. 1d4fc61 w kartach produktu, 7cd9fc2 w CHECKOUT TOP), po przewinięciu przyklejony nagłówek jest czarny. Górny pasek `top-bar` jest ukryty na desktopie i telefonie (zostały w nim tylko ikony social); od 2026-10-19 bez elementu tekstowego "ADD ANYTHING HERE OR JUST REMOVE IT…", który mimo ukrycia był w HTML każdej strony.
- Dolna nawigacja na telefonie (`.wd-toolbar`, opcja Woodmarta `sticky_toolbar`): lokalnie od 2026-10-18 **wyłączona w całości** (wcześniej Sklep / Moje konto / Koszyk). Koszyk jest w nagłówku, sklep w menu.
- Stopka: HTML Block (CPT `cms_block`) ID 6541 "FOOTER". Drugi blok: 29869 "POP". W stopce rząd ikon ("Dostawa w termoboxach", "Gotowe do upieczenia", ...) to karuzela Woodmarta `wd_nested_carousel` (84f75d2). Lokalnie od 2026-10-18 ma w Zaawansowane → "Ustawienia pamięci podręcznej" = **Nieaktywne** (`_element_cache = yes`), patrz niżej "Cache elementów Elementora".
- **Cache elementów Elementora** (`elementor_element_cache_ttl` = 24 h, Elementor → Ustawienia → Wydajność) zapisuje HTML dokumentu i listę skryptów, które widgety dodały przy pierwszym renderze. Do listy trafiają tylko skrypty, których jeszcze nie było w kolejce. Jeśli cache stopki zbuduje się na stronie, która ma już swiper (strona główna), karuzela w stopce traci swiper na stronach bez własnej karuzeli: rozsypuje się w pionową listę (było tak na checkoucie, Kontakcie, Regulaminie, Moim koncie, w pustym koszyku). Widgety ładujące skrypty w `render()` (karuzele Woodmarta i podobne) w blokach używanych na wielu stronach ustawiaj na "Nieaktywne" albo wyłącz cache elementów globalnie (przy LiteSpeed na produkcji zysk z niego jest mały). Po zmianie: `\Elementor\Plugin::$instance->files_manager->clear_cache()` czyści też ten cache (meta `_elementor_element_cache`).
- Popup Woodmarta `wd_popup` 29481 "PRZERWA" (szkic, komunikat o przerwie), floating block 29880 (szkic).
- HTML Block (`cms_block`) **31279 "Info praktyczne (karta produktu)"** (lokalnie od 2026-10-19, meta `_tzp_block = product-info`): kontener z klasą `tzp-info` (w kontenerze pole `css_classes`, nie `_css_classes`) i 4 widgety `wd_infobox` z tytułem i opisem: "7 dni świeżości / w lodówce, liczone od dostawy" (do 26.10 "6 dni"), "Trzymaj w lodówce / zaraz po odbiorze paczki", "Możesz zamrozić / najlepiej od razu po odbiorze", "Termobox / z wkładami chłodzącymi". Link w infoboxach wyczyszczony (Woodmart domyślnie wstawia `#`, a wtedy cały kafelek jest linkiem na górę strony), mniejsze odstępy w `style.css`, sekcja "Info praktyczne". Ikony PNG 128×128 w mediach (meta `_tzp_icon`, źródła SVG w `plugins/tezapiekanki/assets/icons/`). Wstawiony pod "Dodaj do koszyka" w layoutach 6480 i 6533. Edycja: menu **HTML Blocks**. Tworzy, nadpisuje i wstawia go `wp eval-file wp-content/plugins/tezapiekanki/tools/create-product-info-block.php --user=<admin>` (layout, w którym blok już jest, pomija).
- Popup Woodmarta `wd_popup` **31262 "Dlaczego 2 razy w tygodniu (belka)"** (lokalnie od 2026-10-19, meta `_tzp_popup = shipping-why`): otwiera go link w belce o wysyłce (sekcja 8). W bazie nie ma wyzwalacza, dopina go w locie wtyczka `tezapiekanki` (popup wybrany w ustawieniach belki), warunki: wszędzie oprócz checkoutu. Tworzy i nadpisuje go (i ustawia jako popup belki) `wp eval-file wp-content/plugins/tezapiekanki/tools/create-shipping-popup.php --user=<admin>`.
- Popup Elementor Pro 29987 "Opóźnienie" (szkic, warunki: front page, archiwum produktów, produkt).
- Kit Elementora: 6445. Na stronach używane są prawie wyłącznie widgety Woodmarta `wd_*`.
- Menu: "menu" (ID 3, main i mobile), "POZNAJ NAS" (19), "INFORMACJE" (33).

### FAQ: widget "FAQ (zwiń / rozwiń)" (wtyczka `tezapiekanki`, od 2026-10-19)

- Kod: `includes/class-faq.php` (renderer i schema), `includes/class-faq-widget.php` (widget `tzp_faq`, kategoria "TeZapiekanki" w panelu Elementora), `assets/css/faq.css`. Test: `.claude/tests/faq-e2e.js`.
- Markup: `<details class="tzp-faq__item">` + `<summary>`. Rozwijanie bez JS (działa na stronach z cache), z klawiatury (Enter, Spacja) i w czytnikach ekranu. Domyślnie można otworzyć kilka pytań naraz (decyzja Marcina z 19.10: zamykanie poprzedniego przesuwało treść i na telefonie trzeba było przewijać). Opcja "Tylko jedno otwarte naraz" w widgecie = atrybut `name` na `<details>`, wszędzie wyłączona (strona główna, `/faq/`, produkty). Płynne rozwijanie: `::details-content` + `interpolate-size` (Chrome 129+, inne bez animacji).
- Ustawienia widgetu: pytania (repeater: pytanie, odpowiedź WYSIWYG), pierwsze otwarte, jedno otwarte naraz, schema FAQPage, znacznik pytania (tekst / H2 / H3 / H4), style (tło, obramowanie, zaokrąglenie, odstępy, typografia i kolory pytania i odpowiedzi, kolor otwartego pytania i strzałki). Domyślny wygląd = dawne FAQ ze strony głównej.
- **Schema:** pytania ze wszystkich widgetów z włączoną schemą trafiają do **jednego** FAQPage na stronę (JSON-LD `script.tzp-faq-schema` w `wp_footer`, prio 100). Wbudowane akordeony Elementora wypisują schemę osobno dla każdego widgetu (dwa FAQPage = błąd w Google). Powtórzone pytanie liczy się raz. Odpowiedź w schemie ma tylko znaczniki dopuszczone przez Google (p, a[href], ul/ol/li, b/strong, i/em, br, h1-h6, div), `JSON_HEX_TAG`. Filtr `tzp_faq_schema`. W edytorze Elementora schemy nie ma.
- Widget ma `is_dynamic_content() = true`, więc nie trafia do cache elementów Elementora i przy każdym wyświetleniu dopisuje pytania do schemy.
- Zasada Google: te same pytania oznaczaj schemą tylko w jednym miejscu serwisu. Pełna lista ze schemą jest na `/faq/`, a na stronie głównej (te same pytania) schema jest wyłączona.
- **Strona "Najczęstsze pytania"** `/faq/` (lokalnie ID 31375, meta `_tzp_page = faq`, opcja `tzp_faq_page_id`): wstęp i 4 grupy (Zamówienie i dostawa, Świeżość, przechowywanie i mrożenie, Pieczenie w domu, Nasze zapiekanki), 11 pytań, jedna FAQPage, na końcu wyśrodkowany blok "Nie ma tu odpowiedzi na Twoje pytanie?" z przyciskiem "Napisz do nas". Link "Najczęstsze pytania" w stopce (HTML Block 6541, lista "Ważne informacje", po "Sposoby dostawy"). Tworzy i nadpisuje ją `tools/create-faq-page.php`.
- **FAQ w opisach produktów** (`includes/class-product-faq.php`): filtr `the_content` (prio 20) na karcie produktu zamienia sekcję opisu od nagłówka H2 z "FAQ"/"pytania" do następnego H2 (pytania jako H3, odpowiedzi jako akapity) na `Faq::render()`: pytania rozwijane, schema FAQPage (pytania o konkretny smak, inne niż na `/faq/`), przycisk "Zobacz wszystkie pytania" do `/faq/`. W bazie zostają zwykłe nagłówki, klient edytuje FAQ produktu w edytorze opisu. Opisy skrócone 19.10 do 4 pytań (świeżość, wysyłka na świeżo, mrożenie, imprezy) skryptem `tools/shorten-product-faq.php`. Usunięte z produktów: "Czy mogę dodać do zapiekanki dodatkowe składniki?" (sklep nie ma takiej opcji, a odpowiedź obiecywała dodatki), "pakowanie i transport" i "różne smaki" (są na `/faq/`). Numeracja pytań usunięta, "Zapiekanka Szefa – na ostro" w FAQ zmienione na "na ostro".
- Adres strony FAQ w kodzie: `\TZP\Faq::page_url()` (filtr `tzp_faq_page_url`). Widget ma pola "Link pod pytaniami" (tekst, adres, wyrównanie); pusty adres = strona FAQ.
- Zamiana akordeonu Woodmarta na ten widget: `wp eval-file wp-content/plugins/tezapiekanki/tools/convert-accordion-to-faq.php <ID strony> [ID widgetu] [noschema] --user=<admin>` (kopia `_elementor_data` w `uploads/tzp-backup/`).
- Sprawdzone w edytorze Elementora 19.10: widget w panelu, podgląd, repeater, zmiana pytania odświeża podgląd.

### Ważne opcje Woodmarta (`xts-woodmart-options`, odczyt: `woodmart_get_opt( 'slug' )` w hooku, nie przy ładowaniu pliku)

`ajax_shop = 1` (filtry/paginacja przez PJAX), `ajax_scroll = 1`, `quick_view = 0`, `wishlist = 0`, `compare = 0`, `shop_layout = sidebar-left`, `products_columns = 3`, `shipping_progress_bar_enabled = 1` (koszyk, mini-cart, checkout), `waitlist_enabled = 1`, `bought_together_enabled = 1`, `counter_visitor_enabled = 0` (od 26.10 lokalnie; licznik "X osób jest zainteresowanych tym produktem" z losowymi liczbami 1-27, usunięty też widget `wd_single_product_visitor_counter` z layoutów 6480 i 6533, bo wyświetla się mimo wyłączonej opcji), `checkout_fields_enabled` puste (menedżer pól checkoutu Woodmarta **wyłączony**, pola robi FCF; nie włączać obu naraz). `scripts_not_use` (Ustawienia motywu → Wydajność → JS → "Scripts never load"): lista 16 wyłączonych skryptów; lokalnie od 2026-10-19 bez `floating-blocks` (był wyłączony, przez co popupy i floating blocki Woodmarta w ogóle nie działały; skrypt ładuje się tylko na stronach z popupem). Kopia opcji sprzed zmiany: `backups/2026-10-19-shipping-bar/xts-woodmart-options-before.json`.

---

## 6. Woodmart: wiedza techniczna przy dodawaniu funkcji

Ścieżki względem `themes/woodmart/`.

- Bootstrap: `functions.php` → `inc/classes/class-theme.php` ładuje `inc/` przez `get_parent_theme_file_path()`, więc **plików z `inc/` nie da się nadpisać** child theme'em. Większość funkcji jest w `function_exists()` i można je zdefiniować wcześniej (pluggable).
- Override szablonów WC: skopiuj **wersję z Woodmarta** (`themes/woodmart/woocommerce/...`) do `woodmart-child/woocommerce/...`. Elementy nagłówka: `header-elements/` (też można nadpisać w child).
- Woodmart przestawia hooki WC: `woodmart_woocommerce_init_hooks()` (`init`, prio 1000) i `woodmart_woocommerce_hooks()` (`wp`, prio 1000) w `inc/integrations/woocommerce/functions.php`. Usuwają m.in. `woocommerce_template_loop_add_to_cart` (przeniesiony do `woodmart_add_loop_btn`) i sale flash. Żeby cofnąć lub przenieść akcję motywu, rób to po `wp` prio 1000 (np. na `template_redirect`).
- Przydatne hooki Woodmarta:
  - globalne: `woodmart_after_body_open`, `woodmart_after_header`, `woodmart_before_wp_footer`, `woodmart_after_footer`
  - kafelek produktu (`woocommerce/content-product-*.php`): `woodmart_shop_loop_product_thumbnail` (etykiety), `woodmart_add_loop_btn`, `woodmart_product_action_buttons`
  - archiwum: `woodmart_before_shop_page`, `woodmart_shop_filters_area`
  - filtry: `woodmart_localized_string_array` (obiekt JS `woodmart_settings`), `woodmart_option` (wartość opcji), `woodmart_layout_id`, `woodmart_product_label_output`, `woodmart_ajax_links`
- Assety są modułowe: JS ma handle `wd-{nazwa}` (bazowy `woodmart-theme`), CSS jest ładowany na żądanie przez `woodmart_enqueue_inline_style()`. Bazowy styl: `woodmart-style`. Dostępne globalne obiekty JS: `woodmartThemeModule`, `woodmart_settings` (m.in. `ajaxurl`).
- **Po przeładowaniu treści przez AJAX/PJAX** podpinaj JS ponownie na zdarzeniach (`$(document).on(...)`): `wdShopPageInit` (filtry, sortowanie, paginacja sklepu), `pjax:end`, `wdQuickShopSuccess`, `wdActionAfterAddToCart`, `wood-images-loaded`. Na checkoucie: `updated_checkout` (body). Na koszyku: `updated_wc_div`, `updated_cart_totals`.
- Elementor: widgety Woodmarta są w `inc/integrations/elementor/` i `inc/modules/layouts/elementor/` (np. `class-hook.php` = `wd_wc_hook`). Własny widget rejestruj na `elementor/widgets/register`.
- HTML Block w kodzie: `woodmart_get_html_block( $id )` lub shortcode `[html_block id="..."]`.
- **Popupy (`wd_popup`) i floating blocki z Elementora przy Elementorze 4.3 nie działają bez poprawki** z `woodmart-child/inc/woodmart-popups.php`. Moduł MCP Elementora (Pro) rejestruje typy dokumentów na `init` prio 5, a Woodmart podpina swoje typy dopiero na `init` 10, więc się nie rejestrują. Woodmart czyta wtedy popup jak blok Gutenberga z domyślnymi ustawieniami (wyzwalacz po 0 s, "persistent close", warunek "wszędzie"), a edytor Elementora nie pokazuje ustawień popupu. Poprawka dorejestrowuje typy na `init` 11. Sprawdzenie: `get_class( \Elementor\Plugin::$instance->documents->get( <id popupu> ) )` ma zwrócić `Popup_Document`, nie `Post`. Domyślne wartości nowego popupu, na które trzeba uważać: "Persistent close" włączone (po pierwszym zamknięciu popup już się nie otworzy, także z linku) i wyzwalacz "po czasie" włączony z 0 s. Do tego JS popupów (`floating-blocks`) musi być poza `scripts_not_use` (sekcja 5).
- **Opcje Woodmarta zmieniane poza panelem (WP-CLI, `update_option( 'xts-woodmart-options' )`) nie odświeżają CSS generowanego z opcji** (`uploads/RRRR/MM/xts-theme_settings_default-*.css` + opcje `xts-theme_settings_default-*`). Brakuje wtedy np. zmiennej `--wd-sticky-btn-height` (18.10 przycisk "do góry" uciekł przez to poza ekran). Po takiej zmianie przebuduj CSS: `$s = new \XTS\Modules\Styles_Storage( 'theme_settings_default' ); $s->reset_data(); $s->delete_file();`, jedno wejście na stronę (Woodmart wypisze CSS inline), potem zapis do pliku: `$s = new \XTS\Modules\Styles_Storage( 'theme_settings_default', 'option', '', false ); $s->write( $s->get_inline_css() );`. W panelu (Ustawienia motywu → Zapisz) dzieje się to samo automatycznie, więc na produkcji wystarczy zmieniać opcje w panelu.

---

## 7. Model produktowy (ważne przy każdej zmianie produktów, cen lub koszyka)

- Kategorie: `zapiekanki` (ID 18), `zestawy-zapiekanek` (ID 34).
- **Każda zapiekanka istnieje w 3 kopiach** (produkty `simple`):
  1. widoczna w sklepie, cena pełna, SKU np. `768acc4c4423`,
  2. ukryta (`exclude-from-catalog` + `exclude-from-search`), składnik **Zestawu 9**, cena niższa o 1,50 zł, SKU z sufiksem `-1`,
  3. ukryta, składnik **Zestawu 12**, cena niższa o 2,50 zł, SKU z innym sufiksem (`-2`, `-5`, `-69`, ...).
- Zestawy to produkty `woosb` (WPC Bundles): **6412 "Zestaw 9 zapiekanek"** (SKU `ZH9ZAP`) i **6417 "Zestaw 12 zapiekanek"** (SKU `ZH12ZAP`). Wszystkie pozycje są opcjonalne (min 0), klient sam wybiera smaki, a łączna liczba musi wynosić dokładnie 9 / 12 (`woosb_limit_whole_min/max`). Cena zestawu = suma składników (`woosb_disable_auto_price = off`, `_price = 0`). Składniki ukryte w koszyku i mini-koszyku (`hide_bundled = yes`), w zamówieniu widoczne. Lista składników: meta `woosb_ids`.
- 11 smaków: Tradycyjna, Salami, Kebab, Wiejska, Mega Serowa, Szwedzka, na ostro, z boczkiem, Góralska, z Szynką, na dziko (obecnie brak w magazynie). Do tego "Farsz pieczarkowy" (ukryty, brak w magazynie).
- Atrybuty globalne (`pa_*`): waga i wartości odżywcze `waga`, `kalorie` ("Wartość energetyczna"), `tluszcze` ("Tłuszcz"), `kwasy-nasycone`, `weglowodany`, `cukry`, `bialko`, `sol` (od 2026-10-20 z kart produktów, opis niżej), oraz skład i oferta `termin-przydatnosci`, `mieso`, `ser`, `skladnik-dodatkowy`, `sos`, `oferta`, `dlugosc-zapiekanki`. Na stronie widać tylko wagę i wartości odżywcze (`[tzp_nutrition]`); zakładka "Informacje dodatkowe" jest wyłączona, więc reszta to dane w panelu (i w danych dla Google).
- Tagi: `oferta-standardowa`, `oferta-sezonowa`, `oferta-limitowana`.
- Meta produktu: `iconic_wds_weekdays`, `iconic_wds_weekdays_override`, `iconic_wds_mindate` (reguły dostawy per produkt w Iconic), `rwpp_sortorder_18` (kolejność), `_woodmart_product-background`. `_iwo_price_log` to stary format Omnibusa sprzed v3 (obecnie log jest w CPT `iw_omnibus_price_log`).

### Zdjęcia i galeria (lokalnie od 2026-10-18)

- Miniatury WooCommerce: 600 px, 1:1 (`woocommerce_thumbnail_image_width = 600`, `woocommerce_thumbnail_cropping = 1:1`). Listingi używają rozmiaru `woocommerce_thumbnail`: archiwa 6473 i 6868, strona główna 11 (`wd_products` 709318e i 391e7b3, wcześniej 420×275), "podobne" na kartach 6480 i 6533 (94d33da, wcześniej `full`).
- Zdjęcia główne: większość 1254×1254, Mega Serowa i Salami poziome (około 1,3-1,4:1), zestawy pionowe 2:3. Środkowe przycięcie 1:1 sprawdzone: produkt mieści się w kadrze. Zdjęcia w galeriach to głównie 3:2.
- Galeria karty zapiekanki (layout 6480, widget 3123348 z klasą CSS `tzp-gallery`): "Bottom grid", kolumny 2/2/1, karuzela na tablecie wyłączona, na telefonie włączona, bez kropek. Lightbox: opcja Woodmarta `image_action = popup` (PhotoSwipe, działa globalnie na wszystkich kartach produktu). Kod: `themes/woodmart-child/inc/product-gallery.php`, `js/product-gallery.js`, sekcja "Galeria produktu" w `style.css`.
  - Desktop i tablet: duże pierwsze zdjęcie w swojej proporcji, pod nim kafelki 4:3 po 2. Przy więcej niż 6 zdjęciach widać duże + 4 kafelki, a przycisk "Zobacz więcej zdjęć" pod siatką płynnie rozwija resztę w dół (animacja wysokości w JS, kafelki wchodzą z fade) i zmienia się w "Zwiń zdjęcia". Nadmiar chowa CSS od razu (`:has(.wd-carousel-item:nth-child(7))`), skrypt dodaje tylko przycisk. Bez nakładek "+N" na zdjęciach (decyzja Marcina z 18.10).
  - Telefon: Swiper Woodmarta (`js/scripts/wc/productImagesGallery.js`, start na window load, tylko przy szerokości do 768 px i bez `wd-off-sm`). Wystające kolejne zdjęcie: `--wd-col-sm: 1.15 !important` (Woodmart czyta liczbę slajdów z tej zmiennej). Slajdy 4:3, licznik "1 / 8" z `swiper.realIndex` na `slideChange`.
  - Lightbox (`productImages.js` Woodmarta) zbiera wszystkie zdjęcia galerii, także ukryte, i otwiera się na klikniętym.
  - Galeria zestawów (6533) od 2026-10-19 taka sama (decyzja Marcina): te same ustawienia widgetu i klasa `tzp-gallery`. Zdjęcia zestawów są pionowe 2:3, więc pierwsze jest przycięte do 1:1 (`style.css`, reguła z `.product-type-woosb`), inaczej miałoby 930 px wysokości na desktopie i 1230 px na tablecie. Zestawy mają po 5 zdjęć, więc przycisk "Zobacz więcej zdjęć" się nie pojawia. W layoucie 6533 kolumna z tytułem i ceną (bed0dcc) ma od 20.10 na tablecie padding 0/20/0/20, tak samo jak w 6480.
- **Pułapka przy regeneracji miniatur:** `wp media regenerate --image_size=woocommerce_thumbnail` przy kwadratowych oryginałach usuwa stary plik `nazwa-300x300`, a ten sam plik jest rozmiarem `medium`. Przeglądarka bierze go z `srcset` i w listingu pojawiają się zepsute obrazki (zdarzyło się lokalnie 18.10 przy 15 zdjęciach). Regeneruj wszystkie rozmiary naraz: `wp media regenerate <id...> --yes` albo wtyczka Regenerate Thumbnails.
- Testy: `.claude/tests/product-gallery-e2e.js` (desktop, tablet, telefon, lightbox) i `.claude/tests/product-listing-e2e.js` (1:1 w sklepie, zestawach, na stronie głównej i w "podobnych").

### Pasek "Dodaj do koszyka" przy przewijaniu (lokalnie od 2026-10-18)

- Natywny Woodmarta: opcje `single_sticky_add_to_cart` i `mobile_single_sticky_add_to_cart` = 1 (Ustawienia motywu → Produkt). Pasek renderuje `woodmart_sticky_single_add_to_cart()` na `woodmart_before_wp_footer`, więc działa niezależnie od layoutu Elementora. Pokazuje się po przewinięciu poza `form.cart` (pozycja liczona raz, przy załadowaniu strony), ilość synchronizuje się w obie strony z polem na karcie, dodawanie przez AJAX Woodmarta (`single_ajax_add_to_cart`).
- Telefon: natywny wygląd Woodmarta, tylko ilość i przycisk na całą szerokość (42 px, decyzja Marcina z 18.10; wersję ze zdjęciem, nazwą i ceną zrobiłem i usunąłem). Dolnej nawigacji już nie ma, więc pasek leży przy dolnej krawędzi ekranu, a przycisk "do góry" podjeżdża nad pasek (wymaga zmiennej `--wd-sticky-btn-height` z CSS opcji Woodmarta, sekcja 6).
- Zestawy (WPC Bundles, typ `woosb`): Woodmart daje im w pasku link, który przewija do formularza wariantów, więc kliknięcie nic nie robiło. `js/product-sticky-cart.js` (ładowany tylko na kartach zestawów): dopóki przycisk formularza ma klasę `woosb-disabled`, w pasku jest "Wybierz smaki" i przewija do listy `.woosb-wrap` (z odjęciem wysokości nagłówka, który przykleja się przy przewijaniu w górę); po skompletowaniu zestawu "Dodaj do koszyka" klika oryginalny przycisk formularza. Stan odświeża zdarzenie `woosb_check_ready`.
- Na produkcji sprawdź, czy przycisk ponownej zgody CookieYes (`.wcc-revisit-bottom-left`, przesunięty w Dodatkowym CSS) nie wchodzi na pasek (lokalnie CookieYes się nie ładuje).
- Test: `.claude/tests/product-sticky-cart-e2e.js` (desktop, telefon, zestaw; dodaje do koszyka w osobnych kontekstach, bez zamówień).

### Karty produktów, wartość odżywcza, piny smaków, dane dla Google (lokalnie od 2026-10-20)

- **Źródło:** PDF od klienta z 11 kartami (`.claude/doc/materiały/Dokument bez tytułu-38.pdf`, strona = smak), pocięty na `plugins/tezapiekanki/tools/karty-produktow/karta-produktu-zapiekanka-<smak>.pdf`. Wartości odżywcze i wagi na nowych kartach są takie same jak na starych PDF-ach z sierpnia (`uploads/2026/08/*.pdf`, zostały w mediach, nieużywane), zmienił się tylko skład (doszedł parmezan, inne proporcje farszu). Na stronie wcześniej było inne makro (poza Tradycyjną), np. Salami 301,7 kcal i 261 g zamiast 211 kcal i 349 g.
- **Import:** `wp eval-file wp-content/plugins/tezapiekanki/tools/import-product-cards.php [apply] --user=<admin>` (bez `apply` podgląd). Wgrywa PDF-y do mediów (`uploads/RRRR/MM` z dnia wgrania, meta `_tzp_card` = smak, `_tzp_card_md5`; nowy plik w `tools/` = nowy załącznik), tworzy atrybuty `pa_kwasy-nasycone` i `pa_cukry`, zmienia nazwy "Kalorie" → "Wartość energetyczna" i "Tłuszcze" → "Tłuszcz" oraz term "Mozarella" → "Mozzarella" (slug bez zmian), ustawia wszystkim 3 kopiom każdego smaku (po nazwie w kategorii Zapiekanki) wagę, wartości odżywcze, sery z karty (gouda, mozzarella, parmezan, w Góralskiej i Mega Serowej też oscypek) i pole własne `kartaproduktowa` (`<a href="…pdf">POBIERZ KARTĘ PRODUKTU</a>`, ten sam format co wcześniej). "Na ostro" dostała mięso Chorizo, składnik Jalapeño i termin przydatności (od 26.10 wszystkie smaki: 7 dni) (wcześniej nie miała żadnych atrybutów). Usuwa nieużywane termy wagi i wartości odżywczych (lokalnie 74). Ostrzega, gdy energia z makro (9/4/4 kcal) różni się od karty o ponad 5%: tak jest przy "na dziko" (na karcie 222 kcal, z makro 206 kcal), do wyjaśnienia z klientem.
- **`[tzp_nutrition]`** (`includes/class-product-card.php`, `assets/css/product.css`, ładowany w `<head>` na kartach produktu): "Wartość odżywcza, w 100 g, przed upieczeniem", tabela jak na etykiecie (energia, tłuszcz, w tym nasycone, węglowodany, w tym cukry, białko, sól; wiersze "w tym" wcięte), "Waga zapiekanki: 349 g" i przycisk "Pobierz kartę produktu (PDF) / pełny skład, alergeny i wartości odżywcze" (adres z `kartaproduktowa`, filtr `tzp_product_card_url`). Bez danych nic nie wypisuje. `[tzp_product_card]` = sam przycisk. Klient zmienia wartości w atrybutach produktu, a kartę w polu własnym `kartaproduktowa` (sam adres PDF-a też działa).
- **`[tzp_flavor_pins]`** (`includes/class-flavor-pins.php`, `assets/js/flavor-pins.js`): "Smak:" i piny z nazwami widocznych zapiekanek (bez ukrytych kopii) w kolejności sklepu (`rwpp_sortorder_18`), bieżący w kolorze marki (`aria-current`), niedostępne z przerywaną ramką, ale klikalne. Na telefonie jeden przewijany wiersz od krawędzi do krawędzi, skrypt ustawia bieżący smak na środku i cieniuje krawędzie, gdzie jest coś do przewinięcia. Nowy smak pojawia się sam.
- **Dane dla Google** (`includes/class-product-schema.php`, filtr Rank Math `rank_math/snippet/rich_snippet_product_entity`): nazwa produktu z WooCommerce (Rank Math brał tytuł SEO z nazwą sklepu), waga z atrybutu w gramach (Rank Math brał pole wysyłkowe, np. 1 kg przy "na ostro"), podpisy atrybutów zamiast slugów (`pa_waga` → "Waga", wartości z dopiskiem "(w 100 g)"), marka "TE Zapiekanki Skalbmierskie" (filtr `tzp_product_schema_brand`), `offers.shippingDetails` (14,99 zł, stała `SHIPPING_RATE` = najniższy płatny koszt z Flexible Shipping z 20.10, filtr `tzp_product_schema_shipping_rate`; czas: wysyłka 1-6 dni i dostawa 1 dzień, liczone co godzinę przez tydzień z `wc_stn_get_slots()`, transient `tzp_schema_delivery_time` na 12 h, czyszczony po zapisie ustawień terminów) i `hasMerchantReturnPolicy` (`MerchantReturnNotPermitted`, PL, link do `/zwroty-i-reklamacje/`). Filtr całości: `tzp_product_schema`. Przy zmianie kosztu wysyłki w Flexible Shipping zmień też `SHIPPING_RATE`.
- **Opisy:** `wp eval-file wp-content/plugins/tezapiekanki/tools/unify-product-descriptions.php [apply]` (wszystkie 3 kopie każdego smaku): "dwa sery" → gouda, mozzarella i parmezan (jak na kartach i w krótkich opisach), rok receptury 1886/1887 → 1987 (Szwedzka, z Szynką, kopie "na ostro"), "Spinata Piccante" → "Spianata Piccante", "zapiekance Szefa" → "zapiekance na ostro", H1 na początku opisu → H2 (H1 to już nazwa produktu). Lokalnie 201 zamian w 33 produktach. Bez zmian zostały sosy (saszetki do paczki, nie ma ich na kartach) i zdania o dodatkach do zamówienia (pytanie do klienta).
- **Zestawy:** `includes/class-bundles.php` podstawia w komunikacie pustego zestawu liczbę sztuk (`woosb_limit_whole_min`, 9 albo 12) w miejsce `[min]` (filtr `woosb_localization_alert_empty`; wtyczka podmienia `[min]` tylko w innych komunikatach).
- Layout 6480 zmienia `tools/update-product-layout.php` (sekcja 5). Test: `.claude/tests/product-card-e2e.js`.

**Dodanie nowego smaku = 3 produkty + dopisanie kopii do obu zestawów** (6412 i 6417) z właściwą ceną i SKU, ustawienie widoczności kopii na ukryte i uzupełnienie atrybutów (waga i wartości odżywcze z karty, pole `kartaproduktowa` z PDF-em; najprościej dopisać smak do `tools/import-product-cards.php` i uruchomić). Kod liczący sztuki lub raportujący sprzedaż musi uwzględniać, że ten sam smak ma 3 różne ID (grupuj po nazwie lub bazowym SKU, tak robi raport z 4.1).

---

## 8. Checkout, wysyłka, płatności, zamówienia

### Pola checkoutu (FCF, opcja `wpdesk_checkout_fields_settings`)

| Pole | Typ | Meta zamówienia |
|---|---|---|
| `billing_nazwa_firmy` "Nazwa firmy" | text, opcjonalne | `_billing_nazwa_firmy` |
| `billing_numer_nip` "Numer NIP" | text, walidacja `nip` (snippet 6396) | `_billing_numer_nip` |
| `order_zamrozone` "Proszę o wysyłkę zamrożonych zapiekanek" | checkbox | `_order_zamrozone` |

Ukryte: `billing_state`, `order_comments`, od 2026-10-19 lokalnie także `order_zamrozone` (w FCF `visible = 1` znaczy **ukryte**). Kraj tylko PL. Zakupy bez konta dozwolone.

Pól z sekcji "order" (`order_zamrozone`, `order_comments`) i tak nie widać w checkoucie: layout 6470 (obecny i produkcyjny sprzed zmian) nie ma widgetu z polami dodatkowymi zamówienia, tylko billing, podsumowanie i płatności. Dlatego w 2078 zamówieniach nikt nie zaznaczył "Proszę o wysyłkę zamrożonych zapiekanek". Ukrycie w FCF (decyzja z 19.10, sprzeczne z "nie wysyłamy mrożonych") zabezpiecza na wypadek dodania tej sekcji.

Kolejność lokalnie od 2026-10-18 (FCF, `priority`): e-mail 5 (`form-row-first`), telefon 6 (`form-row-last`), imię 10, nazwisko 20, ulica 30, ciąg dalszy 40, kod 50, miasto 60, kraj 70, firma 80, NIP 90, województwo 100. Na produkcji: firma 30, NIP 40, kraj 50, ulica 60, telefon 110 (`form-row-first`), e-mail 120 (`form-row-last`). Pole `class` w FCF to **tekst** (np. `form-row-wide address-field update_totals_on_change`), nie tablica: tablica psuje walidację FCF i gubi klasy `address-field` / `update_totals_on_change`.

### Termin w checkoucie: `wc-ship-today-notice` (lokalnie od 2026-10-18)

- Kafelki `[ship_today_checkout]` w layoucie 6470, ustawienia w WooCommerce → Ship Today Notice → "Wybór terminu w checkoucie" (6 terminów, najbliższy zaznaczony, duża data dostawy, kolor `#8c3215`).
- Godzina graniczna od 26.10 lokalnie **23:59** (było 20:00; klientka chciała 24:00, pole `type="time"` go nie przyjmuje, a 00:00 dnia wysyłki wyświetlałoby się jako "do poniedziałku 00:00"): do niedzieli 23:59 → wysyłka w poniedziałek, dostawa we wtorek; do wtorku 23:59 → wysyłka w środę, dostawa w czwartek. Te same godziny są wpisane tekstem w FAQ (strona główna, `/faq/`) i na stronie "Sposoby dostawy".
- Meta nowych zamówień: `_wc_stn_ship_date`, `_wc_stn_delivery_date` (Y-m-d), `_wc_stn_cutoff`. Stare zamówienia mają tylko `jckwds_*` (bez migracji). Raport "Zestawienie produktów" czyta oba.
- Przełącznik "Wyłącz Iconic w koszyku i checkoucie" włączony: Iconic jest aktywny tylko w panelu (stare zamówienia, strona Deliveries), nowe zamówienia do niego nie trafiają. Po około miesiącu od wdrożenia na produkcji Iconic do dezaktywacji. Moduł działa bez Iconic: sprawdzone 20.10 z wyłączoną wtyczką (pełna ścieżka zamówienia i checkout bez błędów, stare daty `jckwds_*` dalej widać na liście zamówień, w zamówieniu i w raporcie, bo czytamy je z meta). Decyzja 20.10: Iconic na razie zostaje aktywny.
- Snippet WPCode 30864 "KALENDARZ TEST" lokalnie wyłączony (był potrzebny tylko kalendarzowi Iconic).
- Karta produktu: pod krótkim opisem box z terminem dostawy (`[ship_today_notice class="tzp-delivery-box"]`, layouty 6480 i 6533). Tekst z ustawień wtyczki: `<strong>Zamów {cutoff_when}, dostarczymy {delivery_when}.</strong> <span class="tzp-countdown-line">Zostało: {countdown}</span>` (roboczy, do akceptacji klienta). Wygląd w `themes/woodmart-child/style.css` (ładowanie `style.css` motywu potomnego jest od tego momentu włączone).

### Układ checkoutu (wariant B, lokalnie od 2026-10-18)

Layout 6470: wiersz `.tzp-checkout-row` (boxed 1240), w nim:
- `.tzp-checkout-main` (60%): trzy karty `.tzp-card.tzp-step` z numerowanymi nagłówkami. "Kontakt i dane" (widget pól billing), "Dostawa" (`[tzp_checkout_delivery]`, `[ship_today_notice class="tzp-delivery-box tzp-delivery-box--compact"]`, `[ship_today_checkout]`), "Płatność" (widget płatności + pasek logotypów).
- `.tzp-checkout-side` (40%, przyklejony `top: 30px` od 1025 px): karta `.tzp-summary` z widgetem podsumowania. Kod rabatowy jest wierszem nad "Łącznie".
- Tablet i telefon: podsumowanie zwinięte w pasek "Twoje zamówienie · kwota" na górze (`.tzp-summary-toggle`, tworzy go JS). Pod paskiem od razu widać "Masz kod rabatowy?" i użyte kupony (pozostałe wiersze tabeli są ukryte CSS-em do rozwinięcia paska), niżej kroki.
- Odstępy: wiersz `.tzp-checkout-row` ma padding górny 30/24/16 px (desktop/tablet/telefon). Na telefonie kolumny i wiersz nie mają bocznych paddingów, karta 14 px, więc pole zaczyna się 30 px od krawędzi ekranu (wcześniej 62 px).

Kod: `themes/woodmart-child/inc/checkout.php`, `js/checkout.js`, sekcja "Checkout" w `style.css`. **Żaden hook WooCommerce ani wtyczki nie jest usuwany i żaden szablon nie jest nadpisany:**
- Kafelki metod wysyłki to `<label for>` oryginalnych pól `shipping_method[0]`, które zostają w tabeli podsumowania (ukryte wizualnie). Kliknięcie kafelka zaznacza oryginalne pole, dalej działa standardowe `update_checkout`. Sekcja jest fragmentem `woocommerce_update_order_review_fragments` (`.tzp-delivery-methods`).
- Paczkomat obsługuje wtyczka przewoźnika, a my tylko ją uruchamiamy. `tzp_checkout_parcel_provider()` ustala dla metody `data-provider`: `inpost` (InPost PL: metoda `easypack_parcel_machines*` albo metoda Flexible Shipping z "Integracja z InPost PL" = paczkomat) albo `apaczka` (`display_apaczka_map = yes`, teraz instancja 10). Przy obu wygrywa InPost PL. W `js/checkout.js` każda wtyczka ma wpis w `parcelProviders`: oryginalne przyciski mapy (`#apaczka_pl_geowidget_classic`; `#easypack_show_geowidget` / `#easypack_js_type_geowidget`) i funkcję czytającą wybrany punkt.
- Wybrany punkt czytamy z **ostatniego** ukrytego pola w `form.checkout` (`apm_access_point_id` + `apm_street/postal_code/city/name` w Apaczce, `parcel_machine_id` + `parcel_machine_desc` i adres z localStorage `EasyPackPointObject` w InPost PL). Obie wtyczki dopisują te pola na końcu formularza, więc przetrwają `update_checkout`. To samo pole odczytuje PHP przy zamówieniu. Oryginalne "Wybrany paczkomat" obu wtyczek jest w tabeli podsumowania, którą WooCommerce podmienia przy każdym odświeżeniu (zmiana terminu, adresu, płatności). Dlatego przed poprawką z 18.10 wybór "znikał" po kliknięciu terminu. Numer paczkomatu pokazujemy też w podsumowaniu (`.tzp-summary-parcel`).
- Kupon: pole bez `name`, JS wysyła kod do wbudowanego endpointu `wc-ajax=apply_coupon`, usuwanie oryginalnym linkiem WooCommerce. Enter w polu nie wysyła zamówienia.
- Przycisk "Kupuję i płacę · kwota": filtr `woocommerce_order_button_html` (tekst i `data-value`).
- Faktura: checkbox "Chcę fakturę na firmę" (JS) pokazuje pola FCF firmy i NIP; odznaczenie czyści ich wartości. Kraj ukryty CSS-em (wartość PL zostaje w formularzu).
- `tzp_checkout_layout_ready()`: kupon, kwota na przycisku, fragment i skrypt włączają się tylko, gdy layout `checkout_form` zawiera `[tzp_checkout_delivery]`. Dzięki temu motyw potomny można wgrać na produkcję przed przebudową layoutu.

Pułapki:
- Walidacja Apaczki ("Wybierz punkt odbioru przesyłki") sprawdza metodę z sesji (`chosen_shipping_methods`), nie z POST. Wynik zależy od tego, czy `update_order_review` po zmianie metody zdążył się wykonać.
- Po błędzie walidacji WooCommerce zdejmuje nakładkę blockUI z animacją. Testy automatyczne muszą poczekać, aż `.blockUI` zniknie, inaczej klik trafia w nakładkę.
- Kwota "Łącznie" ma dwa elementy `.woocommerce-Price-amount` (suma i "zawiera X zł VAT"). Sumę bierz z pierwszego.
- Strona "Zamówienie" (223): lokalnie od 18.10 ma w treści blok "klasyczny checkout" (`woocommerce/classic-shortcode`, shortcode `checkout`). Na produkcji nadal jest blok `woocommerce/checkout`, choć checkout i tak renderuje klasyczny layout Woodmarta. Przy bloku wtyczki sprawdzające `has_block()` biorą stronę za checkout blokowy: np. tryb InPost PL "przycisk mapy przez JS" (`easypack_js_map_button`) nie ładuje wtedy `front.js`. Domyślny tryb InPost PL działa w obu przypadkach.
- Przejście z Apaczki na InPost PL: aktywuj wtyczkę, wpisz tokeny API i geowidgetu, w metodzie Flexible Shipping "InPost Paczkomat" ustaw "Integracja z InPost PL" = "InPost Paczkomat 24/7" i odznacz mapę Apaczki, tryb przycisku zostaw domyślny. W checkoucie nic nie trzeba zmieniać. Sprawdzone lokalnie 18.10 (tymczasowa aktywacja, zamówienie zapisało `_parcel_machine_id`). Uwaga: nadawanie przesyłek i etykiety idą wtedy przez InPost, a nie Apaczkę.
- Testy E2E (puppeteer, zamówienia testowe przelewem: BACS trzeba na czas testu włączyć, potem wyłączyć, a zamówienia anulować i usunąć):
  - `.claude/tests/checkout-e2e.js`: 26 kroków, kafelki, kupon, faktura, walidacja Apaczki, zamówienie Kurierem, telefon (kupon pod paskiem bez rozwijania, boczny odstęp).
  - `.claude/tests/order-path-e2e.js`: pełna ścieżka (strona główna, popup, sklep, produkt, zestaw 9, koszyk, checkout z paczkomatem, podziękowanie, panel ze zmianą statusu, raport CSV, maile z Mailpit) ze zrzutami do katalogu z `TZP_SHOTS`; sesję administratora tworzy sam przez WP-CLI i usuwa na końcu. `.claude/tests/cutoff-e2e.js`: przejście przez godzinę graniczną z przesuniętym zegarem przeglądarki (karta produktu, kafelki w checkoucie, popup). `.claude/tests/schema-check.js`: dane strukturalne, bez przeglądarki.
  - `.claude/tests/checkout-parcel-e2e.js` (`PROVIDER=apaczka` albo `inpost`): wybór paczkomatu, który przetrwa zmianę terminu, adresu i płatności, numer w podsumowaniu, czyszczenie po zmianie na Kuriera, zamówienie z paczkomatem. Wybór punktu symuluje callback mapy (`apaczka_mp_map_callback`, `inpost_pl_select_point_callback`).

### Data dostawy (Iconic WDS, opcja `jckwds_settings`), stan produkcji do czasu wdrożenia

- Pola wstawiane ręcznie (`general_setup_position = add_manually`) shortcodem `[iconic-wds-fields]` w layoucie CHECKOUT MAIN (6470).
- Tylko **data** (sloty godzinowe wyłączone), pole obowiązkowe. Dni dostaw: **poniedziałek i środa**, zakres 0-30 dni, cutoff same-day 00:05, format `dd/mm/yy`. Specyficzne dni i święta ustawione w panelu.
- Meta zamówienia: `jckwds_date` (tekst do wyświetlenia), **`jckwds_date_ymd`** (`Ymd`, używaj do filtrowania), `jckwds_timestamp`, `jckwds_shipping_method`, `_iconic_wds_is_same_day`, `_iconic_wds_is_next_day`.
- Front: input `#jckwds-delivery-date`, wrapper `#jckwds-delivery-date-wrapper`, kalendarz jQuery UI `#ui-datepicker-div`. Kalendarz jest na stałe rozwinięty dzięki snippetowi 30864 + CSS w Customizerze. Zmiany w kalendarzu testuj po `updated_checkout`.

### Belka o wysyłce z popupem (lokalnie od 2026-10-19)

- Wtyczka `tezapiekanki`, `includes/class-shipping-bar.php`, `assets/css/shipping-bar.css`, `assets/js/shipping-bar.js`. Domyślny tekst "Wysyłamy tylko 2 razy w tygodniu. Sprawdź dlaczego →" (zaakceptowany przez klientkę 26.10), tło czarne z jasną linią na dole (od 26.10, wcześniej `#8C3215`, które zlewało się ze zdjęciem pod przezroczystym nagłówkiem strony głównej).
- **Ustawienia: Wygląd → Dostosuj → "Belka o wysyłce"** (`includes/class-shipping-bar-customizer.php`, opcja `tzp_shipping_bar`, niezależna od motywu): pokazuj belkę, tekst, tekst linku (puste = bez podkreślonego fragmentu), po kliknięciu: popup (lista opublikowanych `wd_popup`) / adres / nic, adres (cel albo zapas dla popupu). Teksty w podglądzie zmieniają się na żywo. Po zapisie ustawień belki i po zapisie wybranego popupu w Elementorze wtyczka czyści cache LiteSpeed (`litespeed_purge_all`). W Dostosuj jest błąd JS z integracji Gutenberga Woodmarta (`registerPlugin`), niezwiązany z belką.
- **Wyzwalacz popupu dopinany w locie:** filtr `get_post_metadata` na `_elementor_page_settings` wybranego popupu (tylko zwykłe żądania strony, nie panel/AJAX/REST/WP-CLI, więc edytor Elementora go nie widzi i nie zapisze) dodaje "On selector click" `.tzp-bar-link` i wyłącza w tym popupie "po czasie" i "persistent close". Dzięki temu zmiana popupu w Dostosuj nie wymaga zmian w ustawieniach popupów, a poprzedni popup przestaje się otwierać z belki.
- Wypisywana na `wp_body_open` przed `.wd-page-wrapper`. Nagłówek leży absolutnie w `.wd-page-wrapper`, więc zjeżdża o wysokość belki (40 px desktop, 34 px telefon, na 320 px dwie linie). Przyklejony nagłówek działa bez zmian (Woodmart liczy próg z pozycji nagłówka przy starcie).
- Ukryta w checkoucie i na thank you page (`is_checkout()`), widoczna w koszyku. Filtr `tzp_shipping_bar_visible`.
- Krzyżyk: data dnia (strefa czasowa WordPressa) w `localStorage` `tzp_bar_closed`. Skrypt i styl inline w `<head>` z `data-no-optimize="1"` dodają `html.tzp-bar-off`, zanim strona się narysuje, więc na stronach z cache LiteSpeed belka nie miga. Następnego dnia wraca.
- Lokalnie link otwiera popup 31262 (sekcja 5). Gdy popup się nie otworzy (nie jest opublikowany, jego warunki nie obejmują strony, JS motywu jeszcze się nie wczytał), link prowadzi pod adres z ustawień, domyślnie `/sposoby-dostawy/` (filtr `tzp_shipping_bar_link`).
- Termin w popupie: jeden, najbliższy (decyzja Marcina z 19.10, bez lewej ramki): `[ship_today_notice class="tzp-why-dates" text="Zamów {cutoff_when}, a dostaniesz ją {delivery_when}."]`. Liczony w przeglądarce z harmonogramu, działa na stronach z cache i po godzinie granicznej sam przeskakuje na kolejny (np. w środę "Zamów do niedzieli 23:59, a dostaniesz ją we wtorek.").
- Edycja popupu: panel → menu **Popups** → "Dlaczego 2 razy w tygodniu (belka)" → Edytuj w Elementorze (ustawienia popupu pod zębatką).
- Zdarzenia dla GTM: `dataLayer` `{event: 'tzp_shipping_bar', tzp_bar_action: 'popup' | 'link' | 'close'}` (żaden tag w GTM ich jeszcze nie używa).

### Wysyłka

- Strefa "Polska" (`country:PL`), metody Flexible Shipping (`flexible_shipping_single`): instancja **10 "InPost Paczkomat"**, instancja **12 "InPost Kurier"**.
- Paczkomat wybierany geowidgetem Apaczki (`#apaczka_pl_geowidget_classic`). Meta zamówienia: `apaczka_delivery_point`, `_apaczka`, `_apaczka_last_order_object`.
- Koszt (decyzja klientki z 26.10): zawsze 14,99 zł, od 300 zł za darmo. Lokalnie od 26.10 obie metody mają jedną regułę "Zawsze" (`condition_id = none`) 14,99 zł, a darmowa wysyłka to ustawienie metody `method_free_shipping = 300`. Wcześniej reguły wagowe (0-4 kg 14,99 zł, od 5 kg 9,99 zł), przy których koszyk 4-5 kg nie pasował do żadnej reguły i miał wysyłkę 0 zł. Na produkcji nadal reguły wagowe.
- Pasek "do darmowej wysyłki" z Woodmarta włączony.

### Płatności

Paynow (`pay_by_paynow_pl_*`: BLIK, PBL, karta, Click to Pay, portfele). Ustawienia Przelewy24 / PayPal / BACS w bazie to pozostałości, tych bramek nie ma aktywnych. Lokalnie nie testuj prawdziwych płatności.

### Zamówienia

- HPOS. Statusy w użyciu: `processing` (do realizacji, na tej podstawie działa raport produkcyjny), `completed`, `cancelled`, `pending`, `checkout-draft`.
- Akcje "Zrealizowane" i "Drukuj" na liście zamówień są ukryte (snippet 30668).
- Faktury i dokumenty: WebToffee (`wf_pklist`).
- Maile: standardowe szablony WooCommerce (YayMail był, ale jest odinstalowany, zostały tylko posty `yaymail_template`). Nadawca `noreply@zapiekankiskalbmierz.pl` ("Anna z TEzapiekanki") jest powiązany ze skrzynką SMTP w WP Mail SMTP, nie zmieniaj go. Od 20.10 lokalnie: WooCommerce → Ustawienia → E-maile, "Adres odpowiedzi" (Reply-to) włączony na sklep@tezapiekanki.pl (opcje `woocommerce_email_reply_to_enabled`, `woocommerce_email_reply_to_address`), a wtyczka `tezapiekanki` (`includes/class-emails.php`) pokazuje go w treści maili do klientów zamiast adresu nadawcy (znacznik `{store_email}` w "Dodatkowej treści" 6 maili to adres nadawcy). Maile do sklepu (nowe, anulowane, nieudane zamówienie) mają odpowiedź do klienta, bez zmian. Ten sam moduł czyści z adresu w zamówieniu puste "Nazwa firmy:" i "Numer NIP:" (FCF dopisuje etykietę także przy pustej wartości).

### Kolejność zapisu meta przy składaniu zamówienia

1. `woocommerce_checkout_create_order` prio 9: FCF zapisuje swoje pola (`'_' . $key`).
2. `woocommerce_checkout_update_order_meta` prio 10: Iconic zapisuje `jckwds_*`.
3. `woocommerce_checkout_update_order_meta` prio 100: Apaczka zapisuje `apaczka_delivery_point` i `_apaczka`.

Własny kod, który potrzebuje daty dostawy lub paczkomatu, podpinaj na `woocommerce_checkout_update_order_meta` z priorytetem > 100 albo później (`woocommerce_checkout_order_processed`, `woocommerce_payment_complete`, `woocommerce_order_status_processing`).

---

## 8a. Punkty rozszerzeń wtyczek (zweryfikowane w kodzie)

Ścieżki względem `wp-content/plugins/`.

### Iconic Delivery Slots (`iconic-woo-delivery-slots`)
- Obiekt: `global $iconic_wds` (alias `$jckwds`), klasa `\Iconic_WDS\Iconic_WDS::instance()`. Logika dat: `$GLOBALS['iconic_wds_dates']`. Ustawienia: `$iconic_wds->settings[...]`.
- Data z zamówienia: `\Iconic_WDS\Order::get_order_date_time( $order )` (`inc/Order.php:197`) zwraca `date`, `time`, `timestamp`, `ymd`, `shipping_method`, ... albo `false`. Prościej: `$order->get_meta( 'jckwds_date_ymd' )`.
- Shortcody (`inc/Shortcodes.php`): `[iconic-wds-fields]`, `[iconic-wds-next-delivery-date]`, `[iconic-wds-get-order-date]`, `[iconic-wds-allowed-days]`, `[iconic-wds-lead-time]`. Pola muszą być wewnątrz `form.checkout`, inaczej się nie wyślą.
- Pola POST: `jckwds-delivery-date`, `jckwds-delivery-date-ymd`. Walidacja na `woocommerce_checkout_process` (`inc/Checkout.php:125`).
- Filtry dat (`inc/Dates.php`): `iconic_wds_available_dates`, `iconic_wds_allowed_days`, `iconic_wds_is_delivery_day`, `iconic_wds_is_day_allowed`, `iconic_wds_is_holiday`, `iconic_wds_specific_delivery_dates`, `iconic_wds_get_lead_time`, `iconic_wds_delivery_slots_allowed` (pokazuje lub ukrywa pola).
- Inne: `iconic_wds_get_product_specific_settings` (reguły per produkt, `inc/OverrideSettings.php:417`), `iconic_wds_delivery_days`, `iconic_wds_minmax_method`, akcje `iconic_wds_before_checkout_fields` / `iconic_wds_after_checkout_fields`, `iconic_wds_date_display` (format daty w mailach i adminie), `iconic_wds_order_timeslot_updated`.
- Wyświetlanie daty: wiersz `iconic_wds_order_date` w `woocommerce_get_order_item_totals` (maile, thank you, Moje konto) + kolumna na liście zamówień (sortowanie po `jckwds_timestamp`).
- JS (`assets/frontend/js/main.js`): obiekty `window.jckwds` i `jckwds_vars` (`bookable_dates`, `settings`, `ajax_url`, `ajax_nonce`). Wybór daty wywołuje `update_checkout`. Po `updated_checkout` wtyczka czyta `fragments.iconic_wds` i odświeża kalendarz. Zdarzenia: `iconic_wds_datepicker_before_show`, `iconic_wds_datepicker_on_change_month_year`.

### Apaczka (`apaczka-pl`)
- Meta `apaczka_delivery_point` (tablica: `apm_access_point_id`, `apm_supplier`, `apm_name`, `apm_street`, `apm_city`, `apm_postal_code`, `apm_country_code`, ...) oraz `_apaczka`. Zapis: `src/Shipping_Method_Apaczka.php:1015`, przez `$order->update_meta_data` (HPOS OK).
- Mapa pojawia się dla instancji metody wysyłki z ustawieniem `display_apaczka_map = yes` (checkbox dodawany do każdej metody, także Flexible Shipping). Konfiguracja: `apaczka()->get_map_config()`.
- Przycisk i wybrany punkt renderuje szablon `templates/checkout/apaczka-review-order-after-shipping.php` na `woocommerce_review_order_after_shipping` (tylko w AJAX). Layout checkoutu musi mieć tabelę podsumowania zamówienia. Szablon można nadpisać w motywie (`woocommerce/checkout/`).
- Walidacja wyboru paczkomatu na `woocommerce_after_checkout_validation`. Wtyczka nie ma własnych hooków.
- Front (`assets/js/frontend.js`): mapa woła `window.apaczka_mp_map_callback( point )`. Callback wpisuje punkt do tabeli podsumowania i dopisuje do **każdego** formularza na stronie `span.apaczka_pl_several_checkout_forms` z polami `apm_*`. Ten span przetrwa `update_checkout`, a przy zmianie metody na taką bez mapy jest usuwany.
- Błąd wtyczki (także na produkcji): pole `apm_country_code` w spanie ma wartość `"undefined"`, bo klasyczny checkout nie ma `#shipping-country`. W zamówieniach od jakiegoś czasu `apaczka_delivery_point.apm_country_code = "undefined"` (547 zamówień, starsze 899 mają "PL").

### InPost PL (`inpost-for-woocommerce`, nieaktywna)
- Metody `easypack_parcel_machines*` albo powiązanie metody Flexible Shipping (`fs_inpost_pl_method` w jej ustawieniach). Meta zamówienia: `_parcel_machine_id`, `_parcel_machine_desc`.
- Domyślny tryb: przycisk `#easypack_show_geowidget` w szablonie `checkout/easypack-review-order-after-shipping.php` na `woocommerce_review_order_after_shipping` (opcja `easypack_button_output`), JS `inpost-pl.js` (modal jBox, callback `inpost_pl_select_point_callback`, punkt w localStorage `EasyPackPointObject` i w sesji WC). Tryb `easypack_js_map_button = yes`: `front.js`, przycisk `#easypack_js_type_geowidget` pod metodą wysyłki (nie działa, gdy strona 223 ma w treści blok checkoutu, sekcja 8).
- Walidacja: `woocommerce_checkout_process` prio 9999 (`$_POST['parcel_machine_id']`). Bez aktywacji, tabel i crona przy włączeniu.

### WPC Product Bundles (`woo-product-bundle-premium`)
- Pozycja koszyka zestawu: `woosb_ids`, `woosb_keys`, `woosb_bundle['role'] = 'parent'`. Składnik: `woosb_parent_id`, `woosb_parent_key`, `woosb_qty`, `woosb_bundle['role'] = 'child'`.
- Meta pozycji zamówienia: `_woosb_ids`, `_woosb_price` (zestaw), `_woosb_parent_id` (składnik), `_woosb_bundle` (oba).
- Filtry/akcje: `woosb_add_to_cart_validation`, `woosb_bundled_cart_item_data`, `woosb_before_add_to_cart_item` / `woosb_after_add_to_cart_item`, `woosb_item_visible`, `woosb_cart_item_price`, `woosb_order_bundled_product_name`, `woosb_after_calculate_prices`.
- JS (`assets/js/frontend.js`, na `document`): `woosb_init`, `woosb_calc_price`, `woosb_check_ready`, `woosb_save_ids`, `woosb_check_qty`. Konfiguracja: `woosb_vars`.

### Flexible Checkout Fields
- Zapis pól: `classes/plugin.php`, `woocommerce_checkout_create_order` prio 9, klucz meta `'_' . $key`. Potem akcja `flexible_checkout_fields_checkout_update_order_meta`.
- Własny walidator: filtr `flexible_checkout_fields_custom_validation` zwraca `['klucz' => ['label' => ..., 'callback' => fn( $label, $value )]]`, callback woła `wc_add_notice()`. Reguła pojawia się do wyboru w ustawieniach pola.
- Inne: `flexible_checkout_fields_field_args`, `flexible_checkout_fields_condition`, `flexible_checkout_fields_print_value`, `flexible_checkout_fields_validate_{pole|typ}`.

### Flexible Shipping
- Metoda `flexible_shipping_single`, reguły w `woocommerce_flexible_shipping_single_{instance_id}_settings` (klucz `method_rules`).
- Filtry: `flexible_shipping_add_method` (pokaż/ukryj metodę), `flexible-shipping/shipping-method/calculated-cost` (koszt), `flexible-shipping/shipping-method/is-free-shipping`, `flexible_shipping_rule_conditions` (własne warunki), `flexible-shipping/rate/meta_data`.

### Faktury WebToffee (`print-invoices-packing-slip-labels-for-woocommerce`)
- Filtry w `admin/modules/customizer/classes/class-customizer.php`, argumenty `( $value, $template_type, $order )`, gdzie `$template_type` to `invoice`, `packinglist`, `deliverynote`, `shippinglabel` lub `dispatchlabel`.
- Najprostsze miejsce na datę dostawy, NIP lub "zamrożone": `wf_pklist_add_additional_info` (placeholder `[wfte_additional_data]`) albo `wf_pklist_alter_additional_fields` (tablica etykieta => wartość).
- Inne: `wf_pklist_add_product_meta`, `wf_pklist_alter_billing_address`, `wf_pklist_alter_shipping_method`, `wf_pklist_alter_find_replace`, `wf_pklist_add_custom_css`.
- Iconic **nie** dodaje daty dostawy do dokumentów WebToffee automatycznie (jego integracja dotyczy innej wtyczki PDF).

### Paynow (`pay-by-paynow-pl`)
- Bramki: `pay_by_paynow_pl_` + `blik`, `card`, `pbl`, `paywall`, `digital_wallets`, `paypo`, `click_to_pay`. Sprawdzenie: `WC_Pay_By_Paynow_PL_Helper::is_paynow_order( $order )`.
- Statusy z webhooka `woocommerce_api_wc_gateway_pay_by_paynow_pl`: CONFIRMED → `payment_complete()` (podpinaj się pod `woocommerce_payment_complete` / `woocommerce_order_status_processing`), REJECTED / ERROR / EXPIRED → `failed`, NEW / PENDING → `pending`.

### Omnibus (`omnibus`)
- Komunikat najniższej ceny domyślnie przez `woocommerce_get_price_html`, tylko dla produktów w promocji (`_iwo_price_lowest_on_sale`).
- Filtry: `iworks_omnibus_show`, `iworks_omnibus_message_template` (placeholdery `{days}`, `{price}`, `{when}`), `iworks_omnibus_message`, `iworks_omnibus_integration_woocommerce_price_lowest`.
- API: `apply_filters( 'iworks_omnibus_wc_get_lowest_price', [], $product_id )`, shortcode `[omnibus_price_message id=""]`. Log cen: CPT `iw_omnibus_price_log` (`post_parent` = produkt).

### GTM4WP
- Filtry `gtm4wp_product_datalayer`, `gtm4wp_order_datalayer` (już używane w snippetach, sekcja 4.2). Przy zmianach trackingu najpierw uporządkuj zdublowane snippety.

---

## 9. Cache, wydajność, produkcja

- Produkcja działa na **LiteSpeed** z LiteSpeed Cache: page cache włączony, **Guest Mode**, **CSS/JS minify, JS defer**. Lokalnie jest nginx, więc LSCache i reguły `.htaccess` (w tym WebP/AVIF z Converter for Media) **nie działają lokalnie**. Błędy związane z kolejnością JS mogą wyjść dopiero na produkcji.
  - Inline `<script>` używające jQuery mogą się wysypać przy defer. Enqueue'uj skrypty z zależnościami.
  - Elementy dynamiczne (koszyk, dane użytkownika) nie mogą być renderowane w HTML strony cache'owanej. Używaj fragmentów WC (`woocommerce_add_to_cart_fragments`), AJAX/REST albo ESI.
  - Po wdrożeniu: purge LSCache, a przy zmianach CSS w Elementorze także "Regenerate CSS".
  - **JS startuje dopiero po pierwszym ruchu użytkownika** (sprawdzone 20.10 na produkcji: na karcie zestawu skrypt WPC Bundles działa dopiero po ruchu myszy albo przewinięciu). Testy automatyczne na produkcji muszą najpierw poruszyć myszą i przewinąć stronę (tak robi `.claude/tests/case-study-shots.js`), a funkcje zależne od JS (odliczanie, przeskok terminu, piny na telefonie, galeria) sprawdzaj po pierwszej interakcji.
- Dane na produkcji zmieniają się po 18.10 (np. ceny 20.10: Tradycyjna 24 zł, Salami 27 zł, w kopii lokalnej 25 i 28 zł). Skrypty z `plugins/tezapiekanki/tools/` cen nie ruszają, ale przed każdym `apply` na produkcji przejrzyj podgląd.
- Zrzuty "tak było / tak jest" do case study: `local site 2/tezapiekanki/case-study/2026-10-20/` (README z opisem par), skrypt `.claude/tests/case-study-shots.js` (blokuje GTM, GA i piksele, chowa baner cookies, do koszyka na produkcji dodaje 1 produkt bez zamówienia).
- Elementor: `elementor_css_print_method = external` (pliki w `uploads/elementor/css`).
- Wdrażanie: brak pipeline'u. Zmiany przenosi się ręcznie (pliki mu-pluginu/child theme przez FTP/SFTP, zmiany w Elementorze/ustawieniach odtwarza się w panelu albo eksportem/importem). **Nie nadpisuj bazy produkcyjnej lokalną**: na produkcji spływają nowe zamówienia.

### Wdrożenie motywu potomnego na produkcję (jednorazowo, jeszcze niewykonane)

Kolejność jest ważna, w każdym kroku sklep działa:
1. Kopia zapasowa produkcji (pliki + baza).
2. Wgraj katalog `wp-content/themes/woodmart-child/` (`style.css`, `functions.php`, `inc/product-summary.php`, `inc/checkout.php`, `js/checkout.js`, `inc/product-gallery.php`, `js/product-gallery.js`, `inc/product-sticky-cart.php`, `js/product-sticky-cart.js`, `inc/woodmart-popups.php`, `screenshot.jpg`). Galeria działa dopiero po ustawieniu widgetu (klasa `tzp-gallery`, niżej "Wdrożenie zdjęć i galerii"), a poprawki paska dopiero po włączeniu go w opcjach Woodmarta. Wcześniej ten CSS i skrypty niczego nie zmieniają. `style.css` zawiera też wygląd boxa z terminem dostawy, koszyka i checkoutu. Style i skrypt checkoutu działają dopiero po przebudowie layoutów (niżej), wcześniej stary checkout wygląda bez zmian.
3. Wygląd → Motywy → aktywuj "Woodmart Child - TeZapiekanki". Raport działa dalej ze starego kodu Woodmarta (zabezpieczenie z sekcji 4.1).
4. Wejdź na stronę główną jako gość (WordPress przepina wtedy położenia menu), potem sprawdź Wygląd → Menu → Zarządzaj położeniami: `main-menu` i `mobile-menu` = "menu".
5. Customizer → Dodatkowy CSS jest dla nowego motywu pusty. Skopiuj CSS z Woodmarta, np. WP-CLI: `wp eval 'wp_update_custom_css_post( wp_get_custom_css( "woodmart" ), array( "stylesheet" => "woodmart-child" ) );'`, albo ręcznie: aktywuj na chwilę podgląd Woodmarta w Customizerze i przeklej.
6. Porównaj produkcyjny `themes/woodmart/functions.php` z kopią z backupu (czy nikt nie dopisał tam czegoś po 18.10). Usuń linie od `/** MENU */` do końca pliku (oryginał kończy się na `define( 'WOODMART_VERSION', … );`). Od teraz raport idzie z motywu potomnego.
7. WooCommerce → Zestawienie produktów: wygeneruj CSV dla najbliższego dnia wysyłki i porównaj z tym, czego oczekuje klient.
8. Purge LiteSpeed Cache. Elementor przy zmianie motywu czyści swój CSS: pierwsze wejście na każdą stronę wypisze go inline, kolejne znowu z plików.

### Wdrożenie zdjęć i galerii na produkcję (jeszcze niewykonane)

Warunek: motyw potomny wdrożony (wyżej). Kopie layoutów: `backups/2026-10-18-gallery/` (przed zmianą) i `backups/2026-10-18-gallery/po-zmianie/` (gotowe `_elementor_data` 6473, 6868, 6480, 6533, 11).
1. Kopia zapasowa bazy i `wp-content/uploads`.
2. Wygląd → Dostosuj → WooCommerce → Obrazki produktów: szerokość miniatury 600, przycinanie 1:1.
3. Regeneracja **wszystkich rozmiarów** zdjęć produktów (WP-CLI `wp media regenerate <id...> --yes` albo Regenerate Thumbnails). Nie regeneruj samego `woocommerce_thumbnail` (sekcja 7, pułapka).
4. Converter for Media → "Optymalizuj teraz" (WebP/AVIF dla nowych plików).
5. Elementor, rozmiar zdjęć "woocommerce_thumbnail": archiwa 6473 i 6868, strona główna (2 widgety produktów, wcześniej 420×275), "podobne" w 6480 i 6533. Albo import plików z `po-zmianie/` (porównaj wcześniej z produkcją, czy nikt nie zmieniał tych layoutów po 18.10).
6. Layouty 6480 i 6533 (od 19.10 także zestawy) → widget galerii: Układ "Bottom grid", kolumny 2 / tablet 2 / telefon 1, karuzela na tablecie: wyłącz, na telefonie: włącz, paginacja: wyłącz, Zaawansowane → Klasy CSS: `tzp-gallery`.
7. Woodmart → Ustawienia motywu → Produkt → zdjęcia: akcja po kliknięciu zdjęcia = Popup (`image_action`).
8. Dodatkowy CSS (`woodmart-child`): usuń regułę `.wd-product img {max-height:200px!Important;object-fit:cover!important;}`.
9. Elementor → Narzędzia → Regeneruj CSS, purge LiteSpeed. Test: sklep, strona główna, karta zapiekanki z 6, 7 i 8 zdjęciami na desktopie, tablecie i telefonie, lightbox.

### Wdrożenie paska "Dodaj do koszyka" na produkcję (jeszcze niewykonane)

1. Motyw potomny z `inc/product-sticky-cart.php`, `js/product-sticky-cart.js` i aktualnym `style.css`.
2. Woodmart → Ustawienia motywu → Produkt → "Sticky add to cart": włącz, oraz "Sticky add to cart on mobile": włącz (wysokości domyślne).
3. Woodmart → Ustawienia motywu → Mobile → dolny pasek narzędzi (sticky toolbar): wyłącz w całości.
4. Layouty 6480 i 6533: kontener 1d4fc61 (czarny) pokaż na tablecie i telefonie, min. wysokość 60, margines górny -100; kontener c9ab516 margines górny 0 na tablecie i telefonie; kontener b931d83 (opis) padding na tablecie prawy 10 zamiast 109. Albo import plików z `backups/2026-10-18-gallery/po-zmianie/` (zawierają też galerię).
5. Purge LiteSpeed. Test: karta zapiekanki i zestawu na desktopie, tablecie i telefonie (czarny pasek, galeria pod nagłówkiem), dodanie do koszyka z paska, "Wybierz smaki" na zestawie, czy przycisk CookieYes nie zasłania paska.

### Wdrożenie checkoutu (wariant B) na produkcję (jeszcze niewykonane)

Warunki: motyw potomny wdrożony (wyżej) i `wc-ship-today-notice` 2.1 aktywna z modułem checkoutu (`[ship_today_checkout]`, atrybut `class` w `[ship_today_notice]`), według `.claude/doc/zastapienie-iconic-plan.md`.
1. Kopia zapasowa bazy. Najlepiej poza godzinami zamówień, bo między krokami 3 i 4 checkout ma dwa pola kuponu.
2. FCF (WooCommerce → Pola zamówienia → Płatność): przeciągnij pola w kolejności e-mail, telefon, imię, nazwisko, ulica, ciąg dalszy adresu, kod pocztowy, miasto, kraj, nazwa firmy, NIP. Klasa CSS: e-mail `form-row-first`, telefon `form-row-last` (pozostałe bez zmian). Zapisz.
3. Layout 6470: wczytaj `_elementor_data` z `backups/2026-10-18-child-theme/layout-6470-checkout-b.json` (WP-CLI: `wp eval 'update_post_meta( 6470, "_elementor_data", wp_slash( file_get_contents( "layout-6470-checkout-b.json" ) ) );'`) albo odtwórz układ ręcznie w Elementorze według sekcji 8. Plik zawiera widgety billing/płatności/podsumowania z kopii produkcji z 18.10 razem z ich ustawieniami. Przed nadpisaniem porównaj produkcyjne `_elementor_data` 6470 z `layout-6470-elementor-data.json`: jeśli ktoś zmieniał layout po 18.10, przenieś te zmiany ręcznie.
4. Layout 6467: najprościej plik `layout-6467-checkout-b.json`. Ręcznie w Elementorze: usuń widget "Checkout coupon form" (`wd_checkout_coupon_form`); kontener 237ffa4: szerokość 1240, padding dolny 0, klasa CSS `tzp-checkout-top`; kontener 674a7e9 (biały box): klasa `tzp-checkout-top__login`; widget formularza logowania: szerokość domyślna (100%).
5. Strona "Zamówienie" (223): zamień treść na blok "Klasyczny checkout" (tak jak lokalnie 18.10, sekcja 8, pułapki).
6. Elementor → Narzędzia → Regeneruj CSS, purge LiteSpeed Cache.
7. Test na produkcji jako gość i jako zalogowany klient (bez pustego boxu nad formularzem): kafelki Paczkomat/Kurier, mapa Apaczki (wybierz punkt, potem zmień termin: punkt musi zostać widoczny w Dostawie i w podsumowaniu), kupon nad "Łącznie", na telefonie kupon pod paskiem, faktura. Zamówienie testowe tylko jeśli klient się zgodzi (prawdziwa płatność Paynow).

### Wdrożenie belki o wysyłce i meta description na produkcję (jeszcze niewykonane)

Warunki: motyw potomny z `inc/woodmart-popups.php` wdrożony (wyżej) i `wc-ship-today-notice` 2.x aktywna (terminy w popupie). Teksty belki, popupu i opisu zaakceptowane przez klienta.
1. Kopia zapasowa bazy.
2. Woodmart → Ustawienia motywu → Wydajność → JS → "Scripts never load": usuń "Floating blocks", zapisz.
3. Wgraj `wp-content/plugins/tezapiekanki/` i aktywuj. Belka pojawia się od razu, link do czasu kroku 4 prowadzi do "Sposoby dostawy".
4. Popup: `wp eval-file wp-content/plugins/tezapiekanki/tools/create-shipping-popup.php --user=<admin>` (tworzy popup i ustawia go jako popup belki). Bez WP-CLI: menu Popups → nowy popup w Elementorze z treścią z zakresu (tytuł, 2 akapity, widget Shortcode z sekcji 8 "Belka o wysyłce", akapit o termoboxie, przycisk "Wybieram zapiekanki" → /sklep/), w ustawieniach popupu szerokość 620 i warunki Include All + Exclude Custom "Checkout page" (wyzwalaczy nie trzeba ustawiać), opublikuj. Potem Wygląd → Dostosuj → "Belka o wysyłce": po kliknięciu "Otwórz popup", wybierz ten popup, opublikuj.
5. Rank Math na stronie głównej (11): opis "Świeże zapiekanki do upieczenia w domu. Wysyłamy 2 razy w tygodniu w termoboxie z wkładami chłodzącymi, dostawa do Paczkomatu lub pod drzwi." (lokalnie ustawiony 19.10, wcześniej opisu nie było i Rank Math brał "Zamów teraz, a my dostarczymy je jutro…" z hero).
6. Purge LiteSpeed Cache. Test jako gość (strona z cache): belka, popup z najbliższym terminem, krzyżyk i przeładowanie (belka nie miga), checkout bez belki, telefon. Lokalnie: `.claude/tests/shipping-bar-e2e.js`.

### Wdrożenie "Info praktyczne" i porządków (pkt 4 i 6 zakresu) na produkcję (jeszcze niewykonane)

Lokalnie zrobione 19.10. Kopie sprzed zmian: `backups/2026-10-19-info-filtry/` (`_elementor_data` 6480, 6533, 6473, 6868, 11, opisy wszystkich produktów, opcje nagłówka, widgetów i FCF); stan po zmianie: `po-zmianie/` (6480, 6533, 6473, 11, blok 31279). Na produkcji ID bloku i elementów może być inne, więc najlepiej skryptami albo ręcznie, a nie importem całych layoutów.
1. Kopia zapasowa bazy.
2. Wgraj aktualną wtyczkę `tezapiekanki` (z `assets/icons/` i `tools/`).
3. Blok ikon: `wp eval-file wp-content/plugins/tezapiekanki/tools/create-product-info-block.php --user=<admin>`. Bez WP-CLI: Media → wgraj 4 pliki PNG z `assets/icons/`; HTML Blocks → nowy blok "Info praktyczne (karta produktu)" w Elementorze: kontener (kierunek: wiersz, zawijanie, odstęp 14/16 px), w nim 4 widgety Woodmart "Information box": typ Icon, obraz = ikona, rozmiar własny 34×34, "Inline" włączone, tytuł i treść (opis) z sekcji 5, podtytuł, przycisk i **link puste** (domyślnie jest `#`), tytuł 14 px / 600, opis 13 px kolor #666, szerokość widgetu 47% (telefon 46%); kontenerowi klasa CSS `tzp-info`. Potem w layoutach 6480 (nowy kontener pod kontenerem z ceną i przyciskiem, z tym samym paddingiem) i 6533 (pod widgetem "Dodaj do koszyka") widget Shortcode `[html_block id="<ID bloku>"]`, w Zaawansowane → pamięć podręczna elementu: Nieaktywne.
4. Wkłady: `wp eval-file wp-content/plugins/tezapiekanki/tools/unify-cooling-inserts.php` (podgląd), potem z argumentem `apply`. Bez WP-CLI: ręcznie w opisach produktów (także ukrytych kopii do zestawów) "żelow…" → "chłodząc…" (lokalnie 102 miejsca w 33 produktach).
5. FCF (WooCommerce → Pola zamówienia → Zamówienie): "Proszę o wysyłkę zamrożonych zapiekanek" ustaw jako ukryte (odznacz "Włącz pole" albo zaznacz ukrycie, zależnie od wersji FCF), zapisz.
6. Filtry: Wygląd → Widżety → "sidebar-shop": przenieś wszystkie 5 widgetów do nieaktywnych. Layout 6473 (ARCHIVE CARD) w Elementorze: usuń kolumnę z widgetem Sidebar, kontener z przyciskiem "FILTRUJ OFERTĘ" i widget aktywnych filtrów, kolumnie z produktami ustaw szerokość 100%, w widgecie produktów kolumny 4 / tablet 3 / telefon 1.
6a. Logotypy płatności ("Szybka płatność i dostawa") w layoutach 6480, 6533 i 6470: widget galerii obrazów → "On click action" = None.
7. Strona główna (11), sekcja "Poznaj 6 smaków": oba przyciski "Sprawdzam pozostałe" (wersja desktop i tablet/telefon) zmień na styl domyślny (wypełniony), kolor Primary, tekst "Zobacz wszystkie smaki", link `/zapiekanki/`, rozmiar Large na desktopie, czcionka 14 px (telefon 13 px).
8. Woodmart → Header Builder → nagłówek → górny pasek (ukryty): usuń element Text "ADD ANYTHING HERE…", zapisz nagłówek.
9. Elementor → Narzędzia → Regeneruj CSS, purge LiteSpeed. Test: karta zapiekanki i zestawu (blok ikon na desktopie i telefonie, ikony i logotypy nieklikalne, galeria zestawu), sklep bez sidebara i bez przycisku filtrów, 4 w rzędzie (desktop, tablet, telefon), przycisk smaków na stronie głównej, opis produktu z "wkładami chłodzącymi". Lokalnie: `.claude/tests/product-listing-e2e.js`, `product-sticky-cart-e2e.js`, `product-gallery-e2e.js`.

### Wdrożenie FAQ na produkcję (moduł, strona FAQ, produkty, strona główna; jeszcze niewykonane)

Kopie sprzed zmian lokalnie: `backups/2026-10-19-faq/` (strona główna przed zamianą i przed skróceniem, stopka 6541, opisy 33 produktów przed skróceniem FAQ).
1. Kopia zapasowa bazy.
2. Wgraj aktualną wtyczkę `tezapiekanki` (1.1: `includes/class-faq*.php`, `includes/class-product-faq.php`, `assets/css/faq.css`, `tools/`). Od tej chwili FAQ w opisach produktów wyświetla się jako pytania rozwijane ze schemą i przyciskiem do `/faq/` (strona jeszcze nie istnieje, krok 5).
3. Strona główna: `wp eval-file wp-content/plugins/tezapiekanki/tools/convert-accordion-to-faq.php 11 --user=<admin>` (akordeon Woodmarta → widget FAQ z tymi samymi pytaniami).
4. Opisy produktów: `wp eval-file wp-content/plugins/tezapiekanki/tools/shorten-product-faq.php` (podgląd), potem z `apply`. Na produkcji opisy mogły się zmienić, więc najpierw przejrzyj podgląd.
5. `wp eval-file wp-content/plugins/tezapiekanki/tools/create-faq-page.php --user=<admin>`: strona `/faq/` (11 pytań w 4 grupach, schema, opis Rank Math), link "Najczęstsze pytania" w stopce, FAQ na stronie głównej skrócone do 4 pytań bez schemy z przyciskiem do `/faq/`. Potem ręcznie w Elementorze na stronie głównej: usuń pasek "Nie zamieściliśmy odpowiedzi… / Napisz do nas" pod FAQ (wersja desktop i tablet/telefon), a sekcji FAQ ustaw na telefonie dolny odstęp 40 px.
   Bez WP-CLI: kroki 3-5 ręcznie w Elementorze według treści z `tools/create-faq-page.php` (tablice `$tzp_q`, `$tzp_groups`, `$tzp_home`), a skrócenie opisów w edytorze produktów (zostają pytania o świeżość, wysyłkę na świeżo, mrożenie i imprezy, bez numeracji).
6. Elementor → Narzędzia → Regeneruj CSS, purge LiteSpeed. Test: `/faq/`, karta zapiekanki, strona główna, stopka, telefon; w źródle `/faq/` i produktu jeden `script.tzp-faq-schema`, na stronie głównej żadnego. Rich Results Test (adres `/faq/` i produktu) bez błędów FAQPage. Lokalnie: `.claude/tests/faq-e2e.js`.

### Wdrożenie kart produktów, wartości odżywczych, pinów i danych dla Google na produkcję (jeszcze niewykonane)

Opis zmian: sekcja 7, "Karty produktów". Kopie sprzed zmian lokalnie: `backups/2026-10-20-karty-produktow/` (cała baza `baza-local-przed.sql.gz`, layout 6480, opisy i atrybuty 34 produktów z kategorii Zapiekanki, wszystkie atrybuty i termy).
1. Kopia zapasowa bazy.
2. Wgraj aktualną wtyczkę `tezapiekanki` (1.2: `includes/class-product-card.php`, `class-flavor-pins.php`, `class-product-schema.php`, `class-bundles.php`, `assets/css/product.css`, `assets/js/flavor-pins.js`, `tools/` razem z `tools/karty-produktow/`). Od razu działają dane dla Google (wysyłka, zwroty, nazwa, waga) i komunikat pustego zestawu z liczbą sztuk. Shortcody są gotowe, ale na stronie pojawią się po kroku 5.
3. `wp eval-file wp-content/plugins/tezapiekanki/tools/import-product-cards.php --user=<admin>` (podgląd: czy wszystkie 11 smaków jest znalezionych po nazwie i ile kopii, ostrzeżenie o energii "na dziko" jest znane), potem z `apply`. Bez WP-CLI (dużo ręcznej pracy, 33 produkty): Media → wgraj 11 PDF-ów z `tools/karty-produktow/`; Produkty → Atrybuty: nowe "Kwasy tłuszczowe nasycone" (slug `kwasy-nasycone`) i "Cukry" (`cukry`), nazwy "Kalorie" → "Wartość energetyczna" i "Tłuszcze" → "Tłuszcz", w "Ser" term "Mozarella" → "Mozzarella"; w każdym produkcie i ukrytych kopiach wartości z tablicy `$tzp_cards` w skrypcie oraz pole własne `kartaproduktowa` z linkiem do PDF-a.
4. `wp eval-file wp-content/plugins/tezapiekanki/tools/unify-product-descriptions.php` (podgląd; na produkcji opisy mogły się zmienić), potem z `apply`.
5. `wp eval-file wp-content/plugins/tezapiekanki/tools/update-product-layout.php --user=<admin>` (layout 6480; na produkcji ID layoutu sprawdź, podaje się je jako pierwszy argument). Bez WP-CLI w Elementorze: pod kontenerem z ceną i "Dodaj do koszyka" nowy kontener (odstępy jak w kontenerze z ceną) z widgetem Shortcode `[tzp_flavor_pins]`; w kontenerze z napisem "Wartości odżywcze na 100g" usuń ten napis, "Pobierz kartę produktu" i tabelę atrybutów, wstaw widget Shortcode `[tzp_nutrition]`, przeciągnij kontener zaraz pod blok "Info praktyczne". Obu widgetom: Zaawansowane → pamięć podręczna elementu = Nieaktywne.
5a. Layouty 6480 i 6533 w Elementorze: kontener kolumny z tytułem, ceną i opisem (obok galerii) → Zaawansowane → Padding na tablecie: 0 / 20 / 0 / 20 (telefon zostaje 0).
6. Sprawdź koszt wysyłki w Flexible Shipping (lokalnie 14,99 zł do 300 zł).
5b. Logo "Skalbmierskie od 1987": `wp eval-file wp-content/plugins/tezapiekanki/tools/replace-logo.php --user=<admin>` (podgląd), potem z `apply`. Bez WP-CLI: Woodmart → Header Builder → element Logo (desktop i telefon): obraz i obraz przyklejonego nagłówka = Light.png, zapisz nagłówek; HTML Block "FOOTER" i "POP" w Elementorze: obraz logo = Black.png, rozmiar Full.
6a. WooCommerce → Ustawienia → E-maile → "Adres odpowiedzi": włącz, adres sklep@tezapiekanki.pl (nazwa pusta = nazwa nadawcy). Od tej chwili stopka maili do klientów podaje ten adres zamiast noreply@. Jeśli jest inny, zmień `SHIPPING_RATE` w `includes/class-product-schema.php`.
7. Elementor → Narzędzia → Regeneruj CSS, purge LiteSpeed. Test: 2-3 zapiekanki na desktopie i telefonie (tabela, waga, PDF się otwiera, piny, przejście pinem do innego smaku), zestaw (komunikat "Wybierz 9 zapiekanek…" po kliknięciu "Zamów zestaw" bez wybranych smaków), Rich Results Test adresu produktu: Product i "Merchant listings" bez błędów (ostrzeżenia o GTIN i opiniach są oczekiwane). Lokalnie: `.claude/tests/product-card-e2e.js`.

### Wdrożenie poprawek po uwagach klientki z 26.10 na produkcję (jeszcze niewykonane)

Lokalnie zrobione 26.10. Kopia bazy sprzed zmian: `backups/2026-10-26-uwagi-klientki/baza-local-przed.sql.gz`, kopie `_elementor_data` w `uploads/tzp-backup/`. Warunki: wdrożona belka z popupem, blok "Info praktyczne", FAQ i `wc-ship-today-notice` (wcześniejsze podsekcje), bo skrypty zmieniają ich treść.
1. Kopia zapasowa bazy.
2. Wgraj wtyczkę `tezapiekanki` (`assets/css/shipping-bar.css`: czarna belka; nowe skrypty w `tools/`).
3. `wp eval-file wp-content/plugins/tezapiekanki/tools/update-shop-texts.php --user=<admin>` (podgląd), potem z `apply`: termin przydatności 7 dni w lodówce (popup, blok ikon, FAQ, opisy produktów, atrybut), pieczenie 220-250°C około 8 minut (kroki na stronie głównej, zakładka "Sposób przygotowania", FAQ, opisy), godzina 23:59 w FAQ, nowa sekcja "Kiedy wysyłamy" na "Sposoby dostawy", bez napisu "Zamów teraz, a my dostarczymy je jutro…" w hero. Dokumenty szuka po roli, nie po ID. Lokalnie 128 zmian. Na produkcji teksty mogły się zmienić: czego skrypt nie znajdzie, popraw ręcznie w Elementorze.
4. `wp eval-file wp-content/plugins/tezapiekanki/tools/remove-visitor-counter.php --user=<admin>`, potem z `apply`. Bez WP-CLI: w layoutach kart produktu usuń widget "Visitor counter" (pod logotypami płatności) i wyłącz Woodmart → Ustawienia motywu → Produkt → "Visitor counter".
5. `wp eval-file wp-content/plugins/tezapiekanki/tools/update-shipping-settings.php`, potem z `apply`. Bez WP-CLI: WooCommerce → Ship Today Notice, godziny graniczne 23:59; WooCommerce → Ustawienia → Wysyłka → Polska → "InPost Paczkomat" i "InPost Kurier": reguły zastąp jedną "Zawsze" z kosztem 14.99 (darmowa od 300 zł zostaje).
6. Elementor → Narzędzia → Regeneruj CSS, purge LiteSpeed. Test: strona główna (czarna belka nad zdjęciem, hero bez napisu, kroki), karta produktu (bez licznika, "7 dni świeżości", zakładka przygotowania), popup belki, `/faq/`, "Sposoby dostawy", koszyk za mniej i za więcej niż 300 zł (14,99 zł / bezpłatnie).

---

## 10. Dług techniczny i pułapki (nie naprawiaj bez uzgodnienia, ale miej na uwadze)

- Snippet 30284 ukrywa wszystkie powiadomienia o aktualizacjach wtyczek, więc w panelu nie widać, że coś wymaga update'u.
- Zdublowane filtry dataLayer (29726 / 29828 / 29829).
- Snippet 5901 zamienia "Cart" w każdym przetłumaczonym tekście (może psuć np. "Cartoon", "Carta").
- Szkic 29729 z sekretami w treści. Tokeny warto zrotować.
- Layout 6868 ma warunek na nieistniejący term 17.
- "Zapiekanka na ostro" (1498 i kopie) od 20.10 ma wagę, wartości odżywcze, ser, mięso, składnik i termin, ale nadal bez "Sos", "Oferta" i "Długość zapiekanki" (nie ma ich na karcie; atrybuty niewidoczne na stronie).
- Karty produktów od klienta (20.10), do wyjaśnienia z klientem, nie poprawiane: "na dziko" ma energię niezgodną z makro (222 kcal na karcie, z tłuszczu, węglowodanów i białka wychodzi około 206 kcal; klientka wie o tym, a producent boczku nie zmieni danych, więc zostaje tak jak na karcie, decyzja z 26.10); parmezan zawiera "lizozym z białka jaj", a jaja są tylko w "Może zawierać"; boczek z dzika zawiera gorczycę, a gorczyca też jest tylko w "Może zawierać"; składniki złożone bez składu (kebab z kurczaka, salami, chorizo, ogórki szwedzkie, cebulka prażona); sosy z opisów (saszetki) nie mają składu ani alergenów; makro identyczne jak na kartach z sierpnia mimo zmiany składu (doszedł parmezan); literówki: "Z SZYNKA", "Białko 9.7 g", Szwedzka ma tytuł "KARTA INFORMACYJNA - ZAPIEKANKA OGÓREK SZWEDZKI" (inne "KARTA INFORMACYJNA PRODUKTU"). Termin przydatności: karty go nie podają; od 26.10 wszędzie na stronie 7 dni od dostawy, w lodówce (decyzja klientki).
- Opisy produktów, sekcja "Sosy i dodatki": "Możesz również wzbogacić swoje zamówienie o prażoną cebulkę, żurawinę lub autorskie sosy", a sklep nie ma takiej opcji (to samo pytanie usunięto 19.10 z FAQ produktów). Czeka na odpowiedź klienta.
- Waga wysyłkowa (pole "Waga" w zakładce Wysyłka) jest tylko przy "na ostro" (3 kopie, 1 kg) i farszu. Od 26.10 lokalnie nie ma to znaczenia: wysyłka zawsze 14,99 zł, od 300 zł za darmo (sekcja 8, "Wysyłka"). Na produkcji do wdrożenia nadal są reguły wagowe.
- Stare karty PDF z sierpnia (`uploads/2026/08/*.pdf`, załączniki 30786-30796) zostały w mediach, żaden produkt ich już nie używa.
- Zapis dokumentu Elementora przez API w WP-CLI renderuje widgety do tekstu (`save_plain_text`). Widgety karty produktu wymagają wtedy globalnego `$product` (inaczej błąd krytyczny w `woocommerce/single-product/product-image.php`, a `_elementor_data` zostaje zapisane bez kroków po zapisie), a widgety archiwum globalnego `$wp_query` z produktami. Przykład: `tools/create-product-info-block.php` (`$tzp_product_context`).
- Pozostałości w bazie po odinstalowanych wtyczkach: Salient (`theme_mods_salient`, `salient_g_sections`, `custom_css` 5700), WPBakery (`_wpb_*` meta, `uploads/js_composer`), Yoast (`_yoast_wpseo_*`), YayMail, AutomateWoo (`aw_workflow`), Advanced Woo Labels (`awl-labels`), WC Conditional Fees (`wc_conditional_fee`), PixelYourSite (`_pys_*`), Site Kit, Przelewy24, YITH FBT. Nie opieraj nowego kodu na tych danych.
- `WP_MEMORY_LIMIT` ustawiony na `3000M` w wp-config.
- Uploads: 20.10 lokalnie usunięte 1270 plików zdjęć (2,29 GB, głównie oryginały z aparatu po 8-23 MB) i 65 ich kopii WebP/AVIF. Nie było ich w bibliotece mediów, w treściach, meta (także Elementor), opcjach ani term meta, nie były kopiami sprzed edycji; potem 54 opublikowane strony, produkty i kategorie bez brakujących obrazów. Lista: `backups/2026-10-20-usuniete-zdjecia/lista-usunietych-plikow.csv`. **Na produkcji te pliki dalej są.** Tego samego dnia usunięte też miniatury w rozmiarach starego motywu Salient (`portfolio-thumb`, `nectar_small_square`, `wide`, `regular`, `tall` itd.): 4185 plików (445 MB) i 1857 kopii WebP/AVIF (119 MB), razem z wpisami w metadanych 307 zdjęć (`srcset` bierze rozmiary z metadanych). Zostały: 65 takich miniatur wstawionych adresem w treściach, rozmiary `site_icon-*` (favicon) i rozmiary "WxH" (Woodmart generuje je w locie dla widgetów z własnym wymiarem). Kopia metadanych sprzed zmiany: `backups/2026-10-20-usuniete-zdjecia/metadane-zalacznikow-przed-usunieciem-rozmiarow-salient-1.json` i `-2.json`. Po sprzątaniu `uploads` ma 863 MB (było 3,6 GB), `uploads-webpc` 262 MB; 54 opublikowane strony bez brakujących obrazów. Do decyzji zostało 989 kopii sprzed edycji zdjęć (153 MB, potrzebne tylko do "Przywróć oryginał" w edytorze obrazów). Paczka Duplicatora: wykluczyć `wp-content/uploads-webpc` (Converter for Media generuje od nowa) i `wp-content/themes/woodmart/inc/admin/modules/import/dummy-data` (209 MB danych demo Woodmarta, używa ich tylko import demo).
- Strona "Zamówienie" (223) na produkcji ma w treści blok checkoutu, a renderuje ją klasyczny layout Woodmarta (sekcja 8, pułapki). Lokalnie zamienione 18.10 na klasyczny checkout, testy checkoutu z Apaczką przechodzą; na produkcji do zrobienia przy wdrożeniu checkoutu.
- Apaczka zapisuje `apm_country_code = "undefined"` (sekcja 8a).
- `wp plugin list` potrafi wisieć na sprawdzaniu aktualizacji (blokuje kolejne komendy WP-CLI). Używaj `wp plugin list --skip-update-check` albo odczytu `active_plugins`.

---

## 11. Checklisty

### Nowa funkcja na karcie produktu / w koszyku / w checkoucie
1. Sprawdź, który layout Woodmarta renderuje dany widok (sekcja 5).
2. Wybierz punkt wpięcia: hook przez widget `wd_wc_hook`, shortcode albo własny widget Elementora.
3. Logika w mu-pluginie, assety enqueue'owane warunkowo (`is_product()`, `is_checkout()`, `is_cart()`).
4. JS odporny na AJAX: `updated_checkout`, `updated_wc_div`, `wdShopPageInit`, `wdActionAfterAddToCart`.
5. Sprawdź zestawy (`woosb`): produkt w koszyku może być zestawem albo ukrytym składnikiem.
6. Test: dodanie do koszyka (pojedyncza + zestaw 9), checkout z terminem dostawy, Paczkomatem i Kurierem, mail w Mailpit, podgląd zamówienia w adminie. Zmiany w checkoucie: `.claude/tests/checkout-e2e.js`.

### Nowe pole w checkoucie
- Proste pola dodawaj przez FCF w panelu (zgodność z obecną konfiguracją). Własną logikę (warunki, walidacja) dopisuj w mu-pluginie przez hooki FCF lub WC.
- Zapis do zamówienia przez `$order->update_meta_data()` na `woocommerce_checkout_create_order`.
- Wyświetlenie w adminie, mailach i na fakturze WebToffee.

### Kod operujący na zamówieniach
- `wc_get_orders()` z parametrami lub `meta_query`, nigdy SQL na `wpiq_posts`.
- Data dostawy: `$order->get_meta( 'jckwds_date_ymd' )`.
- Pamiętaj o trzech ID tego samego smaku (sekcja 7).
