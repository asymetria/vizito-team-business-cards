<?php
/**
 * The plugin's own Polish and German translations, kept as data so the .po
 * files can be regenerated from a fresh .pot. Dev only, not shipped.
 *
 *   php tools/translations.php   Writes languages/vizito-team-business-cards-{pl_PL,de_DE}.po
 *   php tools/i18n.php mo        Then compiles them.
 *
 * Keys are msgids, prefixed with "context\x04" where the string has one.
 * A plural entry is an array of forms: three for Polish, two for German.
 *
 * @package Vizito_Team_Business_Cards
 */

require __DIR__ . '/i18n.php';

$t = array(
	'Card details'                                              => array( 'Dane wizytówki', 'Kartendaten' ),
	'Person'                                                    => array( 'Osoba', 'Person' ),
	'Contact'                                                   => array( 'Kontakt', 'Kontakt' ),
	'Address'                                                   => array( 'Adres', 'Adresse' ),
	'Website and social profiles'                               => array( 'Strona i media społecznościowe', 'Website und soziale Profile' ),
	'Extra buttons'                                             => array( 'Dodatkowe przyciski', 'Zusätzliche Schaltflächen' ),
	'Fields left empty use the company profile where a grey hint is shown.' => array( 'Puste pola z szarą podpowiedzią biorą wartość z profilu firmy.', 'Leere Felder mit grauem Hinweis übernehmen den Wert aus dem Firmenprofil.' ),
	'Role and texts, per language'                              => array( 'Stanowisko i teksty w każdym języku', 'Position und Texte je Sprache' ),
	'Import / Export'                                           => array( 'Import / Eksport', 'Import / Export' ),
	'Sorry, you are not allowed to import cards.'               => array( 'Nie masz uprawnień do importu wizytówek.', 'Sie dürfen keine Karten importieren.' ),
	'%d card imported.'                                         => array(
		array( 'Zaimportowano %d wizytówkę.', 'Zaimportowano %d wizytówki.', 'Zaimportowano %d wizytówek.' ),
		array( '%d Karte importiert.', '%d Karten importiert.' ),
	),
	'Upload a .csv or .txt file of at most 2 MB.'               => array( 'Wgraj plik .csv lub .txt o rozmiarze do 2 MB.', 'Laden Sie eine .csv- oder .txt-Datei mit höchstens 2 MB hoch.' ),
	'Paste the rows or choose a file (at most 2 MB).'           => array( 'Wklej wiersze albo wybierz plik (do 2 MB).', 'Fügen Sie Zeilen ein oder wählen Sie eine Datei (höchstens 2 MB).' ),
	'The preview has expired. Start the import again.'          => array( 'Podgląd wygasł. Zacznij import od nowa.', 'Die Vorschau ist abgelaufen. Starten Sie den Import erneut.' ),
	'Import'                                                    => array( 'Importuj', 'Importieren' ),
	'Columns are the ones in an exported file. Rows match existing cards by email, then by slug. An empty cell leaves a field as it is; a single dash clears it.' => array( 'Kolumny są takie jak w pliku eksportu. Wiersze są dopasowywane do istniejących wizytówek po e-mailu, potem po slugu. Pusta komórka zostawia pole bez zmian, a sam myślnik je czyści.', 'Die Spalten entsprechen einer exportierten Datei. Zeilen werden per E-Mail, dann per Slug bestehenden Karten zugeordnet. Eine leere Zelle lässt ein Feld unverändert; ein einzelner Bindestrich leert es.' ),
	'CSV file'                                                  => array( 'Plik CSV', 'CSV-Datei' ),
	'Or paste rows from a spreadsheet'                          => array( 'Albo wklej wiersze z arkusza', 'Oder Zeilen aus einer Tabelle einfügen' ),
	'Preview'                                                   => array( 'Podgląd', 'Vorschau' ),
	'Export'                                                    => array( 'Eksport', 'Export' ),
	'Download all cards as CSV'                                 => array( 'Pobierz wszystkie wizytówki jako CSV', 'Alle Karten als CSV herunterladen' ),
	'Create'                                                    => array( 'Utwórz', 'Anlegen' ),
	'Update'                                                    => array( 'Zaktualizuj', 'Aktualisieren' ),
	'Error'                                                     => array( 'Błąd', 'Fehler' ),
	'%1$d to create, %2$d to update, %3$d with errors. Nothing has been written yet.' => array( 'Do utworzenia: %1$d, do aktualizacji: %2$d, z błędami: %3$d. Nic jeszcze nie zapisano.', 'Anzulegen: %1$d, zu aktualisieren: %2$d, mit Fehlern: %3$d. Noch wurde nichts gespeichert.' ),
	'Rows with errors are skipped. Fix them in the file and import again, or import the rest now.' => array( 'Wiersze z błędami zostaną pominięte. Popraw je w pliku i zaimportuj ponownie albo zaimportuj teraz resztę.', 'Fehlerhafte Zeilen werden übersprungen. Korrigieren Sie sie in der Datei und importieren Sie erneut, oder importieren Sie jetzt den Rest.' ),
	'Line'                                                      => array( 'Wiersz', 'Zeile' ),
	'Action'                                                    => array( 'Działanie', 'Aktion' ),
	'Name'                                                      => array( 'Imię i nazwisko', 'Name' ),
	'Notes'                                                     => array( 'Uwagi', 'Hinweise' ),
	'Start over'                                                => array( 'Zacznij od nowa', 'Neu beginnen' ),
	'QR code'                                                   => array( 'Kod QR', 'QR-Code' ),
	'Print codes'                                               => array( 'Kody do druku', 'Codes drucken' ),
	"Publish the card to get its QR code. The code holds the card's final address." => array( 'Opublikuj wizytówkę, aby dostać jej kod QR. Kod zawiera ostateczny adres wizytówki.', 'Veröffentlichen Sie die Karte, um ihren QR-Code zu erhalten. Der Code enthält die endgültige Adresse der Karte.' ),
	'logo'                                                      => array( 'logo', 'Logo' ),
	'Give the printer the SVG. Print one proof and scan it from paper before the full run.' => array( 'Drukarni przekaż plik SVG. Przed całym nakładem wydrukuj jedną próbkę i zeskanuj ją z papieru.', 'Geben Sie der Druckerei die SVG-Datei. Drucken Sie vor der Auflage einen Probedruck und scannen Sie ihn vom Papier.' ),
	'Sorry, you are not allowed to export codes.'               => array( 'Nie masz uprawnień do eksportu kodów.', 'Sie dürfen keine Codes exportieren.' ),
	'No published cards yet.'                                   => array( 'Nie ma jeszcze opublikowanych wizytówek.', 'Noch keine veröffentlichten Karten.' ),
	'Download ZIP'                                              => array( 'Pobierz ZIP', 'ZIP herunterladen' ),
	'Open test sheet'                                           => array( 'Otwórz arkusz testowy', 'Testbogen öffnen' ),
	'This link has expired. Reload the card and try again.'     => array( 'Ten link wygasł. Odśwież wizytówkę i spróbuj ponownie.', 'Dieser Link ist abgelaufen. Laden Sie die Karte neu und versuchen Sie es erneut.' ),
	'Nothing to download.'                                      => array( 'Nie ma nic do pobrania.', 'Nichts zum Herunterladen.' ),
	'Select at least one card.'                                 => array( 'Zaznacz co najmniej jedną wizytówkę.', 'Wählen Sie mindestens eine Karte.' ),
	'This server cannot build ZIP files (the PHP zip extension is missing). Download the codes one by one from each card.' => array( 'Ten serwer nie potrafi tworzyć plików ZIP (brak rozszerzenia PHP zip). Pobierz kody pojedynczo z każdej wizytówki.', 'Dieser Server kann keine ZIP-Dateien erstellen (die PHP-Erweiterung zip fehlt). Laden Sie die Codes einzeln bei jeder Karte herunter.' ),
	'QR test sheet'                                             => array( 'Arkusz testowy kodów QR', 'QR-Testbogen' ),
	"The ZIP file could not be created in the server's temporary folder. Download the codes one by one from each card." => array( 'Nie udało się utworzyć pliku ZIP w katalogu tymczasowym serwera. Pobierz kody pojedynczo z każdej wizytówki.', 'Die ZIP-Datei konnte im temporären Ordner des Servers nicht angelegt werden. Laden Sie die Codes einzeln bei jeder Karte herunter.' ),
	'Company profile'                                           => array( 'Profil firmy', 'Firmenprofil' ),
	'Team Cards settings'                                       => array( 'Ustawienia wizytówek', 'Einstellungen für Teamkarten' ),
	'Settings'                                                  => array( 'Ustawienia', 'Einstellungen' ),
	'Cards use these values wherever their own field is left empty.' => array( 'Wizytówki używają tych wartości wszędzie tam, gdzie ich własne pole jest puste.', 'Karten verwenden diese Werte überall dort, wo ihr eigenes Feld leer ist.' ),
	'Select logo'                                               => array( 'Wybierz logo', 'Logo auswählen' ),
	'Languages and address'                                     => array( 'Języki i adres', 'Sprachen und Adresse' ),
	'Languages'                                                 => array( 'Języki', 'Sprachen' ),
	'One per line: a language code and, optionally, a WordPress locale. Example: "de de_DE". The order is the order of the language switcher.' => array( 'Jeden w wierszu: kod języka i opcjonalnie locale WordPressa, np. „de de_DE”. Kolejność jest kolejnością w przełączniku języka.', 'Einer pro Zeile: ein Sprachcode und optional eine WordPress-Locale, z. B. „de de_DE“. Die Reihenfolge ist die des Sprachumschalters.' ),
	'Default language'                                          => array( 'Język domyślny', 'Standardsprache' ),
	'Browser language'                                          => array( 'Język przeglądarki', 'Browsersprache' ),
	"Open a scanned card in the visitor's browser language when it is on the list." => array( 'Otwieraj zeskanowaną wizytówkę w języku przeglądarki odwiedzającego, jeśli jest na liście.', 'Eine gescannte Karte in der Browsersprache des Besuchers öffnen, wenn sie in der Liste steht.' ),
	'Card address'                                              => array( 'Adres wizytówki', 'Kartenadresse' ),
	'Printed QR codes contain this address. Changing it breaks every code already printed.' => array( 'Wydrukowane kody QR zawierają ten adres. Jego zmiana psuje wszystkie wydrukowane już kody.', 'Gedruckte QR-Codes enthalten diese Adresse. Eine Änderung macht alle bereits gedruckten Codes unbrauchbar.' ),
	'Appearance'                                                => array( 'Wygląd', 'Darstellung' ),
	'Accent colour'                                             => array( 'Kolor akcentu', 'Akzentfarbe' ),
	'Colour scheme'                                             => array( 'Schemat kolorów', 'Farbschema' ),
	"Follow the visitor's device"                               => array( 'Według urządzenia odwiedzającego', 'Wie das Gerät des Besuchers' ),
	'Light'                                                     => array( 'Jasny', 'Hell' ),
	'Dark'                                                      => array( 'Ciemny', 'Dunkel' ),
	'Photo shape'                                               => array( 'Kształt zdjęcia', 'Fotoform' ),
	'Round'                                                     => array( 'Okrągłe', 'Rund' ),
	'Square'                                                    => array( 'Kwadratowe', 'Quadratisch' ),
	'Photo in contact file'                                     => array( 'Zdjęcie w pliku kontaktu', 'Foto in der Kontaktdatei' ),
	"Include the photo in the downloaded contact, so it shows in the phone's address book." => array( 'Dołączaj zdjęcie do pobieranego kontaktu, żeby było widać je w książce adresowej telefonu.', 'Das Foto in den heruntergeladenen Kontakt aufnehmen, damit es im Adressbuch des Telefons erscheint.' ),
	'QR codes'                                                  => array( 'Kody QR', 'QR-Codes' ),
	'Module colour'                                             => array( 'Kolor modułów', 'Modulfarbe' ),
	'Background colour'                                         => array( 'Kolor tła', 'Hintergrundfarbe' ),
	'Keep modules dark on a light background. Pairs that would not scan reliably are refused.' => array( 'Moduły muszą być ciemne na jasnym tle. Pary kolorów, których nie da się pewnie zeskanować, są odrzucane.', 'Module dunkel auf hellem Hintergrund halten. Farbpaare, die sich nicht zuverlässig scannen lassen, werden abgelehnt.' ),
	'Logo in the code'                                          => array( 'Logo w kodzie', 'Logo im Code' ),
	'Put the company logo in the middle of printed codes. Codes then use the highest error correction; test a paper proof before printing.' => array( 'Umieść logo firmy na środku drukowanych kodów. Kody używają wtedy najwyższej korekcji błędów; przed drukiem przetestuj próbkę z papieru.', 'Das Firmenlogo in die Mitte gedruckter Codes setzen. Die Codes nutzen dann die höchste Fehlerkorrektur; testen Sie vor dem Druck einen Probedruck.' ),
	'Data'                                                      => array( 'Dane', 'Daten' ),
	'Keep statistics for'                                       => array( 'Przechowuj statystyki przez', 'Statistiken aufbewahren für' ),
	'days (0 = keep)'                                           => array( 'dni (0 = bez limitu)', 'Tage (0 = unbegrenzt)' ),
	'Uninstall'                                                 => array( 'Odinstalowanie', 'Deinstallation' ),
	'Delete all cards, settings and statistics when the plugin is deleted.' => array( 'Usuń wszystkie wizytówki, ustawienia i statystyki przy usuwaniu wtyczki.', 'Beim Löschen des Plugins alle Karten, Einstellungen und Statistiken löschen.' ),
	'The card address changed. QR codes already printed still point to %s and will stop working.' => array( 'Adres wizytówek się zmienił. Wydrukowane już kody QR nadal prowadzą do %s i przestaną działać.', 'Die Kartenadresse wurde geändert. Bereits gedruckte QR-Codes zeigen weiterhin auf %s und funktionieren nicht mehr.' ),
	'Sorry, you are not allowed to manage these settings.'      => array( 'Nie masz uprawnień do zmiany tych ustawień.', 'Sie dürfen diese Einstellungen nicht ändern.' ),
	'Business cards'                                            => array( 'Wizytówki', 'Visitenkarten' ),
	'Business card'                                             => array( 'Wizytówka', 'Visitenkarte' ),
	'Add business card'                                         => array( 'Dodaj wizytówkę', 'Visitenkarte hinzufügen' ),
	'Edit business card'                                        => array( 'Edytuj wizytówkę', 'Visitenkarte bearbeiten' ),
	'All cards'                                                 => array( 'Wszystkie wizytówki', 'Alle Karten' ),
	'Team Cards'                                                => array( 'Wizytówki zespołu', 'Teamkarten' ),
	'First name'                                                => array( 'Imię', 'Vorname' ),
	'Last name'                                                 => array( 'Nazwisko', 'Nachname' ),
	'Job title'                                                 => array( 'Stanowisko', 'Position' ),
	'Intro'                                                     => array( 'Tekst powitalny', 'Einleitung' ),
	'Mobile phone'                                              => array( 'Telefon komórkowy', 'Mobiltelefon' ),
	'Landline'                                                  => array( 'Telefon stacjonarny', 'Festnetz' ),
	'Email'                                                     => array( 'E-mail', 'E-Mail' ),
	'WhatsApp number'                                           => array( 'Numer WhatsApp', 'WhatsApp-Nummer' ),
	'Street and number'                                         => array( 'Ulica i numer', 'Straße und Hausnummer' ),
	'Postcode'                                                  => array( 'Kod pocztowy', 'Postleitzahl' ),
	'City'                                                      => array( 'Miasto', 'Ort' ),
	'Country'                                                   => array( 'Kraj', 'Land' ),
	'Map link'                                                  => array( 'Link do mapy', 'Kartenlink' ),
	'Website'                                                   => array( 'Strona WWW', 'Website' ),
	'Button %d label'                                           => array( 'Przycisk %d – etykieta', 'Schaltfläche %d – Beschriftung' ),
	'Button %d link'                                            => array( 'Przycisk %d – link', 'Schaltfläche %d – Link' ),
	'Company name'                                              => array( 'Nazwa firmy', 'Firmenname' ),
	'Logo'                                                      => array( 'Logo', 'Logo' ),
	'Column "%s" is not known and was ignored.'                 => array( 'Kolumna „%s” jest nieznana i została pominięta.', 'Die Spalte „%s“ ist unbekannt und wurde ignoriert.' ),
	'Only the first %d rows are imported at once.'              => array( 'Jednorazowo importowanych jest tylko pierwszych %d wierszy.', 'Es werden jeweils nur die ersten %d Zeilen importiert.' ),
	'The row has %1$d cells but the header has %2$d.'           => array( 'Wiersz ma %1$d komórek, a nagłówek %2$d.', 'Die Zeile hat %1$d Zellen, die Kopfzeile aber %2$d.' ),
	'%1$s "%2$s" is not valid.'                                 => array( '%1$s „%2$s” jest nieprawidłowy.', '%1$s „%2$s“ ist ungültig.' ),
	'Photo "%s" is not in the Media Library.'                   => array( 'Zdjęcia „%s” nie ma w bibliotece mediów.', 'Das Foto „%s“ ist nicht in der Mediathek.' ),
	'Status "%s" must be "active" or "inactive".'               => array( 'Status „%s” musi mieć wartość „active” albo „inactive”.', 'Der Status „%s“ muss „active“ oder „inactive“ sein.' ),
	'A new card needs a first or last name.'                    => array( 'Nowa wizytówka wymaga imienia lub nazwiska.', 'Eine neue Karte braucht einen Vor- oder Nachnamen.' ),
	'Team Card'                                                 => array( 'Wizytówka', 'Teamkarte' ),
	'Card'                                                      => array( 'Wizytówka', 'Karte' ),
	'Choose a card'                                             => array( 'Wybierz wizytówkę', 'Karte wählen' ),
	'— Choose a card —'                                         => array( '— Wybierz wizytówkę —', '— Karte wählen —' ),
	'Language'                                                  => array( 'Język', 'Sprache' ),
	'Page language'                                             => array( 'Język strony', 'Sprache der Seite' ),
	'If the person has left'                                    => array( 'Gdy osoba już nie pracuje', 'Wenn die Person ausgeschieden ist' ),
	'Show nothing'                                              => array( 'Nie pokazuj nic', 'Nichts anzeigen' ),
	'Show company details'                                      => array( 'Pokaż dane firmy', 'Firmendaten anzeigen' ),
	'Choose a card in the element settings.'                    => array( 'Wybierz wizytówkę w ustawieniach elementu.', 'Wählen Sie in den Elementeinstellungen eine Karte.' ),
	'Vizito Team Business Cards: card not found.'                      => array( 'Vizito Team Business Cards: nie znaleziono wizytówki.', 'Vizito Team Business Cards: Karte nicht gefunden.' ),
	'Link copied'                                               => array( 'Skopiowano link', 'Link kopiert' ),
	'(opens in a new tab)'                                      => array( '(otwiera się w nowej karcie)', '(öffnet in einem neuen Tab)' ),
	'The accent colour is light: white text on it is hard to read. A darker shade works better for people with low vision.' => array( 'Kolor akcentu jest jasny: biały tekst na nim jest słabo czytelny. Ciemniejszy odcień będzie lepszy dla osób słabowidzących.', 'Die Akzentfarbe ist hell: weißer Text darauf ist schwer lesbar. Ein dunklerer Ton ist für Menschen mit Sehschwäche besser.' ),
	'This business card is no longer active. You can still reach the company:' => array( 'Ta wizytówka nie jest już aktywna. Z firmą nadal możesz się skontaktować:', 'Diese Visitenkarte ist nicht mehr aktiv. Das Unternehmen erreichen Sie weiterhin:' ),
	'Save contact'                                              => array( 'Zapisz kontakt', 'Kontakt speichern' ),
	'Call'                                                      => array( 'Zadzwoń', 'Anrufen' ),
	'Directions'                                                => array( 'Dojazd', 'Route' ),
	'Share'                                                     => array( 'Udostępnij', 'Teilen' ),
	'Status'                                                    => array( 'Status', 'Status' ),
	'This person no longer works here'                          => array( 'Ta osoba już tu nie pracuje', 'Diese Person arbeitet nicht mehr hier' ),
	'Then the card address'                                     => array( 'Wtedy adres wizytówki', 'Dann soll die Kartenadresse' ),
	'shows a notice with company details'                       => array( 'pokazuje komunikat z danymi firmy', 'einen Hinweis mit Firmendaten zeigen' ),
	'redirects to another card'                                 => array( 'przekierowuje na inną wizytówkę', 'auf eine andere Karte weiterleiten' ),
	'redirects to an address'                                   => array( 'przekierowuje na adres', 'auf eine Adresse weiterleiten' ),
	'Successor'                                                 => array( 'Następca', 'Nachfolge' ),
	'Only this site or the company website. Printed codes keep working either way.' => array( 'Tylko ta witryna albo strona firmy. Wydrukowane kody działają w każdym przypadku.', 'Nur diese Website oder die Firmenwebsite. Gedruckte Codes funktionieren in jedem Fall weiter.' ),
	"This card's address changed. Printed QR codes still carry the old address (%s); WordPress redirects it for now, but it breaks if another card takes that address. Reprint the codes when you can." => array( 'Adres tej wizytówki się zmienił. Wydrukowane kody QR nadal zawierają stary adres (%s). WordPress na razie go przekierowuje, ale przestanie, gdy inna wizytówka zajmie ten adres. Gdy możesz, wydrukuj kody ponownie.', 'Die Adresse dieser Karte wurde geändert. Gedruckte QR-Codes enthalten weiterhin die alte Adresse (%s); WordPress leitet sie vorerst weiter, das endet aber, sobald eine andere Karte diese Adresse erhält. Drucken Sie die Codes neu, sobald es geht.' ),
	'QR colours must be given as #rrggbb.'                      => array( 'Kolory QR podaj w formacie #rrggbb.', 'QR-Farben müssen als #rrggbb angegeben werden.' ),
	'QR modules must be darker than the background; inverted codes fail on many phones.' => array( 'Moduły QR muszą być ciemniejsze od tła; odwrócone kody nie działają na wielu telefonach.', 'QR-Module müssen dunkler als der Hintergrund sein; invertierte Codes scheitern auf vielen Telefonen.' ),
	'QR colours are too close together to scan reliably. Choose a darker module colour or a lighter background.' => array( 'Kolory QR są zbyt do siebie zbliżone, żeby kod dał się pewnie zeskanować. Wybierz ciemniejszy kolor modułów albo jaśniejsze tło.', 'Die QR-Farben liegen zu nah beieinander, um zuverlässig gescannt zu werden. Wählen Sie eine dunklere Modulfarbe oder einen helleren Hintergrund.' ),
	'Views (30 days)'                                           => array( 'Wyświetlenia (30 dni)', 'Aufrufe (30 Tage)' ),
	'Contacts saved (30 days)'                                  => array( 'Zapisane kontakty (30 dni)', 'Gespeicherte Kontakte (30 Tage)' ),
	'Last 30 days'                                              => array( 'Ostatnie 30 dni', 'Letzte 30 Tage' ),
	'%1$s views (%2$s from printed codes), %3$s contacts saved.' => array( 'Wyświetlenia: %1$s (z kodów QR: %2$s), zapisane kontakty: %3$s.', '%1$s Aufrufe (%2$s über gedruckte Codes), %3$s gespeicherte Kontakte.' ),
	'Daily views: %1$s from printed codes, %2$s from links.'    => array( 'Wyświetlenia dziennie: %1$s z kodów QR, %2$s z linków.', 'Tägliche Aufrufe: %1$s über gedruckte Codes, %2$s über Links.' ),
	'Numbers'                                                   => array( 'Liczby', 'Zahlen' ),
	'Day'                                                       => array( 'Dzień', 'Tag' ),
	'Code'                                                      => array( 'Kod', 'Code' ),
	'Link'                                                      => array( 'Link', 'Link' ),
	'Statistics'                                                => array( 'Statystyki', 'Statistiken' ),
	'Sorry, you are not allowed to view statistics.'            => array( 'Nie masz uprawnień do przeglądania statystyk.', 'Sie dürfen keine Statistiken ansehen.' ),
	'From'                                                      => array( 'Od', 'Von' ),
	'To'                                                        => array( 'Do', 'Bis' ),
	'Show'                                                      => array( 'Pokaż', 'Anzeigen' ),
	'Download CSV'                                              => array( 'Pobierz CSV', 'CSV herunterladen' ),
	'Counts are indicative: no cookies and no personal data are used, bots are filtered out, and repeated hits from one address are capped.' => array( 'Liczby są orientacyjne: bez ciasteczek i bez danych osobowych, boty są odfiltrowane, a powtarzane wejścia z jednego adresu mają limit.', 'Die Zahlen sind Richtwerte: keine Cookies und keine personenbezogenen Daten, Bots werden herausgefiltert, wiederholte Aufrufe von einer Adresse sind begrenzt.' ),
	'Views'                                                     => array( 'Wyświetlenia', 'Aufrufe' ),
	'Contacts saved'                                            => array( 'Zapisane kontakty', 'Gespeicherte Kontakte' ),
	'Calls'                                                     => array( 'Połączenia', 'Anrufe' ),
	'Emails'                                                    => array( 'E-maile', 'E-Mails' ),
	'Shares'                                                    => array( 'Udostępnienia', 'Geteilt' ),
	'Links'                                                     => array( 'Linki', 'Links' ),
	'This contact is no longer available.'                      => array( 'Ten kontakt nie jest już dostępny.', 'Dieser Kontakt ist nicht mehr verfügbar.' ),
	"block title\x04Team Card"                                  => array( 'Wizytówka', 'Teamkarte' ),
	"block description\x04One person's business card from Vizito Team Business Cards." => array( 'Wizytówka jednej osoby z Vizito Team Business Cards.', 'Die Visitenkarte einer Person aus Vizito Team Business Cards.' ),
	"block keyword\x04business card"                            => array( 'wizytówka', 'Visitenkarte' ),
	"block keyword\x04vcard"                                    => array( 'vcard', 'vcard' ),
	"block keyword\x04team"                                     => array( 'zespół', 'Team' ),
	"block keyword\x04contact"                                  => array( 'kontakt', 'Kontakt' ),
);

$locales = array(
	'pl_PL' => array( 0, 'Polish', 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2);' ),
	'de_DE' => array( 1, 'German', 'nplurals=2; plural=(n != 1);' ),
);

$pot     = tbcards_po_parse( dirname( __DIR__ ) . '/languages/vizito-team-business-cards.pot' );
$missing = array();

foreach ( $locales as $locale => $meta ) {
	list( $index, $name, $plurals ) = $meta;

	$out  = "# $name translation of Vizito Team Business Cards.\n# This file is distributed under the GPL-2.0-or-later.\n";
	$out .= "msgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: Vizito Team Business Cards\\n\"\n\"Language: $locale\\n\"\n\"MIME-Version: 1.0\\n\"\n";
	$out .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Content-Transfer-Encoding: 8bit\\n\"\n\"Plural-Forms: $plurals\\n\"\n\"X-Domain: vizito-team-business-cards\\n\"\n\n";

	foreach ( $pot as $entry ) {
		if ( '' === $entry['msgid'] ) {
			continue;
		}

		$key = ( null === $entry['context'] ? '' : $entry['context'] . "\x04" ) . $entry['msgid'];

		if ( ! isset( $t[ $key ] ) ) {
			$missing[ $key ] = true;
			continue;
		}

		$translation = $t[ $key ][ $index ];

		if ( null !== $entry['context'] ) {
			$out .= 'msgctxt ' . tbcards_po_quote( $entry['context'] ) . "\n";
		}

		$out .= 'msgid ' . tbcards_po_quote( $entry['msgid'] ) . "\n";

		if ( null !== $entry['plural'] ) {
			$out .= 'msgid_plural ' . tbcards_po_quote( $entry['plural'] ) . "\n";

			foreach ( (array) $translation as $n => $form ) {
				$out .= "msgstr[$n] " . tbcards_po_quote( $form ) . "\n";
			}
		} else {
			$out .= 'msgstr ' . tbcards_po_quote( (string) $translation ) . "\n";
		}

		$out .= "\n";
	}

	file_put_contents( dirname( __DIR__ ) . "/languages/vizito-team-business-cards-$locale.po", $out );
}

foreach ( array_keys( $missing ) as $key ) {
	fwrite( STDERR, 'No translation for: ' . str_replace( "\x04", ' | ', $key ) . "\n" );
}

echo count( $pot ) - 1 - count( $missing ) . ' of ' . ( count( $pot ) - 1 ) . " strings translated\n";
exit( array() === $missing ? 0 : 1 );
