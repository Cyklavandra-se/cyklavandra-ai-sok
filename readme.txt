=== Cykla & Vandra – AI-sök ===

Fritextsökning med AI för cyklavandra.se. Kunden beskriver sin resa med egna ord
och får 2–4 matchande resor med motivering.

== Installation ==

1. Tillägg → Lägg till nytt → Ladda upp tillägg → välj cyklavandra-ai-sok.zip → Aktivera.
2. Skaffa en API-nyckel på https://console.anthropic.com (Settings → API Keys).
3. Lägg nyckeln i wp-config.php (säkrast), ovanför raden "That's all, stop editing":
      define( 'CVAI_API_KEY', 'sk-ant-...' );
   eller klistra in den under Cykla&Vandra → AI-sök.
4. Gå till Cykla&Vandra → AI-sök och kontrollera att antalet resor i katalogen stämmer.
   Titta i "Förhandsgranska katalogen" att nivå och månader kommer med.
5. Lägg shortcoden [cv_ai_sok] på startsidan, t.ex. i ett Avada Text Block.

== Shortcode-attribut ==

[cv_ai_sok platshallare="..." knapp="Sök" exempel="nej" exempelfragor="Fråga 1|Fråga 2" dolj="#karta"]

Allt med CSS-klassen cvai-dolj (t.ex. kartan) döljs när kunden söker.

Rubrik och ingress skrivs fritt i Avada ovanför shortcoden.

== Flera språk (WPML) ==

* Lägg [cv_ai_sok] på startsidan i varje språk. Sökrutan, knappar och resultat
  visas på sidans språk, och AI:n svarar på samma språk.
* Varje språk får sin egen resekatalog med de resor som finns på just det språket,
  med rätt länkar.
* Översatta resetaggar hittas automatiskt via WPML. Svårighetstaggar känns igen
  även med översatta slugs (t.ex. hike2-2).
* Inbyggda texter: svenska, norska, danska, engelska, tyska, finska, nederländska,
  franska. Andra språk får engelska texter tills de läggs till.
* Exempelfrågorna översätts i WPML → String Translation (admin_texts_cvai_options)
  eller via shortcode-attributet exempelfragor="Fråga 1|Fråga 2" på respektive sida.

== Så fungerar det ==

* Alla publicerade sidor med taggen "guidad" eller "självguidad" blir katalogen
  (titel, taggar, svårighetstaggar hike1–4/bike1–4 och början av sidtexten).
* Kommande evenemang i The Events Calendar tas med som guidade avgångar med datum.
* Katalogen cachas i 24 timmar och byggs om automatiskt när en sida eller tagg sparas.
* Sökningen går via WordPress-servern till Claude – API-nyckeln syns aldrig för besökaren.
* Spärrar: max sökningar per besökare och timme samt max per dygn (kostnadstak).
* Sökhistorik sparas utan IP-adress (bara en anonym besökarkod som byts varje månad) och visas under Cykla&Vandra → AI-sök.
