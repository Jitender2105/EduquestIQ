<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

const MATERIAL_MAX_UPLOAD_BYTES = 10 * 1024 * 1024; // 10 MB

function ensure_upload_dir(string $relativeDir): string
{
    $base = __DIR__;
    $dir = $base . '/' . trim($relativeDir, '/');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create upload directory.');
    }
    return $dir;
}

function validate_material_upload(array $file, string $materialType): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > MATERIAL_MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Invalid file size. Maximum is 10 MB.');
    }

    $originalName = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowed = [
        'pdf' => ['ext' => ['pdf'], 'mime' => ['application/pdf']],
        'doc' => [
            'ext' => ['doc', 'docx'],
            'mime' => [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/octet-stream',
            ],
        ],
        'ppt' => [
            'ext' => ['ppt', 'pptx'],
            'mime' => [
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/octet-stream',
            ],
        ],
    ];

    if (!isset($allowed[$materialType]) || !in_array($ext, $allowed[$materialType]['ext'], true)) {
        throw new RuntimeException('Invalid file extension for selected material type.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $tmp);
            if (is_string($detected)) {
                $mime = $detected;
            }
            finfo_close($finfo);
        }
    }

    if ($mime !== '' && !in_array($mime, $allowed[$materialType]['mime'], true)) {
        throw new RuntimeException('Invalid file type uploaded.');
    }

    return [
        'ext' => $ext,
        'tmp_name' => $tmp,
        'original_name' => $originalName,
    ];
}

function store_material_upload(array $validatedFile): string
{
    $dir = ensure_upload_dir('uploads/materials');
    $basename = bin2hex(random_bytes(12)) . '.' . $validatedFile['ext'];
    $target = $dir . '/' . $basename;

    if (!move_uploaded_file($validatedFile['tmp_name'], $target)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return 'uploads/materials/' . $basename;
}

