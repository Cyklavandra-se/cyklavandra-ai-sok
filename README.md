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
   Vid uppdatering väljer du *Ersätt nuvarande med uppladdad*.
3. Lägg API-nyckeln i `wp-config.php`:
   ```php
   define( 'CVAI_API_KEY', 'sk-ant-...' );
   ```
4. Lägg shortcoden `[cv_ai_sok]` på startsidan i varje språk.

Shortcode-attribut: `platshallare`, `knapp`, `exempel="nej"`,
`exempelfragor="Fråga 1|Fråga 2"`.

## Filer

| Fil | Innehåll |
|---|---|
| `cyklavandra-ai-sok.php` | Katalog, prompt, REST-endpoint (`/wp-json/cvai/v1/search`), shortcode, admin |
| `includes/i18n.php` | Texter per språk |
| `assets/cvai.js` / `cvai.css` | Sökrutan och resultatkorten |
| `wpml-config.xml` | Gör exempelfrågor och shortcode-attribut översättningsbara |

## Ny version

1. Höj `Version:` och `CVAI_VERSION` i `cyklavandra-ai-sok.php`.
2. Committa och pusha till `main`.
3. På GitHub: **Releases → Draft a new release** → skapa taggen `v1.3.0` → **Publish release**.
4. GitHub Actions bygger zip-filen och bifogar den till releasen efter någon minut.

**Lägg aldrig API-nycklar eller lösenord i repot.**

## Versioner

- **1.3.0** – Exempelfrågor per språk: egna fält i inställningarna och inbyggda exempel på alla åtta språken.
- **1.2.1** – Skydd mot krasch om pluginen är installerad två gånger.
- **1.2.0** – Färginställningar, hörnradie, bilder av/på, max antal träffar och alternativ. Texten i sökrutan får rätt färg i Avada.
- **1.1.0** – WPML-stöd: en katalog per språk och texter på åtta språk.
- **1.0.1** – Strukturerat svar via verktygsanrop (rättar "oväntat svar"). Rubrik och ingress borttagna ur pluginen.
- **1.0.0** – Första versionen.
