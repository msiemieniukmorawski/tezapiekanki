# tezapiekanki.pl - zakres prac (wersja zalecana, pełna)

Zaakceptowana przez klienta. Źródło: wycena z 12.10.2026.
Stawka 110 zł/h netto. Rozliczenie za faktycznie przepracowany czas, widełki = zależność od budowy motywu/Elementora.
Łącznie: 31,5-35,5 h.

**Stan na 20.10.2026:** punkty 0-6 zrobione w kopii lokalnej (opis: `podsumowanie-2026-10-19.md`, `podsumowanie-2026-10-20.md`, szczegóły techniczne w `CLAUDE.md`). W punkcie 7 testy lokalne zrobione 20.10 (366 sprawdzeń, wszystkie OK; raport i zrzuty: `local site 2/tezapiekanki/testy/2026-10-20/RAPORT.md`). Zostaje wdrożenie na staging (akceptacja tekstów przez klienta), potem na produkcję. Otwarte pytania do klienta: `pytania-do-klienta.md`.

## Kontekst dla pracy

- Sklep WooCommerce na WordPressie, motyw + Elementor.
- 11 produktów (zapiekanki) + zestawy (zestawy NIE są w tym zakresie, to faza 2).
- Cel: mniej porzuconych koszyków, więcej zamówień.
- GA4 działa poprawnie od 11.10.2026. Przed wdrożeniem zanotować punkt odniesienia (add to cart, begin checkout, purchase), po 3-4 tygodniach porównać.
- Jeśli któryś punkt ma przekroczyć górną granicę godzin, zatrzymać się i poinformować przed dalszą pracą.
- Czas realizacji liczy się od otrzymania materiałów od klienta: godziny graniczne zamówień, akceptacja tekstów, karty produktów (makro).

## Materiały potrzebne od klienta (blokery)

- [x] Godziny graniczne zamówień i dni wysyłki: nie blokują, klient ustawi je w panelu (WooCommerce → Ship Today Notice). Teraz ustawione: niedziela 20:00 -> wtorek, wtorek 20:00 -> czwartek
- [x] Karty produktów z poprawnymi wartościami odżywczymi dla wszystkich 11 produktów (dostarczone 20.10)
- [ ] Akceptacja tekstu belki i popupu (treść poniżej w pkt 2): klient zaakceptuje na stagingu
- [x] Decyzja, które filtry zostają: usunięte wszystkie (wariant A, 19.10)

---

## 0. Przygotowanie

- [x] Kopia zapasowa (pliki + baza) przed startem: praca na kopii produkcji z 18.10 (Duplicator), przed każdą zmianą kopia w `backups/`
- [x] Zapisać punkt odniesienia z GA4: add_to_cart, begin_checkout, purchase: zamknięte, testy GA/GTM były zrobione wcześniej (decyzja 20.10)
- [x] Staging / środowisko testowe: kopia lokalna (Local); do akceptacji klienta staging

---

## 1. Zdjęcia i galeria (3,5-5 h)

- [x] **Format 1:1** (0,5 h)
  - [x] Ustawić rozmiary miniatur Woo na 1:1 (crop)
  - [x] Zregenerować miniatury
  - [x] Sprawdzić listing sklepu i kartę produktu, czy wyświetla się jednolicie
- [x] **Galeria produktu: desktop** (część z 3-4,5 h)
  - [x] Grid zamiast slidera (obecny slider ma słabo widoczne strzałki)
  - [x] Duże zdjęcie główne, pod nim miniatury po 2 w rzędzie
  - [x] Przy dużej liczbie zdjęć ostatnia miniatura z nakładką "+2" (lub ile zostało): zamiast nakładki przycisk "Zobacz więcej zdjęć", który rozwija resztę (decyzja 18.10)
  - [x] Lightbox po kliknięciu
- [x] **Galeria produktu: mobile**
  - [x] Slider z przesuwaniem palcem
  - [x] Licznik "1/7"
  - [x] Wystający kawałek kolejnego zdjęcia (peek), żeby było widać, że da się przesunąć
  - [x] Lightbox po kliknięciu
- [x] Dodatkowo: ta sama galeria na kartach zestawów (19.10)

---

## 2. Komunikacja wysyłki i dostawy (8,5-9,5 h)

Cel: klient od razu wie, kiedy dostanie zapiekanki. Rzadka wysyłka (2x w tygodniu) ma być atutem, nie powodem porzucenia koszyka.

- [x] **Moduł terminu dostawy** (4-5 h) - na bazie własnej wtyczki, rozbudowanej pod sklep
  - [x] Wyliczanie najbliższej wysyłki i dostawy: "Zamów do niedzieli 20:00, dostarczymy we wtorek"
  - [x] Po minięciu godziny granicznej przeskok na kolejny termin
  - [x] Odliczanie czasu do zamówienia (countdown)
  - [x] Panel admina: godziny graniczne, dni wysyłki, dni wolne
  - [x] Święta pobierane automatycznie
  - [x] Wyświetlanie przy cenie produktu, w koszyku i w checkoucie
- [x] **Daty w koszyku, checkoucie, thank you page i mailu** (1,5 h)
  - [x] Data wysyłki i dostawy w koszyku
  - [x] Data wysyłki i dostawy w checkoucie
  - [x] Data wysyłki i dostawy na stronie podziękowania
  - [x] Data wysyłki i dostawy w mailu z potwierdzeniem zamówienia (zapis w meta zamówienia)
  - Wybór terminu w checkoucie robi nasz moduł (kafelki), Iconic zostaje na razie aktywny tylko w panelu (stare zamówienia). Zgoda klienta na rezygnację z Iconic: `zastapienie-iconic-plan.md`
- [x] **Belka u góry strony z popupem o farszu** (2,75 h)
  - [x] Tekst belki: "Wysyłamy tylko 2 razy w tygodniu. Sprawdź dlaczego ->"
  - [x] Zamykanie krzyżykiem "X", po zamknięciu nie pokazuje się do końca dnia (cookie / localStorage)
  - [x] Belka ukryta w checkoucie
  - [x] Wygląd mobile
  - [x] Popup z treścią (daty pobierane dynamicznie z modułu terminu dostawy; jeden, najbliższy termin zamiast dwóch, decyzja 19.10):

    > **Dlaczego wysyłamy tylko 2 razy w tygodniu?**
    >
    > Bo nasz farsz pieczarkowy nie powstaje w 5 minut na patelni. Gotujemy go powoli, przez kilka godzin, jak dobre ragu, aż osiągnie właściwą gęstość i smak. Bez półproduktów i bez skrótów.
    >
    > Gotujemy go na świeżo dwa razy w tygodniu, dokładnie pod zaplanowane wysyłki. Nic nie czeka w magazynie. Twoja zapiekanka powstaje pod Twoje zamówienie.
    >
    > Zamów do [niedzieli 20:00], a dostaniesz ją we wtorek.
    > Zamów do [wtorku 20:00], a dostaniesz ją w czwartek.
    >
    > Przyjeżdża w termoboxie z wkładami chłodzącymi. W lodówce wytrzyma 6 dni, możesz ją też zamrozić.
    >
    > [Wybieram zapiekanki] -> link do sklepu

- [x] **Meta description strony głównej** (0,25 h)
  - [x] Usunąć obietnicę "dostarczymy je jutro", wpisać zgodne z rzeczywistymi terminami (hero na stronie głównej bez zmian, czeka na zgodę klienta)

---

## 3. Karta produktu (9 h)

- [x] **Poprawne makro na 11 produktach** (2 h)
  - [x] Wpisać wartości odżywcze z przesłanych kart produktów, każdy z 11 produktów (także ukryte kopie do zestawów, 20.10)
  - [x] Sprawdzić, czy wyświetlają się poprawnie (tabela)
- [x] **Wyeksponowanie karty produktu i ujednolicenie opisów** (2 h)
  - [x] Obecnie tylko przycisk "Pobierz kartę produktu" + tabela niżej na stronie - wyeksponować (sekcja "Wartość odżywcza" z przyciskiem PDF pod ikonami, karty PDF przy wszystkich 11 smakach)
  - [x] Ujednolicić opisy (np. liczba serów w opisie vs w składzie)
- [x] **Sticky belka "Dodaj do koszyka" na dole ekranu** (3 h)
  - [x] Pojawia się, gdy główny przycisk zniknie z widoku (natywny pasek Woodmarta)
  - [x] Zawiera: zdjęcie, nazwę, cenę, ilość, przycisk (na telefonie tylko ilość i przycisk, decyzja 18.10)
  - [x] Priorytet: mobile
- [x] **Piny do innych smaków** (2 h)
  - [x] Klikalne "piny" z nazwami zapiekanek na karcie produktu
  - [x] Szybkie przejście między smakami bez wracania do sklepu

---

## 4. Info praktyczne (2,5 h)

- [x] **Blok ikon przy produkcie** (2 h)
  - [x] "6 dni od dostawy"
  - [x] "trzymaj w lodówce"
  - [x] "można zamrozić zaraz po odbiorze"
  - [x] "termobox z wkładami chłodzącymi"
- [x] **Korekta sprzecznych treści** (0,5 h)
  - [x] "nie wysyłamy mrożonych" obok "możesz zamrozić" - uspójnić
  - [x] Dwie różne nazwy wkładów chłodzących - ujednolicić

---

## 5. FAQ (2-3 h)

- [x] **Osobna zakładka FAQ + skrócenie na produktach** (1 h)
  - [x] Pełne FAQ w osobnej zakładce/stronie (`/faq/`)
  - [x] Na stronach produktów tylko 2-3 kluczowe pytania lub link do pełnego FAQ (4 pytania + link)
- [x] **Usunięcie zduplikowanych sekcji FAQ ze strony głównej** (0,25 h)
- [ ] **Dane strukturalne (schema)** (0,75-1,75 h)
  - [x] Sprawdzić schema FAQ (FAQPage)
  - [x] Sprawdzić schema produktów (Product, Offer)
  - [ ] Poprawić, zwalidować (Rich Results Test / Schema Validator): poprawione i sprawdzone lokalnie, Rich Results Test po wdrożeniu na staging (Google nie otworzy kopii lokalnej)

---

## 6. Filtry i porządki (2-2,5 h)

- [x] **Filtry** (1,25-1,75 h)
  - [x] Przy 11 produktach filtry przeszkadzają, część nie dotyczy oferty
  - [x] Usunąć albo zostawić 1-2 sensowne (np. mięsne / wege / ostre) - wg decyzji klienta (usunięte wszystkie)
- [x] **Pozostałości szablonu w kodzie** (0,25 h)
  - [x] Usunąć "ADD ANYTHING HERE OR JUST REMOVE IT..." (niewidoczne na stronie, ale w kodzie)
- [x] **Sekcja "11 smaków" na stronie głównej** (0,5 h)
  - [x] Pokazuje 6 produktów bez jasnego przejścia do pełnej listy - dodać przejście / pokazać wszystkie (przycisk "Zobacz wszystkie smaki")

---

## 7. Testy, kopia zapasowa, wdrożenie (4 h)

- [x] Testy desktop + mobile (galeria, sticky belka, belka z popupem, countdown): lokalnie 20.10, zrzuty w `testy/2026-10-20/`
- [x] Testy przejścia przez godzinę graniczną (zmiana terminu): silnik 18/18, strona bez przeładowania 8/8 (20.10)
- [x] Test pełnej ścieżki zamówienia: produkt -> koszyk -> checkout -> thank you -> mail: 20/20 ze zrzutami i mailami (20.10), także z wyłączonym Iconic
- [ ] Walidacja schema: lokalnie 35/35 (20.10), Rich Results Test Google po wdrożeniu na staging
- [ ] Test płatności Paynow na stagingu albo produkcji (lokalnie niemożliwy)
- [ ] Kopia zapasowa przed wdrożeniem na produkcję
- [ ] Wdrożenie (najpierw staging do akceptacji klienta, potem produkcja; procedury: `CLAUDE.md`, sekcja 9)
- [ ] Sprawdzenie po wdrożeniu (cache, Elementor regenerate CSS)

---

## Podsumowanie godzin

| # | Zakres | Godz. |
|---|--------|-------|
| 1 | Zdjęcia i galeria | 3,5-5 |
| 2 | Komunikacja wysyłki i dostawy | 8,5-9,5 |
| 3 | Karta produktu | 9 |
| 4 | Info praktyczne | 2,5 |
| 5 | FAQ | 2-3 |
| 6 | Filtry i porządki | 2-2,5 |
| 7 | Testy, kopia zapasowa, wdrożenie | 4 |
| | **RAZEM** | **31,5-35,5 h** |

## Poza zakresem (nie ruszać w tym etapie)

- Opcja "zapiekanka w warstwach" (9,5 h) - osobna decyzja klienta
- Faza 2: zestawy "Więcej TE" (TE5 / TE9 / TE12) - po pierwszym etapie
- Opinie klientów na stronach produktów - do rozważenia później
- Zrobione dodatkowo, poza wyceną: wybór terminu w checkoucie zamiast kalendarza Iconic (około +3 h, czeka na zgodę klienta), nowy układ koszyka i checkoutu, naprawa popupów Woodmarta, FAQ jako własny widget, poprawki paska zestawów i komunikatu "[min]"
