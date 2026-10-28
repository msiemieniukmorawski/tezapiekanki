# Belka, popupy, FAQ i ikony: instrukcja

Jak zmienić tekst belki nad nagłówkiem, jak edytować popup "Dlaczego wysyłamy tylko 2 razy w tygodniu?", jak zrobić nowy popup, jak edytować FAQ i ikony pod przyciskiem "Dodaj do koszyka". Wszystko robisz w panelu WordPressa, bez programisty.

Stan na 19.10.2026: wszystko działa w kopii testowej sklepu. Na stronie sklepu pojawi się po wdrożeniu.

---

## 1. Belka nad nagłówkiem

Brązowy pasek na samej górze strony, domyślnie: "Wysyłamy tylko 2 razy w tygodniu. **Sprawdź dlaczego →**".

### Gdzie ją zmienić

**Wygląd → Dostosuj → Belka o wysyłce**

| Pole | Co robi |
|---|---|
| Pokazuj belkę | Włącza i wyłącza belkę na całej stronie. |
| Tekst | Główny tekst belki. |
| Tekst linku | Podkreślony fragment ze strzałką na końcu. Puste pole: zostaje sam tekst, ale belka nadal jest klikalna. |
| Po kliknięciu w belkę | "Otwórz popup", "Przejdź pod adres" albo "Nic (sam tekst, bez linku)". |
| Popup | Który popup otworzyć (widać tylko przy "Otwórz popup"). |
| Adres | Dokąd prowadzi kliknięcie. Przy popupie to adres zapasowy, gdyby popup się nie otworzył. |

Tekst zmienia się w podglądzie po prawej od razu, w trakcie pisania. Pozostałe zmiany są widoczne po chwili, gdy podgląd się odświeży.

Na koniec kliknij **Opublikuj** (niebieski przycisk na górze panelu). Bez tego zmiany zostają tylko w podglądzie.

### Jak belka działa na stronie

- Jest na wszystkich stronach oprócz zamówienia (checkout) i strony podziękowania po zamówieniu.
- Klient może ją zamknąć krzyżykiem. Wtedy nie widzi jej do końca dnia, a następnego dnia belka wraca.
- Jeśli sam ją zamkniesz, a potem będziesz sprawdzać zmiany, możesz jej nie widzieć. Otwórz stronę w oknie prywatnym (incognito) albo poczekaj do jutra.

---

## 2. Edycja popupu "Dlaczego wysyłamy tylko 2 razy w tygodniu?"

1. W menu panelu wejdź w **Popups**.
2. Przy popupie **"Dlaczego 2 razy w tygodniu (belka)"** kliknij **Edytuj w Elementorze**.
3. Zmień treść jak na zwykłej stronie: kliknij tekst, tytuł albo przycisk i edytuj w panelu po lewej.
4. Kliknij **Opublikuj** (albo **Aktualizuj**).

W popupie są:

- tytuł,
- dwa akapity o farszu,
- **termin dostawy**, czyli widget Shortcode (szary box z tekstem "Zamów jutro do 20:00, a dostaniesz ją w czwartek."). Treść terminu zmieniasz w polu shortcode'u, opis w punkcie 4,
- akapit o termoboxie,
- przycisk "Wybieram zapiekanki", który prowadzi do sklepu.

Ustawienia popupu (szerokość, odstępy, warunki wyświetlania) są pod ikoną zębatki w Elementorze. W tym popupie **nie ustawiaj wyzwalaczy** (zakładka Triggers). Otwieranie z belki podpina się samo.

---

## 3. Nowy popup podpięty pod belkę

Na przykład gdy chcesz pod belką pokazać inną treść albo przygotować popup na święta i przełączyć go w odpowiednim dniu.

### Krok 1. Utwórz popup

1. **Popups → dodaj nowy**. Woodmart pokaże okno z nazwą popupu i gotowymi szablonami.
2. Wpisz nazwę (widzisz ją tylko Ty, np. "Belka: święta 2026"), wybierz szablon albo pusty popup i kliknij **Create popup**.
3. Otworzy się Elementor. Ułóż treść jak na zwykłej stronie.
4. Kliknij ikonę zębatki i ustaw:
   - **Layout → Width**: szerokość popupu, np. 620 px (tyle ma obecny),
   - **Conditions → Display conditions**: gdzie popup ma być dostępny. Najprościej dodaj warunek **Include → All**. Możesz dodać drugi: **Exclude → Custom → Checkout page** (w zamówieniu belki i tak nie ma).
5. Kliknij **Opublikuj**. Nowy popup zapisuje się najpierw jako szkic, a szkicu nie da się wybrać przy belce.

### Krok 2. Podepnij go pod belkę

1. **Wygląd → Dostosuj → Belka o wysyłce**.
2. "Po kliknięciu w belkę": **Otwórz popup**.
3. "Popup": wybierz nowy popup z listy.
4. **Opublikuj**.

Gotowe. Poprzedni popup przestaje się otwierać z belki, ale nie znika. Możesz do niego wrócić, wybierając go z listy.

Wyzwalaczy (zakładka Triggers) w popupie podpiętym pod belkę nie trzeba ustawiać. Strona sama otwiera go po kliknięciu w belkę i sama wyłącza w nim automatyczne otwieranie przy wejściu na stronę oraz opcję "zamknij na stałe".

---

## 4. Termin dostawy w treści (popup, strona, blok)

Termin wstawiasz widgetem **Shortcode** w Elementorze (wyszukaj "shortcode" na liście widgetów). Obecny popup ma w nim:

```
[ship_today_notice class="tzp-why-dates" text="Zamów {cutoff_when}, a dostaniesz ją {delivery_when}."]
```

- `text="..."` to treść. Możesz ją dowolnie zmienić, wstawiając znaczniki z tabeli niżej.
- `class="tzp-why-dates"` daje jasny box jak w obecnym popupie. Bez tej części wygląd będzie inny (żółty box), więc najlepiej jej nie usuwać.

Termin jest zawsze **najbliższy możliwy** i zmienia się sam. Po godzinie granicznej strona przeskakuje na kolejny termin, nawet bez odświeżania. Święta i dni wolne ustawione w terminach wysyłki (WooCommerce → Ship Today Notice) też są uwzględniane.

| Znacznik | Co wstawia | Przykład (w środę 21.10) |
|---|---|---|
| `{cutoff_when}` | do kiedy zamówić, z "dziś" i "jutro" | do niedzieli 20:00 (w sobotę: jutro do 20:00, w niedzielę: dziś do 20:00) |
| `{cutoff_day}` | dzień godziny granicznej | niedzieli |
| `{cutoff_time}` | godzina graniczna | 20:00 |
| `{cutoff_date}` | data godziny granicznej | 25.10 |
| `{ship_when}` / `{ship_day}` | dzień wysyłki | w poniedziałek |
| `{ship_date}` | data wysyłki | 26.10 |
| `{delivery_when}` / `{delivery_day}` | dzień dostawy | we wtorek |
| `{delivery_date}` | data dostawy | 27.10 |
| `{countdown}` | odliczanie do godziny granicznej | 23h 33min |

Wersje z `_when` wstawiają "dziś" albo "jutro", gdy pasuje, a w inne dni nazwę dnia.

Przykłady:

- `text="Zamów {cutoff_when}, a dostaniesz ją {delivery_when}."` → "Zamów do niedzieli 20:00, a dostaniesz ją we wtorek."
- `text="Najbliższa dostawa: {delivery_date}. Zamów {cutoff_when}."` → "Najbliższa dostawa: 27.10. Zamów do niedzieli 20:00."
- `text="Do końca zamówień zostało {countdown}."` → "Do końca zamówień zostało 4 dni 08h 12min." (format odliczania ustawia się w Ship Today Notice)

Godziny graniczne i dni wysyłki zmienia się w **WooCommerce → Ship Today Notice**, nie w popupie. Popup pokazuje to, co tam jest ustawione.

---

## 5. Popup niezwiązany z belką (np. promocja przy wejściu)

Robisz go tak samo jak w punkcie 3, krok 1, ale nie wybierasz go przy belce, tylko ustawiasz mu wyzwalacz w zakładce **Triggers** (ikona zębatki).

Uwaga na dwa ustawienia, które nowy popup ma włączone od razu:

- **Triggers → Page loaded**: popup otwiera się sam przy wejściu na stronę, domyślnie po 0 sekundach. Jeśli tego nie chcesz, wyłącz. Jeśli chcesz, ustaw sensowne opóźnienie (Time to show, w milisekundach: 5000 = 5 sekund) i włącz **Trigger once**, żeby klient nie widział go przy każdej podstronie.
- **Close behavior → Persistent close**: po zamknięciu popup nie pokaże się temu klientowi już nigdy. Przy promocji zwykle dobrze, przy popupie otwieranym przyciskiem źle (przycisk przestaje działać).

Inne przydatne wyzwalacze: **User scroll** (po przewinięciu strony), **Exit intent** (gdy kursor ucieka w stronę zamknięcia karty, tylko komputer), **On selector click** (po kliknięciu elementu o podanej klasie CSS).

---

## 6. Zmiany nie są widoczne na stronie

Strona sklepu jest zapisywana w pamięci podręcznej (LiteSpeed Cache), żeby szybciej się ładowała.

- Po zapisie ustawień belki (Dostosuj) i po zapisie popupu wybranego przy belce pamięć czyści się sama.
- Po innych zmianach (np. nowy popup z wyzwalaczem "Page loaded") wyczyść ją ręcznie: na górnym pasku panelu **LiteSpeed Cache → Purge All** (Wyczyść wszystko).

Dalej nie widać zmian? Otwórz stronę w oknie prywatnym (incognito), bo przeglądarka też pamięta stare wersje.

---

## 7. Co sprawdzić, gdy coś nie działa

| Problem | Co sprawdzić |
|---|---|
| Nie widzę belki | Czy "Pokazuj belkę" jest zaznaczone (Dostosuj). Czy nie zamknąłeś jej dziś krzyżykiem (sprawdź w oknie prywatnym). W zamówieniu (checkout) belki nie ma celowo. |
| Kliknięcie w belkę przenosi na "Sposoby dostawy" zamiast otworzyć popup | Czy popup jest opublikowany (nie szkic). Czy jest wybrany w Dostosuj → Belka o wysyłce. Czy jego warunki (Conditions) obejmują stronę, na której jesteś. |
| Popup otwiera się sam przy każdym wejściu | W popupie: Triggers → wyłącz "Page loaded". |
| Popup otwiera się raz, potem już nigdy | W popupie: Close behavior → wyłącz "Persistent close". |
| Termin w popupie jest zły | Godziny graniczne, dni wysyłki i święta: WooCommerce → Ship Today Notice. |

---

## 8. FAQ (pytania rozwijane)

Pytania są w trzech miejscach:

- **Strona "Najczęstsze pytania"** (adres /faq/, link w stopce): pełna lista w grupach. Edycja: Strony → Najczęstsze pytania → Edytuj w Elementorze.
- **Strona główna:** 4 pytania i przycisk "Zobacz wszystkie pytania".
- **Karta produktu:** 4 pytania o dany smak i przycisk do pełnej listy (opis niżej, "FAQ na karcie produktu").

Na stronie FAQ i na stronie głównej pytania to widget **FAQ (zwiń / rozwiń)**. W Elementorze jest na liście widgetów w kategorii **TeZapiekanki** (albo wyszukaj "FAQ").

- **Pytania:** w zakładce Treść, lista "Pytania i odpowiedzi". Kliknij pytanie, żeby je rozwinąć i zmienić tekst. "Dodaj element" dodaje nowe pytanie, a pytania przeciągasz, żeby zmienić kolejność.
- **Ustawienia:** "Pierwsze pytanie otwarte" oraz "Tylko jedno otwarte naraz" (otwarcie pytania zamyka poprzednie). Ta druga opcja jest celowo wyłączona, bo na telefonie zamykanie poprzedniego pytania przesuwa stronę.
- **Dane strukturalne FAQPage:** pytania trafiają do danych dla Google. Te same pytania mogą być oznaczone tylko w jednym miejscu serwisu. Jeśli wstawiasz te same pytania w drugim miejscu (np. skrót na stronie głównej i pełne FAQ na osobnej stronie), w jednym z nich wyłącz ten przełącznik.
- **Wygląd:** zakładka Styl (tło, obramowanie, odstępy, czcionki i kolory pytań i odpowiedzi).
- **Link pod pytaniami:** pola "Link pod pytaniami" (tekst, adres, wyrównanie). Pusty adres prowadzi na stronę FAQ.

Teraz dane dla Google są tylko na stronie FAQ. Na stronie głównej są wyłączone, bo te same pytania są na stronie FAQ.

### FAQ na karcie produktu

Pytania produktu są na końcu jego opisu (Produkty → wybierz zapiekankę → duże pole opisu):

- nagłówek **"FAQ – najczęściej zadawane pytania"** w stylu "Nagłówek 2",
- każde pytanie jako **"Nagłówek 3"**,
- odpowiedź pod nim jako zwykły tekst.

Na stronie produktu to samo wyświetla się automatycznie jako pytania rozwijane, z danymi dla Google i przyciskiem "Zobacz wszystkie pytania". Żeby dodać pytanie, dopisz kolejny "Nagłówek 3" z odpowiedzią. Pytania warto trzymać przy konkretnym smaku (świeżość, mrożenie, z czym podać), a ogólne dopisywać na stronie FAQ.

Po zapisie wyczyść pamięć podręczną (punkt 6).

---

## 9. Ikony pod przyciskiem "Dodaj do koszyka"

Cztery ikony z tytułem i krótkim opisem ("6 dni świeżości / liczone od dostawy", "Trzymaj w lodówce / zaraz po odbiorze paczki", "Możesz zamrozić / najlepiej od razu po odbiorze", "Termobox / z wkładami chłodzącymi") to jeden wspólny blok dla wszystkich zapiekanek i zestawów.

1. W menu panelu wejdź w **HTML Blocks**.
2. Przy bloku **"Info praktyczne (karta produktu)"** kliknij **Edytuj w Elementorze**.
3. Kliknij ikonę albo tekst i zmień go w panelu po lewej: tytuł w polu Title, opis w polu treści pod nim. Ikonę podmieniasz w polu obrazu tego samego widgetu. Pole Link zostaw puste, inaczej kafelek zrobi się klikalny.
4. **Aktualizuj**. Zmiana pojawi się od razu na wszystkich kartach produktów.
