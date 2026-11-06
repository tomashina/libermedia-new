<?php

$manual = require __DIR__.'/admin_manual.php';
$manual['intro'] = 'Upute prate Libermedia administraciju. Odaberite područje, otvorite temu i slijedite postupak za uređivanje trgovine.';
$manual['quick_start'][3]['text'] = 'Nakon spremanja otvorite povezani ekran ili webshop i provjerite sadržaj, fotografije, poveznice i prikaz na mobitelu.';

$overrides = [
    'artikli' => [
        'purpose' => 'Uređivanje proizvoda, cijene, zalihe, opisa, slika i pripadnosti katalogu.',
        'steps' => [
            'U Katalog / Artikli pronađite artikl pretragom ili izradite novi zapis.',
            'Unesite naziv, šifru, cijenu, zalihu i opis pa spremite osnovne podatke.',
            'Dodijelite kategorije i brend te dodajte glavnu fotografiju i galeriju.',
            'Ako se koriste, dodajte atribute i opcije artikla.',
            'Provjerite aktivnost artikla, spremite i otvorite ga na webshopu.',
        ],
        'fields' => [
            ['name' => 'Šifra i SKU', 'description' => 'Jedinstvene oznake za pronalazak i povezivanje artikla.'],
            ['name' => 'Cijena, zaliha i porez', 'description' => 'Podaci koji određuju prodajnu cijenu i dostupnost.'],
            ['name' => 'Naziv, slug i opis', 'description' => 'Sadržaj i javna adresa artikla za odabrani jezik.'],
            ['name' => 'Kategorije i brend', 'description' => 'Grupiranje artikla u katalogu i filtrima.'],
            ['name' => 'Slike', 'description' => 'Glavna fotografija i galerija artikla.'],
        ],
        'warning' => '',
    ],
    'opcije-kataloga' => [
        'purpose' => 'Uključivanje ili skrivanje opcijskih modula poput bloga, atributa, opcija, brendova i akcija.',
    ],
    'opcije' => [
        'steps' => [
            'U Katalog / Opcije izradite opciju i njezine vrijednosti.',
            'Otvorite artikl i odaberite tab Opcije.',
            'Dodijelite opciju artiklu i izradite potrebne kombinacije.',
            'Za svaku kombinaciju provjerite SKU, zalihu i eventualnu posebnu cijenu pa spremite.',
        ],
    ],
    'blokovi' => [
        'purpose' => 'Uređivanje widgeta početne stranice i podnožja: promotivne ponude, proizvodi, vrtići, garancija i prednosti kupnje.',
        'steps' => [
            'U Sadržaj / Blokovi pronađite widget u rasporedu početne stranice ili podnožja.',
            'Gumbom Uredi otvorite tekstove, fotografije i poveznice tog widgeta.',
            'Kod proizvoda odaberite artikle i složite njihov redoslijed.',
            'Na popisu uključite ili isključite widget, a strelicama promijenite redoslijed unutar njegove pozicije.',
            'Spremite sadržaj i otvorite pregled webshopa da provjerite rezultat.',
        ],
        'fields' => [
            ['name' => 'Tekstovi i gumbi', 'description' => 'Naslovi, opisi i poveznice prikazane na webshopu.'],
            ['name' => 'Fotografije', 'description' => 'Pregled postojeće slike, odabir nove fotografije i opis slike.'],
            ['name' => 'Vidljivost i redoslijed', 'description' => 'Uključivanje widgeta i njegov položaj unutar pripadajuće pozicije.'],
            ['name' => 'Odabrani artikli', 'description' => 'Proizvodi i njihov redoslijed u kolekciji ili akcijskoj ponudi.'],
        ],
        'warning' => 'Redoslijed se mijenja unutar iste pozicije. Prednosti kupnje nalaze se u podnožju stranice.',
    ],
    'lista-korisnika' => [
        'purpose' => 'Pretraga i pregled korisničkih računa, profila, adresa, grupa, narudžbi i aktivnosti.',
    ],
    'grupe-korisnika' => [
        'purpose' => 'Grupiranje kupaca radi akcija, loyalty pravila i drugih ciljanih funkcija.',
        'steps' => [
            'Izradite grupu s jasnom stabilnom šifrom i nazivom.',
            'Postavite aktivnost, redoslijed i eventualno označite zadanu grupu.',
            'Dodijelite korisnike grupi iz njihovog profila.',
            'Provjerite akcije i ostala pravila vezana uz grupu.',
        ],
    ],
];

$excluded = ['b2b-cjenici', 'wholesale-api', 'b2b-zahtjevi'];
$sections = [];
foreach ($manual['sections'] as $section) {
    if ($section['id'] === 'integracije') {
        continue;
    }

    $items = [];
    foreach ($section['items'] as $item) {
        if (! in_array($item['id'], $excluded, true)) {
            $items[] = array_replace($item, $overrides[$item['id']] ?? []);
        }
    }

    if ($section['id'] === 'katalog') {
        $section['description'] = 'Artikli, kategorije, svojstva, brendovi i akcije.';
    }
    if ($section['id'] === 'korisnici') {
        $section['description'] = 'Korisnički računi, grupe, ovlasti i aktivnosti.';
    }
    if ($section['id'] === 'postavke') {
        $section['description'] = 'Plaćanje, dostava, lokalni šifrarnici, sustav i korisnički moduli.';
    }

    $section['items'] = $items;
    $sections[] = $section;
}
$manual['sections'] = $sections;

return $manual;
