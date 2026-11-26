# CLAUDE.md – Cykla & Vandra AI-sök

WordPress-plugin för cyklavandra.se (Avada-tema, WPML, The Events Calendar). Kunden skriver
en fritextbeskrivning av sin drömresa och får matchande resor från Claude. Planen är att den
här sökningen ska bli grunden till en ny förstasida.

Ägare: Pär (Cykla & Vandra). Skriv till honom på **svenska**, kort och konkret.

## Arkitektur

- `cyklavandra-ai-sok.php` – allt serverlogik:
  - **Katalog** (`cvai_get_catalog`, `cvai_build_catalog`): publicerade sidor med taggen
    `guidad`/`självguidad` (+ varianter på andra språk) och kommande evenemang från
    The Events Calendar. En katalog per WPML-språk, cachad i transient
    `cvai_catalog_{lang}_{ver}_{CVAI_VERSION}`; `cvai_flush_catalog()` bumpar `cvai_cat_ver`.
  - **Destinationssidor** (`cvai_destination_pages`): undersidor i alla nivåer till sidan i
    inställningen `dest_parent` (standard `information/hitta-resa/destinationer`), id `D…`, i
    katalogens `dests`. AI:n lägger dem i fältet `destinationer` (max 2, mest specifika platsen);
    visas längst ner under "Läs mer om destinationen".
  - **Kostnad**: inställningarna `catalog_mode` (`full`/`slim`), `prefilter` och `cache_ttl` (`off`/`5m`/`1h`).
    `slim` = `cvai_slim_item()` (faktarutan Längd/Dagsetapper/Boende/Säsong/Pris + 160 tecken beskrivning),
    `cvai_prefilter()` = aktivitet, land/region (`cvai_region_countries()`), gravel – aldrig månad (taggarna är
    ofullständiga). Loggen får `katalog` och `cache` i tolkningen. Jämför upplägg med `tools/jamfor-upplagg.php`
    (lokal WordPress, se `tools/README.md`) – aldrig på sajten.
    På sajten: adminsidan `admin.php?page=cvai-jamfor` (`cvai_compare_page`, REST `cvai/v1/jamfor`) kör
    upplägg A–E via `$GLOBALS['cvai_override']` (bara det anropet), loggas med `kalla: jämförelse`, `intern: 1`.
  - **Svårighet** från taggar `hike1–4` / `bike1–4` (även WPML-slugs som `hike2-2`). Taggar på
    översatta språk matchas också via sin svenska motsvarighet (`cvai_tag_source`, WPML `wpml_object_id`),
    så nivå, land, aktivitet och guidad/självguidad fungerar även när termen fått ett annat namn.
  - **REST** `POST /wp-json/cvai/v1/search` `{q, lang}` → Anthropic Messages API med
    `tool_choice` på verktyget `visa_resor` (strukturerat svar). Katalogen ligger i
    systemprompten med `cache_control`.
  - **Export** `GET /wp-json/cvai/v1/historik?fran=ÅÅÅÅ-MM-DD&till=…&format=json|csv`, nyckel i
    option `cvai_export_key` (`Authorization: Bearer` eller `X-Cvai-Key`). Maskerar e-post/telefon.
    Kostnad per sökning: `cvai_cost_usd()` (priser i `cvai_model_price()`, filtret `cvai_model_prices`) sparas som `usd`
    och `modell` i tolkningen; adminlistan visar kronor med inställningen `kr_per_usd`. Uppdatera priserna när nya modeller kommer.
    Daglig cron `cvai_cleanup_log` rensar loggen efter `log_months` (standard 12).
    Kolumnen `visitor` = `cvai_visitor_id()` (HMAC av IP + månad med `wp_salt`), aldrig rå IP.
    Sökningar från exempelknapparna skickar `exempel: true` och loggas med `kalla: exempelknapp` i tolkningen
    (fältet `exempelknapp` i exporten).
    Inloggad personal (`edit_posts`, via `wp_validate_auth_cookie` – ingen nonce p.g.a. sidcache) loggas
    med `intern: 1` (fältet `intern` i exporten).
  - **Klick** `POST /wp-json/cvai/v1/klick {sok, token, id, del, plats}` via `sendBeacon`; `token` =
    `cvai_click_token(sok)`. Sparas i loggkolumnen `clicks` (`P123:best:1,…`, DB-version 4).
  - **Sparat resultat**: frontend sparar svaret i `sessionStorage` (30 min, per sida och språk) och
    visar det igen vid bakåtknappen, inklusive scrollposition.
  - Skydd: same-origin-kontroll, rate limit per IP/timme, dygnstak.
  - **Shortcode** `[cv_ai_sok]` (attribut: `platshallare`, `knapp`, `exempel="nej"`,
    `exempelfragor="a|b"`, `dolj="väljare"`). Element med klassen `cvai-dolj` döljs när kunden söker;
    utdata från kortkoden `[resekarta]` får klassen automatiskt (filtret `do_shortcode_tag`). Pär skriver rubrik/ingress själv i Avada – lägg aldrig till det i pluginen.
  - **Admin**: Cykla&Vandra → AI-sök (API-nyckel, modell, taggar, antal träffar, färger,
    exempelfrågor per språk, sökhistorik, katalogförhandsvisning per språk).
- `cyklavandra-meny.php` – gemensam adminmeny Cykla&Vandra (klass `Cyklavandra_Meny`), kopierad
  **oförändrad** från repot `Cyklavandra-se/bokningsformular`. Ändra den aldrig här; ändras den där,
  kopiera in den nya versionen. AI-sök är undersidan `admin.php?page=cvai` + ett kort via
  filtret `cyklavandra_verktyg`.
- `includes/i18n.php` – alla UI-texter + inbyggda exempelfrågor för sv, nb, da, en, de, fi, nl, fr.
  WPML-koden `no` mappas till `nb`.
- `includes/updater.php` – uppdateringar från GitHub-releaser via `Update URI` +
  filtret `update_plugins_github.com` (+ `site_transient_update_plugins` som reserv); först github.com/…/releases/latest-omdirigeringen,
  API:et som reserv och för releasetexten ("Visa detaljer"); kräver bifogad `cyklavandra-ai-sok.zip`
  (cache `cvai_gh_release`, senaste kollen i option `cvai_gh_status`).
- `assets/cvai.js`, `assets/cvai.css` – frontend. CSS-variabler sätts inline från inställningarna.
  Avada skriver över formulärstilar, därav `!important` på textarea/knapp.
- `wpml-config.xml` – gör shortcode-attribut översättningsbara.

## Regler

- API-nyckeln ligger i `wp-config.php` som `CVAI_API_KEY` – **aldrig i repot**.
- AI:n får bara returnera id:n ur katalogen; servern filtrerar bort okända id:n.
- Höj alltid `Version:` och `CVAI_VERSION` vid ändring, och lägg en rad under "Versioner" i README.md.
- Bakåtkompatibilitet: nya inställningar ska ha standardvärden i `cvai_defaults()`.
- Ändras databastabellen: höj `CVAI_DB_VERSION` (uppgradering sker i `cvai_maybe_upgrade`).

## Testa lokalt

Ingen MySQL behövs: WordPress + SQLite Database Integration (från GitHub) + `php`.
Mocka Anthropic-anropet med filtret `pre_http_request` och WPML med filtren
`wpml_current_language`, `wpml_active_languages`, `wpml_post_language_details` och actionen
`wpml_switch_language`. Kör alltid `php -l` och `node --check assets/cvai.js`.

## Release

**Släpp aldrig en ny version (merge till `main` + release) förrän Pär uttryckligen ber om det.**
Gör ändringarna klara på grenen, visa vad som ändrats och vänta på "släpp".

1. Höj versionen och lägg raden i README "Versioner" (blir releasetexten). Pusha till `main`
   (via PR om Pär vill granska).
2. Starta workflowen `release.yml` på `main` (GitHub MCP `actions_run_trigger`, `run_workflow`).
   Den skapar taggen `v` + `Version:`, releasen och `cyklavandra-ai-sok.zip`. Stoppar om
   versionen redan finns. Taggpush från molnsessionen nekas (403), därför knappen.
   Taggpush och manuell release i GitHub fungerar också.
3. Sajter med 1.4.0+ får uppdateringen automatiskt. Äldre: Tillägg → Ladda upp →
   "Ersätt nuvarande med uppladdad" med `cyklavandra-ai-sok.zip`, **inte** "Source code (zip)".

## Idéer framåt

- Ny förstasida byggd runt sökningen.
- Kompletta månadstaggar på alla resor (taggen `oktober` saknas på många resor som går i oktober).
