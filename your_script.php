<?php
/**
 * your_script.php
 *
 * 
 * Usage:
 *   php your_script.php > trips.geojson 
 */

 const FILE_IN      = 'points.csv';
 const FILE_REJECTS = 'rejects.log';
 const GAP_SECONDS  = 25 * 60; // 25 minutes
 const JUMP_KM      = 2.0;     // 2 km
 
 if (!file_exists(FILE_IN)) {
     fwrite(STDERR, "Missing " . FILE_IN . "\n");
     exit(1);
 }
 
 // --- Open files ---
 $csv = fopen(FILE_IN, 'r');
 $log = fopen(FILE_REJECTS, 'w');
 
 // --- Helper functions ---
 function validCoord($lat, $lon): bool {
     return is_numeric($lat) && is_numeric($lon) &&
            $lat >= -90 && $lat <= 90 &&
            $lon >= -180 && $lon <= 180;
 }
 function parseTime($ts) {
     $ts = trim($ts);
     if ($ts === '') return null;
     if (ctype_digit($ts)) {
         if (strlen($ts) > 10) return intval($ts / 1000); // ms
         return intval($ts);
     }
     $t = strtotime($ts);
     return $t === false ? null : $t;
 }
 function haversine($lat1, $lon1, $lat2, $lon2): float {
     $R = 6371.0088; // km
     $dLat = deg2rad($lat2 - $lat1);
     $dLon = deg2rad($lon2 - $lon1);
     $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
     return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
 }
 function tripStats($points): array {
     $total = 0.0; $maxSpd = 0.0;
     for ($i=1; $i<count($points); $i++) {
         $d = haversine($points[$i-1]['lat'], $points[$i-1]['lon'], $points[$i]['lat'], $points[$i]['lon']);
         $dt = $points[$i]['t'] - $points[$i-1]['t'];
         $total += $d;
         if ($dt > 0) $maxSpd = max($maxSpd, $d / ($dt/3600));
     }
     $durSec = $points[count($points)-1]['t'] - $points[0]['t'];
     $avgSpd = $durSec > 0 ? $total / ($durSec/3600) : 0;
     return [
         'total_distance_km' => round($total, 3),
         'duration_min'      => round($durSec/60, 1),
         'avg_speed_kmh'     => round($avgSpd, 2),
         'max_speed_kmh'     => round($maxSpd, 2)
     ];
 }
 function colorList(): array {
     return [
         "#1f77b4","#ff7f0e","#2ca02c","#d62728","#9467bd",
         "#8c564b","#e377c2","#7f7f7f","#bcbd22","#17becf"
     ];
 }
 
 // --- Read CSV ---
 $header = fgetcsv($csv);
 $map = ['lat'=>-1, 'lon'=>-1, 'ts'=>-1];
 foreach ($header as $i=>$h) {
     $h = strtolower(trim($h));
     if (in_array($h, ['lat','latitude'])) $map['lat']=$i;
     if (in_array($h, ['lon','lng','longitude'])) $map['lon']=$i;
     if (in_array($h, ['timestamp','time','datetime','date'])) $map['ts']=$i;
 }
 if (in_array(-1, $map)) {
     fwrite(STDERR, "Missing required headers (lat, lon, timestamp)\n");
     exit(1);
 }
 
 // --- Clean data ---
 $points = [];
 $lineNo = 1;
 while (($row = fgetcsv($csv)) !== false) {
     $lineNo++;
     $lat = $row[$map['lat']] ?? null;
     $lon = $row[$map['lon']] ?? null;
     $t   = parseTime($row[$map['ts']] ?? '');
     if (!validCoord($lat, $lon) || $t === null) {
         fwrite($log, "Line $lineNo rejected: " . implode(',', $row) . "\n");
         continue;
     }
     $points[] = ['lat'=>(float)$lat, 'lon'=>(float)$lon, 't'=>$t];
 }
 fclose($csv);
 fclose($log);
 
 // --- Sort by timestamp ---
 usort($points, fn($a,$b) => $a['t'] <=> $b['t']);
 
 // --- Split into trips ---
 $trips = [];
 $cur = [];
 $prev = null;
 foreach ($points as $p) {
     if (!$prev) {
         $cur[] = $p; $prev = $p; continue;
     }
     $gap  = $p['t'] - $prev['t'];
     $jump = haversine($prev['lat'], $prev['lon'], $p['lat'], $p['lon']);
     if ($gap > GAP_SECONDS || $jump > JUMP_KM) {
         if (count($cur) > 1) $trips[] = $cur;
         $cur = [$p];
     } else {
         $cur[] = $p;
     }
     $prev = $p;
 }
 if (count($cur) > 1) $trips[] = $cur;
 
 // --- Build GeoJSON ---
 $features = [];
 $colors = colorList();
 foreach ($trips as $i=>$trip) {
     $coords = array_map(fn($p) => [$p['lon'],$p['lat']], $trip);
     $stats  = tripStats($trip);
     $features[] = [
         'type' => 'Feature',
         'geometry' => ['type'=>'LineString', 'coordinates'=>$coords],
         'properties' => array_merge($stats, [
             'trip_id' => 'trip_' . ($i+1),
             'color'   => $colors[$i % count($colors)],
             'points'  => count($trip),
             'start_ts'=> $trip[0]['t'],
             'end_ts'  => end($trip)['t']
         ])
     ];
 }
 $geojson = ['type'=>'FeatureCollection', 'features'=>$features];
 echo json_encode($geojson, JSON_PRETTY_PRINT) . "\n";