<?php
/**
 * hybrid-proxy.php
 * Yahoo = live price
 * FMP   = statements + profile + market cap
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$ticker = strtoupper(trim($_GET['ticker'] ?? ''));
if (!$ticker || !preg_match('/^[A-Z0-9.\-\^]{1,15}$/', $ticker)) {
    echo json_encode(['error' => 'Invalid ticker']);
    exit;
}

/* =========================
   CONFIG
   The API key is deliberately NOT in this file — this repository is public.
   Provide it one of two ways, in this order of preference:
     1. Set FMP_API_KEY as an environment variable in hPanel (Advanced → PHP config).
     2. Create projects/config.local.php returning the key as a string.
        That file is git-ignored and must never be committed.
   See config.example.php.
   ========================= */
$FMP_API_KEY = getenv('FMP_API_KEY') ?: '';

if ($FMP_API_KEY === '') {
    // Hostinger only exposes an Environment Variables UI for Node.js deployments, so on a
    // static/PHP deploy getenv() will be empty. Read a file instead.
    //
    // Preferred location is ONE LEVEL ABOVE the web root: git deploys replace the contents
    // of public_html, so anything inside it can be wiped on the next push, and anything
    // committed to the repo is public. A file above the web root is neither.
    $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $home    = rtrim((string) (getenv('HOME') ?: ($_SERVER['HOME'] ?? '')), '/');

    // Layouts differ between Hostinger plans: public_html sometimes sits directly in the
    // account root, sometimes under domains/<site>/. Check the plausible parents rather
    // than assuming one.
    $candidates = [];
    if ($docRoot !== '') {
        $candidates[] = dirname($docRoot) . '/fmp-config.php';           // ← preferred
        $candidates[] = dirname(dirname($docRoot)) . '/fmp-config.php';
    }
    if ($home !== '') {
        $candidates[] = $home . '/fmp-config.php';
    }
    $candidates[] = __DIR__ . '/config.local.php';   // in-repo fallback, git-ignored

    $candidates = array_values(array_unique($candidates));

    foreach ($candidates as $path) {
        if (is_readable($path)) {
            $FMP_API_KEY = trim((string) require $path);
            if ($FMP_API_KEY !== '') {
                break;
            }
        }
    }
}

if ($FMP_API_KEY === '') {
    // Fail loudly, and say where the file is expected. Paths only — never the key itself.
    // Once the key is in place this branch is unreachable; trim the detail then if you like.
    http_response_code(500);
    echo json_encode([
        'error'    => 'FMP_API_KEY is not configured on this server',
        'hint'     => 'Create fmp-config.php at the first path below. It must return the key as a string.',
        'expected' => $candidates[0] ?? 'unknown',
        'searched' => $candidates,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

/* =========================
   HTTP helper
   ========================= */
function yGet(string $url): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/plain, */*',
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ],
    ]);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $decoded = null;
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
    }

    return [
        'ok' => ($raw !== false && $httpCode === 200),
        'httpCode' => $httpCode,
        'curlError' => $err,
        'raw' => $raw,
        'data' => (json_last_error() === JSON_ERROR_NONE) ? $decoded : null,
        'url' => $url
    ];
}

function fmtBig(float $n, string $currency = '$'): string {
    if ($n >= 1e6) return $currency . round($n / 1e6, 2) . 'T';
    if ($n >= 1e3) return $currency . round($n / 1e3, 1) . 'B';
    return $currency . round($n, 0) . 'M';
}

/* =========================
   STEP 1 — Yahoo live price
   ========================= */
$chartRes = yGet("https://query2.finance.yahoo.com/v8/finance/chart/{$ticker}?interval=1d&range=5d&includePrePost=false");
$chart    = $chartRes['data'] ?? null;

if (
    !$chartRes['ok'] ||
    !is_array($chart) ||
    empty($chart['chart']['result'][0])
) {
    echo json_encode([
        'error' => "Ticker '{$ticker}' not found.",
        'debug' => [
            'chartOk' => $chartRes['ok'],
            'chartHttp' => $chartRes['httpCode'],
            'chartCurlError' => $chartRes['curlError'],
            'chartUrl' => $chartRes['url']
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

$meta      = $chart['chart']['result'][0]['meta'] ?? [];
$price     = (float)($meta['regularMarketPrice'] ?? 0);
$prevClose = (float)($meta['chartPreviousClose'] ?? $price);
$currency  = $meta['currency'] ?? 'USD';
$exchange  = $meta['exchangeName'] ?? '—';
$shortName = $meta['shortName'] ?? $ticker;
$longName  = $meta['longName'] ?? $shortName;

function normalizeYahooNews(array $raw, int $limit = 10): array {
    $out = [];
    $items = $raw['news'] ?? [];

    foreach ($items as $item) {
        $title = $item['title'] ?? '';
        if (!$title) continue;

        $provider = 'Yahoo Finance';
        if (!empty($item['publisher'])) {
            $provider = $item['publisher'];
        } elseif (!empty($item['provider']['displayName'])) {
            $provider = $item['provider']['displayName'];
        }

        $ts = $item['providerPublishTime'] ?? null;
        $date = $ts ? date('d M Y', (int)$ts) : date('d M Y');

        $link = '';
        if (!empty($item['link'])) {
            $link = $item['link'];
        } elseif (!empty($item['clickThroughUrl']['url'])) {
            $link = $item['clickThroughUrl']['url'];
        } elseif (!empty($item['canonicalUrl']['url'])) {
            $link = $item['canonicalUrl']['url'];
        }

        $out[] = [
            'headline' => $title,
            'source' => $provider,
            'date' => $date,
            'sentiment' => 'neutral',
            'url' => $link
        ];

        if (count($out) >= $limit) break;
    }

    return $out;
}

/* =========================
   STEP 2 — FMP data
   ========================= */
$newsRes = yGet("https://query1.finance.yahoo.com/v1/finance/search?q=" . urlencode($ticker) . "&quotesCount=1&newsCount=10");
$yahooNews = ($newsRes['ok'] && is_array($newsRes['data']))
    ? normalizeYahooNews($newsRes['data'], 10)
    : [];
$incomeRes   = yGet("https://financialmodelingprep.com/stable/income-statement?symbol={$ticker}&limit=5&apikey={$FMP_API_KEY}");
$balanceRes  = yGet("https://financialmodelingprep.com/stable/balance-sheet-statement?symbol={$ticker}&limit=5&apikey={$FMP_API_KEY}");
$cashflowRes = yGet("https://financialmodelingprep.com/stable/cash-flow-statement?symbol={$ticker}&limit=5&apikey={$FMP_API_KEY}");
$profileRes  = yGet("https://financialmodelingprep.com/stable/profile?symbol={$ticker}&apikey={$FMP_API_KEY}");
$keyMetRes   = yGet("https://financialmodelingprep.com/stable/key-metrics?symbol={$ticker}&limit=1&apikey={$FMP_API_KEY}");

$income   = $incomeRes['data'] ?? null;
$balance  = $balanceRes['data'] ?? null;
$cashflow = $cashflowRes['data'] ?? null;
$profile  = $profileRes['data'] ?? null;
$keyMet   = $keyMetRes['data'] ?? null;

$profile0 = (is_array($profile) && isset($profile[0]) && is_array($profile[0])) ? $profile[0] : [];
$key0     = (is_array($keyMet) && isset($keyMet[0]) && is_array($keyMet[0])) ? $keyMet[0] : [];

/* =========================
   STEP 3 — Company metadata
   ========================= */
$name   = $profile0['companyName'] ?? $longName;
$sector = $profile0['sector'] ?? 'Unknown';
$desc   = $profile0['description'] ?? 'No description available.';
$beta   = (float)($profile0['beta'] ?? 1.0);

/* ---- shares outstanding (millions) ---- */
$sharesOut = 0;

// 1) best source: key metrics
if ((float)($key0['numberOfShares'] ?? 0) > 0) {
    $sharesOut = (float)$key0['numberOfShares'] / 1e6;
}

// 2) fallback: derive from market cap / price
elseif ((float)($profile0['marketCap'] ?? 0) > 0 && $price > 0) {
    $sharesOut = ((float)$profile0['marketCap'] / 1e6) / $price;
}
elseif ((float)($profile0['mktCap'] ?? 0) > 0 && $price > 0) {
    $sharesOut = ((float)$profile0['mktCap'] / 1e6) / $price;
}

// 3) last resort: do NOT use fake 100 unless absolutely necessary
$sharesOut = $sharesOut > 0 ? $sharesOut : 0;

/* ---- other metadata ---- */
$bvps = (float)($key0['bookValuePerShare'] ?? 0);
$dps  = (float)($profile0['lastDiv'] ?? 0);

/* ---- market cap (millions) ---- */
$mktCap = 0;
if ((float)($profile0['marketCap'] ?? 0) > 0) {
    $mktCap = (float)($profile0['marketCap'] ?? 0) / 1e6;
} elseif ((float)($profile0['mktCap'] ?? 0) > 0) {
    $mktCap = (float)($profile0['mktCap'] ?? 0) / 1e6;
} elseif ($price > 0 && $sharesOut > 0) {
    $mktCap = $price * $sharesOut;
}

/* =========================
   STEP 4 — Statements
   ========================= */
$years        = [];
$revenue      = [];
$cogs         = [];
$grossProfit  = [];
$opex         = [];
$ebit         = [];
$interest     = [];
$taxArr       = [];
$netIncome    = [];
$ebitdaArr    = [];
$da           = [];
$capex        = [];

if (is_array($income) && !empty($income)) {
    $income = array_reverse($income);
    foreach ($income as $row) {
        $years[]       = (int)substr(($row['date'] ?? '2020-12-31'), 0, 4);
        $rev           = (float)($row['revenue'] ?? 0);
        $cog           = (float)($row['costOfRevenue'] ?? 0);
        $gp            = (float)($row['grossProfit'] ?? max(0, $rev - $cog));
        $opEx          = (float)($row['operatingExpenses'] ?? 0);
        $opInc         = (float)($row['operatingIncome'] ?? 0);
        $intExp        = abs((float)($row['interestExpense'] ?? 0));
        $taxExp        = (float)($row['incomeTaxExpense'] ?? 0);
        $ni            = (float)($row['netIncome'] ?? 0);
        $ebitda        = (float)($row['ebitda'] ?? 0);

        $revenue[]     = round($rev / 1e6, 2);
        $cogs[]        = round($cog / 1e6, 2);
        $grossProfit[] = round($gp / 1e6, 2);
        $opex[]        = round($opEx / 1e6, 2);
        $ebit[]        = round($opInc / 1e6, 2);
        $interest[]    = round($intExp / 1e6, 2);
        $taxArr[]      = round($taxExp / 1e6, 2);
        $netIncome[]   = round($ni / 1e6, 2);
        $ebitdaArr[]   = round($ebitda / 1e6, 2);
    }
}

if (is_array($cashflow) && !empty($cashflow)) {
    $cashflow = array_reverse($cashflow);
    foreach ($cashflow as $i => $row) {
        $dep = abs((float)($row['depreciationAndAmortization'] ?? 0));
        $cx  = abs((float)($row['capitalExpenditure'] ?? 0));

        $da[]    = round($dep / 1e6, 2);
        $capex[] = round($cx / 1e6, 2);

        if (isset($ebit[$i]) && (!isset($ebitdaArr[$i]) || (float)$ebitdaArr[$i] == 0)) {
            $ebitdaArr[$i] = round(($ebit[$i] ?? 0) + ($da[$i] ?? 0), 2);
        }
    }
}

$cash      = 0;
$shortDebt = 0;
$longDebt  = 0;
$grossDebt = 0;
$totalEq   = 0;
$totalAss  = 0;

if (is_array($balance) && !empty($balance)) {
    $latestBS  = $balance[0];
    $cash      = round(((float)($latestBS['cashAndCashEquivalents'] ?? 0)) / 1e6, 2);
    $shortDebt = round(((float)($latestBS['shortTermDebt'] ?? 0)) / 1e6, 2);
    $longDebt  = round(((float)($latestBS['longTermDebt'] ?? 0)) / 1e6, 2);
    $grossDebt = round($shortDebt + $longDebt, 2);
    $totalEq   = round(((float)($latestBS['totalStockholdersEquity'] ?? 0)) / 1e6, 2);
    $totalAss  = round(((float)($latestBS['totalAssets'] ?? 0)) / 1e6, 2);

    if ($bvps <= 0 && $sharesOut > 0 && $totalEq > 0) {
        $bvps = round($totalEq / $sharesOut, 2);
    }
}

/* =========================
   STEP 5 — Derived metrics
   ========================= */
$histGrowth = [];
for ($i = 1; $i < count($revenue); $i++) {
    $prev = $revenue[$i - 1];
    $histGrowth[] = $prev > 0 ? round((($revenue[$i] - $prev) / $prev) * 100, 1) : 0;
}
$avg5  = count($histGrowth) ? round(array_sum($histGrowth) / count($histGrowth), 1) : 0;
$avg10 = $avg5;

$grossMgn = !empty($revenue) && end($revenue) > 0 ? round((end($grossProfit) / end($revenue)) * 100, 1) : 0;
$ebitMgn  = !empty($revenue) && end($revenue) > 0 ? round((end($ebit) / end($revenue)) * 100, 1) : 0;
$roe      = ($bvps > 0 && !empty($netIncome) && $sharesOut > 0)
    ? round(((end($netIncome) / $sharesOut) / $bvps) * 100, 1)
    : 0;
$debtEq   = $totalEq > 0 ? round($grossDebt / $totalEq, 2) : 0;

$currSymbol = '$';
if ($currency === 'GBP' || $currency === 'GBX') $currSymbol = '£';
if ($currency === 'EUR') $currSymbol = '€';

$metrics = [
    'Market Cap'   => $mktCap > 0 ? fmtBig($mktCap, $currSymbol) : 'N/A',
    'Gross Margin' => round($grossMgn, 1) . '%',
    'EBIT Margin'  => round($ebitMgn, 1) . '%',
    'ROE'          => round($roe, 1) . '%',
    'Debt/Equity'  => round($debtEq, 2) . 'x',
    'Beta'         => round($beta, 2),
    'DPS'          => $dps > 0 ? $currSymbol . round($dps, 2) : 'None',
];

$statementsAvailable = (count($years) > 0 && count($revenue) > 0);

/* =========================
   OUTPUT
   ========================= */
echo json_encode([
    'name'           => $name,
    'ticker'         => $ticker,
    'sector'         => $sector,
    'exchange'       => $exchange,
    'price'          => round($price, 2),
    'change'         => round($price - $prevClose, 2),
    'changePct'      => $prevClose > 0 ? round((($price - $prevClose) / $prevClose) * 100, 2) : 0,
    'marketCap'      => round($mktCap, 2),
    'currency'       => $currency,
    'shares'         => round($sharesOut, 2),

    'years'          => $years,
    'revenue'        => $revenue,
    'cogs'           => $cogs,
    'grossProfit'    => $grossProfit,
    'opex'           => $opex,
    'ebit'           => $ebit,
    'interest'       => $interest,
    'tax'            => $taxArr,
    'netIncome'      => $netIncome,
    'ebitda'         => $ebitdaArr,
    'da'             => $da,
    'capex'          => $capex,

    'grossDebt'      => $grossDebt,
    'cash'           => $cash,
    'equity'         => [$totalEq],
    'totalAssets'    => [$totalAss],

    'bvps'           => round($bvps, 2),
    'dps'            => round($dps, 2),
    'beta'           => round($beta, 2),
    'riskFree'       => 4.3,
    'mktPremium'     => 5.5,
    'description'    => $desc,
    'statementsAvailable' => $statementsAvailable,
    'warning'        => $statementsAvailable ? null : 'Price loaded, but statements were unavailable.',
    'histRevGrowth'  => $histGrowth,
    'longHistGrowth' => [
        'avg5' => $avg5,
        'avg10' => $avg10
    ],
    'metrics'        => $metrics,
    'news' => !empty($yahooNews) ? $yahooNews : [[
        'headline' => "No recent news found for {$name}.",
        'source' => 'Yahoo Finance',
        'date' => date('d M Y'),
        'sentiment' => 'neutral'
    ]],

    // TEMP DEBUG
    'debug' => [
        'chartOk' => $chartRes['ok'],
        'chartHttp' => $chartRes['httpCode'],
        'chartCurlError' => $chartRes['curlError'],

        'fmpKeyPresent' => !empty($FMP_API_KEY),
        'incomeOk' => $incomeRes['ok'],
        'incomeHttp' => $incomeRes['httpCode'],
        'balanceOk' => $balanceRes['ok'],
        'balanceHttp' => $balanceRes['httpCode'],
        'cashflowOk' => $cashflowRes['ok'],
        'cashflowHttp' => $cashflowRes['httpCode'],
        'profileOk' => $profileRes['ok'],
        'profileHttp' => $profileRes['httpCode'],
        'keyMetricsOk' => $keyMetRes['ok'],
        'keyMetricsHttp' => $keyMetRes['httpCode'],

        'incomeRows' => is_array($income) ? count($income) : -1,
        'balanceRows' => is_array($balance) ? count($balance) : -1,
        'cashflowRows' => is_array($cashflow) ? count($cashflow) : -1,
        'profileRows' => is_array($profile) ? count($profile) : -1,
        'keyMetricsRows' => is_array($keyMet) ? count($keyMet) : -1
    ]
], JSON_PRETTY_PRINT);