<?php
require_once 'vendor/autoload.php'; // Composer経由で導入した場合
require_once __DIR__ . '/functions.php';


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// DB接続情報（Xserverで発行された情報に変更）
$host = $_SERVER['DB_HOST'];
$dbname =  $_SERVER['DB_NAME'];
$user =  $_SERVER['DB_USER'];
$pass =  $_SERVER['DB_PASS'];
$charset = 'utf8mb4';

$request = $_POST;

$path = __DIR__ .'/functions.php';
$outputPath = __DIR__ . '/output.txt';

// ファイル読み込み
$code = file_get_contents($path);

// エスケープ（< や & を安全に）
$escaped = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

// 書き出し
file_put_contents($outputPath, $escaped);

\Sentry\init([
  'dsn' => $_SERVER['SENTRY_DSN'],
  // Add request headers, cookies and IP address,
  // see https://docs.sentry.io/platforms/php/data-management/data-collected/ for more info
  'send_default_pii' => true,
	'error_types' => E_ALL, // 全エラーを対象にする
	'before_send' => function (\Sentry\Event $event, ?\Sentry\EventHint $hint) {
			file_put_contents(
					__DIR__ . '/debug_event_dump.txt',
					print_r($event, true)
			);

			$data = [
					'event_id'        => (string)$event->getId(),
					'level'           => (string)$event->getLevel(),
					'logger'          => $event->getLogger(),
					'transaction'     => $event->getTransaction(),
					'server_name'     => $event->getServerName(),
					'release'         => $event->getRelease(),
					'message'         => $event->getMessage(),
					'message_formatted' => $event->getMessageFormatted(),
					'message_params'  => $event->getMessageParams(),
					'environment'     => $event->getEnvironment(),
					'modules'         => $event->getModules(),
					'request'         => $event->getRequest(),
					'tags'            => $event->getTags(),
					'contexts'        => $event->getContexts(),
					'extra'           => $event->getExtra(),
					'fingerprint'     => $event->getFingerprint(),
					'sdk_metadata'    => $event->getSdkMetadata(),
					'sdk_identifier'  => $event->getSdkIdentifier(),
					'sdk_version'     => $event->getSdkVersion(),
					'timestamp'       => $event->getTimestamp(),
					'start_timestamp' => $event->getStartTimestamp(),
					'trace_id'        => $event->getTraceId(),
					'type'            => (string)$event->getType(), // EventTypeオブジェクト
			];

			// Breadcrumbs を展開
			$data['breadcrumbs'] = array_map(function ($breadcrumb) {
					return [
							'type'      => $breadcrumb->getType(),
							'level'     => (string)$breadcrumb->getLevel(),
							'category'  => $breadcrumb->getCategory(),
							'message'   => $breadcrumb->getMessage(),
							'data'      => $breadcrumb->getData(),
							'timestamp' => $breadcrumb->getTimestamp()->format('c'),
					];
			}, $event->getBreadcrumbs());

			// ユーザー情報
			// $user = $event->getUser();
			// if ($user) {
			// 		$data['user'] = $user->toArray(); // UserDataBagはtoArray()実装
			// }

			// 例外（ExceptionDataBag[]）がある場合
			// $data['exceptions'] = array_map(function ($ex) {
			// 		return $ex instanceof \Sentry\ExceptionDataBag ? $ex->toArray() : null;
			// }, $event->getExceptions());

			// スタックトレース（Stacktraceオブジェクト）
			// $stacktrace = $event->getStacktrace();
			// if ($stacktrace) {
			// 		$data['stacktrace'] = $stacktrace->toArray(); // Stacktraceも toArray() 実装
			// }

			// // OS / Runtime Context
			// $os = $event->getOsContext();
			// if ($os) {
			// 		$data['os_context'] = $os->toArray(); // OsContext implements ContextInterface
			// }

			// $runtime = $event->getRuntimeContext();
			// if ($runtime) {
			// 		$data['runtime_context'] = $runtime->toArray(); // RuntimeContext implements ContextInterface
			// }

			// // Spans（トランザクション）
			// $data['spans'] = array_map(function ($span) {
			// 		return method_exists($span, 'toArray') ? $span->toArray() : null;
			// }, $event->getSpans());

			// // Profile
			// $profile = $event->getProfile();
			// if ($profile && method_exists($profile, 'toArray')) {
			// 		$data['profile'] = $profile->toArray();
			// }

			// Hint情報（例外など）
			if ($hint !== null && isset($hint->exception) && $hint->exception instanceof \Throwable) {
					$exception = $hint->exception;
					$data['raw_exception'] = [
							'type'    => get_class($exception),
							'message' => $exception->getMessage(),
							'file'    => $exception->getFile(),
							'line'    => $exception->getLine(),
							'trace'   => explode("\n", $exception->getTraceAsString()),
					];
			}

			// 保存
			file_put_contents(
					__DIR__ . '/last_sentry_event.json',
					json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
			);

			return $event;
	}

]);

\Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
    $scope->setContext("request", [
        "method" => $_SERVER['REQUEST_METHOD'],
        "url" => $_SERVER['REQUEST_URI'],
        "data" => $_POST, // 👈 ここでPOSTデータを追加
    ]);
});

// フォーム値をサニタイズ
$name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars(trim($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars(trim($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8');

// バリデーション
if (empty($name) || empty($email) || empty($message)) {
    die('すべての項目を入力してください。');
}
// if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
//     die('有効なメールアドレスを入力してください。');
// }

// DB接続
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
		// echo $data;
		$email = checkEmail($email)[0];

    // データ登録
    $stmt = $pdo->prepare("INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $message]);

		// ChatGPT API キー
		$apiKey = $_SERVER['AI_key'];


    echo '送信内容を保存しました。ありがとうございました。';
} catch (Throwable $e) {
		$eventId = (string)Sentry\captureException($e);

		header('Content-Type: application/json; charset=utf-8');
		// 末尾に追加（最後の echo json_encode(...) の後でもOK）
		if (file_exists(__DIR__ . '/last_sentry_event.json')) {
				$eventDataJson = file_get_contents(__DIR__ . '/last_sentry_event.json');
				$eventData = json_decode($eventDataJson, true);

				echo json_encode([
						// 'status' => 'error',
						// 'sentry_event_id' => $eventId,
						'event_summary' => $eventData, // last_sentry_event.jsonの中身
						// 'chatgpt_analysis' => $chatReply,
				], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		}


		// $sentryToken = $_SERVER['SENTRY_AUTH_TOKEN'];
		// $orgSlug = $_SERVER['SENTRY_ORG_SLUG'];
		// $projectSlug = $_SERVER['SENTRY_PROJECT_SLUG'];

		// $sentryApiUrl = "https://sentry.io/api/0/projects/{$orgSlug}/{$projectSlug}/events/{$eventId}/";

		// $maxAttempts = 50;
		// $attempt = 0;
		// $sentryEventData = null;

		// do {
		// 		$ch = curl_init();
		// 		curl_setopt_array($ch, [
		// 				CURLOPT_URL => $sentryApiUrl,
		// 				CURLOPT_RETURNTRANSFER => true,
		// 				CURLOPT_HTTPHEADER => [
		// 						'Authorization: Bearer ' . $sentryToken,
		// 						'Content-Type: application/json',
		// 				]
		// 		]);

		// 		$sentryResponse = curl_exec($ch);
		// 		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		// 		curl_close($ch);

		// 		if ($httpCode === 200) {
		// 				$sentryEventData = json_decode($sentryResponse, true);
		// 				break;
		// 		}

		// 		// 404 or 403など → 次のリトライまで少し待機
		// 		usleep(500000); // 0.5秒
		// 		$attempt++;
		// } while ($attempt < $maxAttempts);

		// if (!$sentryEventData) {
		// 		$sentryEventData = [
		// 				'error' => "Sentry API呼び出し失敗(HTTP $httpCode)",
		// 				'raw_response' => $sentryResponse,
		// 				'event_id' => $eventId,
		// 		];
		// }

		// // header('Content-Type: application/json; charset=utf-8');
		// // echo json_encode($sentryEventData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		// // return false;

    // $filePath = ltrim(str_replace(__DIR__, '', $e->getFile()), '/'); // 例: index.php
    // $lineCount = null;

    // // GitHubからファイル内容取得
    // $owner = 'mule-engineer13';
    // $repo = 'ai-test';
    // $branch = 'main';
		// $content = null;

    // // 1. ファイル一覧（tree）を取得
    // $treeUrl = "https://api.github.com/repos/$owner/$repo/git/trees/$branch?recursive=1";

    // $ch = curl_init();
    // curl_setopt_array($ch, [
    //     CURLOPT_URL => $treeUrl,
    //     CURLOPT_RETURNTRANSFER => true,
    //     CURLOPT_HTTPHEADER => [
    //         'User-Agent: PHP',
    //         'Accept: application/vnd.github.v3+json'
    //     ]
    // ]);
    // $treeResponse = curl_exec($ch);
    // curl_close($ch);

    // $treeData = json_decode($treeResponse, true);

    // // 2. 該当ファイルのblob SHAを探す
    // $blobSha = null;
    // foreach ($treeData['tree'] as $item) {
    //     if ($item['type'] === 'blob' && $item['path'] === $filePath) {
    //         $blobSha = $item['sha'];
    //         break;
    //     }
    // }

    // // 3. SHAからblob（中身）を取得して行数を数える
    // if ($blobSha) {
    //     $blobUrl = "https://api.github.com/repos/$owner/$repo/git/blobs/$blobSha";

    //     $ch = curl_init();
    //     curl_setopt_array($ch, [
    //         CURLOPT_URL => $blobUrl,
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_HTTPHEADER => [
    //             'User-Agent: PHP',
    //             'Accept: application/vnd.github.v3+json'
    //         ]
    //     ]);
    //     $blobResponse = curl_exec($ch);
    //     curl_close($ch);

    //     $blobData = json_decode($blobResponse, true);

    //     if (isset($blobData['content'])) {
    //         $content = base64_decode($blobData['content']);
    //         $lineCount = substr_count($content, "\n") + 1;
    //     }
    // }

		// // Stacktrace からファイル・行番号・スニペット取得
		// $frames = $sentryEventData['entries'][0]['data']['values'][0]['stacktrace']['frames'];
		// $lastFrame = end($frames); // 最後が発生地点（新しい方）

		// $filePath = $lastFrame['filename'] ?? '不明';
		// $errorLine = $lastFrame['lineNo'] ?? '不明';
		// $codeSnippet = $lastFrame['context'] ?? [];
		// $errorMessage = $sentryEventData['entries'][0]['data']['values'][0]['value'] ?? '不明なエラー';

		// // コードスニペット整形
		// $formattedCode = implode("\n", array_map(
		// 		fn($line) => $line[0] . ": " . $line[1],
		// 		$codeSnippet
		// ));

		// // ChatGPTプロンプト生成
		// $chatPrompt = <<<EOD
		// 次のPHPコードの {$errorLine} 行目で「{$errorMessage}」というエラーが発生しています。原因と改善案を教えてください。

		// 対象ファイル: {$filePath}
		// 該当コードスニペット:
		// {$content}
		// EOD;

		// // OpenAI API呼び出し
		// $openaiApiKey = $_SERVER['AI_key'];

		// $data = [
		// 		'model' => 'gpt-3.5-turbo',
		// 		'messages' => [
		// 				['role' => 'user', 'content' => $chatPrompt]
		// 		]
		// ];

		// $ch = curl_init('https://api.openai.com/v1/chat/completions');
		// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// curl_setopt($ch, CURLOPT_HTTPHEADER, [
		// 		'Content-Type: application/json',
		// 		'Authorization: ' . 'Bearer ' . $openaiApiKey
		// ]);
		// curl_setopt($ch, CURLOPT_POST, true);
		// curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

		// $chatResponse = curl_exec($ch);
		// curl_close($ch);

		// $chatResult = json_decode($chatResponse, true);

		// // 出力整形
		// if (isset($chatResult['choices'][0]['message']['content'])) {
		// 		$chatReply = $chatResult['choices'][0]['message']['content'];
		// } else {
		// 		$chatReply = "ChatGPT APIから有効な応答が得られませんでした。\nレスポンス内容: " . $chatResponse;
		// }

		// // 出力
		// header('Content-Type: application/json; charset=utf-8');
		// // 末尾に追加（最後の echo json_encode(...) の後でもOK）
		// if (file_exists(__DIR__ . '/last_sentry_event.json')) {
		// 		$eventDataJson = file_get_contents(__DIR__ . '/last_sentry_event.json');
		// 		$eventData = json_decode($eventDataJson, true);

		// 		echo json_encode([
		// 				'status' => 'error',
		// 				'sentry_event_id' => $eventId,
		// 				'event_summary' => $eventData, // last_sentry_event.jsonの中身
		// 				'chatgpt_analysis' => $chatReply,
		// 		], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		// }

		// // 結果表示
		// $result = json_decode($response, true);
		// if (isset($result['choices'][0]['message']['content'])) {
		// 		echo '<br>ChatGPTの返答: ' . htmlspecialchars($result['choices'][0]['message']['content'], ENT_QUOTES, 'UTF-8');
		// } else {
		// 		echo '<br>ChatGPT API エラー: ' . htmlspecialchars($response, ENT_QUOTES, 'UTF-8');
		// }

    // クライアントに返す
    // echo json_encode([
    //     "status" => "error",
    //     "message" => "サーバーエラー: " . $e->getMessage(),
    //     "sentry_event_id" => $eventId ? (string)$eventId : null,
    //     "sentry_message" => $e->getMessage(),
    //     "sentry_file" => $filePath,
    //     "sentry_line" => $e->getLine(),
    //     "file_line_count" => $lineCount,
		// 		"content" => $content,
    // ]);
    // exit;
}
