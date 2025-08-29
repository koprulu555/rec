<?php
// Varsayılan değerler (ikinci ihtimal)
$defaultBaseUrl = 'https://m.prectv49.sbs';
$defaultSuffix = '4F5A9C3D9A86FA54EACEDDD635185/c3c5bd17-e37b-4b94-a944-8a3688a30452/';
$defaultUserAgent = 'Dart/3.7 (dart:io)';
$defaultReferer = 'https://twitter.com/';
$pageCount = 4;

// Github kaynak dosyası (ilk ihtimal)
$sourceUrl = 'https://raw.githubusercontent.com/kerimmkirac/cs-kerim2/main/RecTV/src/main/kotlin/com/kerimmkirac/RecTV.kt';

// Değerlerin son hali burada tutulacak
$baseUrl    = $defaultBaseUrl;
$suffix     = $defaultSuffix;
$userAgent  = $defaultUserAgent;
$referer    = $defaultReferer;

// Github’dan dosya çekmeye çalış
$githubContent = @file_get_contents($sourceUrl);

if ($githubContent !== FALSE) {
    // Base URL (mainUrl) → örnek: override var mainUrl              = "https://m.prectv55.lol"
    if (preg_match('/override\s+var\s+mainUrl\s*=\s*"([^"]+)"/', $githubContent, $baseUrlMatch)) {
        $baseUrl = $baseUrlMatch[1];
    }

    // Suffix Key (swKey) → örnek: private val swKey = "4F5A9C3D9A86FA54EACEDDD635185/64f9535b-bd2e-4483-b234-89060b1e631c"
    if (preg_match('/private\s+val\s+swKey\s*=\s*"([^"]+)"/', $githubContent, $suffixMatch)) {
        $suffix = $suffixMatch[1];
    }

    // User-Agent → örnek: user-agent" to "Dart/3.7 (dart:io)
    if (preg_match('/user-agent"\s*to\s*"([^"]+)"/', $githubContent, $uaMatch)) {
        $userAgent = $uaMatch[1];
    }

    // Referer → örnek: Referer" to "https://twitter.com/"
    if (preg_match('/Referer"\s*to\s*"([^"]+)"/', $githubContent, $refMatch)) {
        $referer = $refMatch[1];
    }
}

// Base URL’nin çalışıp çalışmadığını test et (başarısızsa default’a döner)
function isBaseUrlWorking($baseUrl, $suffix, $userAgent) {
    $testUrl = $baseUrl . '/api/channel/by/filtres/0/0/0/' . $suffix;
    $opts = [
        'http' => [
            'header' => "User-Agent: $userAgent\r\n"
        ]
    ];
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($testUrl, false, $ctx);
    return $response !== FALSE;
}
if (!isBaseUrlWorking($baseUrl, $suffix, $userAgent)) {
    // Sadece baseUrl ve suffix default’a döner, userAgent ve referer github’dan alınmaya devam eder
    $baseUrl = $defaultBaseUrl;
    $suffix = $defaultSuffix;
}

// M3U oluştur
$m3uContent = "#EXTM3U\n";

// API isteklerinde kullanılacak header’lar (user-agent ve referer github’dan/dosyadan alınır)
$options = [
    'http' => [
        'header' => "User-Agent: $userAgent\r\nReferer: $referer\r\n"
    ]
];
$context = stream_context_create($options);

// CANLI YAYINLAR
for ($page = 0; $page < $pageCount; $page++) {
    $apiUrl = $baseUrl . "/api/channel/by/filtres/0/0/$page/" . $suffix;
    $response = @file_get_contents($apiUrl, false, $context);
    if ($response === FALSE) continue;
    $data = json_decode($response, true);
    if ($data === null) continue;

    foreach ($data as $content) {
        if (isset($content['sources']) && is_array($content['sources'])) {
            foreach ($content['sources'] as $source) {
                if (($source['type'] ?? '') === 'm3u8' && isset($source['url'])) {
                    $title = $content['title'] ?? '';
                    $image = $content['image'] ?? '';
                    $categories = isset($content['categories']) && is_array($content['categories'])
                        ? implode(", ", array_column($content['categories'], 'title'))
                        : '';
                    $m3uContent .= "#EXTINF:-1 tvg-id=\"{$content['id']}\" tvg-name=\"$title\" tvg-logo=\"$image\" group-title=\"$categories\", $title\n";
                    $m3uContent .= "#EXTVLCOPT:http-user-agent=googleusercontent\n";
                    $m3uContent .= "#EXTVLCOPT:http-referrer=$referer\n";
                    $m3uContent .= "{$source['url']}\n";
                }
            }
        }
    }
}

// FİLMLER
$movieApis = [
    "api/movie/by/filtres/0/created/SAYFA/$suffix"   => "Son Filmler",
    "api/movie/by/filtres/14/created/SAYFA/$suffix"  => "Aile",
    "api/movie/by/filtres/1/created/SAYFA/$suffix"   => "Aksiyon",
    "api/movie/by/filtres/13/created/SAYFA/$suffix"  => "Animasyon",
    "api/movie/by/filtres/19/created/SAYFA/$suffix"  => "Belgesel Filmleri",
    "api/movie/by/filtres/4/created/SAYFA/$suffix"   => "Bilim Kurgu",
    "api/movie/by/filtres/2/created/SAYFA/$suffix"   => "Dram",
    "api/movie/by/filtres/10/created/SAYFA/$suffix"  => "Fantastik",
    "api/movie/by/filtres/3/created/SAYFA/$suffix"   => "Komedi",
    "api/movie/by/filtres/8/created/SAYFA/$suffix"   => "Korku",
    "api/movie/by/filtres/17/created/SAYFA/$suffix"  => "Macera",
    "api/movie/by/filtres/5/created/SAYFA/$suffix"   => "Romantik",
];
foreach ($movieApis as $movieApi => $categoryName) {
    for ($page = 0; $page <= 25; $page++) {
        $apiUrl = $baseUrl . '/' . str_replace('SAYFA', $page, $movieApi);
        $response = @file_get_contents($apiUrl, false, $context);
        if ($response === FALSE) continue;
        $data = json_decode($response, true);
        if ($data === null) continue;

        foreach ($data as $content) {
            if (isset($content['sources']) && is_array($content['sources'])) {
                foreach ($content['sources'] as $source) {
                    if (($source['type'] ?? '') === 'm3u8' && isset($source['url'])) {
                        $title = $content['title'] ?? '';
                        $image = $content['image'] ?? '';
                        $m3uContent .= "#EXTINF:-1 tvg-id=\"{$content['id']}\" tvg-name=\"$title\" tvg-logo=\"$image\" group-title=\"$categoryName\", $title\n";
                        $m3uContent .= "#EXTVLCOPT:http-user-agent=googleusercontent\n";
                        $m3uContent .= "#EXTVLCOPT:http-referrer=$referer\n";
                        $m3uContent .= "{$source['url']}\n";
                    }
                }
            }
        }
    }
}

// DİZİLER
$seriesApis = [
    "api/serie/by/filtres/0/created/SAYFA/$suffix" => "Son Diziler"
];
foreach ($seriesApis as $seriesApi => $categoryName) {
    for ($page = 0; $page <= 25; $page++) {
        $apiUrl = $baseUrl . '/' . str_replace('SAYFA', $page, $seriesApi);
        $response = @file_get_contents($apiUrl, false, $context);
        if ($response === FALSE) continue;
        $data = json_decode($response, true);
        if ($data === null) continue;

        foreach ($data as $content) {
            if (isset($content['sources']) && is_array($content['sources'])) {
                foreach ($content['sources'] as $source) {
                    if (($source['type'] ?? '') === 'm3u8' && isset($source['url'])) {
                        $title = $content['title'] ?? '';
                        $image = $content['image'] ?? '';
                        $m3uContent .= "#EXTINF:-1 tvg-id=\"{$content['id']}\" tvg-name=\"$title\" tvg-logo=\"$image\" group-title=\"$categoryName\", $title\n";
                        $m3uContent .= "#EXTVLCOPT:http-user-agent=googleusercontent\n";
                        $m3uContent .= "#EXTVLCOPT:http-referrer=$referer\n";
                        $m3uContent .= "{$source['url']}\n";
                    }
                }
            }
        }
    }
}

// Dosyaya kaydet
file_put_contents('output.m3u', $m3uContent);

echo "Oluşturulan M3U dosyası: output.m3u\n";
?>
