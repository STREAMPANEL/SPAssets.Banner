<?php

declare(strict_types=1);

// Custom lightweight PO parser
class SimplePoParser
{

    public static function parse(string $filePath) : array
    {
        $realPath   = realpath($filePath);
        $allowedDir = realpath(__DIR__ . '/locales');

        if ($realPath === false || $allowedDir === false || strpos($realPath, $allowedDir) !== 0) {
            return [];
        }

        if (! file_exists($realPath) || filesize($realPath) > 102400) {
            return [];
        }

        $content = file_get_contents($realPath);
        if ($content === false) {
            return [];
        }

        $lines        = explode("\n", $content);
        $translations = [];
        $currentId    = null;
        $currentStr   = null;
        $inMsgid      = false;
        $inMsgstr     = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            if (preg_match('/^msgid\s+"(.*)"$/', $line, $matches)) {
                if ($currentId !== null && $currentStr !== null && $currentId !== '') {
                    $translations[self::unescape($currentId)] = self::unescape($currentStr);
                }
                $currentId  = $matches[1];
                $currentStr = '';
                $inMsgid    = true;
                $inMsgstr   = false;
            } elseif (preg_match('/^msgstr\s+"(.*)"$/', $line, $matches)) {
                $currentStr = $matches[1];
                $inMsgid    = false;
                $inMsgstr   = true;
            } elseif (preg_match('/^"(.*)"$/', $line, $matches)) {
                if ($inMsgid) {
                    $currentId .= $matches[1];
                } elseif ($inMsgstr) {
                    $currentStr .= $matches[1];
                }
            }
        }
        if ($currentId !== null && $currentStr !== null && $currentId !== '') {
            $translations[self::unescape($currentId)] = self::unescape($currentStr);
        }

        return $translations;
    }

    private static function unescape(string $str) : string
    {

        return stripcslashes($str);
    }

}

// 1. Language Detection & Setup
$supportedLanguages = ['de', 'en'];
$language           = 'de'; // Fallback default

if (isset($_GET['lang']) && is_string($_GET['lang']) && preg_match('/^[a-z]{2}$/', $_GET['lang']) && in_array($_GET['lang'], $supportedLanguages, true)) {
    $language = $_GET['lang'];
} else {
    // Detect from Accept-Language HTTP header
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (! empty($acceptLang) && is_string($acceptLang)) {
        $langs = explode(',', $acceptLang);
        foreach ($langs as $lang) {
            $langCode = strtolower(substr(trim($lang), 0, 2));
            if (in_array($langCode, $supportedLanguages, true)) {
                $language = $langCode;
                break;
            }
        }
    }
}

// Load PO translation catalog
$translations = SimplePoParser::parse(__DIR__ . "/locales/{$language}.po");

function t(string $key)
{

    global $translations;
    return $translations[$key] ?? $key;
}

// 2. Base URL & Dir Configuration
$protocol     = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https://' : 'http://';

$rawHost      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$cleanHost    = preg_replace('/[^a-zA-Z0-9.:-]/', '', $rawHost);
$allowedHosts = ['assets.streampanel.net', 'localhost', '127.0.0.1'];
$hostParts    = explode(':', $cleanHost);
$domain       = strtolower($hostParts[0]);

if (in_array($domain, $allowedHosts, true)) {
    $host = $cleanHost;
} else {
    $host = 'assets.streampanel.net';
}

$scriptName   = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$dir          = dirname($scriptName);
$dirPath      = ($dir === '/' || $dir === '\\') ? '' : rtrim(str_replace('\\', '/', $dir), '/');
$imageUrlBase = $protocol . $host . $dirPath . '/images/';
$requestPath  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$pageUrl      = $protocol . $host . $requestPath;

$imageDir         = __DIR__ . '/images/';
$imagesBySize     = [];
$formatsAvailable = [];

// 3. Scan banner images
if (is_dir($imageDir)) {
    $files = scandir($imageDir);
    if ($files !== false) {
        foreach ($files as $file) {
            if (preg_match('/^(\d+[x-]\d+)_.*\.(jpg|jpeg|png|gif|webp|avif)$/i', $file, $m)) {
                $size = str_replace('-', 'x', $m[1]);
                $ext  = strtolower($m[2]);

                $safeFile = preg_replace('/^(\d+)x(\d+)_/', '$1-$2_', $file);
                if (! isset($imagesBySize[$size])) {
                    $imagesBySize[$size] = [];
                }
                if (! isset($imagesBySize[$size][$safeFile])) {
                    $imagesBySize[$size][$safeFile] = ['file' => $safeFile, 'ext' => $ext];
                }
                if (! in_array($ext, $formatsAvailable, true)) {
                    $formatsAvailable[] = $ext;
                }
            }
        }
    }
}


$preferredOrder = ['webp', 'avif', 'png', 'jpg', 'jpeg', 'gif'];
usort($formatsAvailable, function ($a, $b) use ($preferredOrder) {
    $posA = array_search($a, $preferredOrder, true);
    $posB = array_search($b, $preferredOrder, true);
    return ($posA === false ? 99 : $posA) <=> ($posB === false ? 99 : $posB);
});
$sizes = array_keys($imagesBySize);
natsort($sizes);
$sizes         = array_values($sizes);
$defaultFormat = 'all';

// SEO Title and Meta Description
$pageTitle       = t('Banner Vorschau') . ' | Streampanel';
$metaDescription = $language === 'de'
    ? 'SPCast Werbebanner und Grafiken von Streampanel zum Einbinden. Finden Sie offizielle Bannersets in verschiedenen Formaten.'
    : 'SPCast promotional banners and graphics from Streampanel for integration. Find official banner sets in different formats.';

$ogImageFile = null;
if (! empty($sizes) && ! empty($imagesBySize[$sizes[0]])) {
    $firstCard = reset($imagesBySize[$sizes[0]]);
    if (isset($firstCard['file'])) {
        $ogImageFile = $firstCard['file'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($pageUrl) ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($pageUrl) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <?php if ($ogImageFile): ?>
        <meta property="og:image" content="<?= htmlspecialchars($imageUrlBase . $ogImageFile) ?>">
    <?php endif; ?>

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= htmlspecialchars($pageUrl) ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($metaDescription) ?>">

    <!-- Premium Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Schema.org JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "WebPage",
          "@id": "<?= htmlspecialchars($pageUrl) ?>#webpage",
          "url": "<?= htmlspecialchars($pageUrl) ?>",
          "name": "<?= htmlspecialchars(t('Banner Vorschau')) ?>",
          "description": "<?= htmlspecialchars($metaDescription) ?>",
          "inLanguage": "<?= htmlspecialchars($language) ?>"
        },
        {
          "@type": "Organization",
          "@id": "https://www.streampanel.net/#organization",
          "name": "Streampanel",
          "url": "https://www.streampanel.net/",
          "logo": {
            "@type": "ImageObject",
            "url": "https://www.streampanel.net/wp-content/uploads/logo.png"
          }
        }
      ]
    }
    </script>
</head>

<body>
    <div class="container">
        <!-- Header & Branding -->
        <header>
            <div class="logo-container">
                <h1><?= htmlspecialchars(t('Banner Vorschau')) ?></h1>
                <div class="logo-subtitle">SPCast Official Branding Kit</div>
            </div>

            <!-- Language Selector with Crisp Inline SVG Flags -->
            <div class="lang-selector">
                <a href="?lang=de" id="lang-switch-de" class="lang-btn <?= $language === 'de' ? 'active' : '' ?>" aria-label="Deutsch">
                    <svg class="flag" viewBox="0 0 5 3" width="20" height="12">
                        <rect width="5" height="1" y="0" fill="#000000" />
                        <rect width="5" height="1" y="1" fill="#dd0000" />
                        <rect width="5" height="1" y="2" fill="#ffce00" />
                    </svg>
                    <span>DE</span>
                </a>
                <a href="?lang=en" id="lang-switch-en" class="lang-btn <?= $language === 'en' ? 'active' : '' ?>" aria-label="English">
                    <svg class="flag" viewBox="0 0 60 30" width="20" height="10">
                        <clipPath id="uk-flag-clip">
                            <rect width="60" height="30" />
                        </clipPath>
                        <g clip-path="url(#uk-flag-clip)">
                            <path d="M0 0h60v30H0z" fill="#012169" />
                            <path d="M0 0l60 30M60 0L0 30" stroke="#fff" stroke-width="6" />
                            <path d="M0 0l60 30M60 0L0 30" stroke="#c8102e" stroke-width="4" />
                            <path d="M30 0v30M0 15h60" stroke="#fff" stroke-width="10" />
                            <path d="M30 0v30M0 15h60" stroke="#c8102e" stroke-width="6" />
                        </g>
                    </svg>
                    <span>EN</span>
                </a>
            </div>
        </header>

        <main>
            <!-- Legal Notice Box -->
            <div class="alert-notice" role="alert">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p><?= htmlspecialchars(t('Sie dürfen alle hier aufgelisteten Grafiken aus rechtlichen Gründen ausschließlich per img-Tag einbinden auf Ihrer Seite.')) ?></p>
            </div>

            <!-- Controls (Tabs and Filters) -->
            <div class="controls-row">
                <!-- Size Tabs -->
                <?php if ($sizes): ?>
                    <nav class="tabs-container" aria-label="Banner sizes">
                        <?php foreach ($sizes as $i => $sz): ?>
                            <button class="tab-btn <?= $i === 0 ? 'active' : '' ?>" data-size="<?= htmlspecialchars($sz) ?>" id="sp-tab-<?= htmlspecialchars($sz) ?>">
                                <?= htmlspecialchars($sz) ?>
                            </button>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>

                <!-- Format Filter Dropdown -->
                <?php if ($formatsAvailable): ?>
                    <div class="filter-group">
                        <label for="format-filter"><?= htmlspecialchars(t('Format filtern:')) ?></label>
                        <div class="select-wrapper">
                            <select id="format-filter">
                                <option value="all" <?= $defaultFormat === 'all' ? 'selected' : '' ?>><?= htmlspecialchars($language === 'de' ? 'Alle Formate' : 'All formats') ?></option>
                                <?php foreach ($formatsAvailable as $fmt): ?>
                                    <option value="<?= htmlspecialchars($fmt) ?>" <?= $fmt === $defaultFormat ? 'selected' : '' ?>>
                                        <?= strtoupper(htmlspecialchars($fmt)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Banners Display Section -->
            <?php if ($sizes): ?>
                <?php

                foreach ($sizes as $i => $sz):
                    list($width, $height) = explode('x', $sz);
                    $cardMaxWidth         = (int) $width + 42; // Dynamic padding
                    ?>
                    <section class="grid-section <?= $i !== 0 ? 'hidden' : '' ?>" id="sp-grid-<?= htmlspecialchars($sz) ?>" data-no-images-msg="<?= htmlspecialchars(t('Keine Bilder gefunden.')) ?>">

                        <?php

                        foreach ($imagesBySize[$sz] as $img):
                            $imgUrl    = htmlspecialchars($imageUrlBase . $img['file']);
                            $embedCode = htmlspecialchars('<img src="' . $imageUrlBase . $img['file'] . '" alt="' . t('SPCast Werbebanner') . '">');
                            ?>
                            <div class="card" data-ext="<?= htmlspecialchars($img['ext']) ?>" style="max-width: <?= $cardMaxWidth ?>px;">
                                <div class="image-container">
                                    <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars(t('SPCast Werbebanner')) ?> <?= htmlspecialchars($sz) ?>" data-size="<?= htmlspecialchars($sz) ?>" loading="lazy">
                                </div>
                                <button class="btn-copy" data-code="<?= $embedCode ?>" data-copied-text="<?= htmlspecialchars(t('Kopiert!')) ?>"
                                    id="copy-btn-<?= htmlspecialchars(str_replace('.', '-', $img['file'])) ?>">
                                    <!-- Copy Icon SVG -->
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                    </svg>
                                    <span><?= htmlspecialchars(t('Code kopieren')) ?></span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            <?php else: ?>

                <div class="empty-state">
                    <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p><?= htmlspecialchars(t('Keine Bilder gefunden.')) ?></p>
                </div>
            <?php endif; ?>
        </main>

        <!-- Lightbox Modal for Fullscreen Preview -->
        <div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Lightbox">
            <button class="lightbox-close" id="lightbox-close" aria-label="Close lightbox">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <div class="lightbox-content">
                <img id="lightbox-img" src="" alt="Banner Full View">
            </div>
            <div class="lightbox-caption" id="lightbox-caption"></div>
        </div>

        <!-- Footer Links -->
        <footer>
            <nav class="footer-links" aria-label="Legal pages">
                <a href="https://www.streampanel.net/kontakt/" target="_blank" rel="noopener"><?= htmlspecialchars(t('Kontakt')) ?></a>
                <a href="https://www.streampanel.net/kontakt/cookies/" target="_blank" rel="noopener"><?= htmlspecialchars(t('Cookies')) ?></a>
                <a href="https://www.streampanel.net/kontakt/datenschutz/" target="_blank" rel="noopener"><?= htmlspecialchars(t('Datenschutz')) ?></a>
                <a href="https://www.streampanel.net/kontakt/impressum/" target="_blank" rel="noopener"><?= htmlspecialchars(t('Impressum')) ?></a>
            </nav>
            <div class="copyright">&copy;<?= date('Y') ?> STREAMPANEL</div>
        </footer>
    </div>

    <!-- Script assets -->
    <script src="assets/js/main.js"></script>
</body>

</html>