<?php
function checkEmail(string $email): string
{
		$error = null;
    // フォーマットチェック
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
       $error = メールのフォーマットが誤っています。
    }

    // ドメイン部分を抽出
    [$localPart, $domain] = explode('@', $email);

    // DNSチェック（MXレコードがあるか確認）
    if (!checkdnsrr($domain, 'MX')) {
        $error = "メールのフォーマットが誤っています。"
    }

    return $email;
}
