<?php
// SweetAlert2 popups for Umair's admin pages (instead of the browser's plain alert()).
// Same CDN and version Rafay uses on My Bookings.

const SWAL_CDN = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js';

// <script> that shows a popup and, if $redirect is given, goes there after OK.
// json_encode with the HEX flags makes the text safe inside a <script> tag.
function swal_script($icon, $title, $redirect = null)
{
    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
    $js = 'Swal.fire({icon: ' . json_encode($icon, $flags) . ', title: ' . json_encode($title, $flags) . ', confirmButtonColor: "#ff5860"})';
    if ($redirect !== null) {
        $js .= '.then(function () { window.location.assign(' . json_encode($redirect, $flags) . '); })';
    }
    return '<script>' . $js . ';</script>';
}

// The *_del.php handlers print no page of their own, so they need a tiny page that loads SweetAlert first.
function swal_page($icon, $title, $redirect)
{
    return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>CineMax Karachi Admin</title>'
        . '<script src="' . SWAL_CDN . '"></script></head>'
        . '<body style="background:#22252a;">' . swal_script($icon, $title, $redirect) . '</body></html>';
}
