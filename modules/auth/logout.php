<?php
/**
 * SIMKlinik — Logout Handler
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

logout_user();
set_flash('success', 'Anda berhasil keluar dari sistem.');
redirect(BASE_URL . 'modules/auth/login.php');
