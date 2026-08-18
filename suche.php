<?php
// Konfiguration: Suchbegriff hier definieren
$suchbegriff = '$Subdomain';

// Startverzeichnis: aktuelles Verzeichnis
$startVerzeichnis = __DIR__;

// Dateiendungen, die durchsucht werden sollen
$erlaubteEndungen = ['php', 'js', 'css', 'md', 'ps1'];

function dateienDurchsuchen($verzeichnis, $suchbegriff, $erlaubteEndungen) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($verzeichnis)
    );

    foreach ($iterator as $datei) {
        if ($datei->isFile()) {
            $endung = pathinfo($datei, PATHINFO_EXTENSION);

            if (in_array($endung, $erlaubteEndungen)) {
                $zeilen = file($datei->getPathname());
                foreach ($zeilen as $nummer => $zeile) {
                    if (stripos($zeile, $suchbegriff) !== false) {
                        echo "Gefunden in: " . $datei->getPathname() . " (Zeile " . ($nummer + 1) . ")\n";
                        echo ">> " . trim($zeile) . "\n\n";
                    }
                }
            }
        }
    }
}

dateienDurchsuchen($startVerzeichnis, $suchbegriff, $erlaubteEndungen);
