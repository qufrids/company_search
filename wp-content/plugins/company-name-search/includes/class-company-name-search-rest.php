<?php
/**
 * REST API controller for the company name search endpoint.
 *
 * @package Company_Name_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers REST routes.
 */
class Company_Name_Search_REST {
    /**
     * API helper instance.
     *
     * @var Company_Name_Search_API
     */
    protected $api;

    /**
     * Constructor.
     *
     * @param Company_Name_Search_API $api API helper.
     */
    public function __construct( Company_Name_Search_API $api ) {
        $this->api = $api;
    }

    /**
     * Registers REST routes.
     */
    public function register_routes() {
        register_rest_route(
            'company-name-search/v1',
            '/check',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_search' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'name' => array(
                        'description'       => __( 'Company name to search for.', 'company-name-search' ),
                        'required'          => true,
                        'sanitize_callback' => array( $this, 'sanitize_name' ),
                    ),
                ),
            )
        );
    }

    /**
     * Sanitises the search name parameter.
     *
     * @param string $value Raw value.
     *
     * @return string
     */
    public function sanitize_name( $value ) {
        return sanitize_text_field( $value );
    }

    /**
     * Handles the REST request.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_search( WP_REST_Request $request ) {
        $name = $request->get_param( 'name' );

        if ( '' === trim( (string) $name ) ) {
            return new WP_Error(
                'company_name_search_empty',
                __( 'Please provide a company name to search.', 'company-name-search' ),
                array( 'status' => 400 )
            );
        }

        $result = $this->api->search( $name );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }
}
