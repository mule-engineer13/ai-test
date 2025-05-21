<?php
function validateName(string $name): void
{
    // 全角または半角の日本語・アルファベットを含むか
    if (!preg_match('/^[\p{L}\p{Zs}ー一-龥ぁ-ゔァ-ヴーａ-ｚＡ-Ｚ々〆〤]+$/u', $name)) {
        throw new InvalidArgumentException('名前に使用できない文字が含まれています。');
    }
}