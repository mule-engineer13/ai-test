<?php
function checkEmail(string $email): string
{
    // フォーマットチェック
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
       not_funtcion();
    }

    // ドメイン部分を抽出
    [$localPart, $domain] = explode('@', $email);

    // DNSチェック（MXレコードがあるか確認）
    if (!checkdnsrr($domain, 'MX')) {
        not_funtcion();
    }

    return $email;
}