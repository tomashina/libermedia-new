# Libermedia New

Laravel 12 osnova za novu Liber Media trgovinu, kopirana iz projekta `herrera-new`.

Lokalno: http://libermedia-new.test

Projekt koristi zasebni `.env`, `APP_KEY` i SQLite bazu. Lokalni OpenCart izvor spojen je putem zasebne MySQL veze `libermedia_source`, sa session postavkom `TRANSACTION READ ONLY`.

## Lokalni uvoz

- `php artisan migrate`
- `php artisan libermedia:import --dry-run`
- `php artisan libermedia:import`
- Kopirati originalnu OpenCart mapu `image` u `public/image` bez generiranih predmemorija.
- `php artisan storage:link`
- `php artisan libermedia:import-assets`
- `php artisan libermedia:configure-preview`

Privatne postavke izvora nalaze se samo u `.env`. Baza, slike, izvještaji i sigurnosne kopije nisu u Gitu. Instalacija bez lokalnih podataka zahtijeva `composer install`, `npm install`, `npm run build` i novi `APP_KEY`.

Stanje prijenosa i poznate iznimke: [docs/libermedia-migration.md](docs/libermedia-migration.md).

Naslijeđene Herrera naredbe, integracije i deployment skripte zahtijevaju prilagodbu prije korištenja za Libermediju. Redizajn još nije rađen.

## Vizualna osnova

Kod je preuzet iz lokalnog Herrera brancha `codex/herrera-b2b-foundation` (izvorni commit `16948f5`).

`STOREFRONT_THEME=herrera` uključuje isti raspored, tipografiju i komponente neovisno o nazivu trgovine. `STOREFRONT_HOME=libermedia` prikazuje Libermedia uvod, stvarne kategorije, proizvode i brandove. Logo i podaci ostaju Liber Media.

`libermedia:configure-preview` postavlja lokalnu navigaciju, zaglavlje i footer te iz postojećih proizvoda priprema fotografije glavnih kategorija kojima izvor nema sliku. B2C prikaz cijena uključen je za Libermedia pregled.

## Liber Media identitet

`STOREFRONT_BRAND=libermedia` uključuje narančasto-zelenu paletu neovisno o rasporedu. Glavni logo je vektorski `public/assets/brand/libermedia-logo.svg`, uz svijetlu varijantu za tamni prikaz. Brand narančasta je `#FF4D00`, dopunska zelena `#245B42`, uz tople svijetle podloge.

Početna prikazuje šest brandova u širokom slideru i tri najnovija objavljena blog članka. Blog blok prati postavku bloga i ne ponavlja već postavljen aktivni CMS blog blok.

Glavni izbornik prati originalni redoslijed (Brandovi, Akcije, Posebna ponuda, Blog, O nama, Kontakt), uz Poklon bon s ikonom skroz desno. Stavke, ikona i desno poravnanje uređuju se u **Sadržaj → Navigacija**; ondje su i kontakti te Facebook/Instagram gornje trake. Društvene mreže podnožja uređuju se u postavkama trgovine. `php artisan libermedia:configure-preview --navigation-only` postavlja samo ove podatke. Posebna ponuda i Poklon bon zadržavaju postojeće poveznice na `www.liber-media.hr` jer njihovi OpenCart moduli još nemaju Laravel zamjenu.

`php artisan libermedia:configure-home` dodaje šest CMS modula: prednosti kupnje, tri nove promocije (torbe, Meli, Montessori), opremu za vrtiće, izbor osam dostupnih igračaka, akcijske artikle i garanciju s punim izvornim tekstom. Akcije su između brandova i garancije. Ponovno pokretanje čuva postojeće module; `--force` obnavlja njihove početne postavke. Uređuju se kroz **Sadržaj → Blokovi**, u rasporedu početne stranice i podnožja. Obrasci uređuju tekstove, slike, poveznice i odabrane proizvode; podaci se spremaju u bazu, a Git predlošci ostaju nepromijenjeni. Strelice mijenjaju redoslijed unutar iste pozicije, a prednosti kupnje prikazuju se u podnožju. Polja su definirana u `config/libermedia_widgets.php`; prije prvog spremanja koriste izvorni sadržaj kao početne vrijednosti. Kategorije imaju odgovarajuće zelene/narančaste ikone i desetu karticu za komplete. Liber Media prikazuje samo svijetli izgled i običnu prijavu kupaca, bez B2B poziva i registracije tvrtke. Dostupnost je jedan status prema stvarnoj zalihi, a dodavanje u košaricu otvara potvrdu sa slikom, nazivom i odabranom količinom.

Prije postavljanja akcijskog modula pokreće se `php artisan libermedia:import-promotions`. Naredba pretvara provjerene akcijske cijene u javne proizvodne akcije za B2C, čuva naknadne administratorske promjene i ne aktivira naslijeđeni B2B cjenik.

Pozadinski motiv kombinira dječje ikone `robot`, `teddy-bear`, `balloons`, `paintbrush-pencil`, `duck`, `dinosaur` i `kite` (Font Awesome Pro 7.3.1, light) iz već instaliranog seta. Robot, medvjedić i baloni su najvidljiviji. Kist s olovkom dodaje motiv kreativnog rada, a patkica, dinosaur i zmaj ostaju diskretni sporedni detalji. Veliki obrisi dijele iste fiksne koordinate kroz gornju traku, pretragu, hero i obojene trake. Podloge tih površina koriste zelene tonove; narančasti logo i gumbi ostaju dio identiteta.
