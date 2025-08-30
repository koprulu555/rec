<?php
// Varsayılanlar (ikinci ihtimal - fallback)
$defaultBaseUrl = 'https://m.prectv49.sbs';
$defaultSuffix = '4F5A9C3D9A86FA54EACEDDD635185/c3c5bd17-e37b-4b94-a944-8a3688a30452/';
$defaultUserAgent = 'Dart/3.7 (dart:io)';
$defaultReferer = 'https://twitter.com/';
$pageCount = 4;

// M3U çıktısı için sabit User-Agent
$m3uUserAgent = 'googleusercontent';

// Github kaynak dosyası (ilk ihtimal)
$sourceUrlRaw = 'https://raw.githubusercontent.com/kerimmkirac/cs-kerim2/main/RecTV/src/main/kotlin/com/kerimmkirac/RecTV.kt';
$proxyUrl = 'https://api.codetabs.com/v1/proxy/?quest=' . urlencode($sourceUrlRaw);

// Güncel değerlerin tutulacağı değişkenler (başlangıçta varsayılanlar)
$baseUrl    = $defaultBaseUrl;
$suffix     = $defaultSuffix;
$userAgent  = $defaultUserAgent;
$referer    = $defaultReferer;

// Github içeriğini çekmek için geliştirilmiş fonksiyon
function fetchGithubContent($sourceUrlRaw, $proxyUrl) {
    // Önce doğrudan deneme
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 10
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $githubContent = @file_get_contents($sourceUrlRaw, false, $context);
    if ($githubContent !== FALSE) return $githubContent;
    
    // Proxy ile deneme
    $githubContentProxy = @file_get_contents($proxyUrl, false, $context);
    if ($githubContentProxy !== FALSE) return $githubContentProxy;
    
    return FALSE;
}

// 1. ADIM: Github'dan değerleri çekmeyi dene
$githubContent = fetchGithubContent($sourceUrlRaw, $proxyUrl);

if ($githubContent !== FALSE) {
    // Github içeriği başarıyla alındı, regex ile değerleri çek
    
    $githubBaseUrl = $baseUrl;
    $githubSuffix = $suffix;
    $githubUserAgent = $userAgent;
    $githubReferer = $referer;
    
    $successCount = 0;
    
    // mainUrl - Kotlin syntax'ına uygun regex
    if (preg_match('/override\s+var\s+mainUrl\s*=\s*"([^"]+)"/', $githubContent, $baseUrlMatch)) {
        $githubBaseUrl = $baseUrlMatch[1];
        $successCount++;
    }
    
    // swKey - Kotlin syntax'ına uygun regex
    if (preg_match('/private\s+(val|var)\s+swKey\s*=\s*"([^"]+)"/', $githubContent, $suffixMatch)) {
        $githubSuffix = $suffixMatch[2];
        $successCount++;
    }
    
    // user-agent - Kotlin mapOf syntax'ına uygun regex
    if (preg_match('/headers\s*=\s*mapOf\([^)]*"user-agent"[^)]*to[^"]*"([^"]+)"/s', $githubContent, $uaMatch)) {
        $githubUserAgent = $uaMatch[1];
        $successCount++;
    }
    
    // referer - Kotlin mapOf syntax'ına uygun regex
    if (preg_match('/headers\s*=\s*mapOf\([^)]*"Referer"[^)]*to[^"]*"([^"]+)"/s', $githubContent, $refMatch)) {
        $githubReferer = $refMatch[1];
        $successCount++;
    } 
    // Alternatif referer arama (ExtractorLink içindeki referer)
    else if (preg_match('/referer\s*=\s*"([^"]+)"/', $githubContent, $refMatch2)) {
        $githubReferer = $refMatch2[1];
        $successCount++;
    }
    
    // 2. ADIM: Github'dan alınan değerleri test et
    function testApiConnection($baseUrl, $suffix, $userAgent, $referer) {
        $testUrl = $baseUrl . '/api/channel/by/filtres/0/0/0/' . $suffix;
        
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: $userAgent\r\nReferer: $referer\r\n",
                'timeout' => 15,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        
        $ctx = stream_context_create($opts);
        $response = @file_get_contents($testUrl, false, $ctx);
        
        if ($response === FALSE) {
            return false;
        }
        
        $data = json_decode($response, true);
        return $data !== null && is_array($data);
    }
    
    // Github değerlerini test et
    if (testApiConnection($githubBaseUrl, $githubSuffix, $githubUserAgent, $githubReferer)) {
        // BAŞARILI: Github değerleri çalışıyor, kullan
        $baseUrl = $githubBaseUrl;
        $suffix = $githubSuffix;
        $userAgent = $githubUserAgent;
        $referer = $githubReferer;
        echo "✓ Github değerleri başarıyla alındı ve test edildi\n";
    } else {
        // BAŞARISIZ: Github değerleri çalışmıyor, varsayılanlara dön
        echo "✗ Github değerleri çalışmıyor, varsayılanlar kullanılıyor\n";
    }
} else {
    // Github'dan içerik alınamadı
    echo "✗ Github içeriği alınamadı, varsayılanlar kullanılıyor\n";
}

// 3. ADIM: Son kullanılacak değerleri göster
echo "Kullanılan Base URL: $baseUrl\n";
echo "Kullanılan Suffix: $suffix\n";
echo "Kullanılan User-Agent: $userAgent\n";
echo "Kullanılan Referer: $referer\n";
echo "M3U için User-Agent: $m3uUserAgent\n";

// M3U çıktısı oluştur
$m3uContent = "#EXTM3U\n";

// API çağrılarında kullanılacak context (Github'dan alınan veya varsayılan header'lar ile)
$options = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: $userAgent\r\nReferer: $referer\r\n",
        'timeout' => 30,
        'ignore_errors' => true
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
];
$context = stream_context_create($options);

// CANLI YAYINLAR
for ($page = 0; $page < $pageCount; $page++) {
    $apiUrl = $baseUrl . "/api/channel/by/filtres/0/0/$page/" . $suffix;
    $response = @file_get_contents($apiUrl, false, $context);
    
    if ($response === FALSE) {
        continue;
    }
    
    $data = json_decode($response, true);
    if ($data === null || !is_array($data)) {
        continue;
    }

    foreach ($data as $content) {
        if (isset($content['sources']) && is_array($content['sources'])) {
            foreach ($content['sources'] as $source) {
                if (($source['type'] ?? '') === 'm3u8' && isset($source['url'])) {
                    $title = $content['title'] ?? '';
                    $image = isset($content['image']) ? (
                        (strpos($content['image'], 'http') === 0) ? $content['image'] : $baseUrl . '/' . ltrim($content['image'], '/')
                    ) : '';
                    $categories = isset($content['categories']) && is_array($content['categories'])
                        ? implode(", ", array_column($content['categories'], 'title'))
                        : '';
                    
                    // M3U çıktısında: User-Agent SABİT, Referer dinamik (Github'dan veya varsayılan)
                    $m3uContent .= "#EXTINF:-1 tvg-id=\"{$content['id']}\" tvg-name=\"$title\" tvg-logo=\"$image\" group-title=\"$categories\", $title\n";
                    $m3uContent .= "#EXTVLCOPT:http-user-agent=$m3uUserAgent\n";
                    $m3uContent .= "#EXTVLCOPT:http-referrer=$referer\n";
                    $m3uContent .= "{$source['url']}\n";
                }
            }
        }
    }
}

// FİLMLER (aynı mantık)
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
                        $image = isset($content['image']) ? (
                            (strpos($content['image'], 'http') === 0) ? $content['image'] : $baseUrl . '/' . ltrim($content['image'], '/')
                        ) : '';
                        $m3uContent .= "#EXTINF:-1 tvg-id=\"{$content['id']}\" tvg-name=\"$title\" tvg-logo=\"$image\" group-title=\"$categoryName\", $title\n";
                        $m3uContent .= "#EXTVLCOPT:http-user-agent=$m3uUserAgent\n";
                        $m3uContent .= "#EXTVLCOPT:http-referrer=$referer\n";
                        $m3uContent .= "{$source['url']}\n";
                    }
                }
            }
        }
    }
}

// DİZİLER (aynı mantık)
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
                        $image = isset($content['image']) ? (
                            (strpos($content['image'], 'http') === 0) ? $content['image'] : $baseUrl . '/' . ltrim($content['image'], '/')
                        ) : '';
                        $m3uContent .= "#EXTINF:-1 tvg-id=\"{$content['id']}\" tvg-name=\"$title\" tvg-logo=\"$image\" group-title=\"$categoryName\", $title\n";
                        $m3uContent .= "#EXTVLCOPT:http-user-agent=$m3uUserAgent\n";
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
echo "İşlem tamamlandı!\n";
?>
