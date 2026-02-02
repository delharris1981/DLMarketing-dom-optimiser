<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DLMarketing_DOM_Settings
 * Handles the configuration settings for the plugin.
 */
class DLMarketing_DOM_Settings
{

    /**
     * Instance
     */
    private static $instance = null;

    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add sub-menu to Settings
     */
    public function add_admin_menu()
    {
        add_options_page(
            'DLMarketing DOM Optimiser',
            'DOM Optimiser',
            'manage_options',
            'dlmarketing-dom-optimiser',
            [$this, 'settings_page_html']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings()
    {
        register_setting('dlmarketing_dom_group', 'dlmarketing_dom_enable_wrapper');
        register_setting('dlmarketing_dom_group', 'dlmarketing_dom_enable_ghost');
        register_setting('dlmarketing_dom_group', 'dlmarketing_dom_enable_comments');
        register_setting('dlmarketing_dom_group', 'dlmarketing_dom_enable_aggressive');

        add_settings_section(
            'dlmarketing_dom_general_section',
            'Optimization Settings',
            null,
            'dlmarketing-dom-optimiser'
        );

        add_settings_field(
            'dlmarketing_dom_enable_wrapper',
            'Remove Legacy Wrappers',
            [$this, 'checkbox_callback'],
            'dlmarketing-dom-optimiser',
            'dlmarketing_dom_general_section',
            ['label_for' => 'dlmarketing_dom_enable_wrapper', 'desc' => 'Collapse redundant .elementor-column-wrap and widget containers.']
        );

        add_settings_field(
            'dlmarketing_dom_enable_ghost',
            'Remove Ghost Nodes',
            [$this, 'checkbox_callback'],
            'dlmarketing-dom-optimiser',
            'dlmarketing_dom_general_section',
            ['label_for' => 'dlmarketing_dom_enable_ghost', 'desc' => 'Remove empty elements with no content or visual style.']
        );

        add_settings_field(
            'dlmarketing_dom_enable_comments',
            'Remove HTML Comments',
            [$this, 'checkbox_callback'],
            'dlmarketing-dom-optimiser',
            'dlmarketing_dom_general_section',
            ['label_for' => 'dlmarketing_dom_enable_comments', 'desc' => 'Strip <!-- comments --> from the source.']
        );

        add_settings_field(
            'dlmarketing_dom_enable_aggressive',
            'Enable Aggressive Mode (Deep Space)',
            [$this, 'checkbox_callback'],
            'dlmarketing-dom-optimiser',
            'dlmarketing_dom_general_section',
            ['label_for' => 'dlmarketing_dom_enable_aggressive', 'desc' => 'EXPERIMENTAL. Removes .elementor-inner and flattens nested containers. May affect complex layouts.']
        );
    }

    /**
     * Checkbox Callback
     */
    public function checkbox_callback($args)
    {
        $option = get_option($args['label_for']);
        // Default to 'on' if not set
        if (false === $option) {
            $option = 'on';
        }

        ?>
        <input type="checkbox" id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($args['label_for']); ?>" value="on" <?php checked('on', $option); ?> />
        <p class="description"><?php echo esc_html($args['desc']); ?></p>
        <?php
    }

    /**
     * Render the specific Settings page
     */
    public function settings_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('dlmarketing_dom_group');
                do_settings_sections('dlmarketing-dom-optimiser');
                submit_button('Save Changes');
                ?>
            </form>
        </div>
        <?php
    }
}
