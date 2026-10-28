# Zastąpienie Iconic Delivery Slots własnym wyborem terminu

Status: propozycja z 18.10.2026, **wymaga akceptacji klienta** (poza zakresem z 12.10). Jeśli klient się nie zgodzi, obowiązuje wariant z integracją Iconic opisany w `tezapiekanki-plan-wykonania.md`, pkt 2.

Ustalenia (18.10):
- **bez migracji** starych zamówień,
- przełącznik w naszym module: gdy Iconic i nasz moduł są aktywne, nasz moduł wyłącza Iconic w koszyku i checkoucie, działa tylko nasz wybór terminu,
- po około miesiącu Iconic jest dezaktywowany.

**Status 18.10: zrobione lokalnie** (`wc-ship-today-notice` 2.1.0, gałąź `feature/next-slot-engine`, bez commita):
- moduł włączony, przełącznik Iconic włączony, shortcode w layoucie 6470 podmieniony, snippet 30864 wyłączony,
- testy: zamówienie przez HTTP przechodzi (daty w zamówieniu, na thank you page i w obu mailach w Mailpit, brak danych Iconic), walidacja odrzuca brak daty, dzień bez wysyłki i datę z przeszłości, test kontrolny bez przełącznika blokuje zamówienie błędem Iconic, kolumna, filtr, sortowanie i zmiana daty w panelu działają, raport dla starych zamówień identyczny ze wzorcem,
- zmiana względem planu: CSS kalendarza Iconic w Dodatkowym CSS zostaje do dezaktywacji Iconic (dotyczy tylko jego elementów, a ułatwia wycofanie),
- do decyzji: nagłówek w layoucie 6470 nad kafelkami brzmi "Wybierz datę wysyłki zamówienia", a kafelki pokazują głównie datę dostawy. Propozycja: "Wybierz termin dostawy" i krótsza nazwa pola albo ukrycie legendy.

Dotyczy punktu z zakresu: "Daty w koszyku, checkoucie, thank you page i mailu (zapis w meta zamówienia)". Przy własnym module ten punkt staje się jego częścią, a integracja z Iconic odpada.

---

## 1. Dlaczego

1. **Jedno źródło reguł.** Terminy w checkoucie to dokładnie terminy z silnika `wc-ship-today-notice` (te same co w belce, przy cenie i w popupie). Nie ma synchronizacji dwóch konfiguracji ani ryzyka sprzecznych dat.
2. **Wydajność** (po dezaktywacji Iconic). Iconic zajmuje 0,6-1 s przy każdym żądaniu PHP (zmierzone lokalnie, `Iconic_WDS::on_wp_loaded`), także w koszyku, checkoucie i AJAX-ie, gdzie cache LiteSpeed nie działa. W miesiącu przejściowym Iconic dalej się ładuje, więc zysk pojawi się dopiero po jego wyłączeniu.
3. **Lepsze UX.** Przy 2 dniach wysyłki w tygodniu kalendarz miesięczny pokazuje głównie dni nieklikalne. Kafelki z najbliższymi terminami są czytelniejsze, zwłaszcza na telefonie.
4. **Koniec łatek.** Znika snippet "KALENDARZ TEST" (podmienia wewnętrzną funkcję jQuery UI, żeby kalendarz nie znikał), blok CSS kalendarza w Customizerze i tłumaczenie Iconic w Loco.
5. **Kontrola przy składaniu zamówienia.** Termin jest sprawdzany w chwili złożenia zamówienia według aktualnej godziny granicznej.
6. Koniec opłat za odnawianie licencji Iconic (licencja jest obecnie ważna).

## 2. Co dziś robi Iconic (inwentarz)

| Funkcja Iconic | Używana? | W nowym module |
|---|---|---|
| Kalendarz w checkoucie (shortcode `[iconic-wds-fields]` w layoucie CHECKOUT MAIN 6470), dni pn/śr, do 30 dni, pole obowiązkowe | tak | kafelki z najbliższymi terminami |
| Walidacja "Prosimy o wybranie preferowanej daty wysyłki." | tak | walidacja + kontrola godziny granicznej |
| Meta zamówienia `jckwds_date`, `jckwds_date_ymd`, `jckwds_timestamp` i inne (589 zamówień od 13.05.2026) | tak | nowe klucze `_wc_stn_*` dla nowych zamówień; stare zostają, bez migracji |
| Wiersz z datą w podsumowaniu zamówienia (thank you, maile, Moje konto) | tak | tak |
| Kolumna z datą na liście zamówień, sortowanie, filtr po dacie | tak | tak (dla nowych zamówień) |
| Edycja i usuwanie daty w zamówieniu w panelu | tak | edycja daty z notatką w zamówieniu |
| WooCommerce → Deliveries (lista nadchodzących dostaw i rezerwacji) | do sprawdzenia z klientem | filtr listy zamówień po dacie wysyłki + istniejący raport CSV |
| Tabela rezerwacji `wpiq_jckwds` (616 wierszy) i limity zamówień na dzień | limity puste, nieużywane | nie odtwarzamy |
| Sloty godzinowe, opłaty, przypomnienia mailowe, zmiana daty przez klienta, reguły per produkt | nieużywane | nie odtwarzamy |

Kod i treści zależne od Iconic:
- raport "Zestawienie produktów" (`themes/woodmart-child/inc/product-summary.php`, czyta `jckwds_date_ymd`),
- snippet WPCode 30864 "KALENDARZ TEST" (JS kalendarza),
- snippet WPCode 30284 "DS" (ukrywa wiersz Iconic na liście wtyczek i wszystkie powiadomienia o aktualizacjach),
- Dodatkowy CSS (post 31245 dla `woodmart-child`): reguły `.iconic-wds-*`, `.wds-*`, `#jckwds-delivery-date-wrapper`,
- tłumaczenie `languages/loco/plugins/jckwds-pl_PL.*`.

Poza tym z danych Iconic nie korzysta nic (sprawdzone: wtyczki, motywy, snippety, Elementor, opcje).

---

## 3. Docelowy mechanizm

### 3.1 Gdzie kod

- **`wc-ship-today-notice`**: nowy moduł "Wybór terminu w checkoucie", **domyślnie wyłączony** (inne sklepy z tą wtyczką nic nie zauważą). Pole, walidacja, zapis, wyświetlanie, panel zamówienia, lista zamówień i przełącznik wyłączający Iconic. Wszystko generyczne.
- **Motyw potomny**: raport czyta nowe klucze, a dla zamówień sprzed przełączenia stare.
- **Panel/Elementor**: podmiana shortcode w layoucie 6470, sprzątanie snippetów i CSS.

### 3.2 Dane w zamówieniu (tylko nowe zamówienia)

| Klucz | Wartość |
|---|---|
| `_wc_stn_ship_date` | data wysyłki `Y-m-d` (wybrana przez klienta) |
| `_wc_stn_delivery_date` | planowana dostawa `Y-m-d` (pusta, gdy dostawa wyłączona w ustawieniach) |
| `_wc_stn_cutoff` | znacznik czasu godziny granicznej wybranego terminu (do wglądu przy reklamacjach) |

Zapis wyłącznie przez `$order->update_meta_data()` + `save()` (HPOS). Stare `jckwds_*` zostają nietknięte. Po dezaktywacji Iconic stara data nadal jest widoczna w zamówieniu w polach własnych (`jckwds_date`, bez podkreślnika na początku, więc WooCommerce pokazuje ją w bloku "Pola własne").

### 3.3 Checkout

- **Kafelki** z najbliższymi N terminami (ustawienie, domyślnie 6, czyli około 3 tygodni). Najbliższy termin zaznaczony domyślnie (ustawienie).
- **Treść kafelka** (do decyzji klienta, rekomendacja: dostawa na pierwszym planie): duże "wt 27.10", pod spodem małe "wysyłka pn 26.10". Bez skonfigurowanej dostawy kafelek pokazuje datę wysyłki.
- **Markup:** `fieldset` + `legend` "Termin dostawy", radio `name="wc_stn_ship_date" value="Y-m-d"`, obsługa klawiatury, siatka 3 kolumny na desktopie i 2 na mobile, zaznaczony kafelek w kolorze marki (zmienne CSS, żeby inne sklepy mogły zmienić kolory).
- **Miejsce:** shortcode `[ship_today_checkout]` w layoucie 6470 w miejsce `[iconic-wds-fields]` (wewnątrz `form.checkout`). Dla innych sklepów dodatkowo ustawienie "wstaw automatycznie" przez hook checkoutu.
- **Cache i upływ czasu:** kafelki dostają ten sam JSON harmonogramu co widget odliczania. Gdy godzina graniczna zaznaczonego terminu minie na otwartej stronie, kafelek znika, zaznaczenie przechodzi na następny i pojawia się komunikat: "Termin pn 26.10 właśnie się zamknął, zaznaczyliśmy śr 28.10."
- **Podsumowanie zamówienia:** zmiana kafelka wywołuje `update_checkout`, a w tabeli podsumowania (hook `woocommerce_review_order_after_shipping`, data z `post_data`) jest wiersz "Wysyłka pn 26.10, dostawa wt 27.10".
- **Walidacja na serwerze** (`woocommerce_after_checkout_validation`): data jest wymagana i musi być wśród terminów aktualnych w chwili złożenia zamówienia. Jeśli godzina graniczna minęła, wyświetlamy błąd z prośbą o wybór innego terminu, bez cichej podmiany daty.
- **Zapis** na `woocommerce_checkout_create_order` (klucze z 3.2).

### 3.4 Po złożeniu zamówienia

- **Thank you, maile (klient i admin), Moje konto:** wiersze "Data wysyłki" i "Planowana dostawa" w `woocommerce_get_order_item_totals`.
- **Zamówienie w panelu:** blok w kolumnie wysyłki (`woocommerce_admin_order_data_after_shipping_address`) z datami i polem zmiany daty wysyłki. Dozwolona dowolna data (np. zamówienie telefoniczne), dostawa przeliczana automatycznie, notatka w zamówieniu "Zmieniono datę wysyłki z … na … (użytkownik)". Zapis na `woocommerce_process_shop_order_meta` (działa przy HPOS).
- **Lista zamówień (HPOS):** kolumna "Wysyłka" (data wysyłki i dostawy), sortowanie oraz filtr "Wysyłka w dniu…" z listą najbliższych dat. Hooki: `manage_woocommerce_page_wc-orders_columns`, `manage_woocommerce_page_wc-orders_custom_column`, `manage_woocommerce_page_wc-orders_sortable_columns`, `woocommerce_order_list_table_prepare_items_query_args`, `woocommerce_order_list_table_restrict_manage_orders`. W miesiącu przejściowym obok zostaje kolumna Iconic ze starymi zamówieniami.
- **Strona Deliveries:** zastępuje ją filtr listy zamówień + raport CSV. Jeśli klient z niej korzysta, dorobię prostą listę "nadchodzące wysyłki" (do wyceny osobno).
- **Listy pakowania WebToffee:** data wysyłki i dostawy przez `wf_pklist_add_additional_info`, jeśli klient drukuje listy pakowania (pytanie do klienta).

### 3.5 Współistnienie z Iconic (miesiąc przejściowy)

Ustawienie w module: **"Wyłącz Iconic Delivery Slots w koszyku i checkoucie"**. Jest widoczne tylko wtedy, gdy Iconic jest aktywny, i działa tylko przy włączonym module wyboru terminu.

Gdy jest zaznaczone, nasz moduł na `wp_loaded` (priorytet 99, po rejestracji hooków Iconic, która odbywa się na `wp_loaded` 10; obejmuje też AJAX checkoutu):
- zwraca `false` w filtrze `iconic_wds_delivery_slots_allowed`, co wyłącza pola, walidację i fragmenty Iconic,
- zdejmuje `Iconic_WDS\Checkout::checkout_fields` z `woocommerce_checkout_fields`. To konieczne, bo Iconic dodaje wymagane pole `jckwds` bez sprawdzania powyższego filtra, a WooCommerce zgłosiłby wtedy "pole wymagane",
- zdejmuje `classic_checkout_process` (`woocommerce_checkout_process`), `catch_shipping` (`woocommerce_after_checkout_validation`) i `Order::update_order_meta` (`woocommerce_checkout_update_order_meta`), żeby Iconic niczego nie walidował ani nie zapisywał przy nowych zamówieniach,
- podmienia shortcode `[iconic-wds-fields]` na pusty (gdyby został gdzieś w layoucie),
- wyłącza skrypty i style Iconic w koszyku i checkoucie (kalendarz jQuery UI).

Nazwy klas i hooków są sprawdzone w Iconic 2.13.0. Przy aktualizacji Iconic w miesiącu przejściowym trzeba je zweryfikować, a test z pkt 5 to wyłapie.

Co zostaje w miesiącu przejściowym:
- Iconic w panelu: kolumna, filtr i wiersz w zamówieniu dla **starych** zamówień, ustawienia, strona Deliveries,
- **nowe zamówienia nie pojawią się w Iconic** (ani w jego kolumnie, ani na stronie Deliveries), bo Iconic nie dostaje już danych. Klient musi to wiedzieć od dnia przełączenia: nowe zamówienia widać w naszej kolumnie, filtrze i raporcie,
- raport "Zestawienie produktów" czyta oba klucze, więc zamówienia w realizacji sprzed i po przełączeniu liczą się razem.

Wycofanie: odznaczenie przełącznika i przywrócenie shortcode `[iconic-wds-fields]` w layoucie 6470. Iconic od razu działa jak wcześniej.

Po miesiącu: dezaktywacja Iconic. Przełącznik przestaje mieć znaczenie (znika z ustawień), nic więcej nie trzeba zmieniać.

---

## 4. Kolejność prac (lokalnie)

1. Moduł checkout: kafelki, JS, walidacja, zapis, wiersz w podsumowaniu.
2. Wyświetlanie po zamówieniu, blok w panelu zamówienia z edycją daty, kolumna, sortowanie i filtr na liście zamówień.
3. Przełącznik wyłączający Iconic w koszyku i checkoucie (3.5).
4. Raport w motywie potomnym czytający oba klucze, porównanie CSV starych zamówień ze wzorcem z backupu z 18.10.
5. Przełączenie lokalnie: włączenie modułu i przełącznika, shortcode w layoucie 6470, wyłączenie snippetu 30864, usunięcie CSS Iconic. Iconic zostaje aktywny.
6. Testy (pkt 5).

## 5. Testy

- Tabela przypadków silnika (jest) + nowe przypadki walidacji: brak daty, data spoza terminów, data po godzinie granicznej, data wykluczona, ręcznie podrobiony POST.
- **Iconic aktywny + przełącznik włączony:** w checkoucie nie ma pola ani skryptów Iconic, zamówienie przechodzi bez błędu "wybierz datę", nowe zamówienie nie ma żadnych `jckwds_*` i nie trafia do tabeli `wpiq_jckwds`. Stare zamówienia dalej widać w kolumnie Iconic i na stronie Deliveries.
- **Przełącznik wyłączony:** Iconic działa jak wcześniej (kontrola procedury wycofania).
- Pełna ścieżka lokalnie: produkt → koszyk → kafelki → paczkomat Apaczki → płatność. Paynow lokalnie płatności nie zrealizuje, więc do testu chwilowo włączam lokalnie przelew bankowy i wyłączam go po teście.
- Maile w Mailpit (blokada maili działa lokalnie).
- `?stn_now=` na 19:59:30 na stronie checkoutu: kafelek znika, pojawia się komunikat, a wysłanie formularza ze starą datą daje błąd.
- Panel: kolumna, sortowanie, filtr, edycja daty z notatką w zamówieniu.
- Raport: CSV starych zamówień identyczny ze wzorcem, nowe zamówienie uwzględnione, mieszanka starych i nowych w jednym dniu wysyłki liczona razem.
- Mobile (2 kolumny kafelków), obsługa klawiaturą, czytnik ekranu (legend, aria).
- Wydajność: czas odpowiedzi koszyka i checkoutu z aktywnym Iconic i po jego dezaktywacji.

## 6. Wdrożenie na produkcję

1. Kopia zapasowa (pliki + baza).
2. Wgranie `wc-ship-today-notice` z modułem checkout **wyłączonym**. Klienci nic nie zauważają.
3. Wgranie motywu potomnego z raportem czytającym oba klucze (jeśli motyw potomny nie jest jeszcze wdrożony, najpierw procedura z CLAUDE.md, sekcja 9).
4. **Okno przełączenia, około 10 minut**, np. po godzinie granicznej we wtorek albo w niedzielę wieczorem, gdy jest mało zamówień:
   - włączenie modułu checkout i przełącznika "Wyłącz Iconic w koszyku i checkoucie",
   - podmiana shortcode w layoucie 6470,
   - wyłączenie snippetu 30864 (CSS Iconic zostaje do jego dezaktywacji).
5. Purge LiteSpeed, zamówienie testowe uzgodnione z klientem (BLIK na małą kwotę, potem anulowanie), sprawdzenie maila, kolumny i raportu.
6. Informacja dla klienta: od dziś nowe zamówienia są w kolumnie "Wysyłka", filtrze i raporcie, a nie w Iconic.
7. Kontrola przez pierwsze dni: raport na najbliższy dzień wysyłki vs zamówienia.
8. **Po około miesiącu:** dezaktywacja Iconic, usunięcie CSS Iconic z Dodatkowego CSS (reguły `.iconic-wds-*`, `.wds-*`, `#jckwds-delivery-date-wrapper`), z snippetu 30284 usunięcie ukrywania wiersza Iconic, wyłączenie auto-odnawiania licencji (klient). Wtyczkę i tabelę `wpiq_jckwds` można usunąć później albo zostawić jako archiwum.

**Wycofanie (około 5 minut):** odznaczenie przełącznika i przywrócenie shortcode `[iconic-wds-fields]`. Zamówienia złożone w międzyczasie mają tylko `_wc_stn_*`, więc raport je widzi, a Iconic nie. To jedyna niedogodność.

## 7. Szacunek

| Zakres | Godz. |
|---|---|
| Pole w checkoucie: kafelki, JS, walidacja, zapis, wiersz w podsumowaniu | 2-2,5 |
| Wyświetlanie po zamówieniu + blok w panelu zamówienia z edycją | 1 |
| Kolumna, sortowanie i filtr na liście zamówień (HPOS) | 0,75 |
| Przełącznik wyłączający Iconic + raport czytający oba klucze | 0,5-0,75 |
| Przełączenie lokalne, sprzątanie, testy | 1 |
| **Razem** | **5,25-6** |

Odpada z obecnego planu: integracja z Iconic (około 1-1,5 h) i część punktu "daty w checkoucie, thank you, mailu" (około 1 h). **Netto około +3 h** ponad wycenę. Wdrożenie na produkcję (około 0,5 h) i późniejsza dezaktywacja Iconic (kilka minut) mieszczą się w pkt 7 wyceny.

## 8. Pytania do klienta

1. Zgoda na zakres i około 3 h ponad wycenę oraz rezygnacja z Iconic po miesiącu (i z odnawiania licencji).
2. Czy ktoś korzysta z WooCommerce → Deliveries w Iconic? Od dnia przełączenia nowe zamówienia już się tam nie pojawią. Proponujemy w zamian filtr listy zamówień i raport CSV.
3. Co jest główną informacją na kafelku: data dostawy czy wysyłki? (rekomendacja: dostawa)
4. Ile terminów pokazać (rekomendacja: 6) i czy najbliższy ma być zaznaczony domyślnie (rekomendacja: tak).
5. Czy drukujecie listy pakowania WebToffee? Jeśli tak, dodamy na nich datę wysyłki.
6. Nazwa pola: "Termin dostawy" zamiast obecnego "Preferowana data wysyłki"?
