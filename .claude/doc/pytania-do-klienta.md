# Pytania do klienta (stan na 26.10.2026)

Wszystkie otwarte pytania w jednym miejscu: z zakresu prac, z podsumowań z 19.10 i 20.10 i z planu zastąpienia kalendarza. Odpowiedzi klientki z 26.10 są dopisane przy pytaniach (**Odp. 26.10**), zmiany po nich są zrobione w kopii testowej. Zmiany są gotowe w kopii testowej sklepu. Od odpowiedzi zależą teksty na stronie i termin wdrożenia.

Pytania oznaczone **[blokuje]** wstrzymują wdrożenie albo końcowe teksty. Teksty z punktu 2 klient zaakceptuje na stagingu.

---

## 1. Wysyłka i terminy

1. **Godziny graniczne i dni wysyłki** (nie blokuje: można je zmienić w każdej chwili w panelu, WooCommerce → Ship Today Notice). Sklep jest ustawiony tak:
   - zamówienie do niedzieli 20:00 → wysyłka w poniedziałek, dostawa we wtorek,
   - zamówienie do wtorku 20:00 → wysyłka w środę, dostawa w czwartek.

   Czy tak ma zostać? Te same terminy pokazują belka, popup, karta produktu, koszyk i checkout.

   **Odp. 26.10:** godzina graniczna 24:00. Ustawione 23:59 (pole godziny nie przyjmuje 24:00, a 00:00 wyświetlałoby się jako "do poniedziałku 00:00"): do niedzieli 23:59 i do wtorku 23:59. Zmienione też w FAQ.
2. Czy dostawa jest zawsze następnego dnia po wysyłce, tak samo do Paczkomatu i kurierem?
3. **Strona "Sposoby dostawy"** mówi: "Zamówienia złożone w dni robocze do godziny 10:00 przekazujemy do wysyłki tego samego dnia". Reszta sklepu mówi o wysyłce w poniedziałki i środy. Po potwierdzeniu godzin z punktu 1 poprawimy tę stronę. **Odp. 26.10:** poprawione, sekcja "Kiedy wysyłamy" podaje teraz poniedziałek i środę z godziną 23:59.
4. **Święta:** wysyłka w środę 23.12 dotrze dopiero w poniedziałek 28.12 (24-26.12 to święta, 27.12 niedziela). Czy 23.12 w ogóle wysyłać?
5. **Koszt wysyłki a waga paczki.** Obecne zasady: do 4 kg 14,99 zł, od 5 kg 9,99 zł, od 300 zł za darmo. Wagę ma wpisaną tylko Zapiekanka na ostro (1 kg za sztukę) i farsz, pozostałe zapiekanki i zestawy nie mają wpisanej wagi. W praktyce:
   - prawie każde zamówienie poniżej 300 zł płaci 14,99 zł,
   - tańsza wysyłka 9,99 zł działa tylko przy 5 i więcej sztukach "na ostro".

   Jak ma być? Na przykład zawsze 14,99 zł do 300 zł, albo taniej od określonej liczby zapiekanek (od ilu)?

   **Odp. 26.10:** zawsze 14,99 zł, od 300 zł za darmo. Ustawione w obu metodach. Przy okazji zniknął błąd: koszyk o wadze między 4 a 5 kg miał wysyłkę 0 zł.

## 2. Teksty do akceptacji (na stagingu)

6. **Belka nad nagłówkiem:** "Wysyłamy tylko 2 razy w tygodniu. Sprawdź dlaczego →" **Odp. 26.10:** tekst OK. Belka zlewała się ze zdjęciem na stronie głównej, więc ma teraz czarne tło.
7. **Popup po kliknięciu w belkę:** treść z zakresu prac ("Dlaczego wysyłamy tylko 2 razy w tygodniu?", farsz gotowany kilka godzin, termobox, przycisk "Wybieram zapiekanki"). Zamiast dwóch linijek z terminami jest jedna, z najbliższym terminem, np. "Zamów jutro do 20:00, a dostaniesz ją w czwartek." Termin zmienia się sam. **Odp. 26.10:** teksty OK, termin przydatności zmieniony na 7 dni w lodówce.
8. **Opis strony głównej w Google:** "Świeże zapiekanki do upieczenia w domu. Wysyłamy 2 razy w tygodniu w termoboxie z wkładami chłodzącymi, dostawa do Paczkomatu lub pod drzwi."
9. **Ikony na karcie produktu:** "6 dni świeżości / liczone od dostawy", "Trzymaj w lodówce / zaraz po odbiorze paczki", "Możesz zamrozić / najlepiej od razu po odbiorze", "Termobox / z wkładami chłodzącymi". **Odp. 26.10:** pierwsza ikona to teraz "7 dni świeżości / w lodówce, liczone od dostawy". Pieczenie (220-250°C, około 8 minut) podmienione wszędzie: kroki na stronie głównej, zakładka "Sposób przygotowania" na karcie produktu, FAQ i opisy produktów.
10. **Duży napis na górze strony głównej:** "Zamów teraz, a my dostarczymy je jutro do Paczkomatu lub Twojego domu." Obietnica "jutro" nie zgadza się z wysyłką 2 razy w tygodniu. Czy możemy go zmienić? Opis dla Google już go nie powtarza. **Odp. 26.10:** usunięty.

## 3. Karty produktów (PDF z 20.10)

Wartości z kart są już na stronie. Te rzeczy dotyczą samych kart, warto je sprawdzić z osobą, która je przygotowała:

11. **Termin przydatności:** karty go nie podają. Ikony i opisy mówią "6 dni od dostawy", a Tradycyjna ma w danych 7 dni. Ile dni jest prawidłowo i czy dla wszystkich smaków tyle samo? **Odp. 26.10:** 7 dni pod warunkiem przechowywania w lodówce. Zmienione w popupie, ikonach, FAQ, opisach i atrybucie wszystkich smaków.
12. **Zapiekanka na dziko:** energia na karcie (928 kJ / 222 kcal) nie zgadza się z tłuszczem, węglowodanami i białkiem z tej samej karty, z których wychodzi około 865 kJ / 206 kcal. Pozostałe 10 kart się zgadza. **Odp. 26.10:** klientka wie o rozbieżności, producent boczku z dzika nie zmieni danych. Zostaje tak jak na karcie.
13. **Alergeny:**
    - parmezan zawiera "lizozym z białka jaj", a jaja są tylko w "Może zawierać",
    - boczek z dzika zawiera gorczycę, a gorczyca też jest tylko w "Może zawierać".

    Składnik, który jest w przepisie, powinien być w "Zawiera".
14. **Skład składników złożonych:** kebab z kurczaka, salami, chorizo, ogórki szwedzkie i cebulka prażona nie mają rozpisanego składu.
15. **Sosy:** w opisach są sosy (np. remulada, sos czosnkowo-jogurtowy, ketchup). Rozumiemy, że to saszetki w paczce. Na kartach nie ma ich składu ani alergenów. Czy dołączyć do nich osobne karty?
16. **Wartości odżywcze** są identyczne jak na kartach z sierpnia, choć skład się zmienił (doszedł parmezan, inne proporcje farszu). Czy wartości zostały przeliczone?
17. **Literówki na kartach** (do poprawy w źródle, wtedy podmienimy pliki): "ZAPIEKANKA Z SZYNKA" zamiast "Z SZYNKĄ", "Białko 9.7 g" zamiast "9,7 g" (Szwedzka), tytuł karty Szwedzkiej "KARTA INFORMACYJNA - ZAPIEKANKA OGÓREK SZWEDZKI" (pozostałe karty: "KARTA INFORMACYJNA PRODUKTU - ZAPIEKANKA …", a produkt w sklepie to "Zapiekanka Szwedzka").

## 4. Oferta i treści

18. **Dodatki do zamówienia:** opisy produktów mówią "Możesz również wzbogacić swoje zamówienie o prażoną cebulkę, żurawinę lub autorskie sosy", a w sklepie nie da się ich zamówić. Czy da się je zamówić (np. w uwagach albo jako osobny produkt)? Jeśli nie, usuniemy to zdanie z opisów.
19. **Mrożone zapiekanki:** w zamówieniu było pole "Proszę o wysyłkę zamrożonych zapiekanek", którego klienci i tak nie widzieli (0 zaznaczeń w 2078 zamówieniach). Ukryliśmy je, bo strona mówi "wysyłamy świeże, nie mrożone". Czy potwierdzacie, że mrożonych nie wysyłacie?
20. **Filtry w sklepie:** przy 11 smakach usunęliśmy filtry (mięso, składniki), produkty są na całą szerokość, 4 w rzędzie. Czy tak zostaje? Filtry da się przywrócić.
21. **Nazwa marki w danych dla Google:** wpisaliśmy "TE Zapiekanki Skalbmierskie". W innych miejscach jest "TeZapiekanki" albo "TeZapiekanki Skalbmierz". Która nazwa jest właściwa?

## 5. Wybór terminu w zamówieniu (zamiast kalendarza Iconic)

Zamiast kalendarza miesięcznego klient wybiera jeden z najbliższych terminów na kafelkach (gotowe w kopii testowej od 18.10).

22. Zgoda na zastąpienie kalendarza Iconic kafelkami (około 3 h ponad wycenę). Iconic na razie zostaje aktywny w panelu (stare zamówienia). Czy po około miesiącu można go wyłączyć i nie odnawiać licencji? Nasz wybór terminu działa bez Iconic (sprawdzone 20.10, stare daty z Iconic dalej widać w zamówieniach i w raporcie).
23. Czy ktoś korzysta w panelu z WooCommerce → Deliveries (Iconic)? Od dnia przełączenia nowe zamówienia już się tam nie pojawią. W zamian jest filtr listy zamówień po dniu wysyłki i raport "Zestawienie produktów".
24. Co ma być najważniejsze na kafelku: data dostawy czy data wysyłki? (proponujemy dostawę)
25. Ile terminów pokazać (proponujemy 6, czyli około 3 tygodnie) i czy najbliższy ma być zaznaczony od razu (proponujemy tak)?
26. Czy drukujecie listy pakowania (WebToffee)? Jeśli tak, dopiszemy na nich datę wysyłki i dostawy.
27. Nazwa sekcji: "Termin dostawy" zamiast obecnego "Wybierz datę wysyłki zamówienia"?

## 6. Wdrożenie

28. Zgoda na jedno zamówienie testowe na żywym sklepie po wdrożeniu (np. BLIK na małą kwotę, potem anulowanie), żeby sprawdzić płatność, maila i raport.
29. Kiedy wdrażamy? Najlepiej wieczorem po godzinie granicznej, gdy jest mało zamówień.

---

## Do wiadomości (bez odpowiedzi)

- **Maile do klientów (poprawione 20.10):** w stopce maili było "skontaktuj się z nami pod adresem noreply@zapiekankiskalbmierz.pl", czyli adres, na który nie da się odpisać, do tego z innej domeny niż sklep. Teraz klient widzi sklep@tezapiekanki.pl i na ten adres trafia też jego odpowiedź na maila. Maile dalej wychodzą z tego samego adresu nadawcy, więc nic się nie zmienia w ich doręczaniu.
- **Adres w zamówieniu (poprawione 20.10):** gdy klient nie zamawiał na firmę, w mailach i na stronie podziękowania były puste linie "Nazwa firmy:" i "Numer NIP:". Teraz ich nie ma. Przy zamówieniu na firmę nazwa firmy i NIP są widoczne jak wcześniej.
- **Logo (zmienione 20.10):** w nagłówku i stopce strony jest teraz logo "TE Zapiekanki Skalbmierskie od 1987" z przesłanych materiałów (takie samo jak w mailach). Wcześniej strona miała wersję "est 1987".
- **FAQ w Google:** od 2023 r. Google pokazuje rozwijane pytania FAQ w wynikach wyszukiwania praktycznie tylko serwisom rządowym i medycznym. Dane FAQ na stronie są poprawne i czytelne dla wyszukiwarek i asystentów AI, ale rozwijanych pytań w wynikach Google nie będzie.
- **Licznik "X osób jest zainteresowanych tym produktem" (usunięty 26.10, prośba klientki):** liczba była losowana przez motyw (1-27), nie liczyła prawdziwych odwiedzin. Zniknął z kart zapiekanek i zestawów.
