<?php

namespace App\Services\Mail;

class AttachmentPolicy
{
    public const MAX_ATTACHMENT_SIZE = 10 * 1024 * 1024; // 10 MB

    public const MAX_TOTAL_SIZE = 20 * 1024 * 1024; // 20 MB

    public const MAX_ATTACHMENTS = 10;

    private const ALLOWED_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'txt',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'zip',
    ];

    private const BLOCKED_EXTENSIONS = [
        'exe',
        'msi',
        'bat',
        'cmd',
        'ps1',
        'sh',
        'php',
        'phtml',
        'phar',
        'js',
        'jar',
        'apk',
        'dmg',
        'iso',
        'scr',
        'com',
        'vbs',
    ];

    private const ALLOWED_MIME_TYPES = [
        'application/pdf',

        'image/jpeg',
        'image/png',
        'image/webp',

        'text/plain',

        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

        'application/zip',
        'application/x-zip-compressed',
    ];

    public function evaluate(array $attachments): array
    {
        $batchResult = $this->evaluateBatchLimits($attachments);

        if (! $batchResult['allowed']) {
            return $batchResult;
        }

        foreach ($attachments as $attachment) {
            $result = $this->evaluateAttachment($attachment);

            if (! $result['allowed']) {
                return $result;
            }
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }

    public function evaluateAttachment(array $attachment): array
    {
        $filename = (string) ($attachment['filename'] ?? '');

        $extension = strtolower(
            pathinfo($filename, PATHINFO_EXTENSION)
        );

        $contentType = strtolower(
            trim((string) ($attachment['content_type'] ?? ''))
        );

        $size = (int) ($attachment['size'] ?? 0);

        if ($size <= 0) {
            return [
                'allowed' => false,
                'reason' => 'Attachment size is missing or invalid.',
            ];
        }

        if ($size > self::MAX_ATTACHMENT_SIZE) {
            return [
                'allowed' => false,
                'reason' => 'Attachment exceeds 10 MB.',
            ];
        }

        if ($extension === '') {
            return [
                'allowed' => false,
                'reason' => 'Attachment has no file extension.',
            ];
        }

        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            return [
                'allowed' => false,
                'reason' => 'Executable or script attachments are blocked.',
            ];
        }

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return [
                'allowed' => false,
                'reason' => 'File extension is not allowed.',
            ];
        }

        if (
            $contentType === ''
            || ! in_array($contentType, self::ALLOWED_MIME_TYPES, true)
        ) {
            return [
                'allowed' => false,
                'reason' => 'File MIME type is not allowed.',
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }

    public function evaluateBatchLimits(array $attachments): array
    {
        if (count($attachments) > self::MAX_ATTACHMENTS) {
            return [
                'allowed' => false,
                'reason' => 'Too many attachments. Maximum is 10.',
            ];
        }

        $totalSize = array_sum(
            array_map(
                fn(array $attachment): int =>
                (int) ($attachment['size'] ?? 0),
                $attachments
            )
        );

        if ($totalSize > self::MAX_TOTAL_SIZE) {
            return [
                'allowed' => false,
                'reason' => 'Total attachment size exceeds 20 MB.',
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }
}
