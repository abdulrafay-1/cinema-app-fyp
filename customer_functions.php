<?php
// Helpers for Umair's customer pages (profile, favorites, contact, reviews on moviedetail).
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/Admin/admin_upload.php';

const PROFILE_UPLOAD_DIR = __DIR__ . '/uploads/profile/';

// Site details: the ONE place to change the name and contact info shown in the header, footer and contact page.
const SITE_NAME = 'CineMax Karachi';
const SITE_TEAM = 'Umair and Abdul Rafay';
const CONTACT_PHONE = '0300-0000000';
const CONTACT_EMAIL = 'info@example.com';
const CONTACT_ADDRESS = 'Karachi, Pakistan';
const CONTACT_HOURS = 'Every day, 11:00 AM to 11:00 PM';
// shown on the About page once filled in (left empty so no placeholder text appears on the site)
const SITE_UNIVERSITY = '';
const SITE_DEPARTMENT = '';
const SITE_SUPERVISOR = '';

// "0300-0000000" -> "+923000000000" for tel: links
function phone_link($phone)
{
    $digits = preg_replace('/\D/', '', $phone);
    return 'tel:' . (strpos($digits, '0') === 0 ? '+92' . substr($digits, 1) : '+' . $digits);
}

// The logged-in customer's row, or null for guests.
// A user the admin has blocked is signed out here, so they can't keep using the site.
function customer()
{
    static $loaded = false, $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $id = current_user_id();
    if (!$id) {
        return null;
    }
    $user = db_one(
        'SELECT id, name, email, phone, profile_pic, status, loyalty_points, created_at FROM user_reg_login WHERE id = ?',
        'i',
        [$id]
    );
    if (!$user || $user['status'] === 'Blocked') {
        unset($_SESSION['ux'], $_SESSION['ux_email'], $_SESSION['ux_id']);
        $user = null;
    }
    return $user;
}

// Pages that need an account: guests (and blocked users) go to sign in and come back afterwards.
function require_customer()
{
    $user = customer();
    if (!$user) {
        require_login();
    }
    return $user;
}

// Profile photo, or the user's initials in a pink circle when there is none.
function avatar_html($user, $size = 36)
{
    $style = 'width:' . (int) $size . 'px;height:' . (int) $size . 'px;';
    if (!empty($user['profile_pic']) && is_file(PROFILE_UPLOAD_DIR . $user['profile_pic'])) {
        return '<img class="cu-avatar" src="uploads/profile/' . e($user['profile_pic']) . '" alt="" style="' . $style . '">';
    }
    $initials = '';
    foreach (array_slice(preg_split('/\s+/', trim($user['name'])), 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return '<span class="cu-avatar cu-avatar-initials" style="' . $style . 'font-size:' . round($size * 0.4) . 'px;">' . e($initials ?: '?') . '</span>';
}

// 4.5 -> four full stars and a half star (Font Awesome 5)
function stars_html($rating)
{
    $html = '<span class="cu-stars" title="' . e(number_format((float) $rating, 1)) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<i class="fas fa-star"></i>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<i class="fas fa-star-half-alt"></i>';
        } else {
            $html .= '<i class="far fa-star"></i>';
        }
    }
    return $html . '</span>';
}

// flash() messages in the dark theme (render_flash() prints Bootstrap alerts)
function render_customer_flash()
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $item) {
        $type = $item['type'] === 'success' ? 'success' : ($item['type'] === 'info' ? 'info' : 'error');
        $html .= '<div class="cx-alert cx-alert-' . $type . '" role="alert">' . e($item['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}
