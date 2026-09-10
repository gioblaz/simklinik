<?php
/**
 * SIMKlinik — Footer Layout
 */
?>
    </main><!-- /.page-content -->

    <!-- App Footer -->
    <footer class="app-footer">
      <p>
        &copy; <?= date('Y') ?> <?= APP_NAME ?> &mdash; <?= INSTANSI_NAMA ?> &nbsp;|&nbsp;
        v<?= APP_VERSION ?> &nbsp;|&nbsp;
        <span style="color:var(--primary-600);">
          <i class="fas fa-heart" style="font-size:10px;"></i> Made with care
        </span>
      </p>
    </footer>

  </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- Set Base URL for JavaScript -->
<script>
  window.SIMKLINIK_BASE_URL = '<?= BASE_URL ?>';
</script>

<!-- Font Awesome (already in head, this is just a safety) -->
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- App JS -->
<script src="<?= BASE_URL ?>assets/js/app.js"></script>

<?php if (isset($active_module) && $active_module === 'bridging_monitor'): ?>
<!-- Realtime Bridging Network Monitor Modal (Hanya di Menu Monitoring PCare) -->
<?php include __DIR__ . '/bridging_monitor_modal.php'; ?>
<!-- Bridging Monitor JS -->
<script src="<?= BASE_URL ?>assets/js/bridging_monitor.js?v=<?= file_exists(BASE_PATH . 'assets/js/bridging_monitor.js') ? filemtime(BASE_PATH . 'assets/js/bridging_monitor.js') : time() ?>"></script>
<?php endif; ?>

<?php if (isset($extra_js)): ?>
  <?= $extra_js ?>
<?php endif; ?>

</body>
</html>

