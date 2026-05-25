-- phpMyAdmin SQL Dump
-- version 4.9.11
-- https://www.phpmyadmin.net/
--
-- Host: database-5019693791.webspace-host.com
-- Erstellungszeit: 25. Mai 2026 um 13:08
-- Server-Version: 8.0.36
-- PHP-Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `dbs15317835`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `announcements`
--

CREATE TABLE `announcements` (
  `id` int UNSIGNED NOT NULL,
  `titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Titel der Ankündigung',
  `nachricht` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nachrichtentext',
  `link_text` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Text für den Link-Button',
  `link_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL für den Link-Button',
  `show_banner` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Soll der Banner angezeigt werden (1=ja, 0=nein)',
  `anonym` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Anonyme Ankündigung - zeigt Computer-Technik-Krause statt Erstellername (1=ja, 0=nein)',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Firma für die die Ankündigung gilt (NULL = alle Firmen)',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'Benutzer der die Ankündigung erstellt hat',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Änderungsdatum',
  `aktiv` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Ist die Ankündigung aktiv (1=ja, 0=nein)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System-weite Ankündigungen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `caldav_servers`
--

CREATE TABLE `caldav_servers` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Anzeigename (z.B. Firmen-Nextcloud)',
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'CalDAV-Basis-URL',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Hinweise für Benutzer (z.B. Anleitung zum Hinzufügen)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CalDAV-Server (von Admin gepflegt, User können ICS dort abonnieren)';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `calendar_events`
--

CREATE TABLE `calendar_events` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Besitzer des Termins',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Titel',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Beschreibung',
  `meeting_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Teams/Google Meet Link',
  `invite_emails` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Kommagetrennte E-Mail-Adressen für Einladungen',
  `ics_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Token für ICS-Download-Link',
  `start_at` datetime NOT NULL COMMENT 'Start',
  `end_at` datetime NOT NULL COMMENT 'Ende',
  `all_day` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Ganztägig (0=nein, 1=ja)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kalender-Termine (eigene Termine)';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `calendar_event_invitees`
--

CREATE TABLE `calendar_event_invitees` (
  `id` int UNSIGNED NOT NULL,
  `event_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Einladungen zu Kalender-Terminen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `calendar_subscriptions`
--

CREATE TABLE `calendar_subscriptions` (
  `id` int NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#6366f1',
  `is_active` tinyint(1) DEFAULT '1',
  `last_sync` datetime DEFAULT NULL,
  `sync_interval` int DEFAULT '60',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `calls`
--

CREATE TABLE `calls` (
  `id` int UNSIGNED NOT NULL,
  `telefonnummer` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Telefonnummer die angerufen wurde',
  `empfaenger_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Name des Empfängers (Kunde/Firma)',
  `anruftyp` enum('ausgehend','eingehend','verpasst') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ausgehend' COMMENT 'Typ des Anrufs',
  `status` enum('verbunden','nicht_erreicht','besetzt','abgelehnt','keine_antwort') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Status des Anrufs',
  `dauer_sekunden` int UNSIGNED DEFAULT NULL COMMENT 'Dauer des Anrufs in Sekunden',
  `notizen` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Notizen zum Anruf',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zur Firma (optional)',
  `customer_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Kunden (optional)',
  `ticket_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Ticket (optional)',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Anrufers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum und Zeit des Anrufs',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anrufprotokoll';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `changelog_entries`
--

CREATE TABLE `changelog_entries` (
  `id` int UNSIGNED NOT NULL,
  `version_id` int UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `entry_type` enum('feature','fix','improvement','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'other',
  `sort_order` int UNSIGNED DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `changelog_files`
--

CREATE TABLE `changelog_files` (
  `id` int UNSIGNED NOT NULL,
  `version_id` int UNSIGNED NOT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int UNSIGNED DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `changelog_versions`
--

CREATE TABLE `changelog_versions` (
  `id` int UNSIGNED NOT NULL,
  `version` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `release_date` date NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `comment_attachments`
--

CREATE TABLE `comment_attachments` (
  `id` int UNSIGNED NOT NULL,
  `comment_id` int UNSIGNED NOT NULL COMMENT 'Kommentar-ID',
  `dateiname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Originaler Dateiname',
  `dateipfad` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Relativer Pfad zur Datei',
  `dateigroesse` int UNSIGNED NOT NULL COMMENT 'Dateigröße in Bytes',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME-Typ der Datei',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anhänge für Kommentare';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `companies`
--

CREATE TABLE `companies` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Firmenname',
  `domain` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Firmendomain',
  `kundennummer` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kundennummer',
  `adresse` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Straße und Hausnummer',
  `plz` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Postleitzahl',
  `ort` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ort',
  `lieferadresse` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Lieferadresse (Straße und Hausnummer)',
  `liefer_plz` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Postleitzahl Lieferadresse',
  `liefer_ort` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ort Lieferadresse',
  `rechnungs_adresse` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rechnungsadresse',
  `rechnungs_plz` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Postleitzahl der Rechnungsadresse',
  `rechnungs_ort` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ort der Rechnungsadresse',
  `rechnungs_email` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rechnungs-E-Mail-Adresse',
  `email` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'E-Mail-Adresse der Firma',
  `telefonnummer` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Telefonnummer der Firma',
  `notizen` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Notizen',
  `zugewiesen_an` int UNSIGNED DEFAULT NULL COMMENT 'User-ID des zugewiesenen Mitarbeiters (Techniker/Admin)',
  `ansprechpartner_user_id` int UNSIGNED DEFAULT NULL COMMENT 'Ansprechpartner als User-Referenz',
  `ansprechpartner_manuell_name` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ansprechpartner Name (manuell)',
  `ansprechpartner_manuell_email` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ansprechpartner E-Mail (manuell)',
  `ansprechpartner_manuell_telefon` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ansprechpartner Telefon (manuell)',
  `ansprechpartner_manuell_notiz` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ansprechpartner Notiz (manuell)',
  `ansprechpartner_manuell` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ansprechpartner manuell eingegeben',
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pfad zum Logo',
  `status` enum('aktiv','inaktiv','gesperrt') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktiv' COMMENT 'Status der Firma',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `lager_zugriff` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Firma darf Lager einsehen',
  `require_customer_on_ticket` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Bei Ticket-Erstellung muss ein Kunde angegeben werden (1=ja, 0=nein)',
  `hat_wartungsvertrag` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Hat Wartungsvertrag',
  `wartung_zahlungsrhythmus` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Zahlungsrhythmus Wartungsvertrag: wöchentlich, monatlich, vierteljährlich, halbjährlich, jährlich',
  `wartung_zahlungstag` tinyint UNSIGNED DEFAULT NULL COMMENT 'Zahlungstag: bei wöchentlich 1-7 (Mo-So), sonst 1-31 (Tag im Monat)',
  `erstellt_von` int UNSIGNED DEFAULT NULL COMMENT 'User-ID des Erstellers',
  `geaendert_von` int UNSIGNED DEFAULT NULL COMMENT 'User-ID des letzten Bearbeiters',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Firmen/Unternehmen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `company_contracts`
--

CREATE TABLE `company_contracts` (
  `id` int UNSIGNED NOT NULL,
  `company_id` int UNSIGNED NOT NULL COMMENT 'Referenz zur Firma',
  `dateiname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Original-Dateiname',
  `dateipfad` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Pfad zur gespeicherten Datei',
  `dateigroesse` int UNSIGNED DEFAULT NULL COMMENT 'Dateigröße in Bytes',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME-Typ der Datei',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Beschreibung/Notizen zum Vertrag',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Verträge für Firmen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `company_documents`
--

CREATE TABLE `company_documents` (
  `id` int UNSIGNED NOT NULL,
  `company_id` int UNSIGNED NOT NULL COMMENT 'Referenz zur Firma',
  `dateiname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Original-Dateiname',
  `dateipfad` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Pfad zur gespeicherten Datei',
  `dateigroesse` int UNSIGNED DEFAULT NULL COMMENT 'Dateigröße in Bytes',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME-Typ der Datei',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Beschreibung/Notizen zum Dokument',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dokumente für Firmen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `company_notes`
--

CREATE TABLE `company_notes` (
  `id` int UNSIGNED NOT NULL,
  `company_id` int UNSIGNED NOT NULL COMMENT 'Referenz zur Firma',
  `titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Titel der Notiz',
  `inhalt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Inhalt der Notiz',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notizen für Firmen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `company_wartung_mahnung`
--

CREATE TABLE `company_wartung_mahnung` (
  `company_id` int UNSIGNED NOT NULL,
  `frage_datum` date NOT NULL COMMENT 'Zahlungstag, für den die Mahnung gesendet wurde',
  `gesendet_am` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Wartungsvertrag: Mahn-E-Mail-Versand pro Zahlungstag';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `company_wartung_zahlung_frage`
--

CREATE TABLE `company_wartung_zahlung_frage` (
  `id` int UNSIGNED NOT NULL,
  `company_id` int UNSIGNED NOT NULL,
  `frage_datum` date NOT NULL COMMENT 'Zahlungstag, an dem gefragt wurde',
  `status` enum('paid','inaktiv','gesperrt','remind_5','skipped') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `naechste_frage_datum` date DEFAULT NULL COMMENT 'Bei remind_5: Datum, an dem erneut gefragt wird',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Antworten auf Wartungsvertrag-Zahlungsfrage';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `consumables`
--

CREATE TABLE `consumables` (
  `id` int UNSIGNED NOT NULL,
  `bezeichnung` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `artikelnummer` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ean` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'EAN-/Barcode-Nummer',
  `shop_veroeffentlicht` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = im Shop veröffentlicht',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `mindestbestand` int UNSIGNED DEFAULT NULL,
  `auto_nachbestellen` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = bei Unterschreitung des Mindestbestands automatisch Bestellung auslösen',
  `lagerbestand` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Aktuell auf Lager',
  `shelf_id` int UNSIGNED DEFAULT NULL COMMENT 'Regal',
  `spalte` smallint UNSIGNED DEFAULT NULL COMMENT 'Spalte im Regal (1-n)',
  `fach` smallint UNSIGNED DEFAULT NULL COMMENT 'Fach/Ebene im Regal (1-n)',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Zuordnung zur Firma (optional)',
  `scan_auto_review` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = per Barcode automatisch angelegt, Daten prüfen',
  `pending_stockin_after_delivery` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = Bestellung Im Lager/Angekommen, noch kein Einlagern',
  `erstellt_von` int UNSIGNED DEFAULT NULL,
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft-Delete-Zeitpunkt; NULL = aktiv'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Verbrauchsmaterialien/Ersatzteile';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `consumable_categories`
--

CREATE TABLE `consumable_categories` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kategorien für Verbrauchsmaterialien';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `consumable_category_link`
--

CREATE TABLE `consumable_category_link` (
  `consumable_id` int UNSIGNED NOT NULL,
  `category_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zuordnung Verbrauchsmaterial – Kategorie (n:n)';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `consumable_company_link`
--

CREATE TABLE `consumable_company_link` (
  `consumable_id` int UNSIGNED NOT NULL,
  `company_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zuordnung Verbrauchsmaterial zu Firmen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `consumable_device_models`
--

CREATE TABLE `consumable_device_models` (
  `id` int UNSIGNED NOT NULL,
  `consumable_id` int UNSIGNED NOT NULL,
  `hersteller` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modell` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zuordnung Verbrauchsmaterial zu Gerätemodell';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `customers`
--

CREATE TABLE `customers` (
  `id` int UNSIGNED NOT NULL,
  `company_id` int UNSIGNED NOT NULL COMMENT 'Referenz zur Firma',
  `ansprechpartner_user_id` int UNSIGNED DEFAULT NULL COMMENT 'Ansprechpartner als User-Referenz',
  `ansprechpartner_manuell_name` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ansprechpartner Name (manuell)',
  `ansprechpartner_manuell_email` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ansprechpartner E-Mail (manuell)',
  `ansprechpartner_manuell_telefon` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ansprechpartner Telefon (manuell)',
  `ansprechpartner_manuell_notiz` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Ansprechpartner Notiz (manuell)',
  `ansprechpartner_manuell` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ansprechpartner manuell eingegeben',
  `name` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name des Kunden',
  `kundennummer` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eindeutige Kundennummer',
  `email` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'E-Mail-Adresse',
  `telefon` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Telefonnummer',
  `adresse` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Adresse',
  `plz` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Postleitzahl',
  `ort` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ort',
  `lieferadresse` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Lieferadresse',
  `liefer_plz` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Postleitzahl der Lieferadresse',
  `liefer_ort` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ort der Lieferadresse',
  `rechnungs_adresse` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rechnungsadresse',
  `rechnungs_plz` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Postleitzahl der Rechnungsadresse',
  `rechnungs_ort` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ort der Rechnungsadresse',
  `rechnungs_email` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'E-Mail-Adresse für Rechnungen',
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pfad zum Kunden-Logo',
  `notizen` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Notizen',
  `status` enum('aktiv','inaktiv','gesperrt') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktiv' COMMENT 'Status',
  `erstellt_von` int UNSIGNED DEFAULT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kunden für Firmen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `customer_contracts`
--

CREATE TABLE `customer_contracts` (
  `id` int UNSIGNED NOT NULL,
  `customer_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Kunden',
  `dateiname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Original-Dateiname',
  `dateipfad` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Pfad zur gespeicherten Datei',
  `dateigroesse` int UNSIGNED DEFAULT NULL COMMENT 'Dateigröße in Bytes',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME-Typ der Datei',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Beschreibung/Notizen zur Rechnung',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rechnungen für Kunden';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `customer_documents`
--

CREATE TABLE `customer_documents` (
  `id` int UNSIGNED NOT NULL,
  `customer_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Kunden',
  `dateiname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Original-Dateiname',
  `dateipfad` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Pfad zur gespeicherten Datei',
  `dateigroesse` int UNSIGNED DEFAULT NULL COMMENT 'Dateigröße in Bytes',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME-Typ der Datei',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Beschreibung/Notizen zum Dokument',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dokumente für Kunden';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `customer_notes`
--

CREATE TABLE `customer_notes` (
  `id` int UNSIGNED NOT NULL,
  `customer_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Kunden',
  `titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Titel der Notiz',
  `inhalt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Inhalt der Notiz',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notizen für Kunden';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `dashboard_cards`
--

CREATE TABLE `dashboard_cards` (
  `id` int UNSIGNED NOT NULL,
  `titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Titel der Card',
  `nachricht` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Beschreibungstext',
  `bild` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pfad zum Bild (optional)',
  `bild_dark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pfad zum Bild für Dark Mode (optional)',
  `button_text` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Text des Buttons',
  `button_link` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL des Buttons',
  `typ` enum('info','warning') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info' COMMENT 'Darstellungsart',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Firma (NULL = alle)',
  `sort_order` int NOT NULL DEFAULT '0' COMMENT 'Sortierung (niedriger = zuerst)',
  `aktiv` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=aktiv, 0=inaktiv',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dashboard-Cards für Aktuelles-Bereich';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `devices`
--

CREATE TABLE `devices` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Gerätename',
  `typ` enum('drucker','computer','netzwerk','smartphone','monitor','divers') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Gerätetyp',
  `details` json DEFAULT NULL COMMENT 'Gerätespezifische Details (JSON)',
  `hersteller` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hersteller',
  `modell` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Modell',
  `seriennummer` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Seriennummer',
  `mac_adresse` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MAC-Adresse',
  `ip_adresse` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP-Adresse',
  `betriebssystem` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Betriebssystem',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Beschreibung/Notizen',
  `status` enum('aktiv','inaktiv','wartung','ausgemustert') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktiv' COMMENT 'Status',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zur Firma (optional)',
  `customer_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Kunden (optional)',
  `user_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Benutzer (optional, für Firmen-User)',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Geräte';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `device_attachments`
--

CREATE TABLE `device_attachments` (
  `id` int UNSIGNED NOT NULL,
  `device_id` int UNSIGNED NOT NULL COMMENT 'Geräte-ID',
  `dateiname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Originaler Dateiname (bei Datei-Upload)',
  `dateipfad` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Relativer Pfad zur Datei (bei Datei-Upload)',
  `link_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL-Link (bei Link-Anhang)',
  `link_titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Titel des Links (bei Link-Anhang)',
  `anhang_typ` enum('datei','link') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'datei' COMMENT 'Typ des Anhangs',
  `dateigroesse` int UNSIGNED DEFAULT NULL COMMENT 'Dateigröße in Bytes (bei Datei-Upload)',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME-Typ der Datei (bei Datei-Upload)',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anhänge für Geräte';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `downloads`
--

CREATE TABLE `downloads` (
  `id` int UNSIGNED NOT NULL,
  `titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Anzeigename des Downloads',
  `typ` enum('link','datei') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'link = externer Link, datei = hochgeladene Datei',
  `url` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL (nur bei typ=link)',
  `dateipfad` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Relativer Pfad in uploads/downloads/ (nur bei typ=datei)',
  `dateiname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Original-Dateiname für Download (nur bei typ=datei)',
  `sichtbar_fuer` enum('alle','person','firma','kunde') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alle' COMMENT 'Zielgruppe',
  `user_id` int UNSIGNED DEFAULT NULL COMMENT 'users.id (nur bei sichtbar_fuer=person)',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'companies.id (nur bei sichtbar_fuer=firma)',
  `customer_id` int UNSIGNED DEFAULT NULL COMMENT 'customers.id (nur bei sichtbar_fuer=kunde)',
  `intern` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = nur Admin/Techniker sichtbar',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'users.id',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Downloads (Links oder Dateien) mit Sichtbarkeit pro Zielgruppe';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `variables` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `erstellt_datum` datetime DEFAULT CURRENT_TIMESTAMP,
  `geaendert_datum` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `explosion_drawings`
--

CREATE TABLE `explosion_drawings` (
  `id` int UNSIGNED NOT NULL,
  `bezeichnung` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Bezeichnung der Explosionszeichnung',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Optionale Beschreibung',
  `dateiname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Original-Dateiname',
  `dateipfad` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Relativer Pfad zur Datei',
  `dateigroesse` int UNSIGNED NOT NULL COMMENT 'Dateigröße in Bytes',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME-Typ der Datei',
  `erstellt_von` int UNSIGNED DEFAULT NULL COMMENT 'Benutzer, der die Zeichnung hochgeladen hat',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Explosionszeichnungen für Geräte';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `explosion_drawing_device_models`
--

CREATE TABLE `explosion_drawing_device_models` (
  `id` int UNSIGNED NOT NULL,
  `explosion_drawing_id` int UNSIGNED NOT NULL,
  `hersteller` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modell` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zuordnung Explosionszeichnung zu Gerätemodell';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `gimmick_dumb_requests`
--

CREATE TABLE `gimmick_dumb_requests` (
  `id` int UNSIGNED NOT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Text der Anfrage (manuell oder von Ticket)',
  `ticket_id` int UNSIGNED DEFAULT NULL COMMENT 'Optional: verknüpftes Ticket',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dümmste Anfragen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `gimmick_records`
--

CREATE TABLE `gimmick_records` (
  `id` int UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Titel des Rekords',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Beschreibung',
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rekord-Wert (z.B. Zahl, Einheit)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rekorde';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `gimmick_wheels`
--

CREATE TABLE `gimmick_wheels` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name des Glücksrads',
  `values_json` json NOT NULL COMMENT 'Segment-Werte als JSON-Array',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Gespeicherte Glücksräder';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kb_attachments`
--

CREATE TABLE `kb_attachments` (
  `id` int UNSIGNED NOT NULL,
  `page_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'relativer Pfad z.B. knowledge/xxx.jpg',
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Originaldateiname',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application/octet-stream',
  `file_size` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Größe in Bytes',
  `uploaded_by` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Wissensdatenbank: Anhänge (Bilder, Dokumente)';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kb_pages`
--

CREATE TABLE `kb_pages` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'UUID',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `content_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'html' COMMENT 'html | json',
  `parent_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_index` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `author_id` int UNSIGNED NOT NULL COMMENT 'users.id',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Firma (Filter über Navigation)',
  `is_system_folder` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = Systemordner (nicht verschiebbar/löschbar)',
  `system_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'calls | notes',
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Wissensdatenbank Seiten';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kb_page_tags`
--

CREATE TABLE `kb_page_tags` (
  `page_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tag_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KB Seite-Tag Zuordnung';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kb_page_versions`
--

CREATE TABLE `kb_page_versions` (
  `id` int UNSIGNED NOT NULL,
  `page_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Titel der Seite vor der Änderung',
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'json' COMMENT 'html | json',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KB Seitenversionen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kb_page_views`
--

CREATE TABLE `kb_page_views` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `page_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KB Zuletzt angesehene Seiten';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kb_tags`
--

CREATE TABLE `kb_tags` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KB Tags';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `links`
--

CREATE TABLE `links` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name des Links',
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL des Links',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zur Firma (optional)',
  `notiz` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Notiz zum Link',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `logs`
--

CREATE TABLE `logs` (
  `id` int UNSIGNED NOT NULL,
  `kategorie` enum('device','customer','todo','ticket','job','software','package','company','user','order','systemmailer','sonstiges') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kategorie der Änderung',
  `entity_id` int UNSIGNED NOT NULL COMMENT 'ID der geänderten Entität',
  `user_id` int UNSIGNED NOT NULL COMMENT 'User-ID des Benutzers, der die Änderung vorgenommen hat',
  `action` enum('created','updated','deleted','viewed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Aktion (erstellt, aktualisiert, gelöscht, aufgerufen)',
  `field_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Name des geänderten Feldes (optional)',
  `old_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Alter Wert (optional)',
  `new_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Neuer Wert (optional)',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Zusätzliche Beschreibung (optional)',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum und Zeit der Änderung'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Änderungsprotokoll';

--
-- Daten für Tabelle `logs`
--

INSERT INTO `logs` (`id`, `kategorie`, `entity_id`, `user_id`, `action`, `field_name`, `old_value`, `new_value`, `beschreibung`, `erstellt_datum`) VALUES
(1, 'sonstiges', 0, 1, 'viewed', NULL, NULL, NULL, 'Tickets: Übersicht aufgerufen', '2026-05-25 14:52:17'),
(2, 'sonstiges', 0, 1, 'viewed', NULL, NULL, NULL, 'Tickets API: Liste (0 Tickets)', '2026-05-25 14:52:17'),
(3, 'sonstiges', 0, 1, 'viewed', NULL, NULL, NULL, 'Tickets: Übersicht aufgerufen', '2026-05-25 14:55:56'),
(4, 'sonstiges', 0, 1, 'viewed', NULL, NULL, NULL, 'Tickets API: Liste (0 Tickets)', '2026-05-25 14:55:56'),
(5, 'sonstiges', 0, 1, 'viewed', NULL, NULL, NULL, 'Tickets: Übersicht aufgerufen', '2026-05-25 14:55:59'),
(6, 'sonstiges', 0, 1, 'viewed', NULL, NULL, NULL, 'Tickets API: Liste (0 Tickets)', '2026-05-25 14:55:59'),
(7, 'sonstiges', 0, 1, 'viewed', NULL, NULL, NULL, 'Tickets: Übersicht aufgerufen', '2026-05-25 14:56:05'),
(8, 'sonstiges', 0, 1, 'viewed', NULL, NULL, NULL, 'Tickets API: Liste (0 Tickets)', '2026-05-25 14:56:05'),
(9, 'sonstiges', 0, 1, 'viewed', NULL, NULL, NULL, 'Tickets: Übersicht aufgerufen', '2026-05-25 14:56:07'),
(10, 'sonstiges', 0, 1, 'viewed', NULL, NULL, NULL, 'Tickets API: Liste (0 Tickets)', '2026-05-25 14:56:08');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mail_log`
--

CREATE TABLE `mail_log` (
  `id` bigint UNSIGNED NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `recipients` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kommagetrennte Empfänger-Adressen',
  `subject` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `from_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Allgemein' COMMENT 'Bereich/z. B. Kalender, Benachrichtigung',
  `status` enum('success','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `notes`
--

CREATE TABLE `notes` (
  `id` int UNSIGNED NOT NULL,
  `folder_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Notiz-Ordner',
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Reihenfolge im Ordner',
  `titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Titel der Notiz',
  `inhalt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Inhalt der Notiz',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Persönliche Notizen in Ordnern';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `note_folders`
--

CREATE TABLE `note_folders` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name des Ordners',
  `is_private` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = persönlich (nur Erstellender + eingeladene Mitglieder)',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordner für persönliche Notizen (Teilen mit Admin/Techniker)';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `note_folder_members`
--

CREATE TABLE `note_folder_members` (
  `folder_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Geteilte Zugriff auf persönliche Notiz-Ordner (Admin/Techniker)';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `notifications`
--

CREATE TABLE `notifications` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Benutzer, der die Benachrichtigung erhält',
  `created_by_user_id` int UNSIGNED DEFAULT NULL COMMENT 'ID des Benutzers, der die Benachrichtigung ausgelöst hat',
  `typ` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Typ der Benachrichtigung (z.B. ticket_erstellt, ticket_nachricht, todo_erstellt)',
  `titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Titel der Benachrichtigung',
  `nachricht` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nachrichtentext',
  `link` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Link zur relevanten Seite',
  `relevanz` enum('niedrig','normal','hoch','kritisch') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT 'Relevanz der Benachrichtigung',
  `ist_gelesen` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Ist die Benachrichtigung gelesen (0=nein, 1=ja)',
  `gelesen_datum` datetime DEFAULT NULL COMMENT 'Datum wann gelesen wurde',
  `referenz_id` int UNSIGNED DEFAULT NULL COMMENT 'ID des referenzierten Objekts (z.B. Ticket-ID, Todo-ID)',
  `referenz_typ` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Typ des referenzierten Objekts (z.B. ticket, todo)',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System-Benachrichtigungen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `orders`
--

CREATE TABLE `orders` (
  `id` int UNSIGNED NOT NULL,
  `bestellnummer` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Bestellnummer',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Beschreibung der Bestellung',
  `notizen` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Notizen zur Bestellung',
  `tracking_nummer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tracking-Nummer der Sendung',
  `tracking_link` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tracking-Link zur Sendungsverfolgung',
  `status` enum('Neu','Bestellt','Unterwegs','Beim Kunden','Im Lager','Angekommen') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Neu',
  `garantie` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = Bestellung über Garantie',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zur Firma (optional)',
  `customer_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Kunden (optional)',
  `bestellung_durch` enum('intern','firma','kunde') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Bestellung durch: intern = wir, firma = Firma, kunde = Kunde',
  `ticket_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Ticket (optional)',
  `project_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Projekt',
  `comment_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Ticket-Kommentar (optional)',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bestellungen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL COMMENT 'Referenz zur Bestellung',
  `status` enum('Neu','Bestellt','Unterwegs','Beim Kunden','Im Lager','Angekommen') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `geaendert_von` int UNSIGNED DEFAULT NULL COMMENT 'User-ID des Änderers',
  `geaendert_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Änderungsdatum',
  `bemerkung` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optionaler Grund (z. B. Ticket geschlossen)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bestellungs-Status-Historie';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `projects`
--

CREATE TABLE `projects` (
  `id` int UNSIGNED NOT NULL,
  `bezeichnung` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Projektbezeichnung',
  `project_nummer` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Eindeutige Projektnummer',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Projektbeschreibung',
  `status` enum('Neu','In Planung','In Bearbeitung','Wartend','Abgeschlossen','Archiviert') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Neu' COMMENT 'Projektstatus',
  `sort_order` int NOT NULL DEFAULT '0',
  `start_datum` date DEFAULT NULL COMMENT 'Projektstart',
  `end_datum` date DEFAULT NULL COMMENT 'Projektende',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zur Firma',
  `customer_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Kunden',
  `beauftragter_user_id` int UNSIGNED DEFAULT NULL COMMENT 'Beauftragter (User)',
  `ansprechpartner_user_id` int UNSIGNED DEFAULT NULL COMMENT 'Ansprechpartner als User-Referenz',
  `ansprechpartner_manuell_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ansprechpartner Name (manuell)',
  `ansprechpartner_manuell_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ansprechpartner E-Mail (manuell)',
  `ansprechpartner_manuell_telefon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ansprechpartner Telefon (manuell)',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum',
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft-Delete-Zeitpunkt; NULL = aktiv'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Projekte';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `project_attachments`
--

CREATE TABLE `project_attachments` (
  `id` int UNSIGNED NOT NULL,
  `project_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Projekt',
  `dateiname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Original-Dateiname',
  `dateipfad` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Speicherpfad',
  `dateigroesse` int UNSIGNED DEFAULT NULL COMMENT 'Größe in Bytes',
  `mime_type` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `erstellt_von` int UNSIGNED NOT NULL,
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dokumente/Anhänge zu Projekten';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `project_notes`
--

CREATE TABLE `project_notes` (
  `id` int UNSIGNED NOT NULL,
  `project_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Projekt',
  `titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Titel der Notiz',
  `inhalt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Inhalt der Notiz',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notizen zu Projekten';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `project_observers`
--

CREATE TABLE `project_observers` (
  `project_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `hinzugefuegt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Beobachter von Projekten';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `project_tickets`
--

CREATE TABLE `project_tickets` (
  `project_id` int UNSIGNED NOT NULL,
  `ticket_id` int UNSIGNED NOT NULL,
  `hinzugefuegt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum der Verknüpfung'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Verknüpfung Projekte – Tickets';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `endpoint` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint_sha256` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `p256dh` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `auth_secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_encoding` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aesgcm',
  `user_agent` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `remember_me_tokens`
--

CREATE TABLE `remember_me_tokens` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `token_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_fingerprint` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `satisfaction_surveys`
--

CREATE TABLE `satisfaction_surveys` (
  `id` int UNSIGNED NOT NULL,
  `titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Interner Titel',
  `frage` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Umfragefrage',
  `aktiv` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Umfrage aktiv (1=ja, 0=nein)',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Firma (NULL = alle Firmen)',
  `erstellt_von` int UNSIGNED DEFAULT NULL,
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zufriedenheitsumfragen (mehrere)';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `satisfaction_survey_responses`
--

CREATE TABLE `satisfaction_survey_responses` (
  `id` int UNSIGNED NOT NULL,
  `survey_id` int UNSIGNED NOT NULL DEFAULT '1',
  `user_id` int UNSIGNED NOT NULL,
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Firma zum Zeitpunkt der Antwort',
  `rating` tinyint UNSIGNED NOT NULL COMMENT 'Bewertung 1-5 (1=sehr unzufrieden, 5=sehr zufrieden)',
  `feedback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Verbesserungsvorschlag bei Rating 1-3',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zufriedenheitsumfrage-Antworten';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `shelves`
--

CREATE TABLE `shelves` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Regal-Bezeichnung (z.B. A, Regal 1)',
  `beschreibung` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optionale Beschreibung',
  `sort_order` int NOT NULL DEFAULT '0' COMMENT 'Sortierung in Listen',
  `spalten_anzahl` tinyint UNSIGNED NOT NULL DEFAULT '5' COMMENT 'Anzahl Spalten für 3D-Darstellung',
  `faecher_anzahl` tinyint UNSIGNED NOT NULL DEFAULT '6' COMMENT 'Anzahl Fächer/Ebenen für 3D-Darstellung',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Regale im Lager';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `erstellt_datum` datetime DEFAULT CURRENT_TIMESTAMP,
  `geaendert_datum` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tickets`
--

CREATE TABLE `tickets` (
  `id` int UNSIGNED NOT NULL,
  `ticket_nummer` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Eindeutige Ticket-Nummer',
  `titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Titel des Tickets',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Beschreibung des Problems',
  `status` enum('Neu','In Bearbeitung','Warteschlange','Geplant','Bestellung offen','Geschlossen','Archiv') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Neu' COMMENT 'Status des Tickets',
  `prioritaet` enum('niedrig','normal','hoch','kritisch') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT 'Priorität',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zur Firma',
  `customer_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Kunden',
  `device_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Gerät',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `zugewiesen_an` int UNSIGNED DEFAULT NULL COMMENT 'User-ID des zugewiesenen Technikers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum',
  `geschlossen_datum` datetime DEFAULT NULL COMMENT 'Schließungsdatum',
  `faellig_datum` datetime DEFAULT NULL COMMENT 'Fälligkeitsdatum',
  `faellig_datum_ende` datetime DEFAULT NULL COMMENT 'Ende des fälligen Zeitfensters',
  `geplant_datum` datetime DEFAULT NULL COMMENT 'Geplantes Datum',
  `geplant_datum_ende` datetime DEFAULT NULL COMMENT 'Ende des geplanten Zeitfensters',
  `abgeschlossen_datum` datetime DEFAULT NULL COMMENT 'Abgeschlossen-Datum',
  `abgerechnet` tinyint(1) DEFAULT NULL COMMENT 'Abgerechnet (1=ja, 0=nein, NULL=noch nicht abgerechnet)',
  `bearbeitungszeit_minuten` int UNSIGNED DEFAULT NULL COMMENT 'Aufgewendete Zeit in Minuten beim Schließen (nur Techniker/Admin)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tickets';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ticket_appointments`
--

CREATE TABLE `ticket_appointments` (
  `id` int UNSIGNED NOT NULL,
  `ticket_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Ticket',
  `titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Titel des Termins (optional)',
  `typ` enum('geplant','faellig') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Typ des Termins: geplant oder fällig',
  `start_datum` datetime NOT NULL COMMENT 'Startdatum des Termins',
  `ende_datum` datetime DEFAULT NULL COMMENT 'Enddatum des Termins (optional)',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `erstellt_von` int UNSIGNED DEFAULT NULL COMMENT 'Benutzer, der den Termin erstellt hat'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Termine für Tickets';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ticket_attachments`
--

CREATE TABLE `ticket_attachments` (
  `id` int UNSIGNED NOT NULL,
  `ticket_id` int UNSIGNED NOT NULL COMMENT 'Ticket-ID',
  `dateiname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Originaler Dateiname',
  `dateipfad` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Relativer Pfad zur Datei',
  `dateigroesse` int UNSIGNED NOT NULL COMMENT 'Dateigröße in Bytes',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME-Typ der Datei',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anhänge für Tickets';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ticket_comments`
--

CREATE TABLE `ticket_comments` (
  `id` int UNSIGNED NOT NULL,
  `ticket_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Ticket',
  `user_id` int UNSIGNED NOT NULL COMMENT 'User-ID des Kommentar-Erstellers',
  `kommentar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kommentar-Text',
  `nachrichtentyp` enum('nachricht','loesung','aufgabe','bestellung') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nachricht' COMMENT 'Typ der Nachricht',
  `ist_intern` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Ist interner Kommentar (nicht für Kunde sichtbar)',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ticket-Kommentare';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ticket_comment_reads`
--

CREATE TABLE `ticket_comment_reads` (
  `id` int UNSIGNED NOT NULL,
  `comment_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Kommentar',
  `user_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Benutzer',
  `gelesen_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum wann der Kommentar gelesen wurde'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Gelesene Kommentare';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ticket_observers`
--

CREATE TABLE `ticket_observers` (
  `id` int UNSIGNED NOT NULL,
  `ticket_id` int UNSIGNED NOT NULL COMMENT 'Ticket-ID',
  `user_id` int UNSIGNED NOT NULL COMMENT 'User-ID des Beobachters',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Beobachter für Tickets';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ticket_unread_reminder`
--

CREATE TABLE `ticket_unread_reminder` (
  `user_id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `gesetzt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `time_tracking`
--

CREATE TABLE `time_tracking` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Benutzer',
  `start_time` datetime NOT NULL COMMENT 'Startzeitpunkt',
  `end_time` datetime DEFAULT NULL COMMENT 'Endzeitpunkt (NULL wenn noch läuft)',
  `duration_minutes` int DEFAULT NULL COMMENT 'Dauer in Minuten',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Beschreibung/Notiz',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zeiterfassung';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `time_tracking_vacation`
--

CREATE TABLE `time_tracking_vacation` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Benutzer',
  `date` date NOT NULL COMMENT 'Urlaubstag',
  `hours` decimal(4,2) DEFAULT '8.00' COMMENT 'Stunden für diesen Tag',
  `type` enum('vacation','sick','holiday','school','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vacation' COMMENT 'Art des Tages',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Urlaubstage';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `todos`
--

CREATE TABLE `todos` (
  `id` int UNSIGNED NOT NULL,
  `titel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Titel der Aufgabe',
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Beschreibung der Aufgabe',
  `status` enum('offen','in_bearbeitung','erledigt') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'offen' COMMENT 'Status der Aufgabe',
  `prioritaet` enum('niedrig','normal','hoch','kritisch') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT 'Priorität',
  `favorit` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Favoriten-Status (0 = nicht favorisiert, 1 = favorisiert)',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zur Firma (optional)',
  `folder_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Ordner (optional)',
  `ticket_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Ticket (optional)',
  `project_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Projekt',
  `comment_id` int UNSIGNED DEFAULT NULL COMMENT 'Ticket-Kommentar-ID bei Aufgabe aus Service-Chat',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `zugewiesen_an` int UNSIGNED DEFAULT NULL COMMENT 'User-ID des zugewiesenen Benutzers',
  `faellig_am` datetime DEFAULT NULL COMMENT 'Fälligkeitsdatum',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum',
  `erledigt_datum` datetime DEFAULT NULL COMMENT 'Erledigungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Aufgaben/Todos';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `todo_attachments`
--

CREATE TABLE `todo_attachments` (
  `id` int UNSIGNED NOT NULL,
  `todo_id` int UNSIGNED NOT NULL COMMENT 'Todo-ID',
  `dateiname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Originaler Dateiname',
  `dateipfad` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Relativer Pfad zur Datei',
  `dateigroesse` int UNSIGNED NOT NULL COMMENT 'Dateigröße in Bytes',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME-Typ der Datei',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anhänge für Todos';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `todo_folders`
--

CREATE TABLE `todo_folders` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name des Ordners',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zur Firma (optional)',
  `is_private` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = nur Erstellender + eingeladene Mitglieder sehen den Ordner, 0 = altes Verhalten (alle Techniker/Admin)',
  `erstellt_von` int UNSIGNED NOT NULL COMMENT 'User-ID des Erstellers',
  `is_ticket_system_folder` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = Systemordner für Ticket-Aufgaben, kann nicht gelöscht werden',
  `is_project_system_folder` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = Systemordner für Projektaufgaben, kann nicht gelöscht werden',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordner für Todos';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `todo_folder_members`
--

CREATE TABLE `todo_folder_members` (
  `folder_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Eingeladene Benutzer für private/geteilte Todo-Ordner';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `todo_user_sorts`
--

CREATE TABLE `todo_user_sorts` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'User-ID',
  `todo_id` int UNSIGNED NOT NULL COMMENT 'Todo-ID',
  `folder_id` int UNSIGNED DEFAULT NULL COMMENT 'Ordner-ID (NULL für Todos ohne Ordner)',
  `sort_order` int NOT NULL DEFAULT '0' COMMENT 'Sortierreihenfolge',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Benutzerdefinierte Sortierung von Todos';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `trusted_devices`
--

CREATE TABLE `trusted_devices` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Benutzer',
  `device_fingerprint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Eindeutiger Geräte-Fingerprint',
  `device_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Benutzerdefinierter Gerätename',
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'User-Agent String',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP-Adresse',
  `last_used` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letzte Verwendung',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vertraute Geräte für 2FA';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'E-Mail-Adresse',
  `passwort` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Gehashtes Passwort',
  `company_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zur Firma',
  `lager_bestand_anpassen` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Darf Lagerbestand anpassen',
  `customer_id` int UNSIGNED DEFAULT NULL COMMENT 'Referenz zum Kunden (erforderlich für Rolle Kunde)',
  `vorname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Vorname',
  `nachname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nachname',
  `anrede` enum('herr','frau','divers','neutral') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Anrede',
  `position_funktion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Position/Funktion',
  `abteilung` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Abteilung',
  `sprache` enum('de','en') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'de' COMMENT 'Sprache',
  `zeitzone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Europe/Berlin' COMMENT 'Zeitzone',
  `stellvertreter_user_id` int UNSIGNED DEFAULT NULL COMMENT 'Stellvertreter',
  `stellvertreter_von` date DEFAULT NULL,
  `stellvertreter_bis` date DEFAULT NULL,
  `kontakt_messenger` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `erreichbarkeit` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `kontaktkanal` enum('portal','email','telefon','teams','whatsapp') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'email' COMMENT 'Bevorzugter Kontaktkanal',
  `telefonnummer` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Telefonnummer des Benutzers',
  `mobilnummer` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mobiltelefon',
  `logopfad` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pfad zum Benutzer-Logo/Avatar',
  `rolle` enum('Admin','Techniker','Firmen-Admin','Firmen-User','Kunde') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Kunde' COMMENT 'Benutzerrolle',
  `passwort_zuruecksetzen` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Muss Passwort zurückgesetzt werden (0=nein, 1=ja)',
  `onboarding_abgeschlossen` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Onboarding abgeschlossen (0=nein, 1=ja)',
  `letztes_pw_change` datetime DEFAULT NULL COMMENT 'Datum der letzten Passwortänderung',
  `letzte_anmeldung` datetime DEFAULT NULL COMMENT 'Datum der letzten Anmeldung',
  `status` enum('aktiv','inaktiv','gesperrt') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktiv' COMMENT 'Status des Benutzers',
  `calendar_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fehlversuche` int NOT NULL DEFAULT '0' COMMENT 'Anzahl fehlgeschlagener Login-Versuche',
  `gesperrt` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Ist Account gesperrt (0=nein, 1=ja)',
  `gesperrt_bis` datetime DEFAULT NULL COMMENT 'Sperre bis zu diesem Datum',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum',
  `webauthn_user_handle` varbinary(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Benutzer';

--
-- Daten für Tabelle `users`
--

INSERT INTO `users` (`id`, `email`, `passwort`, `company_id`, `lager_bestand_anpassen`, `customer_id`, `vorname`, `nachname`, `anrede`, `position_funktion`, `abteilung`, `sprache`, `zeitzone`, `stellvertreter_user_id`, `stellvertreter_von`, `stellvertreter_bis`, `kontakt_messenger`, `erreichbarkeit`, `kontaktkanal`, `telefonnummer`, `mobilnummer`, `logopfad`, `rolle`, `passwort_zuruecksetzen`, `onboarding_abgeschlossen`, `letztes_pw_change`, `letzte_anmeldung`, `status`, `calendar_token`, `fehlversuche`, `gesperrt`, `gesperrt_bis`, `erstellt_datum`, `geaendert_datum`, `webauthn_user_handle`) VALUES
(1, 'admin@serohub.de', '$2y$12$8CBPr7ViFle6oOLHs46FiezZQZN1A1oIanT4whFTl84hI/a287v4.', 1, 0, NULL, 'Admin', 'Serohub', 'herr', NULL, NULL, 'de', 'Europe/Berlin', NULL, NULL, NULL, NULL, NULL, 'portal', NULL, NULL, 'preset:#f59e0b:SH', 'Admin', 0, 1, '2026-05-24 13:06:18', '2026-05-25 14:55:27', 'aktiv', '', 0, 0, NULL, '2026-01-14 18:16:15', '2026-05-25 14:55:27', '');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_caldav_sync`
--

CREATE TABLE `user_caldav_sync` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `caldav_server_id` int UNSIGNED NOT NULL,
  `caldav_username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Benutzername für CalDAV (z.B. Nextcloud-Login)',
  `caldav_password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Verschlüsseltes Passwort',
  `calendar_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Personal' COMMENT 'Kalendername (z.B. Personal bei Nextcloud)',
  `export_sources` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON: my_calendar, vacation, invitations, service_tickets, todos',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Bezeichnung',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_sync` datetime DEFAULT NULL,
  `last_sync_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ok, error, etc.',
  `last_sync_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CalDAV-Push-Sync pro Benutzer';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_passkeys`
--

CREATE TABLE `user_passkeys` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `credential_id` varbinary(1024) NOT NULL,
  `credential_data` json NOT NULL,
  `label` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int UNSIGNED NOT NULL,
  `session_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PHP session_id()',
  `user_id` int UNSIGNED NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `browser_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser_version` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `forwarded_for` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accept_language` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sec_ch_ua` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sec_ch_ua_platform` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sec_ch_ua_mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sec_ch_ua_model` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remote_port` int UNSIGNED DEFAULT NULL,
  `is_https` tinyint(1) NOT NULL DEFAULT '0',
  `login_method` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_me_used` tinyint(1) NOT NULL DEFAULT '0',
  `last_activity` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Aktive Anmeldungen pro Benutzer (für Einstellungen Sicherheit)';

--
-- Daten für Tabelle `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `session_id`, `user_id`, `ip_address`, `user_agent`, `browser_name`, `browser_version`, `os_name`, `device_type`, `forwarded_for`, `accept_language`, `sec_ch_ua`, `sec_ch_ua_platform`, `sec_ch_ua_mobile`, `sec_ch_ua_model`, `remote_port`, `is_https`, `login_method`, `remember_me_used`, `created_at`) VALUES
(1, '05e9743e8f5718a9e9dcd935f7ab4c8a', 1, '2a00:1e:ef00:dd01:19ff:56b:c2b4:6e73', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'Chrome', '148.0.0.0', 'macOS', 'desktop', '', 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7', '\"Chromium\";v=\"148\", \"Google Chrome\";v=\"148\", \"Not/A)Brand\";v=\"99\"', '\"macOS\"', '?0', '', 65535, 1, 'session', 0, '2026-05-25 14:52:14'),
(45, '05c34c936ca41e5724b676d24e7c8a3d', 1, '2a00:1e:ef00:dd01:19ff:56b:c2b4:6e73', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'Chrome', '148.0.0.0', 'macOS', 'desktop', '', 'de,de-DE;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6', '\"Chromium\";v=\"148\", \"Microsoft Edge\";v=\"148\", \"Not/A)Brand\";v=\"99\"', '\"macOS\"', '?0', '', 65535, 1, 'session', 0, '2026-05-25 14:55:27');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Referenz zum Benutzer',
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Einstellungsschlüssel',
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Einstellungswert (JSON oder Text)',
  `erstellt_datum` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum',
  `geaendert_datum` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letztes Änderungsdatum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Benutzereinstellungen';

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aktiv` (`aktiv`),
  ADD KEY `idx_show_banner` (`show_banner`),
  ADD KEY `idx_erstellt_datum` (`erstellt_datum`),
  ADD KEY `fk_announcements_user` (`erstellt_von`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indizes für die Tabelle `caldav_servers`
--
ALTER TABLE `caldav_servers`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ics_token` (`ics_token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_start_at` (`start_at`),
  ADD KEY `idx_end_at` (`end_at`);

--
-- Indizes für die Tabelle `calendar_event_invitees`
--
ALTER TABLE `calendar_event_invitees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_event_user` (`event_id`,`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indizes für die Tabelle `calendar_subscriptions`
--
ALTER TABLE `calendar_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_active` (`user_id`,`is_active`);

--
-- Indizes für die Tabelle `calls`
--
ALTER TABLE `calls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_telefonnummer` (`telefonnummer`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_anruftyp` (`anruftyp`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_erstellt_datum` (`erstellt_datum`);

--
-- Indizes für die Tabelle `changelog_entries`
--
ALTER TABLE `changelog_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `version_id` (`version_id`);

--
-- Indizes für die Tabelle `changelog_files`
--
ALTER TABLE `changelog_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `version_id` (`version_id`);

--
-- Indizes für die Tabelle `changelog_versions`
--
ALTER TABLE `changelog_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_version` (`version`);

--
-- Indizes für die Tabelle `comment_attachments`
--
ALTER TABLE `comment_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_comment_id` (`comment_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kundennummer` (`kundennummer`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_domain` (`domain`),
  ADD KEY `idx_zugewiesen_an` (`zugewiesen_an`),
  ADD KEY `idx_ansprechpartner_user_id` (`ansprechpartner_user_id`);

--
-- Indizes für die Tabelle `company_contracts`
--
ALTER TABLE `company_contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_erstellt_datum` (`erstellt_datum`);

--
-- Indizes für die Tabelle `company_documents`
--
ALTER TABLE `company_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_erstellt_datum` (`erstellt_datum`);

--
-- Indizes für die Tabelle `company_notes`
--
ALTER TABLE `company_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_erstellt_datum` (`erstellt_datum`);

--
-- Indizes für die Tabelle `company_wartung_mahnung`
--
ALTER TABLE `company_wartung_mahnung`
  ADD PRIMARY KEY (`company_id`,`frage_datum`);

--
-- Indizes für die Tabelle `company_wartung_zahlung_frage`
--
ALTER TABLE `company_wartung_zahlung_frage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `company_frage_datum` (`company_id`,`frage_datum`),
  ADD KEY `naechste_frage` (`naechste_frage_datum`);

--
-- Indizes für die Tabelle `consumables`
--
ALTER TABLE `consumables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_consumables_artikelnummer` (`artikelnummer`),
  ADD KEY `idx_consumables_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_consumables_shelf_id` (`shelf_id`),
  ADD KEY `idx_consumables_company_id` (`company_id`);

--
-- Indizes für die Tabelle `consumable_categories`
--
ALTER TABLE `consumable_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_consumable_categories_name` (`name`);

--
-- Indizes für die Tabelle `consumable_category_link`
--
ALTER TABLE `consumable_category_link`
  ADD PRIMARY KEY (`consumable_id`,`category_id`),
  ADD KEY `idx_consumable_category_link_category` (`category_id`);

--
-- Indizes für die Tabelle `consumable_company_link`
--
ALTER TABLE `consumable_company_link`
  ADD PRIMARY KEY (`consumable_id`,`company_id`),
  ADD KEY `idx_ccl_company` (`company_id`);

--
-- Indizes für die Tabelle `consumable_device_models`
--
ALTER TABLE `consumable_device_models`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_consumable_hersteller_modell` (`consumable_id`,`hersteller`,`modell`),
  ADD KEY `idx_consumable_device_models_consumable` (`consumable_id`),
  ADD KEY `idx_consumable_device_models_hersteller_modell` (`hersteller`,`modell`);

--
-- Indizes für die Tabelle `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_customers_kundennummer` (`kundennummer`),
  ADD KEY `idx_ansprechpartner_user_id` (`ansprechpartner_user_id`);

--
-- Indizes für die Tabelle `customer_contracts`
--
ALTER TABLE `customer_contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_erstellt_datum` (`erstellt_datum`);

--
-- Indizes für die Tabelle `customer_documents`
--
ALTER TABLE `customer_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_erstellt_datum` (`erstellt_datum`);

--
-- Indizes für die Tabelle `customer_notes`
--
ALTER TABLE `customer_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_erstellt_datum` (`erstellt_datum`);

--
-- Indizes für die Tabelle `dashboard_cards`
--
ALTER TABLE `dashboard_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aktiv_company` (`aktiv`,`company_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indizes für die Tabelle `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_typ` (`typ`);

--
-- Indizes für die Tabelle `device_attachments`
--
ALTER TABLE `device_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_device_id` (`device_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_anhang_typ` (`anhang_typ`);

--
-- Indizes für die Tabelle `downloads`
--
ALTER TABLE `downloads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sichtbar_user` (`sichtbar_fuer`,`user_id`),
  ADD KEY `idx_sichtbar_company` (`sichtbar_fuer`,`company_id`),
  ADD KEY `idx_sichtbar_customer` (`sichtbar_fuer`,`customer_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `explosion_drawings`
--
ALTER TABLE `explosion_drawings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_explosion_drawings_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_explosion_drawings_bezeichnung` (`bezeichnung`);

--
-- Indizes für die Tabelle `explosion_drawing_device_models`
--
ALTER TABLE `explosion_drawing_device_models`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_explosion_drawing_hersteller_modell` (`explosion_drawing_id`,`hersteller`,`modell`),
  ADD KEY `idx_explosion_drawing_device_models_drawing` (`explosion_drawing_id`),
  ADD KEY `idx_explosion_drawing_device_models_hersteller_modell` (`hersteller`,`modell`);

--
-- Indizes für die Tabelle `gimmick_dumb_requests`
--
ALTER TABLE `gimmick_dumb_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gimmick_dumb_requests_ticket_id` (`ticket_id`),
  ADD KEY `idx_gimmick_dumb_requests_created_by` (`created_by`);

--
-- Indizes für die Tabelle `gimmick_records`
--
ALTER TABLE `gimmick_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gimmick_records_created_by` (`created_by`);

--
-- Indizes für die Tabelle `gimmick_wheels`
--
ALTER TABLE `gimmick_wheels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gimmick_wheels_created_by` (`created_by`);

--
-- Indizes für die Tabelle `kb_attachments`
--
ALTER TABLE `kb_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kb_attachments_page_id` (`page_id`),
  ADD KEY `idx_kb_attachments_uploaded_by` (`uploaded_by`);

--
-- Indizes für die Tabelle `kb_pages`
--
ALTER TABLE `kb_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_order_index` (`order_index`),
  ADD KEY `idx_deleted_at` (`deleted_at`),
  ADD KEY `idx_author_id` (`author_id`),
  ADD KEY `idx_kb_pages_company_id` (`company_id`),
  ADD KEY `idx_kb_pages_company_parent` (`company_id`,`parent_id`);

--
-- Indizes für die Tabelle `kb_page_tags`
--
ALTER TABLE `kb_page_tags`
  ADD PRIMARY KEY (`page_id`,`tag_id`),
  ADD KEY `idx_tag_id` (`tag_id`);

--
-- Indizes für die Tabelle `kb_page_versions`
--
ALTER TABLE `kb_page_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_page_id` (`page_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_kb_page_versions_user` (`created_by`);

--
-- Indizes für die Tabelle `kb_page_views`
--
ALTER TABLE `kb_page_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_page` (`user_id`,`page_id`),
  ADD KEY `idx_viewed_at` (`viewed_at`),
  ADD KEY `fk_kb_page_views_page` (`page_id`);

--
-- Indizes für die Tabelle `kb_tags`
--
ALTER TABLE `kb_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indizes für die Tabelle `links`
--
ALTER TABLE `links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kategorie` (`kategorie`),
  ADD KEY `idx_entity_id` (`entity_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_erstellt_datum` (`erstellt_datum`),
  ADD KEY `idx_kategorie_entity` (`kategorie`,`entity_id`),
  ADD KEY `idx_logs_kategorie_entity_date` (`kategorie`,`entity_id`,`erstellt_datum`);

--
-- Indizes für die Tabelle `mail_log`
--
ALTER TABLE `mail_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sent_at` (`sent_at`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`);

--
-- Indizes für die Tabelle `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notes_folder_id` (`folder_id`),
  ADD KEY `idx_notes_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_notes_erstellt_datum` (`erstellt_datum`);

--
-- Indizes für die Tabelle `note_folders`
--
ALTER TABLE `note_folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_note_folders_erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `note_folder_members`
--
ALTER TABLE `note_folder_members`
  ADD PRIMARY KEY (`folder_id`,`user_id`),
  ADD KEY `idx_note_folder_members_user_id` (`user_id`);

--
-- Indizes für die Tabelle `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_ist_gelesen` (`ist_gelesen`),
  ADD KEY `idx_typ` (`typ`),
  ADD KEY `idx_relevanz` (`relevanz`),
  ADD KEY `idx_erstellt_datum` (`erstellt_datum`),
  ADD KEY `idx_referenz` (`referenz_typ`,`referenz_id`),
  ADD KEY `idx_created_by_user_id` (`created_by_user_id`);

--
-- Indizes für die Tabelle `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_bestellnummer` (`bestellnummer`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_comment_id` (`comment_id`),
  ADD KEY `idx_orders_project_id` (`project_id`);

--
-- Indizes für die Tabelle `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_geaendert_von` (`geaendert_von`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_geaendert_datum` (`geaendert_datum`);

--
-- Indizes für die Tabelle `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_projects_project_nummer` (`project_nummer`),
  ADD KEY `idx_projects_company_id` (`company_id`),
  ADD KEY `idx_projects_customer_id` (`customer_id`),
  ADD KEY `idx_projects_status` (`status`),
  ADD KEY `idx_projects_beauftragter` (`beauftragter_user_id`),
  ADD KEY `idx_projects_erstellt_von` (`erstellt_von`),
  ADD KEY `fk_projects_ansprechpartner` (`ansprechpartner_user_id`);

--
-- Indizes für die Tabelle `project_attachments`
--
ALTER TABLE `project_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_attachments_project_id` (`project_id`),
  ADD KEY `idx_project_attachments_erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `project_notes`
--
ALTER TABLE `project_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_notes_project_id` (`project_id`),
  ADD KEY `idx_project_notes_erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `project_observers`
--
ALTER TABLE `project_observers`
  ADD PRIMARY KEY (`project_id`,`user_id`),
  ADD KEY `idx_project_observers_user_id` (`user_id`);

--
-- Indizes für die Tabelle `project_tickets`
--
ALTER TABLE `project_tickets`
  ADD PRIMARY KEY (`project_id`,`ticket_id`),
  ADD KEY `idx_project_tickets_ticket_id` (`ticket_id`);

--
-- Indizes für die Tabelle `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_push_endpoint_sha256` (`endpoint_sha256`),
  ADD KEY `idx_push_user` (`user_id`);

--
-- Indizes für die Tabelle `remember_me_tokens`
--
ALTER TABLE `remember_me_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Indizes für die Tabelle `satisfaction_surveys`
--
ALTER TABLE `satisfaction_surveys`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aktiv` (`aktiv`),
  ADD KEY `idx_company` (`company_id`);

--
-- Indizes für die Tabelle `satisfaction_survey_responses`
--
ALTER TABLE `satisfaction_survey_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indizes für die Tabelle `shelves`
--
ALTER TABLE `shelves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_shelves_name` (`name`);

--
-- Indizes für die Tabelle `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indizes für die Tabelle `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_nummer` (`ticket_nummer`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_zugewiesen_an` (`zugewiesen_an`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_prioritaet` (`prioritaet`),
  ADD KEY `idx_device_id` (`device_id`);

--
-- Indizes für die Tabelle `ticket_appointments`
--
ALTER TABLE `ticket_appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_appointments_ticket_id` (`ticket_id`),
  ADD KEY `idx_ticket_appointments_start_datum` (`start_datum`),
  ADD KEY `idx_ticket_appointments_typ` (`typ`),
  ADD KEY `fk_ticket_appointments_erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `ticket_attachments`
--
ALTER TABLE `ticket_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `ticket_comments`
--
ALTER TABLE `ticket_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_nachrichtentyp` (`nachrichtentyp`);

--
-- Indizes für die Tabelle `ticket_comment_reads`
--
ALTER TABLE `ticket_comment_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_comment_user` (`comment_id`,`user_id`),
  ADD KEY `idx_comment_id` (`comment_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indizes für die Tabelle `ticket_observers`
--
ALTER TABLE `ticket_observers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ticket_user` (`ticket_id`,`user_id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indizes für die Tabelle `ticket_unread_reminder`
--
ALTER TABLE `ticket_unread_reminder`
  ADD PRIMARY KEY (`user_id`,`ticket_id`),
  ADD KEY `idx_tur_ticket` (`ticket_id`);

--
-- Indizes für die Tabelle `time_tracking`
--
ALTER TABLE `time_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_start_time` (`start_time`),
  ADD KEY `idx_end_time` (`end_time`);

--
-- Indizes für die Tabelle `time_tracking_vacation`
--
ALTER TABLE `time_tracking_vacation`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`date`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_date` (`date`);

--
-- Indizes für die Tabelle `todos`
--
ALTER TABLE `todos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`),
  ADD KEY `idx_zugewiesen_an` (`zugewiesen_an`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_prioritaet` (`prioritaet`),
  ADD KEY `idx_faellig_am` (`faellig_am`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_folder_id` (`folder_id`),
  ADD KEY `idx_favorit` (`favorit`),
  ADD KEY `idx_todos_comment_id` (`comment_id`),
  ADD KEY `idx_todos_project_id` (`project_id`);

--
-- Indizes für die Tabelle `todo_attachments`
--
ALTER TABLE `todo_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_todo_id` (`todo_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `todo_folders`
--
ALTER TABLE `todo_folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `todo_folder_members`
--
ALTER TABLE `todo_folder_members`
  ADD PRIMARY KEY (`folder_id`,`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indizes für die Tabelle `todo_user_sorts`
--
ALTER TABLE `todo_user_sorts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_todo_folder` (`user_id`,`todo_id`,`folder_id`),
  ADD KEY `idx_user_folder_order` (`user_id`,`folder_id`,`sort_order`),
  ADD KEY `idx_todo_id` (`todo_id`),
  ADD KEY `fk_todo_user_sorts_folder` (`folder_id`);

--
-- Indizes für die Tabelle `trusted_devices`
--
ALTER TABLE `trusted_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_device` (`user_id`,`device_fingerprint`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_device_fingerprint` (`device_fingerprint`),
  ADD KEY `idx_last_used` (`last_used`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `idx_calendar_token` (`calendar_token`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_rolle` (`rolle`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_gesperrt` (`gesperrt`),
  ADD KEY `idx_users_company_status` (`company_id`,`status`),
  ADD KEY `idx_users_email_status` (`email`,`status`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_onboarding_abgeschlossen` (`onboarding_abgeschlossen`);

--
-- Indizes für die Tabelle `user_caldav_sync`
--
ALTER TABLE `user_caldav_sync`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_active` (`user_id`,`is_active`),
  ADD KEY `fk_caldav_server` (`caldav_server_id`);

--
-- Indizes für die Tabelle `user_passkeys`
--
ALTER TABLE `user_passkeys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_passkey_cred` (`credential_id`(384)),
  ADD KEY `idx_passkey_user` (`user_id`);

--
-- Indizes für die Tabelle `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `last_activity` (`last_activity`);

--
-- Indizes für die Tabelle `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_setting` (`user_id`,`setting_key`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `caldav_servers`
--
ALTER TABLE `caldav_servers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `calendar_events`
--
ALTER TABLE `calendar_events`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `calendar_event_invitees`
--
ALTER TABLE `calendar_event_invitees`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `calendar_subscriptions`
--
ALTER TABLE `calendar_subscriptions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `calls`
--
ALTER TABLE `calls`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `changelog_entries`
--
ALTER TABLE `changelog_entries`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `changelog_files`
--
ALTER TABLE `changelog_files`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `changelog_versions`
--
ALTER TABLE `changelog_versions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `comment_attachments`
--
ALTER TABLE `comment_attachments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `company_contracts`
--
ALTER TABLE `company_contracts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `company_documents`
--
ALTER TABLE `company_documents`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `company_notes`
--
ALTER TABLE `company_notes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `company_wartung_zahlung_frage`
--
ALTER TABLE `company_wartung_zahlung_frage`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `consumables`
--
ALTER TABLE `consumables`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `consumable_categories`
--
ALTER TABLE `consumable_categories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `consumable_device_models`
--
ALTER TABLE `consumable_device_models`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `customer_contracts`
--
ALTER TABLE `customer_contracts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `customer_documents`
--
ALTER TABLE `customer_documents`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `customer_notes`
--
ALTER TABLE `customer_notes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `dashboard_cards`
--
ALTER TABLE `dashboard_cards`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `device_attachments`
--
ALTER TABLE `device_attachments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `downloads`
--
ALTER TABLE `downloads`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `explosion_drawings`
--
ALTER TABLE `explosion_drawings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `explosion_drawing_device_models`
--
ALTER TABLE `explosion_drawing_device_models`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `gimmick_dumb_requests`
--
ALTER TABLE `gimmick_dumb_requests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `gimmick_records`
--
ALTER TABLE `gimmick_records`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `gimmick_wheels`
--
ALTER TABLE `gimmick_wheels`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `kb_attachments`
--
ALTER TABLE `kb_attachments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `kb_page_versions`
--
ALTER TABLE `kb_page_versions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `kb_page_views`
--
ALTER TABLE `kb_page_views`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `kb_tags`
--
ALTER TABLE `kb_tags`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `links`
--
ALTER TABLE `links`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT für Tabelle `mail_log`
--
ALTER TABLE `mail_log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `note_folders`
--
ALTER TABLE `note_folders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `project_attachments`
--
ALTER TABLE `project_attachments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `project_notes`
--
ALTER TABLE `project_notes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `remember_me_tokens`
--
ALTER TABLE `remember_me_tokens`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `satisfaction_surveys`
--
ALTER TABLE `satisfaction_surveys`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `satisfaction_survey_responses`
--
ALTER TABLE `satisfaction_survey_responses`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `shelves`
--
ALTER TABLE `shelves`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=173;

--
-- AUTO_INCREMENT für Tabelle `ticket_appointments`
--
ALTER TABLE `ticket_appointments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `ticket_attachments`
--
ALTER TABLE `ticket_attachments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `ticket_comments`
--
ALTER TABLE `ticket_comments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `ticket_comment_reads`
--
ALTER TABLE `ticket_comment_reads`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `ticket_observers`
--
ALTER TABLE `ticket_observers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `time_tracking`
--
ALTER TABLE `time_tracking`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `time_tracking_vacation`
--
ALTER TABLE `time_tracking_vacation`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `todos`
--
ALTER TABLE `todos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `todo_attachments`
--
ALTER TABLE `todo_attachments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `todo_folders`
--
ALTER TABLE `todo_folders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `todo_user_sorts`
--
ALTER TABLE `todo_user_sorts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `trusted_devices`
--
ALTER TABLE `trusted_devices`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `user_caldav_sync`
--
ALTER TABLE `user_caldav_sync`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `user_passkeys`
--
ALTER TABLE `user_passkeys`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT für Tabelle `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_announcements_user` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD CONSTRAINT `fk_calendar_events_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `calendar_event_invitees`
--
ALTER TABLE `calendar_event_invitees`
  ADD CONSTRAINT `fk_cal_inv_event` FOREIGN KEY (`event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cal_inv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `calendar_subscriptions`
--
ALTER TABLE `calendar_subscriptions`
  ADD CONSTRAINT `calendar_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `calls`
--
ALTER TABLE `calls`
  ADD CONSTRAINT `fk_calls_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_calls_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_calls_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_calls_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `changelog_entries`
--
ALTER TABLE `changelog_entries`
  ADD CONSTRAINT `changelog_entries_ibfk_1` FOREIGN KEY (`version_id`) REFERENCES `changelog_versions` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `changelog_files`
--
ALTER TABLE `changelog_files`
  ADD CONSTRAINT `changelog_files_ibfk_1` FOREIGN KEY (`version_id`) REFERENCES `changelog_versions` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `comment_attachments`
--
ALTER TABLE `comment_attachments`
  ADD CONSTRAINT `fk_comment_attachments_comment` FOREIGN KEY (`comment_id`) REFERENCES `ticket_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_comment_attachments_user` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `fk_companies_ansprechpartner_user` FOREIGN KEY (`ansprechpartner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_companies_zugewiesen_an` FOREIGN KEY (`zugewiesen_an`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `company_contracts`
--
ALTER TABLE `company_contracts`
  ADD CONSTRAINT `fk_company_contracts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_company_contracts_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `company_documents`
--
ALTER TABLE `company_documents`
  ADD CONSTRAINT `fk_company_documents_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_company_documents_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `company_notes`
--
ALTER TABLE `company_notes`
  ADD CONSTRAINT `fk_company_notes_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_company_notes_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `company_wartung_mahnung`
--
ALTER TABLE `company_wartung_mahnung`
  ADD CONSTRAINT `company_wartung_mahnung_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `company_wartung_zahlung_frage`
--
ALTER TABLE `company_wartung_zahlung_frage`
  ADD CONSTRAINT `company_wartung_zahlung_frage_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `consumables`
--
ALTER TABLE `consumables`
  ADD CONSTRAINT `fk_consumables_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consumables_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consumables_shelf` FOREIGN KEY (`shelf_id`) REFERENCES `shelves` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `consumable_category_link`
--
ALTER TABLE `consumable_category_link`
  ADD CONSTRAINT `fk_consumable_category_link_category` FOREIGN KEY (`category_id`) REFERENCES `consumable_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consumable_category_link_consumable` FOREIGN KEY (`consumable_id`) REFERENCES `consumables` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `consumable_company_link`
--
ALTER TABLE `consumable_company_link`
  ADD CONSTRAINT `fk_ccl_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ccl_consumable` FOREIGN KEY (`consumable_id`) REFERENCES `consumables` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `consumable_device_models`
--
ALTER TABLE `consumable_device_models`
  ADD CONSTRAINT `fk_consumable_device_models_consumable` FOREIGN KEY (`consumable_id`) REFERENCES `consumables` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_ansprechpartner_user` FOREIGN KEY (`ansprechpartner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customers_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `customer_contracts`
--
ALTER TABLE `customer_contracts`
  ADD CONSTRAINT `fk_customer_contracts_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customer_contracts_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `customer_documents`
--
ALTER TABLE `customer_documents`
  ADD CONSTRAINT `fk_customer_documents_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customer_documents_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `customer_notes`
--
ALTER TABLE `customer_notes`
  ADD CONSTRAINT `fk_customer_notes_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customer_notes_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `devices`
--
ALTER TABLE `devices`
  ADD CONSTRAINT `fk_devices_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_devices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_devices_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_devices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `device_attachments`
--
ALTER TABLE `device_attachments`
  ADD CONSTRAINT `fk_device_attachments_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_device_attachments_user` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `explosion_drawings`
--
ALTER TABLE `explosion_drawings`
  ADD CONSTRAINT `fk_explosion_drawings_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `explosion_drawing_device_models`
--
ALTER TABLE `explosion_drawing_device_models`
  ADD CONSTRAINT `fk_explosion_drawing_device_models_drawing` FOREIGN KEY (`explosion_drawing_id`) REFERENCES `explosion_drawings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `kb_attachments`
--
ALTER TABLE `kb_attachments`
  ADD CONSTRAINT `fk_kb_attachments_page` FOREIGN KEY (`page_id`) REFERENCES `kb_pages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kb_attachments_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `kb_pages`
--
ALTER TABLE `kb_pages`
  ADD CONSTRAINT `fk_kb_pages_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kb_pages_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kb_pages_parent` FOREIGN KEY (`parent_id`) REFERENCES `kb_pages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `kb_page_tags`
--
ALTER TABLE `kb_page_tags`
  ADD CONSTRAINT `fk_kb_page_tags_page` FOREIGN KEY (`page_id`) REFERENCES `kb_pages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kb_page_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `kb_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `kb_page_versions`
--
ALTER TABLE `kb_page_versions`
  ADD CONSTRAINT `fk_kb_page_versions_page` FOREIGN KEY (`page_id`) REFERENCES `kb_pages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kb_page_versions_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `kb_page_views`
--
ALTER TABLE `kb_page_views`
  ADD CONSTRAINT `fk_kb_page_views_page` FOREIGN KEY (`page_id`) REFERENCES `kb_pages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kb_page_views_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `links`
--
ALTER TABLE `links`
  ADD CONSTRAINT `fk_links_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_links_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `fk_notes_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notes_folder` FOREIGN KEY (`folder_id`) REFERENCES `note_folders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `note_folders`
--
ALTER TABLE `note_folders`
  ADD CONSTRAINT `fk_note_folders_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `note_folder_members`
--
ALTER TABLE `note_folder_members`
  ADD CONSTRAINT `fk_note_folder_members_folder` FOREIGN KEY (`folder_id`) REFERENCES `note_folders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_note_folder_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_comment` FOREIGN KEY (`comment_id`) REFERENCES `ticket_comments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `fk_order_status_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_status_history_user` FOREIGN KEY (`geaendert_von`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `fk_projects_ansprechpartner` FOREIGN KEY (`ansprechpartner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_projects_beauftragter` FOREIGN KEY (`beauftragter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_projects_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_projects_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_projects_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `project_attachments`
--
ALTER TABLE `project_attachments`
  ADD CONSTRAINT `fk_project_attachments_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_project_attachments_user` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `project_notes`
--
ALTER TABLE `project_notes`
  ADD CONSTRAINT `fk_project_notes_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_project_notes_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `project_observers`
--
ALTER TABLE `project_observers`
  ADD CONSTRAINT `fk_project_observers_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_project_observers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `project_tickets`
--
ALTER TABLE `project_tickets`
  ADD CONSTRAINT `fk_project_tickets_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_project_tickets_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `remember_me_tokens`
--
ALTER TABLE `remember_me_tokens`
  ADD CONSTRAINT `fk_remember_me_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `satisfaction_survey_responses`
--
ALTER TABLE `satisfaction_survey_responses`
  ADD CONSTRAINT `fk_survey_response_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_zugewiesen_an` FOREIGN KEY (`zugewiesen_an`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `ticket_appointments`
--
ALTER TABLE `ticket_appointments`
  ADD CONSTRAINT `fk_ticket_appointments_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_appointments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `ticket_attachments`
--
ALTER TABLE `ticket_attachments`
  ADD CONSTRAINT `fk_ticket_attachments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_attachments_user` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `ticket_comments`
--
ALTER TABLE `ticket_comments`
  ADD CONSTRAINT `fk_ticket_comments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `ticket_comment_reads`
--
ALTER TABLE `ticket_comment_reads`
  ADD CONSTRAINT `fk_comment_reads_comment` FOREIGN KEY (`comment_id`) REFERENCES `ticket_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_comment_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `ticket_observers`
--
ALTER TABLE `ticket_observers`
  ADD CONSTRAINT `fk_ticket_observers_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_observers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `time_tracking`
--
ALTER TABLE `time_tracking`
  ADD CONSTRAINT `fk_time_tracking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `time_tracking_vacation`
--
ALTER TABLE `time_tracking_vacation`
  ADD CONSTRAINT `fk_time_tracking_vacation_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `todos`
--
ALTER TABLE `todos`
  ADD CONSTRAINT `fk_todos_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todos_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todos_folder` FOREIGN KEY (`folder_id`) REFERENCES `todo_folders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todos_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todos_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todos_zugewiesen_an` FOREIGN KEY (`zugewiesen_an`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `todo_attachments`
--
ALTER TABLE `todo_attachments`
  ADD CONSTRAINT `fk_todo_attachments_todo` FOREIGN KEY (`todo_id`) REFERENCES `todos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todo_attachments_user` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `todo_folders`
--
ALTER TABLE `todo_folders`
  ADD CONSTRAINT `fk_todo_folders_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todo_folders_erstellt_von` FOREIGN KEY (`erstellt_von`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `todo_folder_members`
--
ALTER TABLE `todo_folder_members`
  ADD CONSTRAINT `fk_todo_folder_members_folder` FOREIGN KEY (`folder_id`) REFERENCES `todo_folders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todo_folder_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `todo_user_sorts`
--
ALTER TABLE `todo_user_sorts`
  ADD CONSTRAINT `fk_todo_user_sorts_folder` FOREIGN KEY (`folder_id`) REFERENCES `todo_folders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todo_user_sorts_todo` FOREIGN KEY (`todo_id`) REFERENCES `todos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todo_user_sorts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `trusted_devices`
--
ALTER TABLE `trusted_devices`
  ADD CONSTRAINT `fk_trusted_devices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_users_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `user_caldav_sync`
--
ALTER TABLE `user_caldav_sync`
  ADD CONSTRAINT `fk_user_caldav_sync_server` FOREIGN KEY (`caldav_server_id`) REFERENCES `caldav_servers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_caldav_sync_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `user_passkeys`
--
ALTER TABLE `user_passkeys`
  ADD CONSTRAINT `fk_passkeys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_user_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
