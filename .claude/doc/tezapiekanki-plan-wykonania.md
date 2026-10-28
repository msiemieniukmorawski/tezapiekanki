# tezapiekanki.pl - plan wykonania zadań

Plan do pliku `tezapiekanki-todo.md` (zakres z 12.10.2026). Opisuje, jak technicznie wykonam każde zadanie na tej konkretnej instalacji. Stan sprawdzony w kopii lokalnej 18.10.2026 (baza, layouty Elementora, opcje Woodmarta, kod wtyczek).

Oznaczenia przy krokach:
- **[kod]** pliki wtyczki, przenoszone na produkcję jako pliki,
- **[panel]** zmiana w panelu / Elementorze / ustawieniach, którą trzeba powtórzyć na produkcji (zbiorcza lista na końcu),
- **[treść]** praca na tekstach.

---

## Założenia techniczne (wspólne dla wszystkich punktów)

1. **Dwie wtyczki.** Nic nie edytuję w Woodmarcie ani w innych wtyczkach.
   - **`wc-ship-today-notice`** (Twoja wtyczka, repo `studiozaiste/wc-ship-today-notice`) jako silnik terminów wysyłki i dostawy, rozbudowany generycznie (szczegóły w pkt 2),
   - **`tezapiekanki`** (wtyczka sklepu): integracja z tym sklepem (Iconic, zapis w zamówieniu, belka, popup) oraz moduły `Product` (piny smaków, karta produktu, galeria) i `Schema`.
2. **Cache LiteSpeed na produkcji trzyma strony 7 dni** (`ttl_pub` i `ttl_frontpage` = 604800 s). Wszystko, co zależy od godziny (termin, odliczanie, treść belki i popupu), musi być liczone w przeglądarce. Serwer wypisuje JSON z harmonogramem najbliższych terminów na kilka tygodni, JS wybiera aktualny. Dodatkowo czyszczę cache LiteSpeed o każdej godzinie granicznej i przy zapisie ustawień (`do_action( 'litespeed_purge_all' )`), żeby tekst startowy w HTML też był świeży.
3. **JS defer w LiteSpeed:** skrypty wtyczki rejestruję przez `wp_enqueue_script` z zależnościami. Mały skrypt inline w `<head>` (ukrywanie zamkniętej belki przed wyrenderowaniem) dostaje `data-no-optimize="1"`, żeby LiteSpeed go nie przesunął.
4. **Iconic Delivery Slots zostaje.** Klient wybiera w nim dziś "Preferowaną datę wysyłki" (tak jest przetłumaczone w Loco), dni w kalendarzu to poniedziałek i środa. Silnik w `wc-ship-today-notice` staje się jedynym źródłem reguł (dni wysyłki, godziny graniczne, święta, daty wykluczone), a Iconic dostaje te same reguły przez filtry z wtyczki `tezapiekanki`. Kalendarz nie może pokazać daty, której silnik by nie zaproponował.
5. **Produkty:** każdy smak istnieje w 3 kopiach (widoczna + 2 ukryte składniki zestawów). Zmiany w tym zakresie robię tylko na 11 widocznych produktach. Zestawy to faza 2.
6. **Praca lokalnie, potem przeniesienie.** Bazy produkcyjnej nie nadpisuję (spływają zamówienia). Kod idzie jako pliki, zmiany w panelu powtarzam według listy z końca dokumentu. Większe sekcje Elementora przenoszę przez "Zapisz jako szablon" → eksport JSON → import na produkcji.

---

## 0. Przygotowanie

**Kopia zapasowa**
- Produkcja: kopia z hostingu + Duplicator (pliki + baza), zanim cokolwiek zmienię na produkcji. Lokalnie pracuję na kopii z 18.10.

**Punkt odniesienia GA4**
- GA4 → Eksploracje → Lejek: `view_item` → `add_to_cart` → `begin_checkout` → `purchase`, zakres od 11.10.2026 do dnia wdrożenia.
- Zapisuję liczby zdarzeń, liczbę sesji i współczynniki (add_to_cart/sesje, purchase/begin_checkout) w tym pliku, z datami.
- W okresie porównawczym **nie ruszam snippetów GTM** (DATALAYER FIX, DL FIX2, DL3), mimo że się dublują. Zmiana trackingu w trakcie pomiaru zepsułaby porównanie.

**Staging**
- Stagingiem jest kopia w Local (`tezapiekanki.local`). Maile testowe lądują w Mailpit (`http://localhost:10054`), płatności testuję tylko do momentu przekierowania do Paynow.

---

## 1. Zdjęcia i galeria (3,5-5 h)

**Status 18.10: zrobione lokalnie** (opis w CLAUDE.md, sekcja 7 "Zdjęcia i galeria", wdrożenie: sekcja 9). Różnice względem planu niżej, uzgodnione z Marcinem:
- galeria to "Bottom grid" Woodmarta (duże pierwsze zdjęcie, pod nim 2 kolumny), a nie "Combined grid",
- przy więcej niż 6 zdjęciach widać duże + 4 kafelki, a przycisk "Zobacz więcej zdjęć" pod siatką **rozwija resztę w dół** (i zwija z powrotem); bez nakładki "+N" na zdjęciu; do 6 zdjęć widać wszystkie,
- kafelki pod dużym zdjęciem i slajdy na telefonie w proporcji 4:3 (zdjęcia w galeriach to głównie 3:2, przy 1:1 zapiekanka miałaby ucięte końce),
- galeria zestawów (6533) bez zmian, do decyzji klienta.

### Format 1:1 (0,5 h)

Stan obecny:
- Przycinanie miniatur WooCommerce nie jest ustawione, więc domyślnie i tak jest 1:1. Niejednolitość bierze się z innych miejsc:
  - archiwum sklepu (layouty 6473 i 6868, widget `wd_archive_products`) używa rozmiaru **`full`**, czyli oryginałów, bez przycinania i z dużą wagą,
  - sekcja smaków na stronie głównej (widget `wd_products` 709318e) używa rozmiaru **420×275**,
  - w Dodatkowym CSS jest `.wd-product img {max-height:200px; object-fit:cover}`, które ucina zdjęcia.

Kroki:
1. **[panel]** WooCommerce → Wygląd → Obrazki produktów: szerokość miniatury 600 px (ostrość przy 3 kolumnach i ekranach retina), przycinanie 1:1.
2. **[panel]** W Elementorze zmieniam `img_size` na `woocommerce_thumbnail`: layouty 6473, 6868 i oba widgety `wd_products` na stronie głównej.
3. **[panel]** Usuwam regułę `.wd-product img {max-height…}` z Dodatkowego CSS.
4. Regeneracja miniatur: lokalnie `wp media regenerate --image_size=woocommerce_thumbnail --yes`. Na produkcji WP-CLI, jeśli hosting go ma, inaczej tymczasowo wtyczka Regenerate Thumbnails.
5. Po regeneracji Converter for Media → "Optymalizuj teraz", żeby powstały WebP/AVIF dla nowych rozmiarów.
6. Sprawdzam listing, stronę główną i kartę produktu na desktopie i mobile. Jeśli któreś zdjęcie źródłowe jest poziome i środkowe przycięcie ucina produkt, zgłaszam to klientowi (potrzebne zdjęcie 1:1 albo inny kadr).

### Galeria produktu: desktop (część z 3-4,5 h)

Stan obecny: widget `wd_single_product_gallery` w layoutach 6480 (zapiekanki) i 6533 (zestawy) z miniaturami po lewej (domyślnie), bez paginacji. **Lightbox jest wyłączony** (`image_action = none` w opcjach Woodmarta).

Kroki:
1. **[panel]** W widgecie galerii (layout 6480) ustawiam `thumbnails_position = bottom_combined` ("Combined grid", duże zdjęcie, pod nim siatka). Sprawdzam warianty 1, 2 i 3 i wybieram ten, który daje 2 miniatury w rzędzie. Odstęp zostaje 15 px.
2. **[panel]** Woodmart → Produkt → akcja po kliknięciu zdjęcia: `popup` (PhotoSwipe), czyli natywny lightbox.
3. **[kod]** Nakładka "+N": tego Woodmart nie ma. Mały skrypt i CSS w module `Product`:
   - na desktopie pokazuję zdjęcie główne + 4 miniatury, resztę ukrywam w CSS (`:nth-child(n+6)`),
   - na ostatniej widocznej miniaturze nakładka "+N" (N = liczba ukrytych),
   - kliknięcie otwiera lightbox od tego zdjęcia. PhotoSwipe Woodmarta zbiera wszystkie zdjęcia galerii, także ukryte, więc pozostałe da się przewijać w lightboxie. Sprawdzę to w przeglądarce, zanim napiszę resztę.
4. Layout 6533 (zestawy) poza zakresem. Zostawiam bez zmian, chyba że klient chce spójności.

### Galeria produktu: mobile

Kroki:
1. **[panel]** W widgecie galerii `carousel_on_mobile = enable`: na mobile siatka zamienia się w slider z przesuwaniem palcem.
2. **Peek** (wystający kawałek kolejnego zdjęcia): karuzele Woodmarta biorą liczbę slajdów ze zmiennej CSS `--wd-col-*` (`js/scripts/global/swiperInit.js`). **[kod]** CSS: `--wd-col-sm: 1.15` dla głównej galerii na mobile. Jeśli Swiper galerii nie czyta zmiennej, ustawiam `slidesPerView` przez instancję.
3. **[kod]** Licznik "1/7": podpinam się pod instancję Swipera galerii (zdarzenie `slideChange`) i aktualizuję licznik w rogu zdjęcia. Natywnie Woodmart ma tylko kropki.
4. Lightbox: ten sam co na desktopie (krok 2 wyżej).

Szacunek: konfiguracja z kroków [panel] zajmie mało czasu dzięki natywnym opcjom Woodmarta. Główna praca to "+N", licznik i testy na urządzeniach. Mieszczę się w widełkach, raczej bliżej dolnej granicy.

---

## 2. Komunikacja wysyłki i dostawy (8,5-9,5 h)

**Bloker:** godziny graniczne i dni wysyłki od klienta. Bez nich buduję i testuję wszystko na założeniu z listy (niedziela 20:00 → wtorek, wtorek 20:00 → czwartek) z konfiguracją w panelu.

### Moduł terminu dostawy (4-5 h), baza: wtyczka `wc-ship-today-notice`

**Co już jest we wtyczce** (v1.2.0, około 1150 linii, jedna klasa `WC_Ship_Today_Notice`):
- panel WooCommerce → Ship Today Notice (Settings API, opcja `wc_ship_today_notice_options`): dni wysyłki z osobną godziną graniczną dla każdego dnia, daty wykluczone, tryby widoczności, 3 teksty z tokenem `{countdown}`, format odliczania,
- święta z OpenHolidays API we własnej tabeli `{prefix}wc_ship_today_notice_holidays`, cron co 4 miesiące + ręczne pobieranie, możliwość ignorowania wybranych świąt,
- shortcode `[ship_today_notice]`: tekst pobierany AJAX-em (`admin-ajax.php`, akcja `wc_ship_today_notice_get`), odliczanie w JS,
- tłumaczenie pl_PL, obsługa WPML.

**Czego brakuje pod ten sklep:**
- Wtyczka zna tylko scenariusz "zamów przed X, wyślemy dziś". Tu godzina graniczna wypada **dzień przed wysyłką** (niedziela 20:00 → wysyłka pn), a klient ma zobaczyć konkretne dni: "Zamów do niedzieli 20:00, dostarczymy we wtorek".
- Nie liczy daty dostawy ani kolejnych terminów. Popup potrzebuje dwóch najbliższych.
- Po godzinie granicznej pokazuje stały tekst ("wyślemy jutro"), bez wyliczonej daty.
- Odliczanie nie ma dni. Od środy do niedzieli 20:00 to ponad 4 doby, wyszłoby "98h".
- Nie ma hooków (są w TODO w README), więc wtyczka sklepu nie ma jak pobrać terminów.

**Błędy, które wyjdą na tym sklepie** (naprawiam w ramach rozbudowy):
1. **Nonce w HTML z cache.** JS wysyła nonce wygenerowany przy renderze strony. LiteSpeed trzyma strony 7 dni, a nonce wygasa po 12-24 h. Wtedy endpoint zwraca 403, a zamiast komunikatu widget pokazuje samą kreskę. Endpoint tylko odczytuje dane publiczne, więc nonce nie jest potrzebny.
2. **Zapytanie do admin-ajax przy każdym wyświetleniu strony.** To niecache'owane żądanie z pełnym startem WordPressa przy każdej odsłonie. Zamieniam je na harmonogram wypisany w stronie jako JSON (zero dodatkowych zapytań).
3. **Wykluczenia świąt zapisane po ID wiersza.** Pobieranie świąt kasuje wiersze i wstawia je od nowa, więc ID się zmieniają i wykluczenia po cichu przepadają (przy każdym cronie co 4 miesiące albo ręcznym pobraniu). Zmieniam na wykluczenia po dacie.
4. **Pobierany jest tylko bieżący rok.** W grudniu wtyczka nie zna świąt ze stycznia (1.01, 6.01), a pierwszy termin "po świętach" wypada właśnie wtedy. Pobieram bieżący i następny rok.
5. Po zejściu odliczania do zera zostaje tekst "przed godziną graniczną" z 00:00, bez przełączenia na kolejny termin (to jest w TODO w README).

Drobiazgi przy okazji: wersja w nagłówku (1.2.0) różni się od README i CSS (1.1.0). W repo są niezacommitowane zmiany tłumaczeń (pl_PL, nowy de_DE), więc nie mogą przepaść przy przenoszeniu.

Model reguł (zgodny z obecnym Iconic):
- dni wysyłki: poniedziałek i środa,
- godzina graniczna: dzień przed wysyłką o 20:00 (niedziela 20:00 → wysyłka pn, wtorek 20:00 → wysyłka śr),
- dostawa: następny dzień roboczy po wysyłce (wtorek, czwartek). Do potwierdzenia, czy tak samo dla Paczkomatu i Kuriera,
- strefa czasowa: `Europe/Warsaw` (ustawienie WordPressa).

**Status 18.10:** kroki 1-8 w `wc-ship-today-notice` zrobione lokalnie (wersja 2.0.0, gałąź `feature/next-slot-engine`, bez commita). Tabela 18 przypadków przechodzi, przełączanie terminu w JS sprawdzone w Chrome. Ustawienia TeZapiekanki zapisane lokalnie z roboczym tekstem. Do zrobienia: integracja z Iconic i wyświetlanie w sklepie (wtyczka `tezapiekanki`).

Do decyzji klienta, wyszło w testach: przy wysyłce w środę 23.12 dostawa wypada dopiero w poniedziałek 28.12 (24-26.12 to święta, 27.12 niedziela). Czy 23.12 w ogóle wysyłać? Jeśli nie, wystarczy dodać 23-12-2026 do dat wykluczonych.

**Wariant do decyzji klienta:** zamiast integrować Iconic, zastąpić go własnym wyborem terminu w checkoucie. Bez migracji: przełącznik w naszym module wyłącza Iconic w koszyku i checkoucie, po miesiącu Iconic jest dezaktywowany. Plan, inwentarz, wdrożenie i szacunek (netto około +3 h): `zastapienie-iconic-plan.md`. Jeśli klient się zgodzi, kroki "Synchronizacja z Iconic" niżej odpadają.

Kroki w `wc-ship-today-notice` (zmiany generyczne, istniejące instalacje działają po staremu):
1. **[kod] Przeniesienie** wtyczki do `wp-content/plugins/wc-ship-today-notice/` jako repozytorium git (z kopii w `.claude/doc`, razem z niezacommitowanymi tłumaczeniami) i osobny branch na rozbudowę.
2. **[kod] Silnik terminów**, nowa klasa z czystą logiką (łatwa do przetestowania):
   - per dzień wysyłki: godzina graniczna + **przesunięcie dnia** (0 = ten sam dzień, jak dotąd; -1 = dzień wcześniej, jak w TeZapiekanki). Domyślnie 0,
   - **przesunięcie dostawy** w dniach roboczych (0 = bez daty dostawy, jak dotąd),
   - `next_slots( $now, $count )` zwraca listę `{ cutoff, ship, delivery }`: pomija dni bez wysyłki, święta i daty wykluczone, a przy dostawie także niedziele. Po minięciu godziny granicznej pierwszy termin sam przeskakuje na następny,
   - API dla innych wtyczek: `wc_stn_get_slots( $count, $now = null )`, `wc_stn_delivery_for_ship_date( $ymd )` oraz filtry z TODO w README (`wc_ship_today_notice_cutoff_time`, `wc_ship_today_notice_is_shipping_day`, `wc_ship_today_notice_text`) + `wc_ship_today_notice_slots` i `wc_ship_today_notice_now` (tryb testowy).
3. **[kod] Nowe tokeny w tekstach:** `{countdown}`, `{cutoff_day}` ("do niedzieli"), `{cutoff_time}`, `{ship_day}`, `{delivery_day}` ("we wtorek", "w czwartek"), `{delivery_date}` ("22.10"). Polska odmiana nazw dni w tablicach tłumaczeń (dopełniacz i "w/we" + biernik), więc inne języki dalej działają.
4. **[kod] Shortcode z atrybutami:** `[ship_today_notice count="2" text="…"]`, żeby ten sam silnik obsłużył widget przy cenie i listę dwóch terminów w popupie.
5. **[kod] Odliczanie z dniami** ("1 dzień 5 h 12 min", poprawne formy liczby mnogiej) i przełączanie na kolejny termin po zejściu do zera, bez przeładowania strony.
6. **[kod] Frontend bez AJAX:** shortcode wypisuje tekst startowy policzony na serwerze + JSON harmonogramu na 6 tygodni. JS wybiera bieżący termin. Przy zapisie ustawień i o godzinach granicznych czyszczę cache LiteSpeed, jeśli wtyczka LiteSpeed jest aktywna.
7. **[kod] Poprawki błędów 1-5** z listy wyżej.
8. **[kod] Panel:** pola przesunięć w tabeli dni, lista świąt na bieżący i następny rok, podgląd 5 najbliższych terminów (punkt "Live Preview" z TODO w README), żeby klient od razu widział efekt zmian.

Kroki w `tezapiekanki` (integracja ze sklepem, korzysta z API powyżej):
1. **[kod] Synchronizacja z Iconic:**
   - filtr `iconic_wds_is_holiday`: święta i daty wykluczone z silnika blokują też daty w kalendarzu,
   - filtr `iconic_wds_is_delivery_day`: dni wysyłki z silnika (żeby nie ustawiać ich w dwóch miejscach),
   - godzina graniczna: w Iconic `nextday_cutoff = 20:00` i wyłączony same-day **[panel]**, albo filtr `iconic_wds_is_day_allowed`, jeśli godziny będą różne dla różnych dni,
   - do decyzji z klientem: `auto_select_first = 1`, czyli kalendarz od razu zaznacza najbliższą datę, taką samą jak w komunikacie.
2. **[kod/panel] Wyświetlanie:**
   - przy cenie produktu: `[ship_today_notice]` wstawiony widgetem Shortcode pod `wd_single_product_price` w layoucie 6480 **[panel]**,
   - w koszyku: hook `woocommerce_cart_totals_after_shipping` (layout CART CARD ma widget sumy koszyka),
   - w checkoucie: hook `woocommerce_review_order_after_shipping` (tabela podsumowania odświeżana fragmentami WooCommerce).

Ustawienia dla TeZapiekanki **[panel]**:
- poniedziałek: wysyłka, godzina graniczna 20:00, przesunięcie -1,
- środa: wysyłka, godzina graniczna 20:00, przesunięcie -1,
- dostawa: +1 dzień roboczy,
- kraj PL, pobrane święta na bieżący i następny rok,
- tekst np. "Zamów do {cutoff_day} {cutoff_time}, dostarczymy {delivery_day}. Zostało {countdown}." (do akceptacji klienta).

Rozliczenie: zmiany we wtyczce bazowej są generyczne i zostają u Ciebie do innych projektów. Klientowi liczę to, czego potrzebuje ten sklep: silnik terminów, tokeny, odliczanie z dniami, poprawki 1-5 i integrację z Iconic. Rzeczy niezwiązane z tym sklepem (WPML, de_DE, strefy czasowe per użytkownik i strefy wysyłki z TODO) nie wchodzą w te godziny.

Ryzyko budżetu: wtyczka oszczędza panel, święta i odliczanie. Większość z 4-5 h zjedzą silnik kolejnych terminów, poprawki pod cache i integracja z Iconic. Jeśli zobaczę, że przekroczę 5 h, zatrzymam się i poinformuję.

### Daty w koszyku, checkoucie, thank you page i mailu (1,5 h)

1. Koszyk i checkout: hooki z kroku "Wyświetlanie" wyżej. W checkoucie, gdy klient wybierze w kalendarzu inną datę wysyłki, Iconic wywołuje `update_checkout`. Wtyczka `tezapiekanki` czyta wtedy `jckwds-delivery-date-ymd` z `post_data` i pokazuje dostawę dla wybranej daty (`wc_stn_delivery_for_ship_date()`).
2. **[kod] Zapis w zamówieniu:** `woocommerce_checkout_update_order_meta` z priorytetem 20 (Iconic zapisuje swoje meta na 10):
   - `_tzp_ship_date` (Ymd, z `jckwds_date_ymd`),
   - `_tzp_delivery_date` (Ymd, wyliczona przez `wc_stn_delivery_for_ship_date()`),
   - zapis przez `$order->update_meta_data()` + `save()` (HPOS).
3. **[kod] Thank you page i mail** z potwierdzeniem: wiersz "Planowana dostawa" w `woocommerce_get_order_item_totals`. Ten filtr obsługuje oba miejsca naraz, tak samo jak robi to Iconic.
4. Iconic już dodaje wiersz "Preferowana data wysyłki". Zostawiam go, ewentualnie zmieniam etykietę na "Data wysyłki", żeby nie było dwóch podobnych wierszy.
5. **[kod]** Panel zamówienia w adminie: obie daty pod adresem wysyłki (`woocommerce_admin_order_data_after_shipping_address`).
6. Test: zamówienie lokalnie → mail w Mailpit → thank you page → podgląd w adminie.

### Belka u góry strony z popupem o farszu (2,75 h)

**Bloker:** akceptacja tekstu belki i popupu.

**Status 19.10: zrobione lokalnie** na roboczych tekstach z zakresu (CLAUDE.md, sekcja 8 "Belka o wysyłce", wdrożenie: sekcja 9). Wtyczka `tezapiekanki` powstała przy tej okazji. Różnice i rzeczy, które wyszły po drodze:
- cały tekst belki jest linkiem (na telefonie łatwiej trafić), krzyżyk osobno; bez linku do popupu (zanim JS się wczyta albo bez popupu) link prowadzi do "Sposoby dostawy",
- ustawienia dla klienta (na prośbę Marcina z 19.10): Wygląd → Dostosuj → "Belka o wysyłce", czyli tekst, tekst linku i działanie kliknięcia (dowolny popup Woodmarta z listy, adres albo nic) oraz włącz/wyłącz. Wyzwalacz dopina wtyczka do wybranego popupu, więc klient przepina belkę na inny popup bez ruszania ustawień popupów,
- w popupie tylko **jeden, najbliższy termin** (decyzja Marcina z 19.10, zamiast dwóch linii z zakresu), z `{cutoff_when}`, np. "Zamów jutro do 20:00, a dostaniesz ją w czwartek." (zamiast samych nazw dni, bo "do niedzieli" w niedzielę brzmi dziwnie); po godzinie granicznej sam przeskakuje na kolejny,
- **popupy Woodmarta z Elementora w ogóle nie działały** (także na produkcji, te same wersje): moduł MCP Elementora 4.3 blokuje rejestrację typu dokumentu popupu, a skrypt popupów był wyłączony w opcjach wydajności Woodmarta. Poprawka w motywie potomnym (`inc/woodmart-popups.php`) i zmiana opcji. Diagnoza tego błędu to praca poza zakresem belki (do rozliczenia według faktycznego czasu),
- zdarzenia `tzp_shipping_bar` (open/close) w dataLayer, żeby po wdrożeniu dało się zmierzyć użycie belki (tag w GTM do ustawienia, jeśli klient chce).

Belka:
1. **[kod]** Wypisywana przez wtyczkę `tezapiekanki` na `wp_body_open` (Woodmart wywołuje go w `header.php`). Tekst: "Wysyłamy tylko 2 razy w tygodniu. Sprawdź dlaczego ->".
2. **[kod]** Ukryta w checkoucie (`is_checkout()`), widoczna na reszcie stron.
3. **[kod]** Krzyżyk "X" zapisuje w `localStorage` dzisiejszą datę (w strefie Europe/Warsaw). Skrypt inline w `<head>` sprawdza ją przed wyrenderowaniem i dodaje klasę ukrywającą, żeby belka nie mignęła na stronach z cache.
4. **[kod]** Mobile: jedna linia, mniejsza czcionka, krzyżyk z polem dotyku 44 px. Sprawdzam współpracę z przyklejonym nagłówkiem Woodmarta (żeby belka nie zasłaniała menu po przewinięciu).

Popup:
1. **[panel]** Nowy popup Woodmarta (`wd_popup`, edytowalny w Elementorze przez klienta) z treścią z listy zadań i przyciskiem "Wybieram zapiekanki" → /sklep/.
2. **[panel]** Dynamiczne daty w treści: shortcode `[ship_today_notice count="2" text="Zamów do {cutoff_day} {cutoff_time}, a dostaniesz ją {delivery_day}."]` wstawiony w popupie. Wypisuje dwie linie z tekstem startowym, a JS aktualizuje je z harmonogramu (cache).
3. **[panel]** Wyzwalacz popupu: "On click", selektor `.tzp-bar-link` (link w belce).
4. Mobile: popup na pełną szerokość, przewijana treść.

### Meta description strony głównej (0,25 h)

**Status 19.10: opis ustawiony lokalnie** (Rank Math, strona 11, 140 znaków, bez konkretnych dni do czasu potwierdzenia godzin granicznych): "Świeże zapiekanki do upieczenia w domu. Wysyłamy 2 razy w tygodniu w termoboxie z wkładami chłodzącymi, dostawa do Paczkomatu lub pod drzwi." Do akceptacji klienta. Hero ("dostarczymy je jutro") bez zmian, czeka na zgodę (pytanie 8).

Stan obecny: strona główna (ID 11) nie ma własnego opisu w Rank Math, więc Rank Math bierze `%excerpt%`, czyli początek treści. A na początku jest **hero z tekstem "Zamów teraz, a my dostarczymy je jutro do Paczkomatu lub Twojego domu."**

Kroki:
1. **[panel]** Rank Math na stronie 11: własny opis, np. "Świeże zapiekanki do upieczenia w domu. Wysyłka 2 razy w tygodniu w termoboxie, dostawa do Paczkomatu lub pod drzwi. Zamów do niedzieli 20:00, dostawa we wtorek." (do akceptacji klienta, bez konkretnego dnia, jeśli terminy mają się zmieniać).
2. **[treść]** Ten sam tekst "dostarczymy je jutro" widać w hero strony. Proponuję poprawić go przy okazji (kilka minut, ta sama obietnica co w meta). Zgłoszę klientowi, bo formalnie lista mówi tylko o meta description.

---

## 3. Karta produktu (9 h)

### Poprawne makro na 11 produktach (2 h)

**Status 20.10: zrobione lokalnie** (CLAUDE.md, sekcja 7 "Karty produktów", wdrożenie: sekcja 9). Karty przyszły jednym PDF-em (11 stron), pocięte na pliki i podpięte pod produkty. Wartości z kart wpisane skryptem `tools/import-product-cards.php` na wszystkich 33 produktach (z kopiami do zestawów), doszły "w tym kwasy tłuszczowe nasycone" i "w tym cukry" (wymagane na etykiecie), zapis jednolity ("8,5 g", "880 kJ / 211 kcal"), nieużywane termy usunięte (74). Wcześniej na stronie było inne makro niż na kartach (poza Tradycyjną) i inne wagi (np. Salami 261 g zamiast 349 g). Energia "na dziko" nie zgadza się z makro, do wyjaśnienia z klientem.

**Bloker:** karty produktów od klienta (dostarczone 20.10).

Stan obecny: wartości odżywcze to atrybuty globalne (`pa_kalorie`, `pa_tluszcze`, `pa_weglowodany`, `pa_bialko`, `pa_sol`, `pa_waga`), po jednym termie na wartość (np. "6,7g"). Wyświetla je widget `wd_single_product_additional_info_table` w layoucie 6480 (siatka 3 kolumny). W bazie jest sporo osieroconych termów z liczbą produktów 0.

Kroki:
1. Przepisuję wartości z kart klienta do arkusza: produkt × 6 wartości, jednolity zapis ("6,7 g", kJ / kcal).
2. **[kod]** Skrypt WP-CLI (`wp eval-file`) ustawia termy na 11 widocznych produktach (`wp_set_object_terms` + aktualizacja `_product_attributes`) i tworzy brakujące termy. 66 wartości skryptem to mniej literówek niż ręcznie. Na produkcji uruchamiam ten sam skrypt, a bez WP-CLI wpisuję ręcznie z arkusza.
3. Ujednolicam zapis termów (spacja przed "g", "kcal" zamiast "kcl"). Osierocone termy usuwam tylko po sprawdzeniu, że nie używają ich ukryte kopie produktów.
4. Sprawdzam tabelę na każdym z 11 produktów (desktop + mobile).

### Wyeksponowanie karty produktu i ujednolicenie opisów (2 h)

**Status 20.10: zrobione lokalnie.** Zamiast małego "Pobierz kartę produktu" i siatki atrybutów na końcu kolumny: shortcode `[tzp_nutrition]` zaraz pod blokiem ikon (tabela jak na etykiecie, waga zapiekanki, wyraźny przycisk "Pobierz kartę produktu (PDF): pełny skład, alergeny i wartości odżywcze"). Karty PDF są teraz przy wszystkich 11 smakach (także Wiejska i na ostro). Opisy ujednolicone skryptem `tools/unify-product-descriptions.php`: trzy sery zamiast dwóch (tak mówią karty i krótkie opisy), literówki w roku receptury (1886, 1887), "Spianata Piccante", stara nazwa "Szefa", podwójny H1. Shortcode nazywa się `[tzp_nutrition]` (sam przycisk: `[tzp_product_card]`).

Stan obecny:
- Przycisk "Pobierz kartę produktu" to widget Elementor Pro "post-info" w layoucie 6480 z tagiem dynamicznym pola `kartaproduktowa`. W polu siedzi gotowy link HTML do PDF-a.
- **Wiejska (404) i na ostro (1498) nie mają karty PDF.**

Kroki:
1. **[kod]** Shortcode `[tzp_product_card]`: wyciąga URL z pola `kartaproduktowa` i wypisuje wyraźny przycisk z ikoną PDF. Gdy pola brak, nie wypisuje nic (żadnego pustego przycisku).
2. **[panel]** Wstawiam go w layoucie 6480 pod przyciskiem dodania do koszyka, a stary widget "post-info" usuwam. Tabelę wartości odżywczych przesuwam wyżej, bliżej opisu.
3. Proszę klienta o PDF-y dla Wiejskiej i na ostro (albo wygeneruję je z kart produktów, jeśli dostanę dane).
4. **[treść]** Ujednolicenie opisów:
   - zestawiam dla każdego produktu skład z atrybutów (`pa_ser`, `pa_mieso`, `pa_sos`, `pa_skladnik-dodatkowy`) z tym, co mówi opis,
   - rozbieżności (np. liczba serów) poprawiam według karty produktu od klienta, a wątpliwe zgłaszam.

### Sticky belka "Dodaj do koszyka" na dole ekranu (3 h)

**Status 18.10: zrobione lokalnie** (CLAUDE.md, sekcja 7 "Pasek Dodaj do koszyka", wdrożenie: sekcja 9). Natywny pasek Woodmarta (na telefonie tylko ilość i przycisk, decyzja Marcina) plus poprawka w motywie potomnym dla zestawów: przycisk "Wybierz smaki" / "Dodaj do koszyka" (natywnie kliknięcie nic nie robiło). Przy okazji, na prośbę Marcina: na telefonie i tablecie czarny pasek pod nagłówkiem i galeria pod nim (layouty 6480 i 6533), dolna nawigacja na telefonie wyłączona w całości, poprawiony padding opisu produktu na tablecie (literówka 109 px w layoutach 6480 i 6533).

Stan obecny: Woodmart ma to natywnie (`single_sticky_add_to_cart` i `mobile_single_sticky_add_to_cart`, oba wyłączone). Pasek zawiera zdjęcie, nazwę, cenę, pole ilości i przycisk. Pokazuje się po przewinięciu poza `form.cart`, więc działa też z przyciskiem z layoutu Elementora.

Kroki:
1. **[panel]** Woodmart → Produkt → włączam sticky add to cart na desktopie i mobile, ustawiam wysokości.
2. Sprawdzam na mobile: czy pasek nie zasłania belki cookies (w Dodatkowym CSS jest już przesunięcie `.wcc-revisit-bottom-left`), dolnej nawigacji ani przycisku "do góry".
3. **[kod]** Dopiero gdy natywny pasek czegoś nie spełni, piszę własny na IntersectionObserver. Na tę chwilę nie widzę takiej potrzeby.

Szacunek: natywna opcja powinna zmieścić się w około 1-1,5 h zamiast 3 h. Klient płaci za faktyczny czas, więc to oszczędność dla niego.

### Piny do innych smaków (2 h)

**Status 20.10: zrobione lokalnie** zgodnie z krokami niżej (`[tzp_flavor_pins]` w layoucie 6480 pod ceną i "Dodaj do koszyka", decyzja Marcina z 20.10; pierwotnie pod tytułem, "Smak:" + 11 pinów w kolejności sklepu, na telefonie przewijany wiersz ustawiony na bieżącym smaku).

Kroki:
1. **[kod]** Shortcode `[tzp_flavor_pins]`:
   - lista **widocznych** produktów z kategorii Zapiekanki (bez ukrytych kopii do zestawów, filtr `product_visibility`), w kolejności ustawionej w Rearrange Products (`rwpp_sortorder_18`),
   - bieżący smak wyróżniony, smaki niedostępne wyszarzone, ale klikalne,
   - nowy smak pojawi się automatycznie, bez edycji layoutu.
2. **[panel]** Wstawiam shortcode w layoucie 6480 pod tytułem produktu (widget Shortcode).
3. Mobile: piny w poziomym, przewijanym pasku zamiast łamania na kilka linii.

Alternatywa bez kodu: "Linked variations" Woodmarta (opcja jest włączona). Wymagałaby jednak nowego atrybutu "Smak" i ręcznego łączenia produktów. Shortcode jest prostszy w utrzymaniu.

---

## 4. Info praktyczne (2,5 h)

### Blok ikon przy produkcie (2 h)

**Status 19.10: zrobione lokalnie** (CLAUDE.md, sekcja 5 "HTML Block 31279", wdrożenie: sekcja 9 "Info praktyczne i porządki"). Decyzja Marcina: blok także na karcie zestawów (6533). Ikony PNG wyrenderowane z własnych SVG (WordPress bez wtyczki nie przyjmuje SVG do mediów), na desktopie i telefonie układ 2×2. Po uwagach Marcina: tytuł + krótki opis zamiast samego "6 dni od dostawy" (było niejasne), kafelki nieklikalne, mniejsze odstępy. "6 dni świeżości" przy Tradycyjnej (7 dni w atrybucie) czeka na decyzję klienta (pytanie 5).

1. **[panel]** Nowy HTML Block Woodmarta (`cms_block`) "Info praktyczne": 4 widgety `wd_infobox` z ikonami SVG i tekstami:
   - "6 dni od dostawy",
   - "trzymaj w lodówce",
   - "można zamrozić zaraz po odbiorze",
   - "termobox z wkładami chłodzącymi".
2. **[panel]** Wstawiam blok w layoucie 6480 pod przyciskiem dodania do koszyka (i w 6533, jeśli klient chce). Jedno miejsce edycji dla wszystkich produktów.
3. Ikony: spójny zestaw SVG w kolorze marki `#8C3215`, lekki, bez fontu ikon.
4. Spójność z atrybutem `pa_termin-przydatnosci`: wszystkie smaki mają "6 dni" poza Tradycyjną, która ma "7 dni". Do wyjaśnienia z klientem przy kartach produktów.

### Korekta sprzecznych treści (0,5 h)

**Status 19.10: zrobione lokalnie.**
- Mrożenie: teksty na stronie były już spójne ("nie mrozimy, wysyłamy świeże, po odbiorze możesz zamrozić"). Checkboxa "Proszę o wysyłkę zamrożonych zapiekanek" klienci i tak nie widzieli: layout checkoutu (także produkcyjny) nie ma sekcji z polami zamówienia, stąd 0 zaznaczeń w 2078 zamówieniach. Decyzja Marcina: ukryty w FCF.
- Wkłady: "żelowe" było w opisach wszystkich produktów (102 miejsca w 33 produktach razem z ukrytymi kopiami), a nie w 2 miejscach, jak zakładał plan. Zamienione skryptem `tools/unify-cooling-inserts.php` na "chłodzące", tak jak na stronie głównej, w "Sposobach dostawy" i w popupie.

Gdzie dokładnie jest sprzeczność o mrożeniu:
- strona główna: "dostajesz je świeże - bez mrożenia!" oraz FAQ "Czy zapiekanki są mrożone przed wysyłką? Nie…",
- opisy 11 produktów: "Możesz również zamrozić je po dostawie",
- produkt Farsz: "Świeże, nie mrożone",
- **checkout: checkbox "Proszę o wysyłkę zamrożonych zapiekanek"** (pole FCF `order_zamrozone`). To najpoważniejsza sprzeczność, bo deklaracja "nie wysyłamy mrożonych" stoi obok opcji zamówienia mrożonych. W 343 zamówieniach pole zapisało się puste, czyli **nikt go nigdy nie zaznaczył**.

Kroki:
1. Pytanie do klienta: czy wysyłka mrożonych zapiekanek jest możliwa? Od odpowiedzi zależy, czy checkbox zostaje (i wtedy teksty mówią "domyślnie świeże, na życzenie mrożone"), czy go ukrywamy. Skoro nikt z niego nie korzystał, ukrycie niczego nie zabierze klientom.
2. **[treść]** Wspólny komunikat, np. "Wysyłamy świeże, nie mrożone. Po odbiorze możesz je zamrozić." Wstawiam go w wymienionych miejscach.
3. **[treść]** Wkłady: zdecydowana większość tekstów (layout 6480, opisy produktów, strona główna) mówi "wkłady chłodzące", a "wkłady żelowe (mrożące)" pojawia się 2 razy. Ujednolicam do "wkłady chłodzące" (2 ręczne poprawki, miejsca znajdę wyszukiwaniem w bazie).

---

## 5. FAQ (2-3 h)

**Status 19.10: moduł FAQ gotowy lokalnie** (na prośbę Marcina, CLAUDE.md, sekcja 5 "FAQ"). Widget Elementora "FAQ (zwiń / rozwiń)" we wtyczce `tezapiekanki` zamiast natywnego akordeonu Elementora: natywne `<details>` (bez JS), jedna schema FAQPage na stronę zbierana ze wszystkich widgetów (akordeony Elementora wypisują FAQPage osobno w każdym widgecie, a dwa na stronie to błąd w Google), wygląd jak dotychczasowe FAQ. 

**Status 19.10 (cd.): FAQ zrobione lokalnie w całości poza Product/Offer** (CLAUDE.md, sekcja 5 "FAQ", wdrożenie: sekcja 9 "Wdrożenie FAQ"):
- strona "Najczęstsze pytania" `/faq/`: 11 pytań w 4 grupach (zebrane ze strony głównej, opisów produktów i popupu o wysyłce, bez powtórzeń), jedna schema FAQPage, link w stopce,
- produkty: FAQ w opisie wyświetla się jako pytania rozwijane ze schemą i przyciskiem "Zobacz wszystkie pytania"; opisy skrócone z 6 do 4 pytań o konkretny smak (decyzja Marcina: 3-4 pytania + przejście do pełnej listy),
- strona główna: FAQ skrócone do 4 pytań, których nie powtarza tekst SEO (mrożenie, farsz i Paczkomat są w tym tekście i na `/faq/`), bez schemy, z przyciskiem do `/faq/`; tekst SEO bez zmian,
- usunięte z produktów pytanie "Czy mogę dodać do zapiekanki dodatkowe składniki?": sklep nie ma takiej opcji, a odpowiedź obiecywała dodatki (do potwierdzenia z klientem),
- znalezione: strona "Sposoby dostawy" obiecuje wysyłkę tego samego dnia przy zamówieniu do 10:00, sprzeczne z wysyłką pn i śr (do poprawy po potwierdzeniu godzin granicznych).

### Osobna zakładka FAQ + skrócenie na produktach (1 h)

Stan obecny:
- na stronie głównej: akordeon `wd_accordion` (4 pytania) + bloki tekstowe z pytaniami w sekcji SEO,
- na produktach: rozbudowane FAQ wpisane w opis produktu (`post_content`, zakładka Opis), osobno dla każdego z 11 produktów.

Kroki:
1. **[panel]** Nowa strona "FAQ" w Elementorze. Pytania zebrane ze strony głównej i produktów, bez duplikatów, pogrupowane (dostawa, przechowywanie, pieczenie, zamówienia). Link w menu "INFORMACJE" i w stopce.
2. **[treść]** Na każdym z 11 produktów zostawiam 2-3 kluczowe pytania + link "Wszystkie pytania →". To praca na 11 opisach, dlatego 1 h jest napięta. Jeśli treści będą się mocno różnić, poinformuję przed przekroczeniem.

### Usunięcie zduplikowanych sekcji FAQ ze strony głównej (0,25 h)

1. **[panel]** Na stronie 11 zostawiam jedną sekcję (akordeon 2e3bb3e albo skrót z linkiem do strony FAQ). Duplikaty w blokach tekstowych usuwam po sprawdzeniu wizualnym, którą wersję klient uważa za aktualną.

### Dane strukturalne (0,75-1,75 h)

Stan obecny: Rank Math (darmowy) generuje schema produktów. Akordeon Woodmarta nie generuje schema FAQPage.

Kroki:
1. **FAQPage:** zrobione 19.10 jako własny widget (wyżej). Schema tylko w jednym miejscu dla tych samych pytań (przełącznik w widgecie).
2. **Uczciwie do klienta:** od 2023 r. Google pokazuje rozszerzone wyniki FAQ praktycznie tylko serwisom rządowym i medycznym. Schema FAQPage nadal jest poprawna i czytelna dla wyszukiwarek oraz AI, ale gwiazdek ani rozwijanych pytań w wynikach nie będzie.
3. **Product/Offer (status 20.10: zrobione lokalnie, `includes/class-product-schema.php`):** wysyłka (14,99 zł, czas z silnika terminów), zwroty (odstąpienie nie przysługuje), nazwa produktu bez nazwy sklepu, waga z atrybutu, podpisy atrybutów, marka. Walidacja w Rich Results Test po wdrożeniu (lokalnego adresu Google nie otworzy). Pierwotny plan: sprawdzam kod generowany przez Rank Math. Ostrzeżenia Merchant listings (brak `shippingDetails`, `hasMerchantReturnPolicy`) uzupełniam filtrem `rank_math/snippet/rich_snippet_product_entity` **[kod]**, z danymi wysyłki i zwrotów ze stron "Sposoby dostawy" i "Zwroty i reklamacje".
4. Walidacja: Rich Results Test i Schema Validator. Lokalnie przez wklejenie kodu HTML strony, na produkcji przez URL po wdrożeniu.

---

## 6. Filtry i porządki (2-2,5 h)

### Filtry (1,25-1,75 h)

**Status 19.10: wariant A zrobiony lokalnie** (decyzja Marcina): layout 6473 bez sidebara, przycisku "FILTRUJ OFERTĘ" i aktywnych filtrów, produkty na pełną szerokość; widgety z `sidebar-shop` w nieaktywnych (do przywrócenia). Layout 6868 (zestawy) już wcześniej nie miał sidebara.

**Bloker:** decyzja klienta.

Stan obecny: w `sidebar-shop` (wyświetlanym widgetem `wd_sidebar` w layoucie 6473) jest 5 widgetów:
- filtr kategorii "Nasza oferta" (przy 2 kategoriach mało przydatny),
- 4 filtry atrybutów Woodmarta, m.in. "Rodzaj mięsa" (`pa_mieso`) i "Dodatkowy składnik".

AJAX sklepu jest włączony.

Wariant A, usuwamy wszystkie:
1. **[panel]** Usuwam widgety z `sidebar-shop`. W layoutach 6473 i 6868 usuwam kolumnę z sidebarem i aktywnymi filtrami, produkty na pełną szerokość (3 kolumny).

Wariant B, zostaje 1 filtr "mięsne / wege / ostre":
1. **[panel]** Nowy atrybut "Rodzaj" (Mięsna, Wege, Ostra) przypisany 11 produktom (jeden produkt może mieć kilka, np. ostra i mięsna).
2. **[panel]** Jeden widget filtra Woodmarta, wyświetlany jako przyciski nad listą produktów zamiast w sidebarze. Pozostałe 5 widgetów usuwam.

W obu wariantach sprawdzam mobile (Woodmart ukrywa sidebar na mobile, więc przycisk "Filtry" też musi zniknąć albo działać).

### Pozostałości szablonu w kodzie (0,25 h)

**Status 19.10: zrobione lokalnie** (element tekstowy usunięty z ukrytego górnego paska nagłówka przez API Header Buildera, CSS nagłówka przebudowany).

1. **[panel]** Tekst "ADD ANYTHING HERE OR JUST REMOVE IT…" siedzi w elemencie "Text" w Header Builderze (nagłówek `header_686073`), prawdopodobnie w ukrytym wierszu albo w wersji mobilnej. Usuwam element i zapisuję nagłówek, a Woodmart przegeneruje CSS nagłówka.

### Sekcja "11 smaków" na stronie głównej (0,5 h)

**Status 19.10: zrobione lokalnie** (decyzja Marcina: 6 bestsellerów + wyraźny przycisk). Przejście już było, ale jako mały przycisk z obramowaniem "Sprawdzam pozostałe" (12-13 px) pod trzema wypełnionymi "Dodaj do koszyka". Teraz wypełniony przycisk w kolorze marki "Zobacz wszystkie smaki" → `/zapiekanki/` (bez liczby "11", bo jeden smak bywa niedostępny).

Stan obecny: nagłówek "Poznaj 6 smaków uwielbianych przez naszych Klientów", widget `wd_products` (709318e) pokazuje 6 bestsellerów z kategorii Zapiekanki, bez przejścia dalej.

Kroki (propozycja, do akceptacji):
1. **[panel]** Zostawiam 6 bestsellerów i dodaję pod nimi wyraźny przycisk "Zobacz wszystkie 11 smaków →" do /sklep/.
2. Alternatywa: wszystkie 11 produktów (4 rzędy po 3, ostatni niepełny) albo karuzela.
3. Przy okazji rozmiar zdjęć 1:1 (pkt 1).

---

## 7. Testy, kopia zapasowa, wdrożenie (4 h)

Testy lokalne:
- Desktop (Chrome, Firefox) i mobile (Android Chrome, iOS Safari, emulacja + prawdziwy telefon przez adres lokalny): galeria (+N, licznik, peek, lightbox), sticky belka, belka z popupem (zamykanie do końca dnia, brak w checkoucie), odliczanie.
- Godzina graniczna: tryb testowy `?stn_now=2026-10-25T19:59` (dla administratora) na 19:59 i 20:01 w niedzielę i wtorek. Do tego przypadki ze świętami (24-26.12, przełom roku, Poniedziałek Wielkanocny, 1 i 3 maja), data wykluczona z panelu i zgodność kalendarza Iconic z komunikatem.
- Silnik `wc-ship-today-notice`: tabela przypadków (teraz → oczekiwany termin) uruchamiana skryptem WP-CLI, także dla starego trybu "wyślemy dziś", żeby rozbudowa nie zepsuła innych instalacji wtyczki.
- Strona z cache: nonce i AJAX usunięte, więc widget musi działać na stronie wyrenderowanej kilka dni wcześniej (lokalnie: zapisany HTML strony otwarty po zmianie `stn_now`).
- Pełna ścieżka: produkt → koszyk → checkout (data w Iconic + paczkomat w Apaczce) → thank you → mail w Mailpit → zamówienie w adminie (obie daty zapisane).
- Walidacja schema.

Wdrożenie:
1. Kopia zapasowa produkcji (hosting + Duplicator).
2. Wgranie wtyczek `wc-ship-today-notice` (nowa wersja) i `tezapiekanki`, aktywacja, pobranie świąt, ustawienia terminów w panelu.
3. Zmiany w panelu według listy niżej (w tej kolejności, żeby sklep cały czas działał).
4. Purge LiteSpeed Cache, Elementor → Narzędzia → "Regeneruj CSS i dane", zapis nagłówka i opcji Woodmarta (przegenerowanie CSS), regeneracja miniatur i WebP.
5. Na produkcji: zamówienie testowe uzgodnione z klientem (np. BLIK na małą kwotę, potem anulowanie) i sprawdzenie maila.
6. Sprawdzenie strony z cache: wejście jako niezalogowany, czy odliczanie i belka liczą się poprawnie na stronie z cache.
7. Zapis daty wdrożenia w tym pliku (start okresu porównawczego GA4, porównanie po 3-4 tygodniach).

---

## Pytania i decyzje dla klienta (poza blokerami z listy)

1. **Mrożone zapiekanki:** czy wysyłka mrożonych jest możliwa? W checkoucie jest checkbox "Proszę o wysyłkę zamrożonych zapiekanek" (nikt go dotąd nie zaznaczył), a strona mówi "nie są mrożone".
2. **Dostawa:** czy dla Paczkomatu i Kuriera dostawa jest zawsze następnego dnia po wysyłce?
3. **Kalendarz w checkoucie:** czy ma od razu zaznaczać najbliższy termin (ten sam co w komunikacie)? Czy klient dalej może wybrać późniejszą datę (do 30 dni)?
4. **Karty PDF** dla Wiejskiej i na ostro.
5. **Termin przydatności:** 6 czy 7 dni (Tradycyjna ma 7 w atrybucie, reszta 6, a ikona ma mówić "6 dni").
6. **Galeria:** ile miniatur widocznych przed "+N" (proponuję zdjęcie główne + 4).
7. **Sekcja smaków na stronie głównej:** 6 bestsellerów + przycisk czy wszystkie 11.
8. **Hero na stronie głównej:** zgoda na poprawę tekstu "dostarczymy je jutro" razem z meta description.
9. **FAQ w Google:** informacja, że rozszerzonych wyników FAQ nie będzie (ograniczenie Google), schema robimy dla poprawności.

## Proponowana kolejność

0 (kopia, GA4) → 1 (zdjęcia, galeria) → 3 (sticky, piny, karta; makro po otrzymaniu kart) → 4 → 6 → 5 → 2 (po otrzymaniu godzin granicznych i akceptacji tekstów) → 7.

Punkt 2 jest największy i zależy od blokerów, dlatego idzie na końcu. Do tego czasu reszta może być gotowa lokalnie.

## Lista zmian w panelu do powtórzenia na produkcji

| # | Gdzie | Zmiana |
|---|---|---|
| 1 | WooCommerce → Obrazki produktów | miniatura 600 px, 1:1, potem regeneracja **wszystkich** rozmiarów zdjęć produktów i Converter for Media (lokalnie zrobione 18.10) |
| 1 | Layouty 6473, 6868, strona 11, "podobne" w 6480 i 6533 | `img_size` → `woocommerce_thumbnail` (lokalnie zrobione) |
| 1 | Customizer → Dodatkowy CSS | usunięcie `.wd-product img {max-height…}` (lokalnie zrobione) |
| 1 | Layout 6480, widget galerii | "Bottom grid", kolumny 2/2/1, karuzela: tablet wyłączona, telefon włączona, bez paginacji, klasa CSS `tzp-gallery` (lokalnie zrobione; pliki w `backups/2026-10-18-gallery/po-zmianie/`) |
| 1 | Woodmart → Produkt | akcja zdjęcia `popup` (lokalnie zrobione) |
| 2 | Wtyczki | wgranie i aktywacja `wc-ship-today-notice` (nowa wersja) i `tezapiekanki` |
| 2 | WooCommerce → Ship Today Notice | dni wysyłki, godziny graniczne, przesunięcia, pobranie świąt, teksty |
| 2 | Iconic → ustawienia | next day cutoff 20:00, ewentualnie auto select first |
| 2 | Layouty 6480 i 6533 | widget Shortcode `[ship_today_notice class="tzp-delivery-box"]` pod krótkim opisem (lokalnie zrobione 18.10; wygląd w `style.css` motywu potomnego) |
| 2 | Layout 6460 (koszyk) | nowy układ (lokalnie zrobione 18.10): górna sekcja usunięta, komunikaty + tabela produktów przeniesione do lewej kolumny nad "Dobierz do zamówienia" (60%, klasa `tzp-cart-left`, widgety polecanych `tzp-cart-cross`), prawa kolumna (40%, `tzp-cart-right`): box z terminem nad "Wartość koszyka"; wiersz `tzp-cart-row`, padding górny 40. Przeniesienie na produkcję: odtworzyć w Elementorze albo import JSON layoutu. CSS w `style.css` motywu potomnego |
| 2 | Checkout: FCF + layouty 6470 i 6467 | nowy checkout "wariant B" (lokalnie zrobione 18.10): kroki Kontakt i dane / Dostawa (kafelki metod, paczkomat, termin) / Płatność, podsumowanie przyklejone z prawej, kupon nad "Łącznie", kwota na przycisku, checkbox faktury, na telefonie podsumowanie zwinięte w pasek. Kolejność pól FCF (e-mail i telefon na początku, firma i NIP na końcu). Procedura krok po kroku: CLAUDE.md, sekcja 9 "Wdrożenie checkoutu". Kod w motywie potomnym (`inc/checkout.php`, `js/checkout.js`, `style.css`), test: `.claude/tests/checkout-e2e.js` |
| 2 | WooCommerce → Ship Today Notice | tekst trybu "najbliższy termin" w dwóch liniach (pogrubiony termin + `<span class="tzp-countdown-line">Zostało: {countdown}</span>`) |
| 2 | Woodmart → Wydajność → JS | "Scripts never load": usunąć "Floating blocks" (lokalnie zrobione 19.10) |
| 2 | Wtyczki | wgranie i aktywacja `tezapiekanki` (belka; lokalnie zrobione 19.10) |
| 2 | Popupy Woodmarta + Dostosuj | nowy popup skryptem `tools/create-shipping-popup.php` albo ręcznie i wybór go w Wygląd → Dostosuj → "Belka o wysyłce" (lokalnie zrobione 19.10, popup 31262; wymaga `inc/woodmart-popups.php` z motywu potomnego). Procedura: CLAUDE.md, sekcja 9 "Wdrożenie belki" |
| 2 | Rank Math, strona 11 | meta description (lokalnie zrobione 19.10; hero, jeśli zgoda) |
| 3 | Layout 6480 | `[tzp_nutrition]` zamiast tekstu, "post-info" i tabeli Woodmarta, kontener pod blokiem ikon, `[tzp_flavor_pins]` pod formularzem zakupu (lokalnie zrobione 20.10, skrypt `tools/update-product-layout.php`) |
| 3 | Woodmart → Produkt | sticky add to cart: desktop i mobile włączone, wysokości domyślne (lokalnie zrobione 18.10; kod w motywie potomnym: `inc/product-sticky-cart.php`, `js/product-sticky-cart.js`, `style.css`) |
| 3 | Produkty (33 z kopiami) | makro, wagi, sery, PDF-y (skrypt `tools/import-product-cards.php`), opisy (`tools/unify-product-descriptions.php`), lokalnie zrobione 20.10. Procedura: CLAUDE.md, sekcja 9 "Wdrożenie kart produktów" |
| 5 | Wtyczka `tezapiekanki` 1.2 | dane Product/Offer dla Google (wysyłka, zwroty), sprawdzić koszt wysyłki w Flexible Shipping (lokalnie zrobione 20.10) |
| 4 | HTML Block + layouty 6480 i 6533 | blok ikon (lokalnie zrobione 19.10, skrypt `tools/create-product-info-block.php`) |
| 4 | Produkty, FCF | wkłady w opisach (skrypt `tools/unify-cooling-inserts.php`), checkbox "zamrożone" ukryty (lokalnie zrobione 19.10) |
| 5 | Nowa strona FAQ, stopka, strona 11, produkty | FAQ (lokalnie zrobione 19.10; skrypty `tools/convert-accordion-to-faq.php`, `tools/shorten-product-faq.php`, `tools/create-faq-page.php`; procedura: CLAUDE.md, sekcja 9 "Wdrożenie FAQ") |
| 6 | Widgety `sidebar-shop`, layout 6473 | filtry, wariant A (lokalnie zrobione 19.10) |
| 6 | Header Builder | usunięcie "ADD ANYTHING HERE…" (lokalnie zrobione 19.10) |
| 6 | Strona 11 | przycisk "Zobacz wszystkie smaki" (lokalnie zrobione 19.10) |
| 4, 6 | Procedura | CLAUDE.md, sekcja 9 "Wdrożenie Info praktyczne i porządków" |

## Uwagi poza zakresem (warto wiedzieć przed wdrożeniem)

- Raport "Zestawienie produktów" przeniesiony 18.10 do motywu potomnego `woodmart-child` (lokalnie). Na produkcji do wdrożenia według procedury w CLAUDE.md (sekcja 9), najlepiej razem z pierwszym wdrożeniem z tego zakresu.
- Silnik terminów może w przyszłości zasilić też ten raport (te same daty wysyłki), ale to nie wchodzi w obecny zakres.
- Kopia wtyczki w `.claude/doc/wc-ship-today-notice` (z `.git`) i druga w `Documents/tezapiekanki/` mają identyczny kod. Po przeniesieniu do `wp-content/plugins/` warto zostawić jedną roboczą kopię, żeby nie rozjechały się wersje.
