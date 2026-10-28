# Podsumowanie prac: 20.10.2026

Wszystko zrobione i sprawdzone **w kopii lokalnej** (tezapiekanki.local). Na produkcji nic się jeszcze nie zmieniło. Procedura wdrożenia krok po kroku jest w `CLAUDE.md`, sekcja 9 "Wdrożenie kart produktów".

---

## 1. Karty produktów i wartości odżywcze (pkt 3 zakresu)

**Karty PDF**
- PDF od klienta z 11 kartami pocięty na 11 plików (`karta-produktu-zapiekanka-salami.pdf` itd.), wgrany do mediów i podpięty pod swój smak.
- Kartę mają teraz wszystkie smaki, także Wiejska i na ostro, które jej wcześniej nie miały.
- Stare karty z sierpnia zostały w mediach, ale żaden produkt ich już nie używa.

**Makro i wagi z kart**
- Wpisane na wszystkich smakach, także w ukrytych kopiach do zestawów.
- Wcześniej na stronie były inne liczby niż na kartach. Przykład: Salami miało 261 g i 301,7 kcal, a karta mówi 349 g i 211 kcal na 100 g. Zgodna była tylko Tradycyjna.
- Doszły "w tym kwasy tłuszczowe nasycone" i "w tym cukry". Są na kartach i należą do tabeli wartości odżywczych.
- "Zapiekanka na ostro" nie miała żadnych danych, teraz ma wagę, makro, sery, mięso i termin.

**Nowa sekcja "Wartość odżywcza" na karcie produktu** (pod ikonami "6 dni świeżości" itd.)

| Było | Jest |
|---|---|
| Mały link "Pobierz kartę produktu" na końcu kolumny | Wyraźny przycisk "Pobierz kartę produktu (PDF)" z dopiskiem "pełny skład, alergeny i wartości odżywcze" |
| Waga całej zapiekanki w tabeli "na 100 g" | Waga osobno: "Waga zapiekanki: 349 g" |
| Wartości wielkimi literami: "1.327,3 KJ / 301,7 KCL", "11,2G" | Tabela jak na etykiecie: "880 kJ / 211 kcal", "8,5 g", wiersze "w tym" wcięte |

Klient zmienia wartości jak dotąd, w atrybutach produktu, a kartę w polu "kartaproduktowa".

**Opisy ujednolicone z kartami**
- Sery: według kart każda zapiekanka ma goudę, mozzarellę i parmezan. Krótkie opisy już tak mówiły, a długie mówiły o "dwóch serach". Poprawione we wszystkich.
- Rok receptury farszu: w Szwedzkiej było "1886", w Zapiekance z Szynką "1887", poprawione na 1987 jak wszędzie.
- "Spinata Piccante" → "Spianata Piccante" (Salami).
- Na każdej karcie produktu były dwa nagłówki H1 (nazwa produktu i pierwszy nagłówek opisu), teraz jest jeden. To poprawka SEO, pierwszy nagłówek opisu wygląda teraz jak pozostałe nagłówki sekcji.

## 2. Piny smaków (pkt 3 zakresu)

- Pod ceną i przyciskiem "Dodaj do koszyka" "Smak:" i 11 klikalnych nazw zapiekanek (najpierw były pod nazwą produktu, przeniesione 20.10). Szybkie przejście do innego smaku bez wracania do sklepu.
- Bieżący smak w kolorze marki. Niedostępny smak ("na dziko") ma przerywaną ramkę, ale da się go otworzyć.
- Na telefonie piny są w jednym przewijanym wierszu, ustawionym od razu na bieżącym smaku.
- Kolejność jak w sklepie. Nowy smak pojawi się sam.

## 3. Dane produktu dla Google (pkt 5 zakresu)

- Koszt i czas dostawy: 14,99 zł, wysyłka w 1-6 dni (zależnie od dnia zamówienia) i dostawa następnego dnia. Czas liczy się z ustawień terminów wysyłki, więc po zmianie godzin granicznych zmieni się sam.
- Zwroty: odstąpienie od umowy nie przysługuje po nadaniu paczki, z linkiem do strony "Zwroty i reklamacje".
- Nazwa produktu bez nazwy sklepu, prawdziwa waga (wcześniej "na ostro" miała 1 kg z pola wysyłkowego), czytelne nazwy wartości odżywczych.
- Sprawdzenie w Rich Results Test Google jest możliwe dopiero po wdrożeniu, bo Google nie otworzy kopii lokalnej.

## 4. Przy okazji

- Karta zestawu: komunikat "Wybierz [min] zapiekanek przed dodaniem zestawu do koszyka" pokazuje teraz liczbę: "Wybierz 9 zapiekanek…" i "Wybierz 12 zapiekanek…".
- Maile do klientów: w stopce było "skontaktuj się z nami pod adresem noreply@zapiekankiskalbmierz.pl" (adres, na który nie da się odpisać). Teraz jest sklep@tezapiekanki.pl i na ten adres trafia odpowiedź klienta. Nadawca bez zmian.
- Logo: w nagłówku i stopce wersja "TE Zapiekanki Skalbmierskie od 1987" z materiałów klienta (wcześniej "est 1987"), tak jak w mailach.
- Adres w mailach i na stronie podziękowania: bez pustych "Nazwa firmy:" i "Numer NIP:", gdy klient nie zamawia na firmę.
- Tablet (768-1024 px): na kartach zapiekanek i zestawów kolumna z nazwą, ceną i opisem nie miała bocznego odstępu, tekst dotykał krawędzi ekranu. Teraz ma 20 px z każdej strony. Desktop i telefon bez zmian.

## 5. Testy

- Nowy test karty produktu: 112/112 (wszystkie 11 smaków: tabela zgodna z kartą, PDF się otwiera, piny, dane dla Google, opisy; desktop i telefon).
- Wcześniejsze testy bez zmian: galeria 16/16, pasek "Dodaj do koszyka" 12/12, listingi 8/8, FAQ 25/25.
- Checkout się nie zmienił, więc jego testu (tworzy zamówienia) nie puszczałem.

---

## 6. Do wyjaśnienia z klientem

Wszystkie otwarte pytania (także z 19.10 i z planu zastąpienia kalendarza Iconic) są zebrane w jednym pliku: `pytania-do-klienta.md`. Nowe z 20.10:

**Karty produktów.** Na stronę trafiło to, co jest na kartach. Poniższe rzeczy dotyczą samych kart, warto je sprawdzić z osobą, która je przygotowała:

1. **Zapiekanka na dziko:** energia na karcie (928 kJ / 222 kcal) nie zgadza się z makro. Z tłuszczu, węglowodanów i białka wychodzi około 865 kJ / 206 kcal. Pozostałe 10 kart się zgadza.
2. **Alergeny:**
   - parmezan zawiera "lizozym z białka jaj", a jaja są tylko w "Może zawierać",
   - boczek z dzika zawiera gorczycę, a gorczyca też jest tylko w "Może zawierać".
   Składnik, który jest w przepisie, powinien być w "Zawiera".
3. **Składniki złożone bez składu:** kebab z kurczaka, salami, chorizo, ogórki szwedzkie, cebulka prażona.
4. **Sosy** z opisów (np. remulada, sos czosnkowo-jogurtowy) nie mają na kartach składu ani alergenów.
5. **Wartości odżywcze są identyczne jak na kartach z sierpnia**, choć skład się zmienił (doszedł parmezan, inne proporcje farszu). Czy makro zostało przeliczone?
6. **Literówki na kartach:** "Z SZYNKA", "Białko 9.7 g" (kropka), karta Szwedzkiej ma tytuł "KARTA INFORMACYJNA - ZAPIEKANKA OGÓREK SZWEDZKI" (pozostałe "KARTA INFORMACYJNA PRODUKTU - …").
7. **Termin przydatności:** karty go nie podają. Blok ikon i opisy mówią 6 dni, Tradycyjna ma w danych 7 dni (pytanie z 19.10 dalej otwarte).

**Koszt wysyłki a waga paczki.** W Flexible Shipping: do 4 kg 14,99 zł, od 5 kg 9,99 zł, od 300 zł za darmo. Wagę ma tylko "na ostro" (1 kg) i farsz, więc tańsza wysyłka działa tylko przy 5 i więcej sztukach "na ostro". Pytanie, jak ma być, jest w `pytania-do-klienta.md` (punkt 5).

## 7. Testy (pkt 7 zakresu)

Pełne testy lokalne: 366 sprawdzeń, wszystkie OK, ze zrzutami ekranu i maili. Raport: `local site 2/tezapiekanki/testy/2026-10-20/RAPORT.md`. W tym pełna ścieżka zamówienia z mailami, przejście przez godzinę graniczną i sprawdzenie, że nasz wybór terminu działa bez Iconic.

## 8. Co zostało z zakresu

- **Pkt 0:** zamknięty (testy GA/GTM były zrobione wcześniej).
- **Pkt 7:** wdrożenie na staging (tam klient akceptuje teksty), potem produkcja, test płatności Paynow, Rich Results Test, sprawdzenie po wdrożeniu.
- Reszta zakresu jest zrobiona lokalnie. Odpowiedzi klienta: `pytania-do-klienta.md`.

---

## Pliki i miejsca zmian (dla programisty)

- Wtyczka `wp-content/plugins/tezapiekanki/` (wersja 1.2):
  - `includes/class-product-card.php` (`[tzp_nutrition]`, `[tzp_product_card]`), `includes/class-flavor-pins.php` (`[tzp_flavor_pins]`), `includes/class-product-schema.php` (Rank Math Product/Offer), `includes/class-bundles.php` (komunikat zestawu), `includes/class-emails.php` (kontakt w mailach, puste pola adresu), `assets/css/product.css`, `assets/js/flavor-pins.js`,
  - `tools/import-product-cards.php` (PDF-y, makro, sery, pole karty), `tools/unify-product-descriptions.php` (opisy), `tools/update-product-layout.php` (layout 6480), `tools/karty-produktow/` (11 PDF-ów).
- Panel i Elementor: layout 6480, padding kontenera bed0dcc na tablecie w layoutach 6480 i 6533 (0/20/0/20), atrybuty (2 nowe, 2 nowe nazwy, term "Mozzarella"), 33 produkty (atrybuty, pole `kartaproduktowa`, opisy), 11 PDF-ów w mediach (`uploads/2026/10/`).
- Kopie sprzed zmian: `backups/2026-10-20-karty-produktow/` (cała baza, layout 6480, produkty, atrybuty) i `uploads/tzp-backup/elementor-data-6480-*.json`, `elementor-data-6533-*.json`.
- Test: `.claude/tests/product-card-e2e.js`.
