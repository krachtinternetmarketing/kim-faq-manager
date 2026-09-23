# FAQ Manager — WordPress Plugin

Versie 3.12.0 | Vereist WordPress 6.0+ en PHP 8.0+

---

## Installatie

1. Upload de map `kim-faq-manager` naar `/wp-content/plugins/`
2. Activeer de plugin via **WordPress Dashboard → Plugins**
3. Het nieuwe menu-item **FAQ Manager** verschijnt in de zijbalk

> Bij updaten: **vervang** de bestaande map `kim-faq-manager`. Zet de nieuwe
> versie niet als tweede map ernaast — dat geeft een fatale fout.

---

## Automatische updates (GitHub)

Vanaf 3.12.0 controleert de plugin zelf op nieuwe versies via de GitHub-releases
van de repo `krachtinternetmarketing/kim-faq-manager`. Zodra er een nieuwere
release is, verschijnt de update onder **Plugins → Updates**, met één klik
bijwerken — net als elke andere plugin. Geen externe library nodig.

### Een nieuwe versie uitrollen

1. Hoog het versienummer op in `faq-manager.php` (header **Version** én de
   constante `FAQM_VERSION`) — deze twee moeten gelijk zijn.
2. Zet de gewijzigde bestanden in de repo (bijv. via GitHub Desktop).
3. Maak op GitHub een **Release** aan met tag `3.12.0` (of de nieuwe versie;
   een voorloop-`v` mag, `v3.12.0` werkt ook).
4. Voeg de plugin-zip als **asset** toe aan de release. De map ín de zip moet
   `kim-faq-manager` heten. (Zonder asset gebruikt de plugin automatisch de
   "Source code (zip)"; de mapnaam wordt bij installatie sowieso rechtgezet.)
5. Publiceer de release. Binnen enkele uren zien alle sites de update; forceren
   kan via **Dashboard → Updates → Opnieuw controleren**.

Het versienummer van de plugin is leidend: is de release-versie hoger dan de
geïnstalleerde versie, dan wordt de update aangeboden.

### Private repo (optioneel)

Wordt de repo later privé gemaakt, definieer dan op elke site in `wp-config.php`:

```php
define( 'FAQM_GITHUB_TOKEN', 'github_pat_...' ); // fine-grained token, Contents: Read-only
```

Zonder token werkt een publieke repo zonder verdere instellingen.

---

## Gebruik

### 1. FAQ-vragen aanmaken

Ga naar **FAQ Manager → Nieuwe vraag toevoegen**.

- **Titel** → de vraag zelf (bijv. *Wat is de levertijd?*)
- **Lang antwoord** → uitgebreide versie met volledige opmaak (koppen, lijsten, links). Dit verschijnt op de FAQ-overzichtspagina.
- **Kort antwoord** → beknopte samenvatting met basisopmaak. Dit verschijnt bij de WooCommerce productpagina's.

---

### 2. FAQ-overzichtspagina

Maak een pagina aan in WordPress en voeg de shortcode toe:

```
[faq_overview]
```

**Opties:**

| Attribuut     | Standaard | Beschrijving                          |
|---------------|-----------|---------------------------------------|
| `show_search` | `yes`     | Toon zoekbalk boven de FAQ            |
| `accordion`   | `yes`     | Gebruik accordeon-stijl               |
| `framework`   | instelling| `bs5` of `standalone` (zie hieronder) |
| `group`       | instelling| `letter` of `category` (zie hieronder)|

Voorbeeld met opties:
```
[faq_overview show_search="yes"]
```

De pagina toont alle FAQ-vragen **alfabetisch gegroepeerd** per beginletter, met een klikbare letter-navigatie en live-zoekfunctie.

---

### 3. WooCommerce-koppeling

Open een product in WooCommerce (**Producten → bewerken**).

In de metabox **"Gekoppelde FAQ-vragen"** zie je alle gepubliceerde FAQ-vragen. Klik de vragen aan die bij dit product horen.

Op de productpagina verschijnen vervolgens de gekoppelde vragen met hun **korte antwoorden** als uitklapbare accordeon.

---

### 4. Schema markup (SEO / Google / AI)

De plugin genereert automatisch **JSON-LD FAQPage schema** op:

- De FAQ-overzichtspagina (lange antwoorden)
- Elke WooCommerce productpagina met gekoppelde FAQ's (korte antwoorden)

Je hoeft hier verder niets voor in te stellen. De markup is conform de [Google FAQ rich result-richtlijnen](https://developers.google.com/search/docs/appearance/structured-data/faqpage).

---

## FAQ's koppelen aan andere posttypes

Naast WooCommerce-producten kun je FAQ-vragen ook aan andere posttypes koppelen
(bijv. een eigen CPT `trainingen`, of gewone pagina's/berichten).

1. Ga naar **FAQ Manager → Instellingen → FAQ's koppelen aan posttypes** en
   vink de gewenste posttypes aan.
2. Op het bewerkscherm van die items verschijnt dan de metabox
   **Gekoppelde FAQ-vragen** met een zoekbare keuzelijst.
3. De gekozen vragen worden standaard **automatisch onder de inhoud** getoond.
   Wil je ze zelf plaatsen? Zet "Automatisch tonen" uit en gebruik in de
   template:

   ```php
   <?php if ( function_exists( 'faqm_render_faqs' ) ) faqm_render_faqs( get_the_ID() ); ?>
   ```

De titel boven het blok en of het korte/lange antwoord wordt getoond, stel je
in dezelfde instellingensectie in. De weergave volgt automatisch de
framework-instelling (Bootstrap 5 of standalone).

---

## Categorieën (groeperen per categorie)

Standaard groepeert de overzichtspagina vragen **per beginletter**. Soms is
groeperen **per categorie** handiger. Daarvoor heeft elke FAQ-vraag een
categorie-veld (taxonomie `faq_category`), te vinden als metabox op het
bewerkscherm van een vraag en als aparte submenu-pagina onder FAQ Manager →
Categorieën.

Inschakelen van categorie-groepering:

- **Globaal:** FAQ Manager → Instellingen → *Weergave / framework* →
  **Groepering overzicht** → **Per categorie**.
- **Per shortcode:** `[faq_overview group="category"]` (overschrijft de instelling).

Vragen zonder categorie worden automatisch ondergebracht onder **Algemeen**.
Heeft een vraag meerdere categorieën, dan wordt de eerste (alfabetisch)
als primaire categorie gebruikt. De categorieën worden alfabetisch getoond,
met "Algemeen" bovenaan. Werkt in zowel de Bootstrap 5- als de
standalone-weergave.

### Alleen vragen uit één categorie tonen

Wil je ergens alleen de vragen uit één FAQ-categorie tonen (bijvoorbeeld op een
WooCommerce-categoriepagina i.p.v. alle vragen), gebruik dan de
`category`-parameter met de slug (of id) van de FAQ-categorie:

```
[faq_overview show_search="no" category="dakbedekking"]
```

Meerdere categorieën mogen komma-gescheiden. Zonder treffers toont de shortcode
niets. Dit filtert op de FAQ-categorie (`faq_category`) en staat los van de
WooCommerce-productcategorie.

In een gedeeld categoriesjabloon kun je de slug van de huidige categorie
meegeven:

```php
<?php
$term = get_queried_object();
$slug = ( $term instanceof WP_Term ) ? $term->slug : '';
echo do_shortcode( '[faq_overview show_search="no" category="' . esc_attr( $slug ) . '"]' );
?>
```

---

## Bootstrap-compatibiliteit (Bootstrap 5 vs. standalone)

Standaard genereert de plugin een **Bootstrap 5**-accordeon (`data-bs-toggle`). Dat
vereist dat het thema Bootstrap 5 meelevert.

Draait de site op **Bootstrap 3** of helemaal geen Bootstrap? Zet de plugin dan in
**standalone-modus**. De accordeon werkt dan zonder Bootstrap- of Font Awesome-
afhankelijkheid: eigen `faqm-acc__*` klassen, een eigen open/dicht-toggle in
`frontend.js` en een plus/min-icoon via CSS.

Inschakelen kan op twee manieren:

- **Globaal:** FAQ Manager → Instellingen → *Weergave / framework* → **Standalone**.
- **Per shortcode:** `[faq_overview framework="standalone"]` (overschrijft de instelling).

De Bootstrap 5-output blijft de standaard; bestaande sites veranderen niet.

---

## Stijlen aanpassen

De plugin laadt automatisch `assets/css/frontend.css`. Je kunt de stijlen overschrijven in je thema's `style.css` of via **Weergave → Aanpassen → Extra CSS**.

Alle klassen beginnen met `.faqm-` om conflicten te vermijden.

---

## Bestandsstructuur

```
faq-manager/
├── faq-manager.php                    ← Hoofd plugin-bestand
├── includes/
│   ├── class-faq-post-type.php        ← Custom Post Type registratie
│   ├── class-faq-meta.php             ← Meta boxes (lang/kort antwoord)
│   ├── class-faq-accordion.php        ← Standalone accordeon-markup (geen Bootstrap)
│   ├── class-faq-taxonomy.php         ← Taxonomie faq_category (categorieën)
│   ├── class-faq-posttypes.php        ← FAQ-koppeling voor overige posttypes
│   ├── class-faq-updater.php          ← Automatische updates via GitHub-releases
│   ├── class-faq-shortcode.php        ← [faq_overview] shortcode
│   ├── class-faq-woocommerce.php      ← WooCommerce integratie
│   ├── class-faq-category.php         ← Categoriepagina + [faq_category]
│   ├── class-faq-import.php           ← CSV-import
│   ├── class-faq-settings.php         ← Instellingenpagina
│   └── class-faq-schema.php           ← Schema.org helpers
└── assets/
    ├── css/
    │   ├── admin.css                  ← Admin-stijlen
    │   └── frontend.css               ← Frontend-stijlen
    └── js/
        ├── admin.js                   ← Admin JS
        └── frontend.js                ← Accordeon + zoeken
```

---

## Compatibiliteit

- WordPress 6.0+
- WooCommerce 7.0+ (optioneel; plugin werkt ook zonder WooCommerce)
- PHP 8.0+
- Compatibel met Yoast SEO en RankMath (geen conflicten met schema)
