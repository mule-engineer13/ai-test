<?php
function checkEmail(string $email)
{
		$error = null;
    // フォーマットチェック
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    	$error = $content."を確認してください";
		}

    // ドメイン部分を抽出
    [$localPart, $domain] = explode('@', $email);

    // DNSチェック（MXレコードがあるか確認）
    if (!checkdnsrr($domain, 'MX')) {
      $error = $content."を確認してください";
    }

		$returnArray = [$email,$error];

    return $returnArray;
}
