# SeroHub
SeroHub ist eine moderne All-in-One Plattform für IT-Service, Helpdesk und Unternehmensverwaltung.
Das System kombiniert ein Ticketsystem mit Kunden- und Firmenverwaltung, Inventarverwaltung, Projekten, Wissensdatenbank, Bestellungen, Kalenderfunktionen und vielen weiteren Tools für IT-Dienstleister und Unternehmen.
---
# ⚠️ Beta Hinweis
SeroHub befindet sich aktuell noch in der Beta-Phase.
Der Installationsprozess wird in zukünftigen Versionen weiter verbessert und vereinfacht.  
Aktuell müssen einige Schritte noch manuell durchgeführt werden.
Fehler, unvollständige Funktionen oder Änderungen an der Datenbankstruktur sind möglich.
---
# Funktionen
- Ticketsystem
- Firmen- & Kundenverwaltung
- Geräte- & Inventarverwaltung
- Projekte & Aufgaben
- Wissensdatenbank
- Kalender mit CalDAV Unterstützung
- Zeiterfassung
- Interner Onlineshop
- Bestellungen & Lagerbuchungen
- Dashboard mit Benachrichtigungen
- Mailversand & Ticket-Erstellung per Mail
- Wartungsverträge
- Rollen- & Rechteverwaltung
- 2FA Unterstützung
- Einfache Ansicht für ältere Nutzer
- Globaler Firmenfilter
- Globale Suche
- Anpassbares Branding
---
# Systemanforderungen
Benötigt wird ein Server mit:
- Linux
- Apache2
- MySQL / MariaDB
- PHP 8+
Empfohlen:
- SSL / HTTPS
- phpMyAdmin
- Cronjobs
---
# Installation
## 1. Dateien hochladen
Den Inhalt des Ordners `application` auf den Webserver hochladen.
Beispiel:
```txt
/var/www/html/

⸻

2. Datenbank erstellen

Eine neue MySQL/MariaDB Datenbank anlegen.

Danach die Datei:

database.sql

in die Datenbank importieren.

⸻

3. Datenbankzugang konfigurieren

Die Datei:

/assets/config.php

öffnen und die Zugangsdaten der Datenbank eintragen.

Beispiel:

$db_host = "localhost";
$db_name = "serohub";
$db_user = "root";
$db_pass = "password";

⸻

4. Apache & PHP konfigurieren

Sicherstellen, dass:

* mod_rewrite aktiviert ist
* PHP korrekt eingerichtet ist
* Schreibrechte gesetzt sind

⸻

5. Fertig

Danach kann SeroHub im Browser geöffnet werden.

Beispiel:

https://deine-domain.de

⸻

Sicherheit

Vor dem produktiven Einsatz sollten:

* sichere Passwörter verwendet werden
* HTTPS aktiviert sein
* Dateiberechtigungen geprüft werden
* regelmäßige Backups eingerichtet werden

⸻

Lizenz

Aktuell keine öffentliche Lizenz festgelegt.

⸻

Hinweis

Dieses Projekt befindet sich weiterhin in aktiver Entwicklung.
Änderungen an Funktionen, Datenbankstruktur oder APIs sind jederzeit möglich.
