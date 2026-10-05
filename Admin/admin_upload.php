<?php
// Image upload shared by Umair's pages: admin snacks / coming soon (Admin/upload-images/,
// which the customer pages read) and customer profile photos (uploads/profile/).

const ADMIN_UPLOAD_DIR = __DIR__ . '/upload-images/';
const ADMIN_UPLOAD_MAX = 2 * 1024 * 1024;

// Returns null when no file was chosen, false on error (message in $error), or the new file name.
function admin_upload_image($field, &$error, $dir = ADMIN_UPLOAD_DIR)
{
    $error = '';
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'The image could not be uploaded. Please try again.';
        return false;
    }
    if ($file['size'] > ADMIN_UPLOAD_MAX) {
        $error = 'The image is too big (max 2 MB).';
        return false;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $ext = $ext === 'jpeg' ? 'jpg' : $ext;
    // the extension AND the real file content must be an image
    $info = @getimagesize($file['tmp_name']);
    $allowed = ['jpg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP];
    if (!isset($allowed[$ext]) || !$info || $info[2] !== $allowed[$ext]) {
        $error = 'Please upload a JPG, PNG or WEBP image.';
        return false;
    }
    // random name: no clashes with other files and the user can't choose the file name
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    // git does not keep empty folders, so create the upload folder if a fresh clone is missing it
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        $error = 'The upload folder is missing and could not be created.';
        return false;
    }
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        $error = 'The image could not be saved.';
        return false;
    }
    return $name;
}

// Deletes an image only if this helper created it (random hex name), so shared posters are never removed.
function admin_delete_upload($name, $dir = ADMIN_UPLOAD_DIR)
{
    if ($name && preg_match('/^[0-9a-f]{16}\.(jpg|png|webp)$/', $name) && is_file($dir . $name)) {
        @unlink($dir . $name);
    }
}
