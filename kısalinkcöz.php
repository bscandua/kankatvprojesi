<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Resolver</title>
</head>
<body>
    <form method="post">
        <label for="shortUrl">Kısa URL:</label>
        <input type="text" id="shortUrl" name="shortUrl" placeholder="https://t.co/xwFnBvOcgI" required>
        <button type="submit">Gönder</button>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shortUrl'])) {
        $shortUrl = $_POST['shortUrl'];

        // ExpandURL kullanarak ana URL'yi çözme
        function resolveShortURL($shortUrl) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://www.expandurl.net/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'accept-language: tr,en;q=0.9,en-GB;q=0.8,en-US;q=0.7',
                'cache-control: max-age=0',
                'content-type: application/x-www-form-urlencoded',
                'origin: https://www.expandurl.net',
                'referer: https://www.expandurl.net/',
                'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'url=' . urlencode($shortUrl));

            $response = curl_exec($ch);
            curl_close($ch);

            preg_match('@<div class="mb-3"><a class="text-link" style="overflow-wrap:break-word" target="_blank" href="(.*?)">(.*?)</a></div>@', $response, $matches);

            return $matches[1] ?? null;
        }

        // Ana URL'yi çöz ve yeni URL'yi al
        $resolvedUrl = resolveShortURL($shortUrl);

        if ($resolvedUrl) {
            echo "<p>Çözümlenmiş URL: $resolvedUrl</p>";
            $url = $resolvedUrl . "/channel.html?id=yayininat";
        } else {
            die("<p>Kısa URL çözümlenemedi!</p>");
        }

        // URL'nin kaynak kodunu al
        $source = @file_get_contents($url);

        if ($source === false) {
            die("<p>Kaynak kod indirilemedi! URL'yi kontrol edin.</p>");
        }

        // Kaynak kodu hata ayıklama için dosyaya yaz
        file_put_contents('source_debug.html', $source);

        // Kaynak kodunda baseurl'i bul
        preg_match('/var baseurl = \"(.*?)\";/', $source, $baseurlMatch);

        if (!empty($baseurlMatch[1])) {
            $baseurl = $baseurlMatch[1];
            echo "<p>Base URL: $baseurl</p>";
        } else {
            die("<p>Base URL bulunamadı!</p>");
        }

        // Kaynak kodunda kanalurl'u bul
        preg_match('/var kanalurl\s*=\s*\{(.*?)\};/s', $source, $kanalurlMatch);

        if (!empty($kanalurlMatch[1])) {
            $kanalurlRaw = $kanalurlMatch[1];

            // Kanal URL'lerini bir diziye dönüştür
            preg_match_all('/\d+\s*:\s*\"(.*?)\"/', $kanalurlRaw, $kanalUrls);

            if (!empty($kanalUrls[1])) {
                $kanalList = $kanalUrls[1];

                // Base URL ile birleştir ve sonucu göster
                $fullUrls = [];
                echo "<p>Kanal URL'leri:</p><ul>";
                foreach ($kanalList as $index => $kanal) {
                    $fullUrl = $baseurl . $kanal;
                    $fullUrls[] = $fullUrl;
                    echo "<li>" . ($index + 1) . ". $fullUrl</li>";
                }
                echo "</ul>";

                // Tüm URL'leri bir dosyaya yaz
                $file = 'yayinlar.txt';
                file_put_contents($file, implode(PHP_EOL, $fullUrls));
                echo "<p>Tüm yayın URL'leri <strong>$file</strong> dosyasına kaydedildi.</p>";
            } else {
                die("<p>Kanal URL'leri bulunamadı!</p>");
            }
        } else {
            die("<p>Kanal URL bilgisi bulunamadı!</p>");
        }
    }
    ?>
</body>
</html>
