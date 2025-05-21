<?php
require_once 'vendor/autoload.php';
require_once __DIR__ . '/image_functions.php';
require_once __DIR__ . '/name_functions.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_SERVER['DB_HOST'];
$dbname = $_SERVER['DB_NAME'];
$user = $_SERVER['DB_USER'];
$pass = $_SERVER['DB_PASS'];
$charset = 'utf8mb4';

// Sentry 初期化
\Sentry\init([
    'dsn' => $_SERVER['SENTRY_DSN'],
    'send_default_pii' => true,
    'error_types' => E_ALL,
    'before_send' => function (\Sentry\Event $event, ?\Sentry\EventHint $hint) {
        if ($hint !== null && isset($hint->exception) && $hint->exception instanceof \Throwable) {
            $exception = $hint->exception;
            $data = [
                'raw_exception' => [
                    'type' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => explode("\n", $exception->getTraceAsString()),
                ],
            ];
            file_put_contents(__DIR__ . '/last_sentry_event.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        return $event;
    }
]);

// POSTデータ受け取り
$name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$file = $_FILES['file'] ?? null;

// Sentryスコープ設定
\Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($name, $file) {
    $scope->setContext("request", [
        "method" => $_SERVER['REQUEST_METHOD'],
        "url" => $_SERVER['REQUEST_URI'],
        "data" => [
            "name" => $name,
            "file" => $file['name'] ?? null,
        ],
    ]);
});

try {
    // バリデーション
    if (empty($name)) {
        throw new InvalidArgumentException('お名前を入力してください。');
    }

    validateName($name); // account_functions.php に定義済み
    $savedFileName = null;

    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        validateFileSize($file); // ファイルサイズチェック（500KB制限）

        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $safeFileName = uniqid() . '_' . basename($file['name']);
        move_uploaded_file($file['tmp_name'], "$uploadDir/$safeFileName");
        $savedFileName = $safeFileName;
    }

    // DB保存
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->prepare("INSERT INTO accounts (name, file) VALUES (?, ?)");
    $stmt->execute([$name, $savedFileName]);

    echo '送信内容を保存しました。ありがとうございました。';
} catch (Throwable $e) {
    \Sentry\captureException($e);
    echo $e->getMessage(); // ユーザにエラー内容を返す（開発用）
}
