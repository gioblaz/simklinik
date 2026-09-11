<?php
/**
 * SIMKlinik — Pengaturan Sistem (Redirect to Identitas Klinik)
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_module_access('settings');
header('Location: ' . BASE_URL . 'modules/settings/identitas.php');
exit;
