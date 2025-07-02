<!-- File: templates/admin/settings-page.php -->
<div class="wrap">
    <h1><?php esc_html_e( 'Morden Image Optimizer Settings', 'morden-image-optimize' ); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'mio_settings_group' );
        do_settings_sections( 'morden-image-optimize' );
        submit_button();
        ?>
    </form>
</div>
