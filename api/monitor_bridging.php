<?php
/**
 * SIMKlinik — API Realtime PCare BPJS Latency & Connection Monitor
 * Berdasarkan Spesifikasi TrustMark BPJS Kesehatan (Master Data & Services)
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/pcare_service.php';

$endpoint_key = sanitize($_GET['channel'] ?? ($_GET['endpoint'] ?? 'all'));
$results = [];

$cfg = PCareService::getConfig();

if (!$cfg['is_valid']) {
    echo json_encode([
        'success'   => false,
        'message'   => 'Kredensial PCare belum lengkap (ConsID, SecretKey, UserKey).',
        'timestamp' => date('d-m-Y H:i:s'),
        'summary'   => [
            'total'          => 6,
            'online'         => 0,
            'offline'        => 6,
            'avg_latency_ms' => 0,
            'health_score'   => 0,
            'status_text'    => 'Kredensial Belum Lengkap'
        ],
        'channels'  => []
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Daftar Endpoint Master Data TrustMark BPJS PCare
$endpoints = [
    'diagnosa' => [
        'name'        => 'Referensi Kamus ICD-10',
        'sub'         => 'GET /diagnosa/{keyword}/{start}/{limit}',
        'endpoint'    => '/diagnosa/A00/0/1',
        'icon'        => 'fa-book-medical',
        'color'       => '#0284c7'
    ],
    'dokter' => [
        'name'        => 'Referensi Dokter Faskes',
        'sub'         => 'GET /dokter/{start}/{limit}',
        'endpoint'    => '/dokter/0/1',
        'icon'        => 'fa-user-doctor',
        'color'       => '#7c3aed'
    ],
    'poli' => [
        'name'        => 'Referensi Poli FKTP',
        'sub'         => 'GET /poli/fktp/{start}/{limit}',
        'endpoint'    => '/poli/fktp/0/1',
        'icon'        => 'fa-hospital',
        'color'       => '#059669'
    ],
    'kesadaran' => [
        'name'        => 'Status Kesadaran',
        'sub'         => 'GET /kesadaran',
        'endpoint'    => '/kesadaran',
        'icon'        => 'fa-brain',
        'color'       => '#ea580c'
    ],
    'statuspulang' => [
        'name'        => 'Status Pulang Pasien',
        'sub'         => 'GET /statuspulang/rawatInap/{0|1}',
        'endpoint'    => '/statuspulang/rawatInap/false',
        'icon'        => 'fa-person-walking-arrow-right',
        'color'       => '#0d9488'
    ],
    'spesialis' => [
        'name'        => 'Spesialis Rujukan',
        'sub'         => 'GET /spesialis',
        'endpoint'    => '/spesialis',
        'icon'        => 'fa-stethoscope',
        'color'       => '#e11d48'
    ]
];

// Eksekusi Pengujian Endpoint
foreach ($endpoints as $key => $ep) {
    if ($endpoint_key !== 'all' && $endpoint_key !== $key) {
        continue;
    }

    $res  = PCareService::request($ep['endpoint'], 'GET');
    $code = $res['metadata']['code'] ?? ($res['metaData']['code'] ?? 500);
    $msg  = $res['metadata']['message'] ?? ($res['metaData']['message'] ?? '');
    $dur  = $res['debug']['duration_ms'] ?? 0;
    $is_online = ($code == 200 || $code == 208 || $code == 201);

    $results[$key] = [
        'name'        => $ep['name'],
        'key'         => $key,
        'sub'         => $ep['sub'],
        'icon'        => $ep['icon'],
        'color'       => $ep['color'],
        'url'         => $cfg['base_url'],
        'endpoint'    => $ep['endpoint'],
        'online'      => $is_online,
        'http_code'   => (int)$code,
        'duration_ms' => (int)$dur,
        'message'     => $is_online ? 'Terkoneksi & Terenkripsi Normal' : ($msg ?: "HTTP {$code}"),
        'detail'      => $res['response'] ?? null,
        'timestamp'   => date('H:i:s')
    ];
}

// Hitung Statistik Kesehatan & Rata-rata Latensi
$total_count = count($results);
$online_count = 0;
$total_lat = 0;
$lat_count = 0;

foreach ($results as $item) {
    if (!empty($item['online'])) {
        $online_count++;
    }
    if (!empty($item['duration_ms']) && $item['duration_ms'] > 0) {
        $total_lat += (int)$item['duration_ms'];
        $lat_count++;
    }
}

$avg_latency  = $lat_count > 0 ? round($total_lat / $lat_count) : 0;
$health_score = $total_count > 0 ? round(($online_count / $total_count) * 100) : 0;

echo json_encode([
    'success'   => true,
    'provider'  => 'PCare BPJS Kesehatan (FKTP v4.0)',
    'base_url'  => $cfg['base_url'],
    'cons_id'   => $cfg['cons_id'],
    'timestamp' => date('d-m-Y H:i:s'),
    'summary'   => [
        'total'          => $total_count,
        'online'         => $online_count,
        'offline'        => $total_count - $online_count,
        'avg_latency_ms' => $avg_latency,
        'health_score'   => $health_score,
        'status_text'    => ($online_count === $total_count) ? 'PCare Normal & Responsif' : "{$online_count}/{$total_count} Endpoint Aktif"
    ],
    'channels'  => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);


