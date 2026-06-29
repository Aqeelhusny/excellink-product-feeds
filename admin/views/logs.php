<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$logs   = ELF_Logger::get_logs();
$counts = ELF_Logger::get_log_counts();

if ( isset( $_GET['elf_logs_nonce'] ) ) {
    check_admin_referer( 'elf_logs_filter', 'elf_logs_nonce' );
}

$filter_level   = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : '';
$filter_context = isset( $_GET['context'] ) ? sanitize_text_field( wp_unslash( $_GET['context'] ) ) : '';

if ( $filter_level ) {
    $logs = ELF_Logger::get_logs( $filter_level, $filter_context );
} elseif ( $filter_context ) {
    $logs = ELF_Logger::get_logs( '', $filter_context );
}
?>
<div class="wrap elf-wrap">
    <h1><?php esc_html_e( 'Plugin Logs', 'excellink-product-feeds' ); ?></h1>

    <div class="elf-card">
        <div class="elf-log-filters">
            <h2><?php esc_html_e( 'Filter Logs', 'excellink-product-feeds' ); ?></h2>
            
            <form method="get" action="">
                <input type="hidden" name="page" value="elf-logs">
                <?php wp_nonce_field( 'elf_logs_filter', 'elf_logs_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="elf-filter-level"><?php esc_html_e( 'Log Level', 'excellink-product-feeds' ); ?></label>
                        </th>
                        <td>
                            <select name="level" id="elf-filter-level">
                                <option value=""><?php esc_html_e( 'All Levels', 'excellink-product-feeds' ); ?></option>
                                <option value="error" <?php selected( $filter_level, 'error' ); ?>><?php esc_html_e( 'Errors', 'excellink-product-feeds' ); ?></option>
                                <option value="warning" <?php selected( $filter_level, 'warning' ); ?>><?php esc_html_e( 'Warnings', 'excellink-product-feeds' ); ?></option>
                                <option value="info" <?php selected( $filter_level, 'info' ); ?>><?php esc_html_e( 'Info', 'excellink-product-feeds' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="elf-filter-context"><?php esc_html_e( 'Context', 'excellink-product-feeds' ); ?></label>
                        </th>
                        <td>
                            <select name="context" id="elf-filter-context">
                                <option value=""><?php esc_html_e( 'All Contexts', 'excellink-product-feeds' ); ?></option>
                                <option value="feed_generation" <?php selected( $filter_context, 'feed_generation' ); ?>><?php esc_html_e( 'Feed Generation', 'excellink-product-feeds' ); ?></option>
                                <option value="sitemap_generation" <?php selected( $filter_context, 'sitemap_generation' ); ?>><?php esc_html_e( 'Sitemap Generation', 'excellink-product-feeds' ); ?></option>
                                <option value="cron_run" <?php selected( $filter_context, 'cron_run' ); ?>><?php esc_html_e( 'Cron Jobs', 'excellink-product-feeds' ); ?></option>
                                <option value="ajax_regen" <?php selected( $filter_context, 'ajax_regen' ); ?>><?php esc_html_e( 'AJAX Requests', 'excellink-product-feeds' ); ?></option>
                                <option value="taxonomy_fetch" <?php selected( $filter_context, 'taxonomy_fetch' ); ?>><?php esc_html_e( 'Taxonomy Fetch', 'excellink-product-feeds' ); ?></option>
                                <option value="feed_validation" <?php selected( $filter_context, 'feed_validation' ); ?>><?php esc_html_e( 'Feed Validation', 'excellink-product-feeds' ); ?></option>
                                <option value="settings_import" <?php selected( $filter_context, 'settings_import' ); ?>><?php esc_html_e( 'Settings Import', 'excellink-product-feeds' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Filter', 'excellink-product-feeds' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=elf-logs' ) ); ?>" class="button">
                        <?php esc_html_e( 'Reset Filters', 'excellink-product-feeds' ); ?>
                    </a>
                </p>
            </form>
        </div>

        <hr>

        <div class="elf-log-stats">
            <h2><?php esc_html_e( 'Log Statistics', 'excellink-product-feeds' ); ?></h2>
            <table class="widefat">
                <tr>
                    <th><?php esc_html_e( 'Errors', 'excellink-product-feeds' ); ?></th>
                    <td class="<?php echo $counts['error'] > 0 ? 'elf-log-error' : 'elf-log-ok'; ?>">
                        <?php echo absint( $counts['error'] ); ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Warnings', 'excellink-product-feeds' ); ?></th>
                    <td class="<?php echo $counts['warning'] > 0 ? 'elf-log-warning' : 'elf-log-ok'; ?>">
                        <?php echo absint( $counts['warning'] ); ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Info', 'excellink-product-feeds' ); ?></th>
                    <td><?php echo absint( $counts['info'] ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Total Entries', 'excellink-product-feeds' ); ?></th>
                    <td><?php echo absint( array_sum( $counts ) ); ?></td>
                </tr>
            </table>
        </div>

        <hr>

        <div class="elf-log-actions">
            <h2><?php esc_html_e( 'Log Actions', 'excellink-product-feeds' ); ?></h2>
            <button type="button" class="button button-secondary" id="elf-clear-logs-btn">
                <?php esc_html_e( 'Clear All Logs', 'excellink-product-feeds' ); ?>
            </button>
            <span id="elf-clear-logs-status" class="elf-inline-status"></span>
        </div>

        <hr>

        <div class="elf-log-entries">
            <h2><?php esc_html_e( 'Log Entries', 'excellink-product-feeds' ); ?></h2>
            
            <?php if ( empty( $logs ) ) : ?>
                <p><?php esc_html_e( 'No log entries found.', 'excellink-product-feeds' ); ?></p>
            <?php else : ?>
                <table class="widefat elf-log-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Timestamp', 'excellink-product-feeds' ); ?></th>
                            <th><?php esc_html_e( 'Level', 'excellink-product-feeds' ); ?></th>
                            <th><?php esc_html_e( 'Context', 'excellink-product-feeds' ); ?></th>
                            <th><?php esc_html_e( 'Message', 'excellink-product-feeds' ); ?></th>
                            <th><?php esc_html_e( 'Data', 'excellink-product-feeds' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs as $entry ) : ?>
                            <tr class="elf-log-level-<?php echo esc_attr( $entry['level'] ); ?>">
                                <td><?php echo esc_html( $entry['timestamp'] ); ?></td>
                                <td>
                                    <span class="elf-badge elf-badge--<?php echo $entry['level'] === 'error' ? 'error' : ( $entry['level'] === 'warning' ? 'warn' : 'ok' ); ?>">
                                        <?php echo esc_html( strtoupper( $entry['level'] ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $entry['context'] ); ?></td>
                                <td><?php echo esc_html( $entry['message'] ); ?></td>
                                <td>
                                    <?php if ( ! empty( $entry['data'] ) ) : ?>
                                        <code><?php echo esc_html( json_encode( $entry['data'], JSON_PRETTY_PRINT ) ); ?></code>
                                    <?php else : ?>
                                        <em><?php esc_html_e( 'No data', 'excellink-product-feeds' ); ?></em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.elf-log-error { color: #d63638; font-weight: bold; }
.elf-log-warning { color: #dba617; font-weight: bold; }
.elf-log-ok { color: #00a32a; }
.elf-log-level-error { background: #fff5f5; }
.elf-log-level-warning { background: #fffbf0; }
.elf-log-level-info { background: #f0f7ff; }
.elf-log-table code { 
    display: block; 
    max-height: 100px; 
    overflow-y: auto; 
    font-size: 11px; 
    background: #f6f7f7; 
    padding: 4px; 
    border-radius: 3px;
}
.elf-log-filters, .elf-log-stats, .elf-log-actions, .elf-log-entries {
    margin: 20px 0;
}
.elf-log-filters h2, .elf-log-stats h2, .elf-log-actions h2, .elf-log-entries h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #1d2327;
}
.elf-badge--error {
    background: #d63638;
    color: #fff;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#elf-clear-logs-btn').on('click', function() {
        if (!confirm('<?php esc_html_e( 'Are you sure you want to clear all logs? This cannot be undone.', 'excellink-product-feeds' ); ?>')) {
            return;
        }

        var $btn = $(this);
        var $status = $('#elf-clear-logs-status');
        
        $btn.prop('disabled', true);
        $status.text('<?php esc_html_e( 'Clearing…', 'excellink-product-feeds' ); ?>').show();

        $.post(elfData.ajax_url, {
            action: 'elf_clear_logs',
            nonce: elfData.nonce,
        })
        .done(function(res) {
            if (res.success) {
                $status.text('<?php esc_html_e( 'Logs cleared!', 'excellink-product-feeds' ); ?>').addClass('is-success');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                $status.text(res.data.message || '<?php esc_html_e( 'Error clearing logs', 'excellink-product-feeds' ); ?>').addClass('is-error');
            }
        })
        .fail(function() {
            $status.text('<?php esc_html_e( 'Error clearing logs', 'excellink-product-feeds' ); ?>').addClass('is-error');
        })
        .always(function() {
            $btn.prop('disabled', false);
        });
    });
});
</script>
