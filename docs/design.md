# API systemu bibliotecznego — projekt rozwiązania

Data: 2026-07-28
Zadanie: rekrutacja KissList, PHP Developer (Symfony)

## Cel

REST API dla pracowników biblioteki do śledzenia i aktualizowania stanu posiadanych
książek. Zleceniodawca ocenia sposób modelowania danych, organizację kodu, pracę
z API oraz integrację z bazą danych — nie rozbudowaną architekturę ani produkcyjną
konfigurację.

## Wymagania z zadania

O każdej książce przechowujemy:

- unikalny numer seryjny wprowadzany przez pracownika, w formie sześciocyfrowej liczby,
- tytuł,
- autora,
- czy jest obecnie wypożyczona, kiedy i kto ją wypożyczył (wypożyczający mają
  sześciocyfrowe numery karty bibliotecznej).

API umożliwia: dodanie książki, usunięcie książki, pobranie listy wszystkich książek,
aktualizację stanu (wypożyczona / dostępna).

Ograniczenia narzucone przez zadanie:

- framework Symfony,
- baza PostgreSQL,
- `docker compose up` uruchamia działającą aplikację,
- dostarczenie jako publiczne repozytorium Git + link do hostingu,
- uwierzytelnianie i autoryzacja pominięte.

## Stack

| Element | Wybór |
| --- | --- |
| PHP | 8.4 |
| Framework | Symfony 7.4 LTS |
| ORM | Doctrine ORM + Migrations |
| Baza | PostgreSQL 17 |
| Serwer HTTP | FrankenPHP (Caddy + PHP w jednym procesie) |
| Testy | PHPUnit (unit + WebTestCase) |
| Jakość | PHPStan level 8, PHP-CS-Fixer (PSR-12) |
| Dokumentacja | nelmio/api-doc-bundle (OpenAPI + Swagger UI) |

Wersje PHP, Symfony i Postgresa podane z pamięci — do zweryfikowania przy
scaffoldingu; obowiązuje najnowsza stabilna linia zgodna z powyższym.

## Decyzje projektowe

### D1. Czysty Symfony zamiast API Platform

Zadanie ocenia sposób modelowania danych i organizację kodu. API Platform
wygenerowałby CRUD z atrybutów i ukrył dokładnie te decyzje, które mają być
ocenione. Piszemy kontrolery, DTO i walidację ręcznie.

### D2. Wypożyczenie jako osobna encja `Loan`

Książka nie ma flagi `is_borrowed`. „Wypożyczona" oznacza istnienie rekordu `loan`
z `returned_at IS NULL`. Zalety: jedno źródło prawdy, historia wypożyczeń bez
dodatkowej pracy, brak stanów niespójnych (`is_borrowed = true` bez daty i numeru
karty). Koszt: JOIN przy listowaniu.

### D3. Spójność pilnowana przez bazę

Partial unique index `(book_id) WHERE returned_at IS NULL` sprawia, że dwa otwarte
wypożyczenia tej samej książki są niemożliwe na poziomie PostgreSQL, nie tylko
w kodzie aplikacji. Zabezpiecza również przed równoległymi żądaniami.

### D4. Numery jako `VARCHAR(6)`, nie `INT`

`012345` jest poprawnym numerem seryjnym; typ całkowitoliczbowy zjadłby wiodące
zero. Format wymuszany walidatorem `^\d{6}$` na wejściu.

### D5. Numer seryjny jako identyfikator w URL

`/api/books/123456`. Pracownik ma numer wydrukowany na książce i tylko nim się
posługuje — wymaganie wcześniejszego wyszukania technicznego ID byłoby sztuczne.
Wewnętrznym kluczem głównym pozostaje `id`.

### D6. Wypożyczenie i zwrot jako akcje, nie edycja pola

`POST /api/books/{serial}/borrow` i `POST /api/books/{serial}/return` zamiast
`PATCH {"status": "borrowed"}`. To przejścia stanu z własnymi regułami i własnym
payloadem (numer karty przy wypożyczeniu), a nie podmiana wartości pola.

### D7. Usunięcie wypożyczonej książki blokowane (409)

Książki fizycznie nie ma w bibliotece, więc skasowanie rekordu gubi informację, kto
ją trzyma. Pracownik musi najpierw przyjąć zwrot. Usunięcie dostępnej książki kasuje
kaskadowo jej historyczne wypożyczenia.

### D8. Lista zwraca tylko bieżący stan

`GET /api/books` zwraca status i dane aktualnego wypożyczenia. Pełna historia pod
osobnym `GET /api/books/{serial}/loans`, żeby główna odpowiedź nie rosła w
nieskończoność i nie generowała N+1.

### D9. FrankenPHP zamiast nginx + PHP-FPM

Platformy typu Railway czy Render uruchamiają pojedynczy kontener, więc układ
dwuprocesowy wymagałby supervisora w obrazie produkcyjnym albo rozjazdu między
lokalnym stackiem a wdrożeniem. FrankenPHP zawiera serwer HTTP w jednym procesie:
identyczna warstwa HTTP lokalnie i na hostingu, o jedną usługę mniej w
`compose.yaml`, deploy sprowadzony do zwykłego `Dockerfile` słuchającego na
`$PORT`. To również domyślny wybór oficjalnego szablonu Symfony Docker.

### D10. Jeden format błędu — RFC 7807

`application/problem+json` budowany centralnie w `ApiExceptionListener`. Żaden
kontroler nie składa odpowiedzi błędu ręcznie.

## Model danych

```
book
  id            SERIAL PK
  serial_number VARCHAR(6)  NOT NULL UNIQUE   -- ^\d{6}$
  title         VARCHAR(255) NOT NULL
  author        VARCHAR(255) NOT NULL

loan
  id            SERIAL PK
  book_id       INT NOT NULL REFERENCES book(id) ON DELETE CASCADE
  card_number   VARCHAR(6) NOT NULL           -- ^\d{6}$
  borrowed_at   TIMESTAMPTZ NOT NULL
  returned_at   TIMESTAMPTZ NULL              -- NULL = wypożyczenie trwa

  CREATE UNIQUE INDEX loan_one_active_per_book
    ON loan (book_id) WHERE returned_at IS NULL;
```

Partial index wymaga ręcznego SQL-a w klasie migracji — Doctrine nie wygeneruje go
z atrybutów.

## Kontrakt API

Bazowy prefiks: `/api`. Format żądań i odpowiedzi: JSON.

| Metoda | Ścieżka | Sukces | Błędy |
| --- | --- | --- | --- |
| POST | `/api/books` | 201 + `Location` | 422 walidacja, 409 duplikat numeru |
| GET | `/api/books` | 200 | — |
| GET | `/api/books/{serial}` | 200 | 404 |
| DELETE | `/api/books/{serial}` | 204 | 404, 409 wypożyczona |
| POST | `/api/books/{serial}/borrow` | 200 | 404, 409 już wypożyczona, 422 zły numer karty |
| POST | `/api/books/{serial}/return` | 200 | 404, 409 nie jest wypożyczona |
| GET | `/api/books/{serial}/loans` | 200 | 404 |
| GET | `/api/health` | 200 | — |

`GET /api/health` nie wynika z treści zadania — to probe żywotności, dzięki któremu
pierwsze zadanie implementacyjne ma weryfikowalny efekt, zanim powstaną encje,
i który pozwala sprawdzić wdrożenie bez dotykania bazy.

### Dodanie książki

```http
POST /api/books
{ "serialNumber": "123456", "title": "Wiedźmin", "author": "Andrzej Sapkowski" }

201 Created
Location: /api/books/123456
{ "serialNumber": "123456", "title": "Wiedźmin", "author": "Andrzej Sapkowski",
  "status": "available", "currentLoan": null }
```

### Lista książek

```http
GET /api/books

200 OK
{ "items": [
    { "serialNumber": "123456", "title": "Wiedźmin", "author": "Andrzej Sapkowski",
      "status": "available", "currentLoan": null },
    { "serialNumber": "654321", "title": "Lalka", "author": "Bolesław Prus",
      "status": "borrowed",
      "currentLoan": { "cardNumber": "998877", "borrowedAt": "2026-07-28T09:12:00+00:00" } }
  ] }
```

### Wypożyczenie i zwrot

Obie akcje zwracają ten sam `BookResponse` co pozostałe endpointy — pełny obiekt
książki ze zaktualizowanym stanem, nie fragment.

```http
POST /api/books/654321/borrow
{ "cardNumber": "998877" }

200 OK
{ "serialNumber": "654321", "title": "Lalka", "author": "Bolesław Prus",
  "status": "borrowed",
  "currentLoan": { "cardNumber": "998877", "borrowedAt": "2026-07-28T09:12:00+00:00" } }
```

```http
POST /api/books/654321/return

200 OK
{ "serialNumber": "654321", "title": "Lalka", "author": "Bolesław Prus",
  "status": "available", "currentLoan": null }
```

### Format błędu

```json
{
  "type": "/errors/validation",
  "title": "Validation failed",
  "status": 422,
  "violations": [
    { "field": "serialNumber", "message": "Numer seryjny musi składać się z 6 cyfr." }
  ]
}
```

```json
{
  "type": "/errors/conflict",
  "title": "Book is already borrowed",
  "status": 409,
  "detail": "Książka 654321 jest wypożyczona od 2026-07-28 na kartę 998877."
}
```

## Struktura kodu

```
src/
  Controller/
    BookController.php          # 5 akcji, cienki: DTO in, Response out
    LoanController.php          # historia wypożyczeń
  Dto/
    CreateBookRequest.php       # + constraints walidatora
    BorrowBookRequest.php
    BookResponse.php            # kształt odpowiedzi, nie encja
  Entity/
    Book.php
    Loan.php
  Repository/
    BookRepository.php          # findOneBySerialNumber, listWithCurrentLoan
    LoanRepository.php          # findActiveForBook
  Service/
    LibraryService.php          # borrow / return / delete — reguły biznesowe
  Exception/
    BookNotFoundException.php
    BookAlreadyBorrowedException.php
    BookNotBorrowedException.php
    DuplicateSerialNumberException.php
  EventListener/
    ApiExceptionListener.php    # wyjątek domenowy → problem+json
```

Odpowiedzi budowane z DTO (`BookResponse`), nie przez serializację encji — encja nie
wycieka do kontraktu API.

## Obsługa błędów

| Sytuacja | Wyjątek | Kod |
| --- | --- | --- |
| Nieznany numer seryjny | `BookNotFoundException` | 404 |
| Numer seryjny zajęty | `DuplicateSerialNumberException` | 409 |
| Wypożyczenie wypożyczonej | `BookAlreadyBorrowedException` | 409 |
| Zwrot niewypożyczonej | `BookNotBorrowedException` | 409 |
| Usunięcie wypożyczonej | `BookAlreadyBorrowedException` | 409 |
| Zły format numeru / brak pola | `ValidationFailedException` | 422 |
| Niepoprawny JSON | `BadRequestHttpException` | 400 |

Naruszenie partial unique indexu przy równoległych żądaniach łapane jako
`UniqueConstraintViolationException` i tłumaczone na 409 — nie 500.

## Docker

```
services:
  app      # FrankenPHP + kod, :8080, entrypoint: pg_isready → migracje → fixtures
  db       # postgres:17, healthcheck, named volume
  db_test  # postgres:17 dla testów, tmpfs, bez wolumenu
```

`docker compose up` na czystej maszynie ma dać działające API pod
`http://localhost:8080/api/books` bez żadnego kroku ręcznego — migracje i fixtures
odpalają się w entrypoincie po tym, jak baza zgłosi gotowość.

Fixtures ładują się wyłącznie wtedy, gdy zmienna `LOAD_FIXTURES=1` (domyślnie
włączona w `compose.yaml`, ustawiana też na hostingu, żeby oceniający zobaczył
dane) **i** tabela `book` jest pusta. Restart kontenera nie duplikuje więc rekordów
ani nie nadpisuje danych wprowadzonych ręcznie przez API.

Dockerfile wielostopniowy na bazie `dunglas/frankenphp`: `base` (rozszerzenia PHP,
Composer) → `dev` (kod bindowany z hosta, dev-dependencies) → `prod` (kod skopiowany
do obrazu, `composer install --no-dev --optimize-autoloader`, opcache, nasłuch na
`$PORT`). Kontener `app` działa jako `1000:1000`, czyli z UID-em użytkownika, więc
pliki tworzone w bindowanym katalogu nie wychodzą jako `root`.

## Testy

```
tests/
  Functional/
    BookApiTest.php      # POST 201 + Location, duplikat 409, zły serial 422,
                         # GET lista, DELETE 204 → GET 404, DELETE wypożyczonej 409
    LoanApiTest.php      # borrow 200, borrow drugi raz 409, return 200,
                         # return drugi raz 409, historia wypożyczeń
  Unit/
    Domain/LoanTest.php          # cykl życia wypożyczenia
    Service/LibraryServiceTest.php
    Dto/CreateBookRequestTest.php  # walidacja formatu numerów
```

Testy funkcjonalne strzelają po HTTP przez `WebTestCase` w bazę `db_test`, czyszczoną
między testami. Uruchamiane w kontenerze: `docker compose exec app bin/phpunit`.

## CI

GitHub Actions na każdy push i pull request: `composer install`, PHPStan level 8,
PHP-CS-Fixer w trybie `--dry-run`, PHPUnit na usłudze Postgres. Badge w README.

## README

Uruchomienie jedną komendą, tabela endpointów z przykładami `curl`, opis decyzji
projektowych (D1–D10 w skrócie) i sekcja „świadomie poza zakresem".

## Hosting

Railway lub Render: deploy obrazu `prod` z GitHuba, PostgreSQL jako osobna usługa,
konfiguracja wyłącznie przez zmienne środowiskowe (`DATABASE_URL`, `APP_ENV=prod`,
`APP_SECRET`, `LOAD_FIXTURES`), migracje przy starcie. Dzięki FrankenPHP wdrożenie to
jeden kontener nasłuchujący na `$PORT` — bez supervisora i bez osobnej usługi
serwera HTTP. `Dockerfile`, zmienne środowiskowe i instrukcja wdrożenia są częścią
repozytorium.

## Świadomie poza zakresem

Wypisane w README jako decyzje, nie przeoczenia:

- uwierzytelnianie i autoryzacja — wyłączone przez treść zadania,
- paginacja i filtrowanie listy — brak wymagania, biblioteka testowa jest mała,
- encja czytelnika — karta biblioteczna to w tym zadaniu wyłącznie numer,
- rezerwacje, kary za przetrzymanie, egzemplarze wielokrotne,
- soft delete, cache, kolejki, obserwowalność.
