<?php
// DB接続情報（Xserverで発行された情報に変更）
$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$charset = 'utf8mb4';

require_once 'vendor/autoload.php'; // Composer経由で導入した場合

\Sentry\init([
  'dsn' => getenv('SENTRY_DSN'),
  // Add request headers, cookies and IP address,
  // see https://docs.sentry.io/platforms/php/data-management/data-collected/ for more info
  'send_default_pii' => true,
	'error_types' => E_ALL, // 全エラーを対象にする
]);
// フォーム値をサニタイズ
$name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars(trim($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars(trim($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8');

// バリデーション
if (empty($name) || empty($email) || empty($message)) {
    die('すべての項目を入力してください。');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('有効なメールアドレスを入力してください。');
}

// DB接続
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
		// echo $data;
		not_a_function();

    // データ登録
    $stmt = $pdo->prepare("INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $message]);

		// ChatGPT API キー
		// $apiKey = getenv('AI_key')

		// リクエスト用データ
		// $data = [
		// 		'model' => 'gpt-3.5-turbo',
		// 		'messages' => [
		// 				['role' => 'user', 'content' => 'こんにちは']
		// 		]
		// ];

		// $ch = curl_init('https://api.openai.com/v1/chat/completions');
		// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// curl_setopt($ch, CURLOPT_HTTPHEADER, [
		// 		'Content-Type: application/json',
		// 		'Authorization: Bearer ' . $apiKey
		// ]);
		// curl_setopt($ch, CURLOPT_POST, true);
		// curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

		// // APIリクエスト実行
		// $response = curl_exec($ch);
		// curl_close($ch);

		// // 結果表示
		// $result = json_decode($response, true);
		// if (isset($result['choices'][0]['message']['content'])) {
		// 		echo '<br>ChatGPTの返答: ' . htmlspecialchars($result['choices'][0]['message']['content'], ENT_QUOTES, 'UTF-8');
		// } else {
		// 		echo '<br>ChatGPT API エラー: ' . htmlspecialchars($response, ENT_QUOTES, 'UTF-8');
		// }


    echo '送信内容を保存しました。ありがとうございました。';
} catch (Throwable $e) {
    $eventId = Sentry\captureException($e);

    $filePath = ltrim(str_replace(__DIR__, '', $e->getFile()), '/'); // 例: index.php
    $lineCount = null;

    // GitHubからファイル内容取得
    $owner = 'mule-engineer13';
    $repo = 'ai-test';
    $branch = 'main';
		$content = null;

    // 1. ファイル一覧（tree）を取得
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

    // 2. 該当ファイルのblob SHAを探す
    $blobSha = null;
    foreach ($treeData['tree'] as $item) {
        if ($item['type'] === 'blob' && $item['path'] === $filePath) {
            $blobSha = $item['sha'];
            break;
        }
    }

    // 3. SHAからblob（中身）を取得して行数を数える
    if ($blobSha) {
        $blobUrl = "https://api.github.com/repos/$owner/$repo/git/blobs/$blobSha";

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
            $content = base64_decode($blobData['content']);
            $lineCount = substr_count($content, "\n") + 1;
        }
    }

    // クライアントに返す
    echo json_encode([
        "status" => "error",
        "message" => "サーバーエラー: " . $e->getMessage(),
        "sentry_event_id" => $eventId,
        "sentry_message" => $e->getMessage(),
        "sentry_file" => $filePath,
        "sentry_line" => $e->getLine(),
        "file_line_count" => $lineCount,
				"content" => $content,
    ]);
    exit;
}