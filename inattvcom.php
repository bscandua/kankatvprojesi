<?php
function getDynamicDomain() {
    $baseDomain = "https://inattvcom"; 
    $currentNumber = 7362; // Başlangıç numarası
    $maxAttempts = 5000; // Maksimum kontrol denemesi
    $finalDomain = "";

    for ($i = 0; $i < $maxAttempts; $i++) {
        $testDomain = $baseDomain . $currentNumber . ".xyz/player.php?id=test";
        $headers = @get_headers($testDomain);

        if ($headers && strpos($headers[0], '200')) {
            $finalDomain = $baseDomain . $currentNumber . ".xyz";
            break;
        }
        $currentNumber++;
    }

    if (!$finalDomain) {
        die("Uygun bir domain bulunamadı.");
    }

    return $finalDomain;
}

function fetchStreamLink($domain, $id) {
    $url = $domain . "/player.php?id=" . $id;

    // cURL ile veri çekimi
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Referer: $domain",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0 Safari/537.36"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        die("Akış içeriği alınamadı.");
    }

    // m3u8 bağlantısını düzenli ifadelerle bul
    if (preg_match('/source:\s*"([^"]+\.m3u8)"/', $response, $matches)) {
        return $matches[1];
    }

    die("m3u8 bağlantısı bulunamadı.");
}

function fetchAndFixM3U8Content($url, $referer) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Referer: $referer",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0 Safari/537.36"
    ]);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        die("M3U8 içeriği alınamadı: " . curl_error($ch));
    }

    curl_close($ch);

    // Segmentlere tam URL ekle
    $basePath = dirname($url) . '/';
    $fixedResponse = preg_replace_callback('/^([a-zA-Z0-9_-]+\.\w+)$/m', function ($matches) use ($basePath) {
        return $basePath . $matches[1];
    }, $response);

    return $fixedResponse;
}

// İstemciden gelen ID'yi al
$id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$id) {
    die("Lütfen bir ID giriniz.");
}

// Dinamik domaini tespit et
$domain = getDynamicDomain();

// m3u8 bağlantısını al
$streamLink = fetchStreamLink($domain, $id);

// m3u8 dosyasını cURL ile çek, segmentlere tam URL ekle ve istemciye gönder
header("Content-Type: application/vnd.apple.mpegurl");
header("Content-Disposition: inline; filename=\"stream.m3u8\"");
echo fetchAndFixM3U8Content($streamLink, $domain);
exit;
?>