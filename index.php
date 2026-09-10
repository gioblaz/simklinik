<?php
/**
 * SIMKlinik — Entry Point / Router
 * Mengarahkan request ke modul yang sesuai
 */

require_once __DIR__ . '/config.php';

// Jika sudah login, arahkan ke dashboard
if (is_logged_in()) {
    redirect(BASE_URL . 'modules/dashboard/index.php');
} else {
    redirect(BASE_URL . 'modules/auth/login.php');
}
