<?php
/**
 * Core plugin functionality.
 *
 * @package Company_Name_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class.
 */
class Company_Name_Search_Plugin {
    /**
     * Option key used to persist settings.
     */
    const OPTION_NAME = 'company_name_search_settings';

    /**
     * Singleton instance.
     *
     * @var Company_Name_Search_Plugin|null
     */
    protected static $instance = null;

    /**
     * Flag to ensure hooks are registered only once.
     *
     * @var bool
     */
    protected $initialized = false;

    /**
     * API helper instance.
     *
     * @var Company_Name_Search_API
     */
    protected $api;

    /**
     * REST controller instance.
     *
     * @var Company_Name_Search_REST
     */
    protected $rest_controller;

    /**
     * Returns the plugin singleton instance.
     *
     * @return Company_Name_Search_Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        if ( ! self::$instance->initialized ) {
            self::$instance->init();
            self::$instance->initialized = true;
        }

        return self::$instance;
    }

    /**
     * Activation callback.
     */
    public static function activate() {
        $settings = get_option( self::OPTION_NAME, array() );
        update_option( self::OPTION_NAME, wp_parse_args( $settings, self::get_default_settings() ) );
    }

    /**
     * Deactivation callback.
     */
    public static function deactivate() {
        // No specific actions are required on deactivation at this time.
    }

    /**
     * Retrieves the default plugin settings.
     *
     * @return array
     */
    public static function get_default_settings() {
        return array(
            'data_source'                  => 'local',
            'opencorporates_token'         => '',
            'opencorporates_country_code'  => '',
            'opencorporates_results_limit' => 10,
            'cache_duration'               => DAY_IN_SECONDS,
        );
    }

    /**
     * Registers WordPress hooks.
     */
    protected function init() {
        $this->api             = new Company_Name_Search_API( $this );
        $this->rest_controller = new Company_Name_Search_REST( $this->api );

        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_shortcode( 'company_name_search', array( $this, 'render_search_shortcode' ) );
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
    }

    /**
     * Registers plugin styles and scripts.
     */
    public function register_assets() {
        wp_register_style(
            'company-name-search',
            COMPANY_NAME_SEARCH_PLUGIN_URL . 'assets/css/company-name-search.css',
            array(),
            COMPANY_NAME_SEARCH_VERSION
        );

        wp_register_script(
            'company-name-search',
            COMPANY_NAME_SEARCH_PLUGIN_URL . 'assets/js/company-name-search.js',
            array(),
            COMPANY_NAME_SEARCH_VERSION,
            true
        );
    }

    /**
     * Enqueues front-end assets and localises script data.
     */
    protected function enqueue_frontend_assets() {
        $this->register_assets();

        wp_enqueue_style( 'company-name-search' );
        wp_enqueue_script( 'company-name-search' );

        $settings = $this->get_settings();

        wp_localize_script(
            'company-name-search',
            'CompanyNameSearch',
            array(
                'restUrl'  => esc_url_raw( rest_url( 'company-name-search/v1/check' ) ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'settings' => array(
                    'dataSource' => $settings['data_source'],
                ),
                'strings'  => array(
                    'emptyQuery'   => __( 'Please enter a company name before searching.', 'company-name-search' ),
                    'loading'      => __( 'Searching…', 'company-name-search' ),
                    'available'    => __( 'Great news! The name appears to be available.', 'company-name-search' ),
                    'unavailable'  => __( 'This name is already in use. You may want to try a variation.', 'company-name-search' ),
                    'noMatches'    => __( 'No similar names were found.', 'company-name-search' ),
                    'similarTitle' => __( 'Similar names', 'company-name-search' ),
                    'registryLink' => __( 'Registry', 'company-name-search' ),
                    'error'        => __( 'We were unable to complete the search. Please try again.', 'company-name-search' ),
                ),
            )
        );
    }

    /**
     * Renders the company name search form shortcode.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Shortcode content.
     *
     * @return string
     */
    public function render_search_shortcode( $atts, $content = '' ) {
        $atts = shortcode_atts(
            array(
                'title' => __( 'Check company name availability', 'company-name-search' ),
            ),
            $atts,
            'company_name_search'
        );

        $this->enqueue_frontend_assets();

        ob_start();
        ?>
        <div class="company-name-search" data-company-name-search>
            <?php if ( ! empty( $atts['title'] ) ) : ?>
                <h3 class="company-name-search__title"><?php echo esc_html( $atts['title'] ); ?></h3>
            <?php endif; ?>
            <form class="company-name-search__form" novalidate>
                <label class="screen-reader-text" for="company-name-search-input"><?php esc_html_e( 'Company name', 'company-name-search' ); ?></label>
                <input
                    id="company-name-search-input"
                    type="text"
                    name="company_name"
                    class="company-name-search__input"
                    placeholder="<?php esc_attr_e( 'Enter a company name', 'company-name-search' ); ?>"
                    required
                />
                <button type="submit" class="company-name-search__button"><?php esc_html_e( 'Check availability', 'company-name-search' ); ?></button>
            </form>
            <div class="company-name-search__spinner" hidden aria-hidden="true"></div>
            <div class="company-name-search__results" aria-live="polite"></div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Registers the plugin settings page.
     */
    public function register_settings_page() {
        add_options_page(
            __( 'Company Name Search', 'company-name-search' ),
            __( 'Company Name Search', 'company-name-search' ),
            'manage_options',
            'company-name-search',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Renders the settings page markup.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Company Name Search Settings', 'company-name-search' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'company_name_search' );
                do_settings_sections( 'company-name-search' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Registers plugin settings and fields.
     */
    public function register_settings() {
        register_setting(
            'company_name_search',
            self::OPTION_NAME,
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'company_name_search_general',
            __( 'Search configuration', 'company-name-search' ),
            array( $this, 'render_settings_section_description' ),
            'company-name-search'
        );

        add_settings_field(
            'data_source',
            __( 'Data source', 'company-name-search' ),
            array( $this, 'render_data_source_field' ),
            'company-name-search',
            'company_name_search_general'
        );

        add_settings_field(
            'opencorporates_token',
            __( 'OpenCorporates API token', 'company-name-search' ),
            array( $this, 'render_opencorporates_token_field' ),
            'company-name-search',
            'company_name_search_general'
        );

        add_settings_field(
            'opencorporates_country_code',
            __( 'OpenCorporates country code', 'company-name-search' ),
            array( $this, 'render_opencorporates_country_field' ),
            'company-name-search',
            'company_name_search_general'
        );

        add_settings_field(
            'opencorporates_results_limit',
            __( 'Results limit', 'company-name-search' ),
            array( $this, 'render_results_limit_field' ),
            'company-name-search',
            'company_name_search_general'
        );

        add_settings_field(
            'cache_duration',
            __( 'Cache duration (seconds)', 'company-name-search' ),
            array( $this, 'render_cache_duration_field' ),
            'company-name-search',
            'company_name_search_general'
        );
    }

    /**
     * Settings section description renderer.
     */
    public function render_settings_section_description() {
        echo '<p>' . esc_html__( 'Configure how the plugin performs company name availability checks.', 'company-name-search' ) . '</p>';
    }

    /**
     * Renders the data source field.
     */
    public function render_data_source_field() {
        $settings    = $this->get_settings();
        $data_source = isset( $settings['data_source'] ) ? $settings['data_source'] : 'local';
        ?>
        <fieldset>
            <label>
                <input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[data_source]" value="local" <?php checked( 'local', $data_source ); ?> />
                <?php esc_html_e( 'Use bundled sample data (no external requests).', 'company-name-search' ); ?>
            </label>
            <br />
            <label>
                <input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[data_source]" value="opencorporates" <?php checked( 'opencorporates', $data_source ); ?> />
                <?php esc_html_e( 'Query the OpenCorporates API (requires internet access).', 'company-name-search' ); ?>
            </label>
        </fieldset>
        <?php
    }

    /**
     * Renders the OpenCorporates token field.
     */
    public function render_opencorporates_token_field() {
        $settings = $this->get_settings();
        $token    = isset( $settings['opencorporates_token'] ) ? $settings['opencorporates_token'] : '';
        ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[opencorporates_token]" value="<?php echo esc_attr( $token ); ?>" />
        <p class="description"><?php esc_html_e( 'Optional. Provide an API token if your OpenCorporates plan requires one.', 'company-name-search' ); ?></p>
        <?php
    }

    /**
     * Renders the OpenCorporates country code field.
     */
    public function render_opencorporates_country_field() {
        $settings      = $this->get_settings();
        $country_code  = isset( $settings['opencorporates_country_code'] ) ? $settings['opencorporates_country_code'] : '';
        ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[opencorporates_country_code]" value="<?php echo esc_attr( $country_code ); ?>" />
        <p class="description"><?php esc_html_e( 'Optional two-letter country code to limit OpenCorporates results (for example: us, gb, au).', 'company-name-search' ); ?></p>
        <?php
    }

    /**
     * Renders the OpenCorporates results limit field.
     */
    public function render_results_limit_field() {
        $settings = $this->get_settings();
        $limit    = isset( $settings['opencorporates_results_limit'] ) ? (int) $settings['opencorporates_results_limit'] : 10;
        ?>
        <input type="number" min="1" max="50" step="1" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[opencorporates_results_limit]" value="<?php echo esc_attr( $limit ); ?>" />
        <p class="description"><?php esc_html_e( 'Maximum number of records to retrieve from OpenCorporates (default 10).', 'company-name-search' ); ?></p>
        <?php
    }

    /**
     * Renders the cache duration field.
     */
    public function render_cache_duration_field() {
        $settings = $this->get_settings();
        $cache    = isset( $settings['cache_duration'] ) ? (int) $settings['cache_duration'] : DAY_IN_SECONDS;
        ?>
        <input type="number" min="0" step="60" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[cache_duration]" value="<?php echo esc_attr( $cache ); ?>" />
        <p class="description"><?php esc_html_e( 'How long to cache search results (in seconds). Set to 0 to disable caching.', 'company-name-search' ); ?></p>
        <?php
    }


    /**
     * Loads the plugin text domain.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'company-name-search', false, dirname( plugin_basename( COMPANY_NAME_SEARCH_PLUGIN_FILE ) ) . '/languages' );
    }

    /**
     * Sanitises and validates settings before saving.
     *
     * @param array $input Raw settings.
     *
     * @return array
     */
    public function sanitize_settings( $input ) {
        $defaults  = self::get_default_settings();
        $sanitized = wp_parse_args( is_array( $input ) ? $input : array(), $defaults );

        $sanitized['data_source']                  = in_array( $sanitized['data_source'], array( 'local', 'opencorporates' ), true ) ? $sanitized['data_source'] : 'local';
        $sanitized['opencorporates_token']         = sanitize_text_field( $sanitized['opencorporates_token'] );
        $sanitized['opencorporates_country_code']  = sanitize_text_field( $sanitized['opencorporates_country_code'] );
        $sanitized['opencorporates_results_limit'] = max( 1, min( 50, (int) $sanitized['opencorporates_results_limit'] ) );
        $sanitized['cache_duration']               = max( 0, (int) $sanitized['cache_duration'] );

        return $sanitized;
    }

    /**
     * Retrieves plugin settings merged with defaults.
     *
     * @return array
     */
    public function get_settings() {
        return wp_parse_args( get_option( self::OPTION_NAME, array() ), self::get_default_settings() );
    }
}
