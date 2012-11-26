MaxStats
========

Max!Buddy Exporte werden als Grundlage genommen und können wahlweise über die Startseite hochgeladen werden oder via Shell-Befehle.

- Geräte werden automatisch einem Cube zugeordnet und über den Cube können dessen Statistiken abgerufen werden.
- Neue Geräte werden automatisch erkannt und hinzugefügt.
- Raumzuordnung fehlen im Export, daher müssen diese manuell über ein Auswahlfeld in der Statistik `/index/stats` erst vorgenommen werden.
- Eine Installation kann für mehrere Cubes (User) verwendet werden, durch die automatisierte Cube-Zuweisung kann man also eine Installation mit anderen "teilen" :-)
- Die HTML Ausgabe ist für meinen Chrome unter OSX optimiert, andere Browser laufen eventuell nicht so gut.

### Es gibt zwei Statistiken:

- `/index/stats/cubes/<Cube Serial>` – ist die vollständige in welcher Räume und Geräte einzeln gelistet werden
- Die "hübschere" Statistik ist das Dashboard unter `/index/dashboard/cubes/<Cube Serial>`
- Ein "Login" mit Hilfe der Cube-Serial gibt es hier: unter `/index/cube` welches auf das Dashboard weiterleitet

Das ganze kann man sich hier einmal ansehen: http://max.t-0.eu/index/dashboard/cubes/JEQ0193016

Mein eigener minütlicher Upload läuft als Shell-Script nach jedem Max!Buddy Export wie folgt ab:

    # max buddy export upload
    cd ~/ExportVerzeichns/
    curl http://max.t-0.eu/ --form file=@max-buddy.export --form submit=submit -s
    rm max-buddy.export


### Zur Inbetriebnahme:

1. Konfiguration der Datenbank in `application/configs/application.ini`
2. Datenbank-Dump einspielen, zu finden unter `library/favo/Max/Model/dump.mysql.sql`
3. Das `data/upload` Verzeichnis muss Schreiberechtigung durch den Webserver haben, da hier die Uploads hin geladen werden
4. Das Zend-Framework 1 wird benötigt (egal welche 1.x Version), bitte separat laden: http://framework.zend.com/downloads/latest#ZF1
5. Viel Spaß!

### Allgemeiner Hinweis:

Das gesamte Konstrukt wurde schnell zusammen gestellt und ist (noch) nicht für längere Laufzeiten optimiert. Wichtig war bisher nicht Qualität sondern die schnell zum Ziel zu gelangen ;-)

### Cube-Schnittstelle in PHP:

Es gibt einen Ansatz für eine PHP Klasse die direkt aus dem Cube die Daten ausliest, zu finden unter `library/favo/Max/Cube.php`. Das ist noch ziemlich sehr erfolgreich und recht zu Beginn und ich weiss auch nicht ob ich das weiterverfolgen möchte :-)
Vielleicht findet sich jemand anderer der die Klasse ausbauen möchte. Als Grundlage diente dafür http://www.domoticaforum.eu/viewtopic.php?f=66&t=6654#p50589
