Max!Stats
========

( Publiziert auf Anfragen in http://bugs.maxbuddy.de/boards/1/topics/218 )

Max!Buddy Exporte werden als Grundlage genommen und können automatisch über einen Web-Export importiert werden:

- Geräte werden automatisch einem Cube zugeordnet und über den Cube können dessen Statistiken abgerufen werden.
- Neue Geräte werden automatisch erkannt und hinzugefügt.
- Raumzuordnungen geschehen automatisch und werden bei jedem Import aktualisiert
- Eine Installation kann für mehrere Cubes (User) verwendet werden, durch die automatisierte Cube-Zuweisung kann man also eine Installation mit anderen "teilen" :-)

### Es gibt zwei Statistiken:

- Die "hübschere" Statistik ist das Dashboard unter `/index/dashboard/cubes/<Cube Serial>`
- Die anfängliche und ausführlichere ist `/index/stats/cubes/<Cube Serial>` – hier sind auch alle Geräte einzeln gelistet
- Ist die Cube-Serial unbekannt, einfach auf die Startseite gehen `/` gehen und den Cube auswählen

Das ganze kann man sich hier einmal ansehen: http://max.t-0.eu/index/dashboard/cubes/JEQ0193016

### Zur Inbetriebnahme:

1. Konfiguration der Datenbank in `application/configs/application.ini`
2. Schreibberechtigungen für den `public`-Ordner sicherstellen
4. Das Zend-Framework 1 wird benötigt (egal welche 1.x Version), bitte separat laden: http://framework.zend.com/downloads/latest#ZF1
5. `/cube/export` als URL für den Datenexport in Max!Buddy hinterlegen (Z.B. `http://max.t-0.eu/cube/export`)
6. `/index/cube` aufrufen und den eigenen Cube auswählen um die Statistik zu öffnen
7. Viel Spaß!

### Allgemeiner Hinweis:

Das gesamte Konstrukt wurde schnell zusammen gestellt und ist (noch) nicht für längere Laufzeiten optimiert. Wichtig war bisher nicht die Qualität sondern schnell zum Ziel zu gelangen ;-)

### Cube-Schnittstelle in PHP:

Es gibt einen Ansatz für eine PHP Klasse die direkt aus dem Cube die Daten ausliest, zu finden unter `library/favo/Max/Cube.php`. Das ist noch nicht sehr erfolgreich und ich weiss auch nicht ob ich das weiterverfolgen möchte :-)
Vielleicht findet sich jemand anderer der die Klasse ausbauen möchte. Als Grundlage diente dafür http://www.domoticaforum.eu/viewtopic.php?f=66&t=6654#p50589
