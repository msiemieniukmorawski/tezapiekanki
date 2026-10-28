# Podsumowanie prac: 19.10.2026

Wszystko zrobione i sprawdzone **w kopii lokalnej** (tezapiekanki.local). Na produkcji nic się jeszcze nie zmieniło. Procedury wdrożenia krok po kroku są w `CLAUDE.md`, sekcja 9.

---

## 1. Komunikacja wysyłki (pkt 2 zakresu)

**Belka nad nagłówkiem**: "Wysyłamy tylko 2 razy w tygodniu. Sprawdź dlaczego →"
- Nowa wtyczka sklepu `tezapiekanki`.
- Na wszystkich stronach oprócz zamówienia i strony podziękowania.
- Krzyżyk chowa ją do końca dnia. Na stronach z cache LiteSpeed zamknięta belka nie miga przy ładowaniu.
- Ustawienia w panelu: **Wygląd → Dostosuj → Belka o wysyłce**: tekst, tekst linku, włącz/wyłącz i działanie kliknięcia (dowolny popup z listy, adres strony albo nic). Tekst zmienia się w podglądzie na żywo.
- Zmiana popupu podpiętego pod belkę nie wymaga grzebania w ustawieniach popupów, bo wtyczka sama dopina kliknięcie do wybranego popupu.

**Popup "Dlaczego wysyłamy tylko 2 razy w tygodniu?"**
- Treść z zakresu, przycisk "Wybieram zapiekanki" prowadzi do sklepu. Klient edytuje popup w Elementorze (menu Popups).
- Pokazuje jeden, najbliższy termin, np. "Zamów jutro do 20:00, a dostaniesz ją w czwartek."
- Termin liczy się sam i przeskakuje po godzinie granicznej, także na stronach z cache.

**Meta description strony głównej**: "Świeże zapiekanki do upieczenia w domu. Wysyłamy 2 razy w tygodniu w termoboxie z wkładami chłodzącymi, dostawa do Paczkomatu lub pod drzwi."
- Wcześniej Google dostawał tekst z hero "dostarczymy je jutro".
- Sam tekst w hero jest bez zmian i czeka na zgodę klienta.

**Znaleziony i naprawiony błąd: popupy Woodmarta nie działały w ogóle** (dotyczy też produkcji)
- Elementor 4.3 rejestruje typy dokumentów, zanim Woodmart doda swój typ popupu. Woodmart brał wtedy ustawienia domyślne: popup otwierałby się sam przy każdym wejściu, a po pierwszym zamknięciu już nigdy.
- Dodatkowo w opcjach wydajności Woodmarta wyłączony był skrypt popupów.
- Poprawka jest w motywie potomnym, a skrypt włączony w opcjach.

**Instrukcja dla klienta**: `.claude/doc/instrukcja-belka-i-popup.md`. Opisuje belkę, popupy, terminy w treści, cache i blok ikon.

---

## 2. Info praktyczne (pkt 4 zakresu)

**Blok ikon pod przyciskiem "Dodaj do koszyka"**, na kartach zapiekanek i zestawów:

| Tytuł | Opis |
|---|---|
| 6 dni świeżości | liczone od dostawy |
| Trzymaj w lodówce | zaraz po odbiorze paczki |
| Możesz zamrozić | najlepiej od razu po odbiorze |
| Termobox | z wkładami chłodzącymi |

- Własne ikony w kolorze marki, układ 2×2 (także na telefonie), kafelki nieklikalne.
- Jeden wspólny blok, edycja w menu HTML Blocks.

**Wkłady:** "wkłady żelowe" zamienione na "wkłady chłodzące" w opisach wszystkich produktów, czyli 102 miejsca w 33 produktach razem z ukrytymi kopiami do zestawów. Teraz wszędzie jest ta sama nazwa co na stronie głównej i w "Sposobach dostawy".

**Mrożenie:** checkbox "Proszę o wysyłkę zamrożonych zapiekanek" jest ukryty. Okazało się, że klienci i tak go nie widzieli, bo checkout (także produkcyjny) nie ma sekcji z dodatkowymi polami zamówienia, stąd 0 zaznaczeń na 2078 zamówień. Pozostałe teksty o mrożeniu były już spójne ("nie mrozimy, po odbiorze możesz zamrozić").

---

## 3. Filtry i porządki (pkt 6 zakresu)

- **Sklep bez filtrów** (wariant A): bez sidebara, przycisku "FILTRUJ OFERTĘ" i aktywnych filtrów. Produkty po 4 w rzędzie na desktopie, 3 na tablecie, 1 na telefonie. Widgety filtrów są w nieaktywnych, do przywrócenia jednym przeciągnięciem.
- **Nagłówek:** usunięty tekst szablonu "ADD ANYTHING HERE OR JUST REMOVE IT…", który był w kodzie każdej strony.
- **Strona główna, sekcja smaków:** mały przycisk z obramowaniem "Sprawdzam pozostałe" zastąpiony wyraźnym, wypełnionym przyciskiem "Zobacz wszystkie smaki", który prowadzi do kategorii Zapiekanki.

---

## 4. Dodatkowo (poza punktami zakresu, na prośbę)

- **Galeria zestawów** taka sama jak u zapiekanek: duże zdjęcie, pod nim kafelki, na telefonie karuzela z licznikiem, lightbox. Zdjęcia zestawów są pionowe, więc duże zdjęcie jest przycięte do kwadratu, a w lightboxie widać całe.
- **Logotypy płatności** ("Szybka płatność i dostawa") nie są już klikalne, na kartach produktu i w checkoucie. Wcześniej otwierały się w powiększeniu.

---

## 4a. FAQ (pkt 5 zakresu)

- **Moduł:** nowy widget Elementora **"FAQ (zwiń / rozwiń)"** (kategoria TeZapiekanki). Pytania rozwijają się po kliknięciu, działa bez JavaScriptu (także na stronach z cache) i z klawiatury. Sprawdzony w edytorze Elementora.
- **Dane strukturalne FAQPage dla Google:** jeden blok na stronę, nawet przy kilku widgetach FAQ. Wbudowane akordeony Elementora tworzą osobny blok dla każdego widgetu, a to Google zgłasza jako błąd. Treść danych zgadza się z tym, co widać na stronie (sprawdzone testem).
- **Strona "Najczęstsze pytania"** (/faq/): 11 pytań w 4 grupach (zamówienie i dostawa, świeżość i mrożenie, pieczenie, nasze zapiekanki), zebranych ze strony głównej, opisów produktów i popupu o wysyłce, bez powtórzeń. Link w stopce, własny opis dla Google.
- **Produkty:** FAQ z 6 pytań skrócone do 4 o konkretny smak (świeżość, wysyłka na świeżo, mrożenie, imprezy). Pytania rozwijają się, a pod nimi jest przycisk "Zobacz wszystkie pytania". Klient dalej edytuje je w opisie produktu zwykłymi nagłówkami.
- **Usunięte z produktów:** pytanie "Czy mogę dodać do zapiekanki dodatkowe składniki?". Odpowiedź obiecywała dodatki (prażona cebulka, żurawina, sosy), a sklep nie ma takiej opcji.
- **Strona główna:** FAQ ma 4 pytania, których nie powtarza tekst o firmie niżej na stronie, oraz przycisk do pełnej listy. Tematy mrożenia, farszu i Paczkomatu są opisane w tym tekście, więc nie dublujemy ich w FAQ (na stronie FAQ są). Sam tekst bez zmian.
- Po uwagach: można otworzyć kilka pytań naraz (wcześniej otwarcie jednego zamykało poprzednie i na telefonie trzeba było przewijać), ze strony głównej zniknął pasek "Napisz do nas" pod FAQ, a blok kontaktu na końcu strony FAQ jest wyśrodkowany.
- Edycja dla klienta: instrukcja, punkt 8.
- Zostało z pkt 5: uzupełnienie danych produktu dla Google (Product/Offer: wysyłka, zwroty).

---

## 5. Testy

- Testy automatyczne w przeglądarce (desktop, tablet, telefon): belka i popup 31/31, galeria 16/16, pasek "Dodaj do koszyka" 12/12, listingi i zdjęcia 8/8, FAQ 25/25 (strona FAQ, produkt, strona główna, stopka, telefon).
- Każda zmiana obejrzana na zrzutach ekranu.
- Testu checkoutu nie puszczałem, bo tworzy zamówienia. W checkoucie zmieniły się tylko ukryte pole i nieklikalne logotypy.

---

## 6. Do decyzji klienta

1. Akceptacja tekstów: belka, popup, meta description, opisy ikon.
2. Zgoda na zmianę hero na stronie głównej ("dostarczymy je jutro").
3. Termin przydatności: blok mówi "6 dni", a Tradycyjna ma w atrybucie 7 dni.
4. Godziny graniczne i dni wysyłki (silnik terminów jest ustawiony na: niedziela 20:00 → wtorek, wtorek 20:00 → czwartek).
5. Zastąpienie kalendarza Iconic własnym wyborem terminu (zrobione lokalnie 18.10, czeka na zgodę).
6. Strona "Sposoby dostawy" obiecuje wysyłkę tego samego dnia przy zamówieniu do 10:00, a reszta sklepu mówi o wysyłce w poniedziałki i środy. Do poprawy po potwierdzeniu godzin granicznych.
7. Czy da się zamówić dodatki do zapiekanki (cebulka, żurawina, sosy)? Jeśli tak, wrócimy z tym pytaniem do FAQ.

## 7. Znalezione problemy (niezmienione)

- Karta zestawu: komunikat "Wybierz **[min]** zapiekanek przed dodaniem zestawu do koszyka" ma niepodmieniony znacznik.
- "Zapiekanka na ostro" nie ma żadnych atrybutów (skład, termin, wartości odżywcze). Do uzupełnienia razem z makro z kart produktów.

## 8. Co zostało z zakresu

- **Pkt 3:** makro na 11 produktach (czeka na karty produktów), wyeksponowanie karty PDF i ujednolicenie opisów, piny do innych smaków.
- **Pkt 5:** zostało uzupełnienie danych produktu dla Google (Product/Offer w Rank Math) i walidacja w Rich Results Test po wdrożeniu.
- **Pkt 0 i 7:** punkt odniesienia GA4 przed wdrożeniem, wdrożenie wszystkiego na produkcję, testy po wdrożeniu.

---

## Pliki i miejsca zmian (dla programisty)

- Wtyczka `wp-content/plugins/tezapiekanki/`:
  - `includes/class-shipping-bar.php`, `includes/class-shipping-bar-customizer.php`, `includes/class-faq.php`, `includes/class-faq-widget.php`, `includes/class-product-faq.php`, `assets/` (belka, FAQ, ikony),
  - `tools/create-shipping-popup.php`, `tools/create-product-info-block.php`, `tools/unify-cooling-inserts.php`, `tools/convert-accordion-to-faq.php`, `tools/shorten-product-faq.php`, `tools/create-faq-page.php` (skrypty WP-CLI do powtórzenia na produkcji).
- Motyw potomny: `inc/woodmart-popups.php` (poprawka popupów), `style.css` (sekcje "Info praktyczne" i "Galeria produktu").
- Panel i Elementor:
  - layouty 6480, 6533 (karty produktu), 6473 (sklep), 6470 (checkout, tylko logotypy), strona główna 11,
  - popup 31262, HTML Block 31279,
  - opcje Woodmarta (skrypt popupów), nagłówek, widgety sklepu, FCF.
- Kopie sprzed zmian: `backups/2026-10-19-shipping-bar/`, `backups/2026-10-19-info-filtry/`, `backups/2026-10-19-faq/`.
- Testy: `.claude/tests/shipping-bar-e2e.js`, `.claude/tests/faq-e2e.js`.
