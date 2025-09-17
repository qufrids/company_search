<?php
/**
 * Handles company availability lookups.
 *
 * @package Company_Name_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides search helpers for different data sources.
 */
class Company_Name_Search_API {
    /**
     * Parent plugin instance.
     *
     * @var Company_Name_Search_Plugin
     */
    protected $plugin;

    /**
     * Cached local company data.
     *
     * @var array|null
     */
    protected $local_companies = null;

    /**
     * Constructor.
     *
     * @param Company_Name_Search_Plugin $plugin Plugin instance.
     */
    public function __construct( Company_Name_Search_Plugin $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Performs a search for the given company name.
     *
     * @param string $name Company name.
     *
     * @return array|WP_Error
     */
    public function search( $name ) {
        $name = trim( wp_unslash( $name ) );

        if ( '' === $name ) {
            return new WP_Error(
                'company_name_search_empty',
                __( 'Please provide a company name to search.', 'company-name-search' ),
                array( 'status' => 400 )
            );
        }

        $settings = $this->plugin->get_settings();
        $cache_key = 'company_name_search_' . md5( strtolower( $settings['data_source'] . '|' . $name ) );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        if ( 'opencorporates' === $settings['data_source'] ) {
            $result = $this->search_opencorporates( $name, $settings );
        } else {
            $result = $this->search_local( $name );
        }

        if ( ! is_wp_error( $result ) ) {
            $expiration = (int) $settings['cache_duration'];
            if ( $expiration > 0 ) {
                set_transient( $cache_key, $result, $expiration );
            }
        }

        /**
         * Filters the search result before it is returned.
         *
         * @param array|WP_Error $result The result array or error.
         * @param string         $name   The company name searched for.
         */
        return apply_filters( 'company_name_search_result', $result, $name );
    }

    /**
     * Returns the bundled local companies data.
     *
     * @return array
     */
    protected function get_local_companies() {
        if ( null !== $this->local_companies ) {
            return $this->local_companies;
        }

        $data = array();
        $path = COMPANY_NAME_SEARCH_PLUGIN_DIR . 'data/sample-companies.json';

        if ( file_exists( $path ) && is_readable( $path ) ) {
            $contents = file_get_contents( $path );
            if ( $contents ) {
                $decoded = json_decode( $contents, true );
                if ( is_array( $decoded ) ) {
                    $data = $decoded;
                }
            }
        }

        $this->local_companies = apply_filters( 'company_name_search_local_companies', $data );

        return $this->local_companies;
    }

    /**
     * Performs a search against the bundled local dataset.
     *
     * @param string $name Company name.
     *
     * @return array
     */
    protected function search_local( $name ) {
        $companies     = $this->get_local_companies();
        $matches       = array();
        $has_exact_hit = false;

        foreach ( $companies as $company ) {
            if ( is_array( $company ) ) {
                $company_name = isset( $company['name'] ) ? $company['name'] : '';
            } else {
                $company_name = (string) $company;
                $company      = array( 'name' => $company_name );
            }

            if ( '' === $company_name ) {
                continue;
            }

            if ( 0 === strcasecmp( $company_name, $name ) ) {
                $has_exact_hit = true;
            }

            if ( false !== stripos( $company_name, $name ) ) {
                $matches[] = array(
                    'name'               => $company_name,
                    'company_number'     => isset( $company['company_number'] ) ? $company['company_number'] : '',
                    'jurisdiction_code'  => isset( $company['jurisdiction_code'] ) ? $company['jurisdiction_code'] : '',
                    'status'             => isset( $company['status'] ) ? $company['status'] : '',
                    'incorporation_date' => isset( $company['incorporation_date'] ) ? $company['incorporation_date'] : '',
                    'source'             => __( 'Bundled sample data', 'company-name-search' ),
                );
            }
        }

        $response = array(
            'query'      => $name,
            'available'  => ! $has_exact_hit,
            'source'     => 'local',
            'matches'    => $matches,
            'fetched_at' => current_time( 'mysql' ),
        );

        if ( $response['available'] ) {
            $response['message'] = __( 'The company name does not appear in the bundled dataset.', 'company-name-search' );
        } else {
            $response['message'] = __( 'The company name already exists in the bundled dataset.', 'company-name-search' );
        }

        return $response;
    }

    /**
     * Performs a search using the OpenCorporates API.
     *
     * @param string $name     Company name.
     * @param array  $settings Plugin settings.
     *
     * @return array|WP_Error
     */
    protected function search_opencorporates( $name, $settings ) {
        $endpoint = 'https://api.opencorporates.com/v0.4/companies/search';
        $limit    = isset( $settings['opencorporates_results_limit'] ) ? (int) $settings['opencorporates_results_limit'] : 10;
        $limit    = max( 1, min( 100, $limit ) );
        $args     = array(
            'q'        => $name,
            'per_page' => $limit,
            'order'    => 'score',
        );

        if ( ! empty( $settings['opencorporates_country_code'] ) ) {
            $args['country_code'] = $settings['opencorporates_country_code'];
        }

        $url = add_query_arg( $args, $endpoint );

        $request_args = array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        );

        if ( ! empty( $settings['opencorporates_token'] ) ) {
            $request_args['headers']['Authorization'] = 'Token token=' . $settings['opencorporates_token'];
        }

        $response = wp_remote_get( $url, $request_args );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'company_name_search_http_error',
                __( 'An error occurred while contacting the OpenCorporates API.', 'company-name-search' ),
                array( 'status' => 500, 'data' => $response->get_error_message() )
            );
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( 200 !== $code ) {
            return new WP_Error(
                'company_name_search_http_status',
                __( 'Unexpected response received from the OpenCorporates API.', 'company-name-search' ),
                array( 'status' => $code, 'data' => wp_remote_retrieve_body( $response ) )
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) ) {
            return new WP_Error(
                'company_name_search_invalid_json',
                __( 'Unable to decode the OpenCorporates API response.', 'company-name-search' ),
                array( 'status' => 500 )
            );
        }

        $raw_companies = array();

        if ( isset( $body['results']['companies'] ) && is_array( $body['results']['companies'] ) ) {
            $raw_companies = $body['results']['companies'];
        }

        $matches       = array();
        $has_exact_hit = false;

        foreach ( $raw_companies as $entry ) {
            if ( ! isset( $entry['company'] ) || ! is_array( $entry['company'] ) ) {
                continue;
            }

            $company = $entry['company'];
            $name_in_result = isset( $company['name'] ) ? $company['name'] : '';

            if ( '' === $name_in_result ) {
                continue;
            }

            if ( 0 === strcasecmp( $name_in_result, $name ) ) {
                $has_exact_hit = true;
            }

            $matches[] = array(
                'name'               => $name_in_result,
                'company_number'     => isset( $company['company_number'] ) ? $company['company_number'] : '',
                'jurisdiction_code'  => isset( $company['jurisdiction_code'] ) ? $company['jurisdiction_code'] : '',
                'status'             => isset( $company['current_status'] ) ? $company['current_status'] : ( isset( $company['status'] ) ? $company['status'] : '' ),
                'incorporation_date' => isset( $company['incorporation_date'] ) ? $company['incorporation_date'] : '',
                'source'             => 'OpenCorporates',
                'registry_url'       => isset( $company['registry_url'] ) ? $company['registry_url'] : '',
            );
        }

        $response = array(
            'query'      => $name,
            'available'  => ! $has_exact_hit,
            'source'     => 'opencorporates',
            'matches'    => $matches,
            'fetched_at' => current_time( 'mysql' ),
        );

        if ( $response['available'] ) {
            $response['message'] = __( 'No exact match was found in OpenCorporates.', 'company-name-search' );
        } else {
            $response['message'] = __( 'An exact match was found in OpenCorporates.', 'company-name-search' );
        }

        if ( isset( $body['results']['total_count'] ) ) {
            $response['total_results'] = (int) $body['results']['total_count'];
        }

        return $response;
    }
}
