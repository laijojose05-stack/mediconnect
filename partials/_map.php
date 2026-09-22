<?php
/* ============================================================
   partials/_map.php — location action buttons.

   Renders a compact "location" row with two new-tab links:
     • Get Directions      → Google Maps turn-by-turn navigation
     • Open in Google Maps → full map of the location

   No API key is required. The query is built from the facility
   name + address + city (or from explicit lat/lng coordinates).

   Usage (inside any page that loads partials/_theme.php):
       <?php require_once __DIR__ . '/../partials/_map.php'; ?>
       <?= mc_map_block([
           'name'    => $h['hospital_name'],
           'address' => $h['address'] ?? '',
           'city'    => $h['city'] ?? '',
       ]) ?>
============================================================ */

if (!function_exists('mc_map_block')) {
    function mc_map_block(array $opts = []): string
    {
        $lat = trim((string)($opts['lat'] ?? ''));
        $lng = trim((string)($opts['lng'] ?? ''));

        if ($lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng)) {
            $query = $lat . ',' . $lng;
        } else {
            $parts = [];
            foreach (['name', 'address', 'city'] as $k) {
                if (!empty($opts[$k])) {
                    $parts[] = trim((string)$opts[$k]);
                }
            }
            $query = implode(', ', $parts);
        }

        if ($query === '') {
            return '';
        }

        $qEnc  = rawurlencode($query);
        $qHtml = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');

        $dir  = "https://www.google.com/maps/dir/?api=1&destination={$qEnc}";
        $view = "https://www.google.com/maps?q={$qEnc}";

        return '<div class="map-actions" role="group" aria-label="Location actions for ' . $qHtml . '">'
             . '<a class="btn primary" target="_blank" rel="noopener noreferrer" href="' . htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') . '">🧭 Get Directions</a>'
             . '<a class="btn" target="_blank" rel="noopener noreferrer" href="' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '">🗺 Open in Google Maps</a>'
             . '</div>';
    }
}

/* Once-per-page styling for the action row. */
if (empty($GLOBALS['_mc_map_css'])) {
    $GLOBALS['_mc_map_css'] = 1;
    echo '<style>' . "\n"
       . '.map-actions{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0 4px;}' . "\n"
       . '</style>' . "\n";
}