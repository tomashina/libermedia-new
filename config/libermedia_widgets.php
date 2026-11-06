<?php

$field = static fn (string $label, string $type, mixed $default, string $group = ''): array => compact('label', 'type', 'default', 'group');

$benefits = [
    'auto_shipping' => $field('Automatski prikaži uvjete dostave iz postavki trgovine', 'checkbox', true, 'Dostava'),
];
foreach ([
    ['truck-fast', 'Dostava', 'Provjerite mogućnosti dostave', '/page/nacin-placanja-i-dostava'],
    ['credit-card', 'Sigurno plaćanje', 'Kupujte jednostavno i bez brige', '/page/nacin-placanja-i-dostava'],
    ['shield-check', 'Zaštita kupca', 'Od narudžbe do dostave', ''],
    ['heart-pulse', 'Zdravstvena provjerenost', 'Držimo do sigurnosti vaše djece', ''],
] as $index => [$icon, $title, $description, $url]) {
    $key = 'benefit_'.($index + 1).'_';
    $group = 'Prednost '.($index + 1);
    $benefits[$key.'icon'] = $field('Ikona', 'icon', $icon, $group);
    $benefits[$key.'title'] = $field('Naslov', 'text', $title, $group);
    $benefits[$key.'description'] = $field('Opis', 'text', $description, $group);
    $benefits[$key.'url'] = $field('Poveznica (neobavezno)', 'url', $url, $group);
}

$campaigns = [];
foreach ([
    'bag' => ['Školske torbe', 'ZA SVAKI ŠKOLSKI DAN', "Velike avanture\npočinju s dobrom torbom.", 'Školske torbe i ruksaci za sve male osobnosti.', 'Pronađi svoju torbu', '/category/skolske-torbe-i-ruksaci'],
    'meli' => ['Meli kocke', 'MELI KOCKE', "Male kocke.\nBezbroj velikih ideja.", 'Spoji, rastavi i izmisli nešto sasvim novo.', 'Istraži Meli', '/manufacturer/ame-group-marcin-hernas'],
    'montessori' => ['Montessori igre', 'MONTESSORI IGRE', "Učim svojim\nmalim koracima.", 'Otkrijte igre za znatiželjne prstiće i glavice.', 'Otkrij igre', '/category/didakticke-igre-za-djecu'],
] as $key => [$group, $eyebrow, $title, $description, $ctaLabel, $ctaUrl]) {
    $campaigns[$key.'_eyebrow'] = $field('Nadnaslov', 'text', $eyebrow, $group);
    $campaigns[$key.'_title'] = $field('Naslov (novi red prelama naslov)', 'textarea', $title, $group);
    $campaigns[$key.'_description'] = $field('Opis', 'textarea', $description, $group);
    $campaigns[$key.'_cta_label'] = $field('Tekst gumba', 'text', $ctaLabel, $group);
    $campaigns[$key.'_cta_url'] = $field('Poveznica gumba', 'url', $ctaUrl, $group);
    $campaigns[$key.'_cta_icon'] = $field('Ikona gumba', 'icon', 'arrow-right', $group);
}
$campaigns['bag_image_back'] = $field('Stražnja slika torbe', 'image', '/assets/libermedia/home/schoolbag-jurassic.webp', 'Školske torbe');
$campaigns['bag_image_front'] = $field('Prednja slika torbe', 'image', '/assets/libermedia/home/schoolbag-unicorn.webp', 'Školske torbe');
$campaigns['meli_image'] = $field('Slika', 'image', '/assets/libermedia/home/meli-model-pink.webp', 'Meli kocke');
$campaigns['meli_image_alt'] = $field('Opis slike', 'text', 'Model složen od Meli kocki', 'Meli kocke');
$campaigns['montessori_image'] = $field('Slika', 'image', '/assets/libermedia/home/montessori-play.webp', 'Montessori igre');
$campaigns['montessori_image_alt'] = $field('Opis slike', 'text', 'Drvena umetaljka s geometrijskim oblicima u bojama', 'Montessori igre');

$assurance = [
    'image' => $field('Slika garancije', 'image', '/assets/brand/libermedia-guarantee.svg', 'Uvod'),
    'body' => $field('Tekst (dopušteni su <strong>naglasci</strong> i novi redovi)', 'textarea', 'Naše <strong>didaktičke igračke</strong> i <strong>oprema za vrtiće</strong> pomno su birani. Nakon više godina praktičnog istraživanja i rada naučili smo na vlastitim greškama te odabrali najbolje partnere. Toliko smo sigurni u <strong>kvalitetu</strong> naših proizvoda da omogućujemo <strong>povrat unutar 2 tjedna</strong> od kupovine. Vjerujemo da ćete uz naše materijale ponešto naučiti, ali i dobro se zabaviti. Sretno!', 'Uvod'),
];
foreach ([
    ['heart', 'Pažljivo odabrana ponuda', 'Partneri i materijali kojima vjerujemo.', '', ''],
    ['comments', 'Tu smo za vas', 'Rado ćemo pomoći pronaći pravi izbor.', '', ''],
    ['rotate-left', '14 dana za povrat', 'Za potpunu sigurnost pri odabiru.', 'Uvjeti povrata', '/page/opci-uvjeti-koristenja'],
] as $index => [$icon, $title, $description, $ctaLabel, $ctaUrl]) {
    $key = 'point_'.($index + 1).'_';
    $group = 'Prednost '.($index + 1);
    $assurance[$key.'icon'] = $field('Ikona', 'icon', $icon, $group);
    $assurance[$key.'title'] = $field('Naslov', 'text', $title, $group);
    $assurance[$key.'description'] = $field('Opis', 'textarea', $description, $group);
    $assurance[$key.'cta_label'] = $field('Tekst poveznice (neobavezno)', 'text', $ctaLabel, $group);
    $assurance[$key.'cta_url'] = $field('Poveznica (neobavezno)', 'url', $ctaUrl, $group);
    $assurance[$key.'cta_icon'] = $field('Ikona poveznice', 'icon', 'arrow-right', $group);
}

$carouselFields = static fn (string $eyebrow): array => [
    'eyebrow' => $field('Nadnaslov', 'text', $eyebrow),
    'cta_icon' => $field('Ikona poveznice', 'icon', 'arrow-right'),
    'previous_label' => $field('Opis gumba za prethodne proizvode', 'text', 'Prethodni proizvodi'),
    'next_label' => $field('Opis gumba za sljedeće proizvode', 'text', 'Sljedeći proizvodi'),
];

return [
    'libermedia-home-benefits' => [
        'label' => 'Prednosti kupnje',
        'description' => 'Četiri prednosti kupnje u podnožju početne stranice. Prva može automatski pratiti uvjete dostave.',
        'content_fields' => [], 'content_defaults' => [], 'fields' => $benefits,
    ],
    'libermedia-home-campaigns' => [
        'label' => 'Odabrana ponuda',
        'description' => 'Tri promotivne kartice za školske torbe, Meli kocke i Montessori igre.',
        'content_fields' => ['title', 'subtitle'],
        'content_defaults' => ['title' => 'Pronađite svoju novu avanturu', 'subtitle' => 'MALI SVIJET. VELIKE MOGUĆNOSTI.'],
        'fields' => $campaigns,
    ],
    'libermedia-home-kindergarten' => [
        'label' => 'Oprema za vrtiće',
        'description' => 'Predstavljanje opremanja vrtića s dvije fotografije i poveznicom.',
        'content_fields' => ['title', 'subtitle', 'cta_label', 'cta_url'],
        'content_defaults' => ['title' => "Oprema i namještaj\nza vrtiće", 'subtitle' => 'PROSTOR ZA SRETNO ODRASTANJE', 'cta_label' => 'Saznajte više', 'cta_url' => 'https://namjestajzavrtice.com/'],
        'fields' => [
            'description' => $field('Prvi odlomak', 'textarea', 'Od prve ideje do posljednjeg detalja. Pomažemo stvoriti poticajan prostor u kojem se djeca mogu igrati, istraživati i rasti.'),
            'description_secondary' => $field('Drugi odlomak', 'textarea', 'Pratimo vas kroz cijeli postupak, od izrade projekta do opremanja interijera i vanjskih igrališta.'),
            'image' => $field('Fotografija interijera', 'image', '/assets/libermedia/home/kindergarten-interior.webp'),
            'image_alt' => $field('Opis fotografije interijera', 'text', 'Svijetao i opremljen prostor dječjeg vrtića'),
            'image_outdoor' => $field('Fotografija igrališta', 'image', '/assets/libermedia/home/kindergarten-playground.webp'),
            'image_outdoor_alt' => $field('Opis fotografije igrališta', 'text', 'Opremljeno vanjsko dječje igralište'),
            'cta_icon' => $field('Ikona gumba', 'icon', 'arrow-up-right'),
        ],
    ],
    'libermedia-home-assurance' => [
        'label' => 'Garancija',
        'description' => 'Uvod u garanciju i tri prednosti kupnje.',
        'content_fields' => ['title', 'subtitle'],
        'content_defaults' => ['title' => 'Više igre. Manje brige.', 'subtitle' => 'GARANCIJA'],
        'fields' => $assurance,
    ],
    'libermedia-home-creative-products' => [
        'label' => 'Izdvojeni proizvodi',
        'description' => 'Odabrani proizvodi s naslovom i poveznicom na ponudu.',
        'content_fields' => ['title', 'cta_label', 'cta_url'],
        'content_defaults' => ['title' => 'Za male graditelje i velike ideje', 'cta_label' => 'Sve didaktičke igračke', 'cta_url' => '/category/didakticke-igracke'],
        'translation_content' => true, 'items_limit' => 8,
        'fields' => $carouselFields('MAŠTA NEMA GRANICA'),
    ],
    'libermedia-home-promotions' => [
        'label' => 'Akcije i popusti',
        'description' => 'Odabrani proizvodi s trenutačno aktivnim akcijskim cijenama.',
        'content_fields' => ['title', 'cta_label', 'cta_url'],
        'content_defaults' => ['title' => 'Akcije i popusti', 'cta_label' => 'Sve akcije', 'cta_url' => '/akcije'],
        'translation_content' => true, 'items_limit' => 8,
        'fields' => $carouselFields('ODABRANA AKCIJSKA PONUDA'),
    ],
];
