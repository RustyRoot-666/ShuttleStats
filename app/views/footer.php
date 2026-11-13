  </main><!-- /.content -->

  <footer class="app-footer">
    <span><?= e(APP_NAME) ?> — <?= e(APP_TAGLINE) ?></span>
    <span>Rekapitulasi mengikuti format template <em>Input Data Task</em> (Winning Shot / Forced Error / Unforced Error)</span>
  </footer>
</div><!-- /.main -->

<?php if (!empty($useCharts)): ?>
<script src="<?= url('assets/js/chart.umd.min.js') ?>"></script>
<?php endif; ?>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
