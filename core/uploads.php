<?php
/**
 * Safe image uploads for products / categories / promotions.
 *
 * - MIME detected from file contents with finfo (the client-supplied type and
 *   extension are ignored), whitelist: JPEG, PNG, WebP. SVG/GIF/anything else rejected.
 * - getimagesize() must parse it as a real raster image.
 * - Max 2 MB.
 * - Random file name + extension derived from the detected MIME; no part of the
 *   user's file name or any user path is used.
 */

const UPLOAD_MAX_BYTES = 2 * 1024 * 1024;
const UPLOAD_ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

class UploadException extends RuntimeException
{
}

/**
 * True when the client actually attached a file under $field.
 */
function upload_present(string $field): bool
{
    return isset($_FILES[$field]) && is_array($_FILES[$field])
        && isset($_FILES[$field]['error']) && !is_array($_FILES[$field]['error'])
        && (int) $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE;
}

/**
 * Validate and store an uploaded image. Returns the new bare file name.
 *
 * @throws UploadException with a user-safe message.
 */
function store_uploaded_image(string $field, string $destDir): string
{
    if (!upload_present($field)) {
        throw new UploadException('No image was uploaded.');
    }
    $file = $_FILES[$field];
    $err = (int) $file['error'];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new UploadException('Image is too large (max 2 MB).');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new UploadException('Image upload failed. Please try again.');
    }
    $size = (int) filesize($file['tmp_name']);
    if ($size <= 0 || $size > UPLOAD_MAX_BYTES) {
        throw new UploadException('Image is too large (max 2 MB).');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    if (!isset(UPLOAD_ALLOWED_MIME[$mime])) {
        throw new UploadException('Invalid image format. Allowed: JPG, PNG, WebP.');
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || ($info['mime'] ?? '') !== $mime) {
        throw new UploadException('The uploaded file is not a valid image.');
    }

    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        throw new UploadException('Upload folder is not writable.');
    }
    $dir = realpath($destDir);
    if ($dir === false || !is_writable($dir)) {
        throw new UploadException('Upload folder is not writable.');
    }

    $name = bin2hex(random_bytes(16)) . '.' . UPLOAD_ALLOWED_MIME[$mime];
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new UploadException('Failed to save the image. Please check folder permissions.');
    }
    @chmod($target, 0644);
    return $name;
}

/**
 * Delete a previously stored upload. Accepts "uploads/<file>" or "<file>";
 * refuses anything that resolves outside $uploadsDir (path traversal safe).
 */
function delete_stored_upload(?string $stored, string $uploadsDir): void
{
    if ($stored === null || $stored === '' || preg_match('#^https?://#i', $stored)) {
        return;
    }
    $base = realpath($uploadsDir);
    if ($base === false) {
        return;
    }
    $name = basename(str_replace('\\', '/', $stored));
    if ($name === '' || $name === '.' || $name === '..') {
        return;
    }
    $path = realpath($base . DIRECTORY_SEPARATOR . $name);
    if ($path !== false && strpos($path, $base . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
        @unlink($path);
    }
}

/**
 * How many rows (products, categories, promotions) still reference this upload.
 * Used so that deleting/replacing one record never removes an image another record shows.
 */
function upload_reference_count(PDO $conn, ?string $stored): int
{
    if ($stored === null || $stored === '') {
        return 0;
    }
    $name = basename(str_replace('\\', '/', $stored));
    $count = 0;
    $queries = [
        "SELECT COUNT(*) FROM products WHERE imagelink = ? OR imagelink = ?",
        "SELECT COUNT(*) FROM categories WHERE image = ? OR image = ?",
        "SELECT COUNT(*) FROM promotions WHERE image_url = ? OR image_url = ?",
    ];
    foreach ($queries as $sql) {
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$name, 'uploads/' . $name]);
            $count += (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            // table may not exist in very old schemas
        }
    }
    return $count;
}

/**
 * Delete the file only if no remaining row references it. Call AFTER the DB change.
 */
function delete_upload_if_unused(PDO $conn, ?string $stored, string $uploadsDir): void
{
    if ($stored !== null && $stored !== '' && upload_reference_count($conn, $stored) === 0) {
        delete_stored_upload($stored, $uploadsDir);
    }
}
