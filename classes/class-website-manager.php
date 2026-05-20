<?php
/**
 * Main class for Blue Dog Website Manager plugin.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Website_Manager {

    protected static $instance = null;
    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    const MENU_SLUG     = 'website-manager';
    const SETTINGS_SLUG = 'website-manager-settings';

    // =========================================================================
    // Helpers
    // =========================================================================

    private function display_name() {
        $opts = $this->get_settings();
        if ( ! empty( $opts['white_label_enabled'] ) && $opts['white_label_name'] !== '' ) {
            return $opts['white_label_name'];
        }
        return 'Blue Dog Website Manager';
    }

    private function is_owner() {
        $user = wp_get_current_user();
        if ( ! $user || ! $user->ID ) return false;
        $opts = $this->get_settings();
        $owner = ! empty( $opts['white_label_user'] ) ? $opts['white_label_user'] : 'bluedogdigital';
        return $user->user_login === $owner;
    }

    private function white_label_active() {
        $opts = $this->get_settings();
        return ! empty( $opts['white_label_enabled'] ) && ! $this->is_owner();
    }

    // =========================================================================
    // Feature registry
    // =========================================================================

    private function get_features() {
        return [
            'bb_dashboard' => [
                'label'      => 'Replace Dashboard with Beaver Builder Template',
                'desc'       => 'Removes all default dashboard widgets and replaces the dashboard with a chosen Beaver Builder template.',
                'has_config' => true,   // shows the gear icon
            ],
            'disable_comments' => [
                'label' => 'Disable Comments',
                'desc'  => 'Completely disables the WordPress commenting system across all post types and removes it from the admin menu.',
            ],
            'disable_image_sizes' => [
                'label' => 'Disable Automatic Additional Image Sizes',
                'desc'  => 'Prevents WordPress from generating medium, large, and thumbnail image sizes on upload to save disk space.',
            ],
            'svg_support' => [
                'label' => 'Enable SVG Upload Support',
                'desc'  => 'Allows SVG files to be uploaded through the WordPress media library. Restricted to administrators only.',
            ],
            'disable_admin_email_check' => [
                'label' => 'Disable Admin Email Verification Check',
                'desc'  => 'Removes the "Is your admin email address still correct?" prompt introduced in WordPress 5.2.',
            ],
            'auto_image_meta' => [
                'label' => 'Auto-Set Image Metadata on Upload',
                'desc'  => 'Automatically populates the title, alt text, caption, and description from the filename when an image is uploaded.',
            ],
            'microthemer_retain_styles' => [
                'label' => 'Microthemer: Retain Styles When Deactivated',
                'desc'  => 'Continues to load Microthemer\'s compiled CSS on the frontend even when the Microthemer plugin is deactivated.',
            ],
            'user_switching' => [
                'label' => 'User Switching',
                'desc'  => 'Switch into any user account directly from the Users list. A red banner is shown while switched with a one-click return. Only available to the owner admin — clients cannot switch to your account.',
            ],
        ];
    }

    // =========================================================================
    // Settings / options helpers
    // =========================================================================

    private function get_settings() {
        $defaults = [
            'white_label_enabled' => 0,
            'white_label_name'    => '',
            'white_label_user'    => 'bluedogdigital',
            'bb_dashboard_id'     => 0,   // post ID of chosen BB template
        ];
        $saved = get_option( 'website_manager_settings', [] );
        return array_merge( $defaults, is_array( $saved ) ? $saved : [] );
    }

    private function get_active_features() {
        return get_option( 'website_manager_features', [] );
    }

    private function is_active( $slug ) {
        $active = $this->get_active_features();
        return ! empty( $active[ $slug ] );
    }

    /**
     * Return all Beaver Builder templates as [ id => title ].
     */
    private function get_bb_templates() {
        $posts = get_posts( [
            'post_type'      => 'fl-builder-template',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        $out = [];
        foreach ( $posts as $p ) {
            $out[ $p->ID ] = $p->post_title;
        }
        return $out;
    }

    // =========================================================================
    // Init
    // =========================================================================

    public function init() {
        add_action( 'admin_menu',            [ $this, 'register_menus' ] );
        add_action( 'admin_init',            [ $this, 'handle_save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_wm_get_bb_templates', [ $this, 'ajax_get_bb_templates' ] );

        // Rename plugin in the Plugins list when white label is active
        add_filter( 'all_plugins', [ $this, 'white_label_plugins_list' ] );

        $this->apply_features();
    }

    public function white_label_plugins_list( $plugins ) {
        // Owner always sees the real name
        if ( $this->is_owner() ) return $plugins;

        $opts = $this->get_settings();
        if ( empty( $opts['white_label_enabled'] ) || empty( $opts['white_label_name'] ) ) return $plugins;

        $key = plugin_basename( WEBSITE_MANAGER_FILE );
        if ( isset( $plugins[ $key ] ) ) {
            $name = $opts['white_label_name'];
            $plugins[ $key ]['Name']       = $name;
            $plugins[ $key ]['Title']      = $name;
            $plugins[ $key ]['Author']     = '';
            $plugins[ $key ]['AuthorName'] = '';
            $plugins[ $key ]['PluginURI']  = '';
            $plugins[ $key ]['AuthorURI']  = '';
        }
        return $plugins;
    }

    // =========================================================================
    // Menu registration
    // =========================================================================

    public function register_menus() {
        $display = $this->display_name();
        $wl      = $this->white_label_active();

        add_menu_page(
            $display, $display, 'manage_options',
            self::MENU_SLUG, [ $this, 'render_modules_page' ],
            'dashicons-admin-tools', 80
        );

        add_submenu_page(
            self::MENU_SLUG, $display . ' — Modules', 'Modules',
            'manage_options', self::MENU_SLUG, [ $this, 'render_modules_page' ]
        );

        // Settings hidden from non-owners in WL mode
        $settings_cap = $wl ? 'do_not_allow' : 'manage_options';
        add_submenu_page(
            self::MENU_SLUG, $display . ' — Settings', 'Settings',
            $settings_cap, self::SETTINGS_SLUG, [ $this, 'render_settings_page' ]
        );

        // Tools menu link — always the only entry point for all users
        // (non-owners in WL mode see the renamed label; Settings stays inaccessible via capability)
        add_management_page(
            $display, $display, 'manage_options',
            self::MENU_SLUG, [ $this, 'render_modules_page' ]
        );

        // Always remove from the main sidebar — Tools only
        remove_menu_page( self::MENU_SLUG );
    }

    // =========================================================================
    // Assets
    // =========================================================================

    public function enqueue_assets( $hook ) {
        // Hook names for submenu pages are derived from the sanitised menu *title*,
        // which changes under white-label mode and is fragile in general.
        // Checking the page slug via $_GET['page'] is simpler and reliable.
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $our_pages = [ self::MENU_SLUG, self::SETTINGS_SLUG ];
        if ( ! in_array( $page, $our_pages, true ) ) return;

        wp_enqueue_style( 'website-manager-admin', WEBSITE_MANAGER_URL . 'assets/css/admin.css', [], WEBSITE_MANAGER_VERSION );
        wp_enqueue_script( 'website-manager-admin', WEBSITE_MANAGER_URL . 'assets/js/admin.js', [ 'jquery' ], WEBSITE_MANAGER_VERSION, true );
        wp_localize_script( 'website-manager-admin', 'wmData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wm_ajax' ),
        ] );
    }

    // =========================================================================
    // AJAX: return BB templates as JSON for the template picker modal
    // =========================================================================

    public function ajax_get_bb_templates() {
        check_ajax_referer( 'wm_ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );
        $templates = $this->get_bb_templates();
        if ( empty( $templates ) ) {
            wp_send_json_error( 'No Beaver Builder templates found. Create a template first.' );
        }
        wp_send_json_success( $templates );
    }

    // =========================================================================
    // Shared app header
    // =========================================================================

    private function render_app_header( $active_tab = 'modules' ) {
        $display = $this->display_name();
        $tabs = [
            'modules'  => [ 'label' => 'Modules',  'icon' => '🔧', 'slug' => self::MENU_SLUG ],
            'settings' => [ 'label' => 'Settings', 'icon' => '⚙️', 'slug' => self::SETTINGS_SLUG ],
        ];
        if ( $this->white_label_active() ) unset( $tabs['settings'] );
        ?>
        <div class="wm-app-header">
            <div class="wm-app-header-left">
                <div class="wm-app-icon">
                    <span class="dashicons dashicons-admin-tools"></span>
                </div>
                <div>
                    <h1 class="wm-app-title"><?php echo esc_html( $display ); ?></h1>
                    <p class="wm-app-subtitle">Toggle site features on or off — settings saved per site</p>
                </div>
                <span class="wm-badge">v<?php echo esc_html( WEBSITE_MANAGER_VERSION ); ?></span>
            </div>
            <?php if ( ! $this->white_label_active() ) : ?>
            <div class="wm-header-actions">
                <button type="button" class="wm-btn wm-btn-secondary" id="wm-import-btn">↑ Import</button>
                <button type="button" class="wm-btn wm-btn-secondary" id="wm-export-btn">↓ Export</button>
            </div>
            <?php endif; ?>
        </div>
        <nav class="wm-nav">
            <?php foreach ( $tabs as $key => $tab ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $tab['slug'] ) ); ?>"
                   class="<?php echo $active_tab === $key ? 'active' : ''; ?>">
                    <span><?php echo $tab['icon']; ?></span>
                    <?php echo esc_html( $tab['label'] ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    // =========================================================================
    // Save handler
    // =========================================================================

    public function handle_save() {
        if ( empty( $_POST['website_manager_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['website_manager_nonce'], 'website_manager_save' ) ) wp_die( 'Security check failed.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Insufficient permissions.' );

        $action = isset( $_POST['wm_action'] ) ? sanitize_text_field( $_POST['wm_action'] ) : 'modules';

        // Import
        if ( ! empty( $_POST['wm_import_string'] ) ) {
            $raw  = base64_decode( sanitize_text_field( wp_unslash( $_POST['wm_import_string'] ) ), true );
            $data = $raw ? json_decode( $raw, true ) : null;
            if ( is_array( $data ) ) {
                if ( isset( $data['features'] ) ) update_option( 'website_manager_features', array_intersect_key( $data['features'], $this->get_features() ) );
                if ( isset( $data['settings'] ) ) update_option( 'website_manager_settings', $this->sanitize_settings( $data['settings'] ) );
                wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&updated=imported' ) );
                exit;
            }
        }

        // Settings save
        if ( $action === 'settings' ) {
            $current  = $this->get_settings();
            $new_settings = [
                'white_label_enabled' => ! empty( $_POST['wm_white_label_enabled'] ) ? 1 : 0,
                'white_label_name'    => sanitize_text_field( wp_unslash( $_POST['wm_white_label_name'] ?? '' ) ),
                'white_label_user'    => sanitize_user( wp_unslash( $_POST['wm_white_label_user'] ?? 'bluedogdigital' ) ),
                // bb_dashboard_id lives on the Modules form — preserve it here so
                // saving Settings never wipes the selected template.
                'bb_dashboard_id'     => $current['bb_dashboard_id'],
            ];
            update_option( 'website_manager_settings', $new_settings );
            wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG . '&updated=1' ) );
            exit;
        }

        // Modules save — also save bb_dashboard_id if posted
        $new = [];
        foreach ( array_keys( $this->get_features() ) as $slug ) {
            if ( ! empty( $_POST[ 'feature_' . $slug ] ) ) $new[ $slug ] = 1;
        }
        update_option( 'website_manager_features', $new );

        // Persist template selection alongside module save
        if ( isset( $_POST['wm_bb_dashboard_id'] ) ) {
            $current = $this->get_settings();
            $current['bb_dashboard_id'] = absint( $_POST['wm_bb_dashboard_id'] );
            update_option( 'website_manager_settings', $current );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&updated=1' ) );
        exit;
    }

    private function sanitize_settings( $data ) {
        return [
            'white_label_enabled' => ! empty( $data['white_label_enabled'] ) ? 1 : 0,
            'white_label_name'    => isset( $data['white_label_name'] ) ? sanitize_text_field( $data['white_label_name'] ) : '',
            'white_label_user'    => isset( $data['white_label_user'] ) ? sanitize_user( $data['white_label_user'] ) : 'bluedogdigital',
            'bb_dashboard_id'     => isset( $data['bb_dashboard_id'] ) ? absint( $data['bb_dashboard_id'] ) : 0,
        ];
    }

    // =========================================================================
    // Modules page
    // =========================================================================

    public function render_modules_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $features  = $this->get_features();
        $active    = $this->get_active_features();
        $settings  = $this->get_settings();
        $total     = count( $features );
        $updated   = isset( $_GET['updated'] ) ? sanitize_text_field( $_GET['updated'] ) : '';

        $export_payload = base64_encode( json_encode( [
            'features' => $active,
            'settings' => $settings,
        ] ) );

        // Get selected template title for display
        $selected_tpl_id    = (int) $settings['bb_dashboard_id'];
        $selected_tpl_title = $selected_tpl_id ? get_the_title( $selected_tpl_id ) : '';
        ?>
        <div class="wm-wrap">

        <?php if ( $updated ) : ?>
            <div class="wm-notice wm-notice-success" style="margin-top:16px;">
                ✓ <?php echo $updated === 'imported' ? 'Settings imported successfully.' : 'Settings saved successfully.'; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" id="wm-form">
        <?php wp_nonce_field( 'website_manager_save', 'website_manager_nonce' ); ?>
        <input type="hidden" name="wm_action" value="modules">
        <input type="hidden" name="wm_import_string" id="wm-import-field" value="">
        <input type="hidden" name="wm_bb_dashboard_id" id="wm-bb-dashboard-id" value="<?php echo esc_attr( $selected_tpl_id ); ?>">

        <?php $this->render_app_header( 'modules' ); ?>

        <div class="wm-page-content">

            <div class="wm-toolbar">
                <div class="wm-search-box">
                    <span class="wm-search-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
                            <path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <input type="text" class="wm-search-input" placeholder="Search modules…">
                </div>
                <span class="wm-results-count">Showing <?php echo esc_html( $total ); ?> of <?php echo esc_html( $total ); ?> modules</span>
                <div class="wm-filter-tabs">
                    <button type="button" class="wm-filter-tab is-active" data-filter="all">All</button>
                    <button type="button" class="wm-filter-tab" data-filter="active">Active</button>
                    <button type="button" class="wm-filter-tab" data-filter="inactive">Inactive</button>
                </div>
            </div>

            <div class="wm-feature-grid">
                <?php foreach ( $features as $slug => $feature ) :
                    $checked    = ! empty( $active[ $slug ] );
                    $has_config = ! empty( $feature['has_config'] );
                    ?>
                    <?php if ( $has_config ) : ?>
                    <div class="wm-card-row wm-card-row--with-controls<?php echo $checked ? ' is-active' : ''; ?>"
                         data-slug="<?php echo esc_attr( $slug ); ?>">
                        <label class="wm-feature-card<?php echo $checked ? ' is-active' : ''; ?>">
                            <div class="wm-feature-info">
                                <span class="wm-feature-label"><?php echo esc_html( $feature['label'] ); ?></span>
                                <span class="wm-feature-desc"><?php echo esc_html( $feature['desc'] ); ?></span>
                                <?php if ( $selected_tpl_title ) : ?>
                                    <span class="wm-feature-meta">
                                        Template: <strong><?php echo esc_html( $selected_tpl_title ); ?></strong>
                                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $selected_tpl_id . '&action=edit&fl_builder' ) ); ?>"
                                           target="_blank" style="margin-left:8px;font-weight:500;">Open with Page Builder ↗</a>
                                    </span>
                                <?php elseif ( $checked ) : ?>
                                    <span class="wm-feature-meta wm-feature-meta--warn">⚠ No template selected — click ⚙ to choose one</span>
                                <?php endif; ?>
                            </div>
                        </label>
                        <div class="wm-card-controls">
                            <button type="button"
                                    id="wm-gear-bb-dashboard"
                                    class="wm-config-btn<?php echo $checked ? '' : ' is-hidden'; ?>"
                                    title="Choose Beaver Builder template">⚙</button>
                            <label class="wm-toggle-label">
                                <div class="wm-toggle-wrap">
                                    <input type="checkbox"
                                           name="feature_<?php echo esc_attr( $slug ); ?>"
                                           value="1"
                                           class="wm-toggle-input"
                                           data-slug="<?php echo esc_attr( $slug ); ?>"
                                           <?php checked( $checked ); ?>>
                                    <span class="wm-toggle-slider"></span>
                                </div>
                            </label>
                        </div>
                    </div>
                    <?php else : ?>
                    <div class="wm-card-row<?php echo $checked ? ' is-active' : ''; ?>"
                         data-slug="<?php echo esc_attr( $slug ); ?>">
                        <label class="wm-feature-card<?php echo $checked ? ' is-active' : ''; ?>">
                            <div class="wm-feature-info">
                                <span class="wm-feature-label"><?php echo esc_html( $feature['label'] ); ?></span>
                                <span class="wm-feature-desc"><?php echo esc_html( $feature['desc'] ); ?></span>
                            </div>
                            <div class="wm-toggle-wrap">
                                <input type="checkbox"
                                       name="feature_<?php echo esc_attr( $slug ); ?>"
                                       value="1"
                                       class="wm-toggle-input"
                                       data-slug="<?php echo esc_attr( $slug ); ?>"
                                       <?php checked( $checked ); ?>>
                                <span class="wm-toggle-slider"></span>
                            </div>
                        </label>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="wm-no-results"><div class="wm-no-results-icon">🔍</div>No modules match your search.</div>
            </div>

            <div class="wm-save-bar">
                <button type="submit" class="wm-btn wm-btn-primary">Save Settings</button>
                <span class="wm-saved-msg<?php echo $updated === '1' ? ' is-visible' : ''; ?>">✓ Settings saved</span>
            </div>

        </div>
        </form>

        <!-- BB Template picker modal -->
        <div class="wm-modal-overlay" id="wm-bb-template-modal">
            <div class="wm-modal">
                <div class="wm-modal-header">
                    <h3>Choose a Beaver Builder Template</h3>
                    <button type="button" class="wm-modal-close-x wm-modal-close" aria-label="Close">×</button>
                </div>
                <p>Select the template to display on the WordPress dashboard.</p>
                <div id="wm-template-list" class="wm-template-list">
                    <div class="wm-template-loading">Loading templates…</div>
                </div>
                <div class="wm-modal-actions">
                    <button type="button" class="wm-btn wm-btn-secondary wm-modal-close">Cancel</button>
                    <button type="button" class="wm-btn wm-btn-primary" id="wm-template-confirm" disabled>Select Template</button>
                </div>
            </div>
        </div>

        <?php $this->render_modals( $export_payload ); ?>
        </div>
        <?php
    }

    // =========================================================================
    // Settings page
    // =========================================================================

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( $this->white_label_active() ) wp_die( 'You do not have permission to access this page.' );

        $updated  = isset( $_GET['updated'] ) ? sanitize_text_field( $_GET['updated'] ) : '';
        $settings = $this->get_settings();

        $export_payload = base64_encode( json_encode( [
            'features' => $this->get_active_features(),
            'settings' => $settings,
        ] ) );
        ?>
        <div class="wm-wrap">

        <?php if ( $updated ) : ?>
            <div class="wm-notice wm-notice-success" style="margin-top:16px;">✓ Settings saved successfully.</div>
        <?php endif; ?>

        <form method="post" action="" id="wm-form">
        <?php wp_nonce_field( 'website_manager_save', 'website_manager_nonce' ); ?>
        <input type="hidden" name="wm_action" value="settings">
        <input type="hidden" name="wm_import_string" id="wm-import-field" value="">

        <?php $this->render_app_header( 'settings' ); ?>

        <div class="wm-page-content">

            <div class="wm-feature-grid wm-settings-grid">

                <!-- White label toggle — uses same card-row as modules page -->
                <div class="wm-card-row<?php echo $settings['white_label_enabled'] ? ' is-active' : ''; ?>">
                    <label class="wm-feature-card<?php echo $settings['white_label_enabled'] ? ' is-active' : ''; ?>">
                        <div class="wm-feature-info">
                            <span class="wm-feature-label">Enable White Label Mode</span>
                            <span class="wm-feature-desc">Renames the plugin and hides it from all users except the admin username specified below.</span>
                        </div>
                        <div class="wm-toggle-wrap">
                            <input type="checkbox" id="wm-wl-toggle" name="wm_white_label_enabled" value="1"
                                   class="wm-toggle-input" <?php checked( $settings['white_label_enabled'] ); ?>>
                            <span class="wm-toggle-slider"></span>
                        </div>
                    </label>
                </div>

                <!-- Plugin display name -->
                <div class="wm-card-row wm-wl-field">
                    <div class="wm-feature-card wm-feature-card--static">
                        <div class="wm-feature-info">
                            <span class="wm-feature-label">Plugin Display Name</span>
                            <span class="wm-feature-desc">Replaces "Blue Dog Website Manager" everywhere in the admin. Leave blank to use the default.</span>
                        </div>
                        <div class="wm-input-wrap">
                            <input type="text" name="wm_white_label_name" class="wm-text-input"
                                   value="<?php echo esc_attr( $settings['white_label_name'] ); ?>"
                                   placeholder="e.g. Site Tools">
                        </div>
                    </div>
                </div>

                <!-- Admin username -->
                <div class="wm-card-row wm-wl-field">
                    <div class="wm-feature-card wm-feature-card--static">
                        <div class="wm-feature-info">
                            <span class="wm-feature-label">Admin Username</span>
                            <span class="wm-feature-desc">This WordPress username always sees the full plugin. All other admins see only the renamed Modules page.</span>
                        </div>
                        <div class="wm-input-wrap">
                            <input type="text" name="wm_white_label_user" class="wm-text-input"
                                   value="<?php echo esc_attr( $settings['white_label_user'] ); ?>"
                                   placeholder="bluedogdigital">
                            <p class="wm-help">Default: <code>bluedogdigital</code></p>
                        </div>
                    </div>
                </div>

            </div>

            <div class="wm-save-bar">
                <button type="submit" class="wm-btn wm-btn-primary">Save Settings</button>
                <span class="wm-saved-msg<?php echo $updated ? ' is-visible' : ''; ?>">✓ Settings saved</span>
            </div>

        </div>
        </form>

        <?php $this->render_modals( $export_payload ); ?>
        </div>
        <?php
    }

    // =========================================================================
    // Export / Import modals
    // =========================================================================

    private function render_modals( $export_payload ) { ?>
        <div class="wm-modal-overlay" id="wm-export-modal">
            <div class="wm-modal">
                <div class="wm-modal-header">
                    <h3>Export Settings</h3>
                    <button type="button" class="wm-modal-close-x wm-modal-close">×</button>
                </div>
                <p>Copy this string and paste it into the Import panel on another site.</p>
                <textarea id="wm-export-string" readonly onclick="this.select()"><?php echo esc_textarea( $export_payload ); ?></textarea>
                <div class="wm-modal-actions">
                    <button type="button" class="wm-btn wm-btn-secondary wm-modal-close">Close</button>
                    <button type="button" class="wm-btn wm-btn-primary" id="wm-export-copy">Copy</button>
                </div>
            </div>
        </div>
        <div class="wm-modal-overlay" id="wm-import-modal">
            <div class="wm-modal">
                <div class="wm-modal-header">
                    <h3>Import Settings</h3>
                    <button type="button" class="wm-modal-close-x wm-modal-close">×</button>
                </div>
                <p>Paste an export string from another site. This will overwrite your current settings.</p>
                <textarea id="wm-import-string" placeholder="Paste export string here…"></textarea>
                <div class="wm-modal-actions">
                    <button type="button" class="wm-btn wm-btn-secondary wm-modal-close">Cancel</button>
                    <button type="button" class="wm-btn wm-btn-primary" id="wm-import-submit">Import</button>
                </div>
            </div>
        </div>
    <?php }

    // =========================================================================
    // Feature implementations
    // =========================================================================

    private function apply_features() {

        // ── Hide owner user from other admins in WL mode ──────
        $settings = $this->get_settings();
        if ( ! empty( $settings['white_label_enabled'] ) ) {
            $owner_login = ! empty( $settings['white_label_user'] ) ? $settings['white_label_user'] : 'bluedogdigital';
            add_action( 'pre_get_users', function ( $query ) use ( $owner_login ) {
                if ( is_admin() && ! $this->is_owner() ) {
                    $excluded = get_user_by( 'login', $owner_login );
                    if ( $excluded ) {
                        $query->set( 'exclude', array_merge(
                            (array) $query->get( 'exclude' ),
                            [ $excluded->ID ]
                        ) );
                    }
                }
            } );
        }

        // ── Disable admin email verification check ────────────
        if ( $this->is_active( 'disable_admin_email_check' ) ) {
            add_filter( 'admin_email_check_interval', '__return_zero' );
        }

        // ── Auto-set image metadata on upload ─────────────────
        if ( $this->is_active( 'auto_image_meta' ) ) {
            add_action( 'add_attachment', function ( $post_id ) {
                $post = get_post( $post_id );
                if ( ! $post || strpos( $post->post_mime_type, 'image/' ) === false ) return;

                $filename = pathinfo( get_attached_file( $post_id ), PATHINFO_FILENAME );
                $title    = ucwords( str_replace( [ '-', '_', '.' ], ' ', $filename ) );

                wp_update_post( [
                    'ID'           => $post_id,
                    'post_title'   => $title,
                    'post_excerpt' => $title,   // caption
                    'post_content' => $title,   // description
                ] );

                update_post_meta( $post_id, '_wp_attachment_image_alt', $title );
            } );
        }

        // ── Microthemer: retain styles when deactivated ───────
        // Uses Microthemer's own AssetLoad class — mirrors the code snippet
        // Microthemer provides for exactly this use case.
        if ( $this->is_active( 'microthemer_retain_styles' ) ) {
            if ( ! defined( 'MT_IS_ACTIVE' ) ) {
                $mt_dir   = WP_CONTENT_DIR . '/micro-themes/';
                $autoload = $mt_dir . 'autoload.php';
                $file     = $mt_dir . 'AssetLoad.php';

                if ( ! class_exists( '\Microthemer\AssetLoad' ) && file_exists( $autoload ) && file_exists( $file ) ) {
                    require $autoload;
                    new \Microthemer\AssetLoad( true );
                }
            }
        }

        // ── BB Dashboard replacement ───────────────────────────
        if ( $this->is_active( 'bb_dashboard' ) ) {

            $tpl_id = (int) $settings['bb_dashboard_id'];

            // Priority 99: clear every default widget
            add_action( 'wp_dashboard_setup', function () {
                global $wp_meta_boxes;
                $wp_meta_boxes['dashboard'] = [
                    'advanced' => [],
                    'side'     => [],
                    'normal'   => [],
                ];
            }, 99 );

            // Priority 100: add our BB widget after the clear
            add_action( 'wp_dashboard_setup', function () use ( $tpl_id ) {
                if ( ! $tpl_id ) return;
                wp_add_dashboard_widget(
                    'wm_bb_dashboard_widget',
                    'Site Dashboard', // non-empty title required — WP JS marks empty-title boxes as closed
                    function () use ( $tpl_id ) {
                        if ( ! class_exists( 'FLBuilder' ) ) {
                            echo '<p style="padding:12px;color:#646970;">Beaver Builder is not active.</p>';
                            return;
                        }
                        $post = get_post( $tpl_id );
                        if ( ! $post || $post->post_status !== 'publish' ) {
                            echo '<p style="padding:12px;color:#646970;">Selected template not found or not published.</p>';
                            return;
                        }

                        // Attempt 1: fl_builder_insert_layout shortcode.
                        $output = do_shortcode( '[fl_builder_insert_layout id="' . intval( $tpl_id ) . '"]' );

                        // Attempt 2: FLBuilder::render_query directly.
                        if ( empty( trim( $output ) ) && method_exists( 'FLBuilder', 'render_query' ) ) {
                            ob_start();
                            FLBuilder::render_query( [
                                'post_type' => 'fl-builder-template',
                                'p'         => $tpl_id,
                            ] );
                            $output = ob_get_clean();
                        }

                        // Attempt 3: the_content filter.
                        if ( empty( trim( $output ) ) ) {
                            $GLOBALS['post'] = $post;
                            setup_postdata( $post );
                            $output = apply_filters( 'the_content', $post->post_content );
                            wp_reset_postdata();
                        }

                        if ( ! empty( trim( $output ) ) ) {
                            echo '<div class="wm-bb-template-wrap">' . $output . '</div>';
                        } else {
                            echo '<p style="padding:12px;color:#646970;">Template <strong>'
                                . esc_html( $post->post_title )
                                . '</strong> could not be rendered. Beaver Builder may not support admin rendering for this template.</p>';
                        }
                    }
                );
            }, 100 );

            // Enqueue BB layout styles so they render correctly in admin
            add_action( 'admin_enqueue_scripts', function ( $hook ) {
                if ( $hook !== 'index.php' ) return;  // dashboard only
                if ( class_exists( 'FLBuilder' ) ) {
                    FLBuilder::register_layout_styles_scripts();
                    FLBuilder::enqueue_all_layouts_styles_scripts();
                }
            } );

            // Strip the postbox wrapper chrome and force the widget open via JS.
            // WP marks postboxes with empty/new titles as closed on first load.
            add_action( 'admin_head', function () {
                $screen = get_current_screen();
                if ( ! $screen || $screen->base !== 'dashboard' ) return;
                echo '<style>
                    /* Force full-width single-column dashboard layout */
                    #dashboard-widgets { display:block !important; }
                    #dashboard-widgets .postbox-container { width:100% !important; float:none !important; }
                    #postbox-container-2,
                    #postbox-container-3,
                    #postbox-container-4 { display:none !important; }
                    /* Strip postbox chrome */
                    #wm_bb_dashboard_widget .postbox-header { display:none !important; }
                    #wm_bb_dashboard_widget .inside { margin:0; padding:0; }
                    #wm_bb_dashboard_widget { border:none; box-shadow:none; background:transparent; }
                    .wm-bb-template-wrap { width:100%; }
                </style>
                <script>
                jQuery(function($){
                    var $box = $("#wm_bb_dashboard_widget");
                    $box.removeClass("closed");
                    $box.find(".inside").show();
                });
                </script>';
            } );
        }

        // ── User Switching ─────────────────────────────────────
        if ( $this->is_active( 'user_switching' ) ) {

            // "Switch to" link in Users list — owner only, not during a switched session
            add_filter( 'user_row_actions', function ( $actions, $user ) {
                if ( ! $this->is_owner() ) return $actions;
                if ( ! empty( $_COOKIE['wm_switch_user'] ) ) return $actions;

                $opts        = $this->get_settings();
                $owner_login = ! empty( $opts['white_label_user'] ) ? $opts['white_label_user'] : 'bluedogdigital';
                if ( $user->user_login === $owner_login ) return $actions;

                $url = wp_nonce_url(
                    admin_url( 'users.php?wm_switch_to=' . $user->ID ),
                    'wm_switch_' . $user->ID
                );
                $actions['wm_switch'] = '<a href="' . esc_url( $url ) . '">Switch to</a>';
                return $actions;
            }, 10, 2 );

            // Handle switch / switch-back
            add_action( 'admin_init', function () {

                // ── Switch TO a user ──────────────────────────
                if ( ! empty( $_GET['wm_switch_to'] ) ) {
                    $target_id = absint( $_GET['wm_switch_to'] );
                    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'wm_switch_' . $target_id ) ) return;
                    if ( ! $this->is_owner() ) return;
                    if ( ! empty( $_COOKIE['wm_switch_user'] ) ) return; // No nested switching

                    $target = get_user_by( 'id', $target_id );
                    if ( ! $target ) return;

                    $opts        = $this->get_settings();
                    $owner_login = ! empty( $opts['white_label_user'] ) ? $opts['white_label_user'] : 'bluedogdigital';
                    if ( $target->user_login === $owner_login ) return; // Can't switch to owner

                    // Store original user in a signed session cookie
                    $original_id = get_current_user_id();
                    $hash        = hash_hmac( 'sha256', (string) $original_id, wp_hash( 'wm_switch' ) );
                    setcookie( 'wm_switch_user', $original_id . '|' . $hash, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

                    wp_clear_auth_cookie();
                    wp_set_current_user( $target_id );
                    wp_set_auth_cookie( $target_id, false );
                    wp_safe_redirect( admin_url() );
                    exit;
                }

                // ── Switch BACK ───────────────────────────────
                if ( ! empty( $_GET['wm_switch_back'] ) ) {
                    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'wm_switch_back' ) ) return;
                    if ( empty( $_COOKIE['wm_switch_user'] ) ) return;

                    $parts = explode( '|', $_COOKIE['wm_switch_user'], 2 );
                    if ( count( $parts ) !== 2 ) return;
                    [ $original_id, $stored_hash ] = $parts;

                    if ( ! hash_equals( hash_hmac( 'sha256', $original_id, wp_hash( 'wm_switch' ) ), $stored_hash ) ) return;

                    $original_user = get_user_by( 'id', (int) $original_id );
                    if ( ! $original_user ) return;

                    // Confirm stored user is the owner before restoring
                    $opts        = $this->get_settings();
                    $owner_login = ! empty( $opts['white_label_user'] ) ? $opts['white_label_user'] : 'bluedogdigital';
                    if ( $original_user->user_login !== $owner_login ) return;

                    // Clear cookie and switch back
                    setcookie( 'wm_switch_user', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
                    wp_clear_auth_cookie();
                    wp_set_current_user( (int) $original_id );
                    wp_set_auth_cookie( (int) $original_id, false );
                    wp_safe_redirect( admin_url( 'users.php' ) );
                    exit;
                }
            } );

            // Red admin bar banner while switched
            add_action( 'admin_bar_menu', function ( \WP_Admin_Bar $wp_admin_bar ) {
                if ( empty( $_COOKIE['wm_switch_user'] ) ) return;

                $parts = explode( '|', $_COOKIE['wm_switch_user'], 2 );
                if ( count( $parts ) !== 2 ) return;
                [ $original_id, $stored_hash ] = $parts;
                if ( ! hash_equals( hash_hmac( 'sha256', $original_id, wp_hash( 'wm_switch' ) ), $stored_hash ) ) return;

                $current_user    = wp_get_current_user();
                $switch_back_url = wp_nonce_url( admin_url( 'users.php?wm_switch_back=1' ), 'wm_switch_back' );

                $wp_admin_bar->add_node( [
                    'id'    => 'wm-user-switch',
                    'title' => '⚡ Switched to: <strong>' . esc_html( $current_user->display_name ) . '</strong>&ensp;&mdash;&ensp;Switch Back',
                    'href'  => esc_url( $switch_back_url ),
                ] );
            }, 1 );

            // Style the banner — red, prominent, both admin and frontend
            $switch_css = function () {
                if ( empty( $_COOKIE['wm_switch_user'] ) ) return;
                echo '<style>
                    #wpadminbar #wp-admin-bar-wm-user-switch { background: #b32d2e; }
                    #wpadminbar #wp-admin-bar-wm-user-switch > .ab-item {
                        color: #fff !important;
                        font-weight: 600;
                    }
                    #wpadminbar #wp-admin-bar-wm-user-switch:hover > .ab-item { background: #8c2324; }
                </style>';
            };
            add_action( 'admin_head', $switch_css );
            add_action( 'wp_head',    $switch_css );
        }
    }
}
