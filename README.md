# SeroHub

## Modernes Helpdesk-, Ticketsystem- und Unternehmensmanagement-System

SeroHub ist eine moderne All-in-One Plattform für IT-Dienstleister, Unternehmen und Support-Teams.

Das System kombiniert ein klassisches Ticketsystem mit einer umfangreichen Firmen-, Kunden-, Inventar- und Projektverwaltung. Zusätzlich bietet SeroHub viele weitere Werkzeuge wie Wissensdatenbanken, Kalender, Bestellungen, Zeiterfassung und interne Verwaltungsfunktionen.

SeroHub wurde entwickelt, um den kompletten Arbeitsalltag eines IT-Dienstleisters oder internen IT-Teams zentral in einem System abzubilden.


# Hauptfunktionen

## Ticketsystem

- Ticketverwaltung
- Prioritäten & Status
- Bearbeitungszeiten
- Ticketabrechnung
- Mail-zu-Ticket Funktion
- Kommentare & Verlauf
- Verknüpfungen mit Projekten, Bestellungen und Aufgaben

---

## Firmen- & Kundenverwaltung

- Firmen anlegen und verwalten
- Kunden Firmen zuweisen
- Ansprechpartner verwalten
- Firmennotizen
- Gesprächsnotizen
- Wartungsverträge
- Zahlungserinnerungen

---

## Inventar & Geräteverwaltung

- Geräte anlegen
- Geräte Firmen oder Kunden zuweisen
- Seriennummern verwalten
- Garantieinformationen
- Lagerverwaltung
- Inventarbuchungen

---

## Projekte & Aufgaben

- Projekte erstellen
- Tickets Projekten zuweisen
- Aufgaben verwalten
- Fortschritt verfolgen
- Teamverwaltung

---

## Kalender

- Integrierter Kalender
- CalDAV Unterstützung
- Firmen- und Kundentermine
- Projekttermine
- Erinnerungen

---

## Wissensdatenbank

- Interne Wissensdatenbank
- Kunden-Wissensdatenbank
- Firmenspezifische Einträge
- Schritt-für-Schritt Anleitungen
- Notion-ähnliche Bedienung

---

## Bestellungen & Shop

- Bestellungen direkt aus Tickets
- Lagerartikel ausbuchen
- Eigene Bestellungen erstellen
- Interner Shop für Kunden/Firmen
- Headsets, Tastaturen usw. direkt bestellbar

---

## Benutzerfunktionen

- Rollenverwaltung
- Admins
- Techniker
- Firmenkunden
- Einfache Ansicht für ältere Nutzer
- 2FA Unterstützung
- Benachrichtigungssystem

---

## Weitere Funktionen

- Dashboard mit Karten
- Systemweite Infobars
- Umfragen
- Globale Suche
- Globaler Firmenfilter
- Verknüpfungen & Downloads
- Mailversand & Mail-Empfang
- Branding & Anpassungen
- Gimmick-Seite mit Rekorden & Glücksrad

---

# Systemanforderungen

Für den Betrieb wird ein eigener Server benötigt.

## Unterstützte Umgebung

- Linux Server
- Apache2
- MySQL oder MariaDB
- PHP 8.0 oder neuer

---

# Installation

## 1. Repository herunterladen

Repository herunterladen oder klonen.

---

## 2. Dateien hochladen

Im Projekt befindet sich der Ordner:

```txt
application
```

Der komplette Inhalt dieses Ordners muss auf den Webserver hochgeladen werden.

Beispielpfad:

```txt
/var/www/html/
```

oder

```txt
/home/webserver/public_html/
```

---

## 3. Datenbank erstellen

Eine neue MySQL/MariaDB Datenbank anlegen.

Danach die Datei:

```txt
database.sql
```

importieren.

Dies kann beispielsweise über:

- phpMyAdmin
- Adminer
- MySQL CLI

erfolgen.

---

## 4. Datenbank konfigurieren

Die Datei:

```txt
/assets/config.php
```

öffnen.

Dort müssen die Zugangsdaten der Datenbank eingetragen werden.

Beispiel:

```php
$db_host = "localhost";
$db_name = "serohub";
$db_user = "root";
$db_pass = "password";
```

---

## 5. Apache konfigurieren

Sicherstellen, dass:

- PHP aktiviert ist
- mod_rewrite aktiviert ist
- Schreibrechte korrekt gesetzt sind
- HTTPS verwendet wird

---

## 6. Fertig

Danach kann SeroHub über den Browser aufgerufen werden.

Beispiel:

```txt
https://example.com
```

---

# Empfohlene PHP Erweiterungen

- mysqli
- pdo_mysql
- mbstring
- curl
- openssl
- json
- fileinfo

---

# Sicherheitshinweise

Vor dem produktiven Einsatz wird empfohlen:

- HTTPS zu aktivieren
- sichere Datenbankpasswörter zu verwenden
- regelmäßige Backups anzulegen
- Schreibrechte zu prüfen
- den Server aktuell zu halten

---

# Roadmap

Geplante Verbesserungen:

- Automatischer Installer
- Docker Support
- API Erweiterungen
- Mobile Optimierungen
- Erweiterte Rechteverwaltung
- Mehr Automatisierungen
- Erweiterte Statistiken

---

# Hinweis zur Entwicklung

SeroHub wird aktiv entwickelt.

Dadurch können sich:

- Funktionen
- APIs
- Datenbankstrukturen
- Designs
- Konfigurationen

jederzeit ändern.

---

# Lizenz

Aktuell wurde noch keine öffentliche Lizenz festgelegt.

---

# Kontakt

Weitere Informationen folgen in zukünftigen Versionen.
