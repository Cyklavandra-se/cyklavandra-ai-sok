# Testverktyg (följer inte med i pluginens zip)

Jämför AI-sökningens upplägg (hela/bantad katalog, förfiltrering, modell) lokalt, utan att röra sajten.

1. Lokal WordPress med SQLite (se CLAUDE.md) och pluginen aktiverad. Lägg `tribe-shim.php` i `wp-content/mu-plugins/`.
2. `python3 tools/hamta-katalog.py /tmp/katalog` – hämtar resor, taggar, evenemang och destinationer (bara läsning).
3. `php tools/importera-katalog.php /tmp/katalog <wp-mapp>/wordpress`. Tömmer den LOKALA databasen och importerar med samma id:n.
4. `ANTHROPIC_API_KEY=… php tools/jamfor-upplagg.php <wp-mapp>/wordpress 2 A,B,C,D` – kör `fragor.json` med varje upplägg.

`extraregler-rekonstruerade.txt` är återskapad ur sökloggens sammanfattningar – byt mot den riktiga texten från inställningarna om den finns.
