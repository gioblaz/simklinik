<?php
/**
 * SIMKlinik — Pengaturan Sistem (Redirect to Identitas Klinik)
 */

require_once dirname(__DIR__, 2) . '/config.php';
header('Location: ' . BASE_URL . 'modules/settings/identitas.php');
exit;
