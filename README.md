# API systemu bibliotecznego

[![CI](https://github.com/Corristo-X/kisslist-recruitment-task/actions/workflows/ci.yml/badge.svg)](https://github.com/Corristo-X/kisslist-recruitment-task/actions/workflows/ci.yml)

REST API do śledzenia i aktualizowania stanu książek posiadanych przez bibliotekę:
dodawanie, usuwanie, listowanie oraz wypożyczanie i przyjmowanie zwrotów.

- **Demo:** <LINK>
- **Dokumentacja interaktywna:** `<LINK>/api/doc`

## Uruchomienie

```bash
docker compose up
```

(jeśli Docker na Twojej maszynie wymaga uprawnień administratora, poprzedź komendę `sudo`)

API nasłuchuje na `http://localhost:8080`. Migracje uruchamiają się automatycznie
przy starcie kontenera. Dane przykładowe (5 książek, w tym jedna wypożyczona)
ładują się razem z nimi, ale tylko wtedy, gdy tabela `book` jest pusta — restart
kontenera nie duplikuje rekordów ani nie nadpisuje danych wprowadzonych przez API.

## Stack

PHP 8.5 · Symfony 8.1 · Doctrine ORM · PostgreSQL 17 · FrankenPHP

## Endpointy

| Metoda | Ścieżka | Opis |
| --- | --- | --- |
| POST | `/api/books` | Dodanie książki |
| GET | `/api/books` | Lista wszystkich książek |
| GET | `/api/books/{serial}` | Pojedyncza książka |
| DELETE | `/api/books/{serial}` | Usunięcie książki |
| POST | `/api/books/{serial}/borrow` | Wypożyczenie |
| POST | `/api/books/{serial}/return` | Przyjęcie zwrotu |
| GET | `/api/books/{serial}/loans` | Historia wypożyczeń |
| GET | `/api/health` | Kontrola żywotności aplikacji |
| GET | `/api/doc` | Swagger UI |
| GET | `/api/doc.json` | Specyfikacja OpenAPI |

### Przykłady

```bash
# dodanie książki
curl -X POST http://localhost:8080/api/books \
  -H 'Content-Type: application/json' \
  -d '{"serialNumber":"123456","title":"Lalka","author":"Bolesław Prus"}'

# lista
curl http://localhost:8080/api/books

# wypożyczenie na kartę 998877
curl -X POST http://localhost:8080/api/books/123456/borrow \
  -H 'Content-Type: application/json' \
  -d '{"cardNumber":"998877"}'

# zwrot
curl -X POST http://localhost:8080/api/books/123456/return

# usunięcie
curl -X DELETE http://localhost:8080/api/books/123456
```

## Format błędów

Wszystkie błędy zwracane są jako `application/problem+json` (RFC 7807):

```json
{
  "type": "/errors/validation",
  "title": "Walidacja nie powiodła się",
  "status": 422,
  "violations": [
    { "field": "serialNumber", "message": "Numer seryjny musi składać się dokładnie z 6 cyfr." }
  ]
}
```

## Decyzje projektowe

- **Czysty Symfony zamiast API Platform** — zadanie ocenia modelowanie danych
  i organizację kodu; generator CRUD-a ukryłby dokładnie te decyzje.
- **Wypożyczenie to osobna encja `Loan`, nie flaga na książce.** „Wypożyczona"
  oznacza istnienie rekordu z `returned_at IS NULL`. Daje to historię wypożyczeń
  i eliminuje stany niespójne typu `is_borrowed = true` bez numeru karty.
- **Spójności pilnuje baza, nie tylko kod** — partial unique index
  `(book_id) WHERE returned_at IS NULL` sprawia, że dwa równoległe żądania nie
  wypożyczą tej samej książki dwa razy.
- **Numery jako `VARCHAR(6)`** — `012345` jest poprawnym numerem seryjnym,
  typ całkowitoliczbowy zjadłby wiodące zero.
- **Numer seryjny jako identyfikator w URL** — pracownik ma go wydrukowanego na
  książce; wymaganie osobnego wyszukania technicznego ID byłoby sztuczne.
- **Wypożyczenie i zwrot jako akcje** (`/borrow`, `/return`), nie `PATCH` pola —
  to przejścia stanu z własnymi regułami i własnym payloadem.
- **Usunięcie wypożyczonej książki zwraca 409** — najpierw zwrot, potem
  usunięcie; inaczej znika informacja, kto trzyma egzemplarz.
- **FrankenPHP zamiast nginx + PHP-FPM** — platformy typu Railway czy Render
  uruchamiają pojedynczy kontener, więc układ dwuprocesowy wymagałby supervisora
  w obrazie produkcyjnym albo rozjazdu między lokalnym stackiem a wdrożeniem.
  FrankenPHP zawiera serwer HTTP w jednym procesie: identyczna warstwa HTTP
  lokalnie i na hostingu, deploy sprowadzony do zwykłego `Dockerfile`. To też
  domyślny wybór oficjalnego szablonu Symfony Docker.
- **500 loguje się po stronie serwera, klient dostaje generyczny `problem+json`.**
  Listener wyjątków wywołuje `setResponse()` na `ExceptionEvent`, co zatrzymuje
  dalszą propagację zdarzenia — w tym domyślny listener Symfony, który normalnie
  loguje wyjątek. Dlatego nieoczekiwane błędy są logowane jawnie, zanim odpowiedź
  trafi do klienta; szczegóły wyjątku nigdy nie wyciekają w treści odpowiedzi.

## Świadomie poza zakresem

Uwierzytelnianie i autoryzacja (wyłączone przez treść zadania), paginacja
i filtrowanie listy, encja czytelnika (karta biblioteczna to w tym zadaniu sam
numer), rezerwacje, kary za przetrzymanie, wiele egzemplarzy tego samego tytułu,
soft delete, cache i kolejki.

## Testy i jakość

```bash
docker compose exec app composer test       # migracje bazy testowej + PHPUnit
docker compose exec app composer phpstan    # analiza statyczna, level 8
docker compose exec app composer cs:check   # styl kodu, PSR-12 + @Symfony
```

57 testów: funkcjonalne strzelają po HTTP w osobną bazę `db_test`, jednostkowe
pokrywają walidację DTO, cykl życia wypożyczenia i mapowanie błędów na
`problem+json`. Ten sam zestaw uruchamia się w GitHub Actions przy każdym pushu.
