<!-- File: templates/admin/bulk-optimizer.php -->
<div class="wrap">
    <h1><?php esc_html_e( 'Bulk Optimize Images', 'morden-image-optimize' ); ?></h1>

    <div class="mio-bulk-optimizer-container">
        <button id="mio-start-bulk-optimization" class="button button-primary">
            <?php esc_html_e( 'Start Optimization', 'morden-image-optimize' ); ?>
        </button>

        <div class="mio-progress-container" style="display: none;">
            <div class="mio-progress-bar" style="width: 0%;">0%</div>
            <p>Processed: <span id="mio-processed-count">0</span> / <span id="mio-total-count">0</span></p>
        </div>
    </div>
</div>
