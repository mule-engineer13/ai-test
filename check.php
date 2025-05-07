<?php
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$openaiApiKey = $_SERVER['AI_key'];

// Sentryイベント読み込み
$sentryEventData = json_decode(file_get_contents(__DIR__ . '/last_sentry_event.json'), true);

$errorFile = ltrim(str_replace(__DIR__, '', $sentryEventData['raw_exception']['file']), '/');
$errorLine = $sentryEventData['raw_exception']['line'] ?? '不明';
$errorMessage = $sentryEventData['raw_exception']['message'] ?? '不明なエラー';

// --- 追加: traceから参照ファイル抽出 ---
$traceFiles = [$errorFile]; // エラー発生ファイルは必ず含める
if (isset($sentryEventData['raw_exception']['trace'])) {
    foreach ($sentryEventData['raw_exception']['trace'] as $traceLine) {
        if (preg_match('/\/([^\/]+\.php)\(/', $traceLine, $matches)) {
            $traceFiles[] = $matches[1];
        }
    }
}
$traceFiles = array_unique($traceFiles);

// GitHubから全tree取得
$owner = 'mule-engineer13';
$repo = 'ai-test';
$branch = 'main';

$treeUrl = "https://api.github.com/repos/$owner/$repo/git/trees/$branch?recursive=1";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $treeUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'User-Agent: PHP',
        'Accept: application/vnd.github.v3+json'
    ]
]);
$treeResponse = curl_exec($ch);
curl_close($ch);
$treeData = json_decode($treeResponse, true);

// 必要ファイルのSHAとパスマップを構築
$shaMap = [];
foreach ($treeData['tree'] as $item) {
    if ($item['type'] === 'blob') {
        foreach ($traceFiles as $file) {
            if (str_ends_with($item['path'], $file)) {
                $shaMap[$item['path']] = $item['sha'];
                break;
            }
        }
    }
}

// GitHubから対象ファイルを取得
$sourceFiles = [];
foreach ($shaMap as $path => $sha) {
    $blobUrl = "https://api.github.com/repos/$owner/$repo/git/blobs/$sha";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $blobUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: PHP',
            'Accept: application/vnd.github.v3+json'
        ]
    ]);
    $blobResponse = curl_exec($ch);
    curl_close($ch);

    $blobData = json_decode($blobResponse, true);
    if (isset($blobData['content'])) {
        $decoded = base64_decode($blobData['content']);
        $sourceFiles[$path] = $decoded;
    }
}

// 操作履歴の受け取り
$historyRaw = $_POST['history'] ?? '[]';
$historyDecoded = json_decode($historyRaw, true);
$historyLines = array_map(function ($entry) {
    $line = $entry['timestamp'] . ' ' . $entry['action'] . ' ' . $entry['target'];
    if (isset($entry['position'])) {
        $line .= " [x:{$entry['position']['x']}, y:{$entry['position']['y']}]";
    }
    return $line;
}, $historyDecoded);
$historyText = implode("\n", $historyLines);

// プロンプト作成
function generateChatPrompt(array $sourceFiles, string $errorMessage, string $errorFile, string $errorLine, string $historyText): string
{
    $prompt = <<<EOD
次のPHPコード群の中で `{$errorFile}` の `{$errorLine}` 行目で「{$errorMessage}」というエラーが発生しました。
また、フォーム送信前に以下のような操作履歴がありました：

{$historyText}

この情報をもとに、バグの原因と改善案を具体的に提示してください。
EOD;

    foreach ($sourceFiles as $path => $content) {
        $prompt .= "\n\n--- {$path} ---\n{$content}";
    }

    return $prompt;
}

$chatPrompt = generateChatPrompt($sourceFiles, $errorMessage, $errorFile, $errorLine, $historyText);

// OpenAI API 呼び出し
$data = [
    'model' => 'gpt-4',
    'messages' => [
        ['role' => 'user', 'content' => $chatPrompt]
    ]
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiApiKey
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data)
]);

$chatResponse = curl_exec($ch);
curl_close($ch);

$chatResult = json_decode($chatResponse, true);
echo $chatResult['choices'][0]['message']['content'] ?? 'ChatGPT API からの応答が取得できませんでした。';
