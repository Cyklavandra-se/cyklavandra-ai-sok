# Cykla & Vandra – AI-sök

WordPress-plugin för cyklavandra.se. Kunden beskriver sin drömresa med egna ord
("Vi är 6 personer som vill vandra i Italien i oktober …") och får 2–4 matchande
resor ur vårt utbud, med en kort motivering till varför varje resa passar.

## Funktioner

- **Fritextsökning med Claude:** frågan och resekatalogen skickas till Anthropic API.
  AI:n tolkar land, månad, aktivitet, nivå och gruppstorlek och väljer resor.
- **Katalogen byggs automatiskt** av alla publicerade sidor med taggen `guidad` eller
  `självguidad`, inklusive svårighetstaggarna `hike1–4` och `bike1–4`. Kommande
  guidade avgångar hämtas från The Events Calendar.
- **WPML:** en katalog per språk, och gränssnitt och svar på sidans språk.
  Inbyggda texter finns för sv, nb, da, en, de, fi, nl och fr.
- **Inställningar:** färger, hörnradie, bilder, antal träffar och alternativ,
  exempelfrågor och extra regler till AI:n.
- **Sökhistorik** utan IP-adress, så att man ser vad kunderna efterfrågar och inte hittar.
- **Skydd:** max antal sökningar per besökare och timme, ett tak per dygn och en kontroll
  av att anropet kommer från den egna sajten.

## Installation

1. Ladda ner senaste `cyklavandra-ai-sok.zip` under **Releases** – *inte* "Source code (zip)".
2. WordPress → Tillägg → Lägg till nytt → Ladda upp tillägg → Aktivera.
   Därefter kommer nya versioner automatiskt (se *Uppdateringar*).
3. Lägg API-nyckeln i `wp-config.php`:
   ```php
   define( 'CVAI_API_KEY', 'sk-ant-...' );
   ```
4. Lägg shortcoden `[cv_ai_sok]` på startsidan i varje språk.

Shortcode-attribut: `platshallare`, `knapp`, `exempel="nej"`,
`exempelfragor="Fråga 1|Fråga 2"`.

## Uppdateringar

Från 1.4.0 hämtar WordPress nya versioner direkt från GitHub-releaserna. De syns som
vanliga uppdateringar under **Tillägg** och **Kontrollpanel → Uppdateringar**.

- Klicka på **Aktivera automatiska uppdateringar** på raden för AI-sök i tilläggslistan
  om WordPress ska installera dem själv (annars klickar du *Uppdatera nu*).
- WordPress kollar två gånger per dygn. Länken **Sök efter uppdatering** på pluginens rad
  kollar direkt.
- Bara releaser med en färdigbyggd `cyklavandra-ai-sok.zip` räknas.

## Filer

| Fil | Innehåll |
|---|---|
| `cyklavandra-ai-sok.php` | Katalog, prompt, REST-endpoint (`/wp-json/cvai/v1/search`), shortcode, admin |
| `includes/i18n.php` | Texter per språk |
| `includes/updater.php` | Uppdateringar från GitHub-releaser |
| `assets/cvai.js` / `cvai.css` | Sökrutan och resultatkorten |
| `wpml-config.xml` | Gör exempelfrågor och shortcode-attribut översättningsbara |

## Ny version

1. Höj `Version:` och `CVAI_VERSION` i `cyklavandra-ai-sok.php` och lägg en rad under
   *Versioner* nedan (den blir releasetexten).
2. Committa och pusha till `main`.
3. På GitHub: **Actions → Bygg release → Run workflow** (gren `main`). Den skapar taggen
   `vX.Y.Z` från `Version:`, releasen och zip-filen. Det går också att pusha taggen
   (`git push origin vX.Y.Z`) eller skapa releasen för hand som förut.
4. Bygget stoppas om taggen inte stämmer med `Version:`.
5. Sajten hittar uppdateringen inom 12 timmar, eller direkt via *Sök efter uppdatering*.

**Lägg aldrig API-nycklar eller lösenord i repot.**

## Versioner

- **1.4.11** – Byte till Claude Sonnet 5.5 (följer extrareglerna bättre). Sajter som hade Haiku byts automatiskt en gång; ett eget val lämnas orört. Låg ansträngning för snabbare svar.
- **1.4.10** – Extrareglerna följs bättre: de ligger efter katalogen som obligatoriska, AI:n måste ange vilka regler som gäller innan den väljer, och resor i länder som reglerna utesluter tas bort av servern. Tillämpade regler syns i sökhistoriken.
- **1.4.9** – Ungern och Ecuador får landsetikett på resekorten (alla språk). Katalogen byggs om automatiskt efter en uppdatering av pluginen.
- **1.4.8** – Skydd mot förväxlade resor: AI:n anger även resans namn och servern rättar id:t om det inte stämmer (t.ex. Galapagos med en motivering om en vinresa). Rättelser syns i sökhistoriken.
- **1.4.7** – Resekatalogen sparas i 24 timmar i stället för 12 (töms fortfarande direkt när något sparas).
- **1.4.6** – Luft mellan tolkningstaggarna och svarstexten (gick förlorad i Avada).
- **1.4.5** – Egen ikon för pluginen (syns bland uppdateringarna).
- **1.4.4** – Test av automatisk uppdatering från GitHub. Inga ändringar i funktionen.
- **1.4.3** – Säkrare uppdateringskoll: reserv om GitHubs API inte svarar och fungerar även om wordpress.org inte svarar. "Sök efter uppdatering" visar resultatet och eventuellt fel.
- **1.4.2** – Första versionen som släpps automatiskt via Run workflow. Inga ändringar i funktionen.
- **1.4.1** – Exempelfrågorna i två kolumner (en på mobil) och döljs när man sökt. Tydligare laddning med snurra och förloppsstapel.
- **1.4.0** – Automatiska uppdateringar från GitHub-releaser. Länken "Sök efter uppdatering" i tilläggslistan.
- **1.3.0** – Exempelfrågor per språk: egna fält i inställningarna och inbyggda exempel på alla åtta språken.
- **1.2.1** – Skydd mot krasch om pluginen är installerad två gånger.
- **1.2.0** – Färginställningar, hörnradie, bilder av/på, max antal träffar och alternativ. Texten i sökrutan får rätt färg i Avada.
- **1.1.0** – WPML-stöd: en katalog per språk och texter på åtta språk.
- **1.0.1** – Strukturerat svar via verktygsanrop (rättar "oväntat svar"). Rubrik och ingress borttagna ur pluginen.
- **1.0.0** – Första versionen.
