# blanq Motion

Lädt **GSAP** und **Lenis** lokal vom eigenen Server (kein CDN) und **nur auf Seiten, die sie brauchen**. Gedacht für Bricksmotion-Komponenten und eigenen GSAP-Code in Bricks. Die Grundbewegung (Einblenden, Hover) läuft über die ACSS-Effekte und braucht dieses Plugin nicht.

Enthalten: GSAP 3.15.0 mit allen Plugins, Lenis 1.3.27.

## Was das Plugin macht

Bei jedem Seitenaufruf prüft es das fertige HTML und bindet nur die nötigen Dateien im `<head>` ein:

| Auf der Seite gefunden | Geladen wird |
|---|---|
| Bricksmotion-Komponente (`BM.ready` / `BM.load`) | GSAP, ScrollTrigger, die in `BM.load([...])` genannten Plugins, Lenis |
| Bricksmotion mit `data-bm-lenis="off"` am `<html>` | wie oben, ohne Lenis |
| Eigener Code mit `gsap.` | GSAP |
| Plugin-Name im Code, z. B. `ScrollTrigger`, `SplitText`, `Flip` | dieses Plugin (+ Abhängigkeiten) |
| Attribut `data-bq-gsap="Flip Draggable"` an einem Element | GSAP + die genannten Plugins |
| nichts davon | **nichts** |

Es erkennt auch Header, Footer, Templates und Komponenten, weil es die fertig gerenderte Seite prüft. Wird GSAP schon von anderer Stelle geladen (z. B. von Bricksforge), lädt es nicht doppelt.

Bricksmotion nutzt das vorhandene GSAP und lädt dann nichts vom CDN nach. **Nicht** `window.BM_GSAP = 'own'` setzen.

**Nicht aktiv** im Bricks-Editor, im Admin, bei REST/AJAX, Feeds und Embeds.

## Prüfen, ob es läuft

1. Als Admin eingeloggt eine Seite öffnen, Quelltext ansehen. Im `<head>` steht z. B.
   `<!-- blanq Motion 1.0.0: gsap, ScrollTrigger, SplitText, Lenis (Grund: Bricksmotion) -->`
2. Entwicklertools → Netzwerk, nach `gsap`, `lenis`, `jsdelivr`, `unpkg`, `cdnjs` filtern. Alles muss von der eigenen Domain kommen.
3. Seite ohne Animationen öffnen: Es darf **keine** GSAP-Datei geladen werden.

## Einstellungen (optional)

In der `wp-config.php`:

```php
define( 'BQ_MOTION_REPO', 'https://github.com/blanq-agency/blanq-motion/' ); // Repository für Updates
define( 'BQ_MOTION_GITHUB_TOKEN', 'github_pat_…' ); // nur bei privatem Repository
define( 'BQ_MOTION_AUTO_UPDATE', false );           // automatische Updates abschalten
```

Filter für Sonderfälle (z. B. in einem Code-Snippet):

```php
// Auf einer bestimmten Seite immer Flip laden
add_filter( 'bq_motion_libraries', function ( $libs ) {
	if ( is_page( 'referenzen' ) ) { $libs = array_unique( array_merge( [ 'gsap', 'Flip' ], $libs ) ); }
	return $libs;
} );

// Skripte mit defer laden (schnellere Darstellung, vorher testen!)
add_filter( 'bq_motion_defer', '__return_true' );

// Plugin auf einer Seite komplett aus
add_filter( 'bq_motion_enabled', fn() => ! is_page( 'impressum' ) );
```

Hinweis zu `defer`: Standardmäßig werden die Bibliotheken direkt geladen, damit sie sicher bereitstehen, bevor Bricksmotion- oder eigener Code läuft. `defer` nur nutzen, wenn alle Animationen erst nach `DOMContentLoaded` starten.

## Performance-Plugins

Bei WP Rocket, Perfmatters, FlyingPress, LiteSpeed o. ä. diese Begriffe von „JavaScript verzögern“ ausnehmen:

```
bq-motion
gsap
BRICKSMOTION
```

## Automatische Updates

So läuft es:

1. **Jeden Montag** prüft eine GitHub-Action, ob es neue GSAP- oder Lenis-Versionen gibt.
2. Falls ja, aktualisiert sie `vendor/`, erhöht die Plugin-Version, lässt die Tests laufen und **öffnet einen Pull Request**.
3. Ihr prüft kurz auf einer Staging-Seite und **führt den Pull Request zusammen**.
4. Daraufhin erstellt die zweite Action automatisch ein **Release mit `blanq-motion.zip`**.
5. Alle Kundenseiten sehen das Update im WordPress-Backend und **installieren es automatisch** (WordPress prüft etwa alle 12 Stunden).

Gleiches gilt für eigene Änderungen: Version in `blanq-motion.php` erhöhen (`npm run build -- --bump`), auf `main` pushen, fertig.

## Entwicklung

```bash
npm install        # GSAP und Lenis holen
npm run build      # nach vendor/ kopieren, manifest.json schreiben
npm test           # Erkennung testen (PHP)
npm run package    # dist/blanq-motion.zip bauen
```

## Lizenzen

- GSAP: GreenSock Standard License (kostenlos, auch kommerziell): https://gsap.com/standard-license
- Lenis: MIT, siehe `vendor/lenis/LICENSE.txt`
- Plugin Update Checker: MIT, siehe `lib/plugin-update-checker/license.txt`
