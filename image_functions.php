<?php
function validateFileSize(array $file, int $maxSize = 512000): void
{
    if (!isset($file['size']) || $file['size'] > $maxSize) {
        throw new RuntimeException('ファイルサイズが大きすぎます（' . ($maxSize / 1024) . 'KBを超えています）');
    }
}