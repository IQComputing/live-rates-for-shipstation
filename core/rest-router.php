<?php
/**
 * Rest Router Controller
 * Route REST requests to various API Callbacks.
 */
namespace IQLRSS\Core;

if( ! defined( 'ABSPATH' ) ) {
	return;
}

Class Rest_Router {

	/**
	 * Initialize controller
	 *
	 * @return void
	 */
	public static function initialize() {

		$class = new self();
		$class->action_hooks();

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Action Hooks :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Add any necessary action hooks
	 *
	 * @return void
	 */
	private function action_hooks() {

        add_action( 'rest_api_init', array( $this, 'api_actions_endpoint' ) );

    }


    /**
	 * REST Endpoint to validate the users API Key and clear API caches.
	 *
	 * @return void
	 */
	public function api_actions_endpoint() {

		$prefix = \IQLRSS\Driver::get( 'slug' );

        // Route setting requests to the various callbacks.
		register_rest_route( "{$prefix}/v1", 'settings', array(
            'methods'   => array( 'POST' ),
			'callback'  => array( $this, 'route_settings_request' ),
			'permission_callback' => fn() => is_user_logged_in(),
        ) );

	}



    /**------------------------------------------------------------------------------------------------ **/
	/** :: Settings :: **/
	/**------------------------------------------------------------------------------------------------ **/
    /**
     * Settings Route
     *
     * @param WP_REST_Request $request
     */
    public function route_settings_request( $request ) {

        $params = $request->get_params();
        if( ! isset( $params['action'] ) || empty( $params['action'] ) ) {
            wp_send_json_error();
        }

        switch( $params['action'] ) {

            /**
             * Clear API Caches
             */
			case 'clearcache':
                \IQLRSS\Driver::clear_cache();
                wp_send_json_success();
            break;


            /**
             * Verify the API Key.
             */
            case 'verify':

                // Error - Unknown Type
                if( empty( $params['type'] ) || ! in_array( $params['type'], array( 'v1', 'v2' ) ) ) {
                    wp_send_json_error( esc_html__( 'System could not discern API type.', 'live-rates-for-shipstation' ), 401 );

                // Error - v1 API missing key or secret.
                } else if( 'v1' == $params['type'] && ( empty( $params['key'] ) || empty( $params['secret'] ) ) ) {
                    wp_send_json_error( esc_html__( 'The ShipStation [v1] API required both a valid [v1] key and [v1] secret.', 'live-rates-for-shipstation' ), 401 );

                // Error v2 API missing api key.
                } else if( empty( $params['key'] ) ) {
                    wp_send_json_error( esc_html__( 'The ShipStation v2 API requires an API key.', 'live-rates-for-shipstation' ), 401 );
                }

                // V1 API
                if( 'v1' == $params['type'] ) {
                    $this->api_verify_v1( array_intersect_key( $params, array_fill_keys( array(
                        'type',
                        'key',
                        'secret',
                    ), '' ) ) );
                }

                // V2 API
                $this->api_verify_v2( array_intersect_key( $params, array_fill_keys( array(
                    'type',
                    'key',
                ), '' ) ) );

            break;

        }

    }


    /**
     * Verify the v1 API Key.
     *
     * @param Array $params - Array( 'key', 'secret', 'type' )
     *
     * @return void
     */
    protected function api_verify_v1( $params ) {

        $keydata = array(
            'old' => array(
                'key'		=> \IQLRSS\Driver::get_ss_opt( 'apiv1_key' ),
                'secret'	=> \IQLRSS\Driver::get_ss_opt( 'apiv1_secret' ),
                'valid_time'=> \IQLRSS\Driver::get_ss_opt( 'apiv1_key_vt' ),
            ),
            'new' => array(
                'key'	 => sanitize_text_field( $params['key'] ),
                'secret' => ( ! empty( $params['secret'] ) ) ? sanitize_text_field( $params['secret'] ) : '',
            )
        );

        // Only allow verification once a day if the data is the same.
        if( $keydata['old']['key'] == $keydata['new']['key'] ) {

            // Return Early - We don't need to make a call, it is still valid.
            $valid_time = ( $keydata['old']['secret'] != $keydata['new']['secret'] ) ? 0 : $keydata['old']['valid_time'];
            if( ! empty( $valid_time ) && $valid_time >= gmdate( 'Ymd', strtotime( 'today' ) ) ) {
                wp_send_json_success();
            }
        }

        // The API requires the keys to exist before being pinged.
        \IQLRSS\Driver::set_ss_opt( 'apiv1_key', $keydata['new']['key'] );
        \IQLRSS\Driver::set_ss_opt( 'apiv1_secret', $keydata['new']['secret'] );

        // Ping the stores so that it sets the currently connected store ID.
        $request = ( new Api\Shipstationv1( $skip_cache = true ) )->get_stores();

        // Error - Something went wrong, the API should let us know.
        if( is_wp_error( $request ) || empty( $request ) ) {

            // Revert to old key and secret.
            \IQLRSS\Driver::set_ss_opt( 'apiv1_key', $keydata['old']['key'] );
            \IQLRSS\Driver::set_ss_opt( 'apiv1_secret', $keydata['old']['secret'] );
            wp_send_json_error(
                ( is_wp_error( $request ) ) ? $request->get_error_message() : '',
                ( is_wp_error( $request ) ) ? $request->get_error_code()    : 400,
            );

        }

        // Success! - Denote v2 validity and valid time.
        \IQLRSS\Driver::set_ss_opt( 'apiv1_key_valid', true );
        \IQLRSS\Driver::set_ss_opt( 'apiv1_key_vt', gmdate( 'Ymd', strtotime( 'today' ) ) );
        wp_send_json_success();

    }


    /**
     * Verify the v2 API Key.
     *
     * @param Array $params - Array( 'key', 'type' )
     *
     * @return void
     */
    protected function api_verify_v2( $params ) {

        $keydata = array(
            'old' => array(
                'key'		 => \IQLRSS\Driver::get_ss_opt( 'api_key' ),
                'valid_time' => \IQLRSS\Driver::get_ss_opt( 'api_key_vt' ),
            ),
            'new' => array(
                'key' => sanitize_text_field( $params['key'] ),
            )
        );

        // Only allow verification once a day if the data is the same.
        if( $keydata['old']['key'] == $keydata['new']['key'] ) {

            // Return Early - We don't need to make a call, it is still valid.
            $valid_time = $keydata['old']['valid_time'];
            if( ! empty( $valid_time ) && $valid_time >= gmdate( 'Ymd', strtotime( 'today' ) ) ) {
                wp_send_json_success();
            }
        }

        // The API requires the keys to exist before being pinged.
        \IQLRSS\Driver::set_ss_opt( 'api_key', $keydata['new']['key'] );

        // Ping the carriers so that they are cached.
        $request = ( new Api\Shipstation( $skip_cache = true ) )->get_carriers();

        // Error - Something went wrong, the API should let us know.
        if( is_wp_error( $request ) || empty( $request ) ) {

            // Revert to old key.
            \IQLRSS\Driver::get_ss_opt( 'api_key', $keydata['old']['key'] );
            wp_send_json_error(
                ( is_wp_error( $request ) ) ? $request->get_error_message() : '',
                ( is_wp_error( $request ) ) ? $request->get_error_code()    : 400
            );
        }

        // Success! - Denote v2 validity and valid time.
        \IQLRSS\Driver::set_ss_opt( 'api_key_valid', true );
        \IQLRSS\Driver::set_ss_opt( 'api_key_vt', gmdate( 'Ymd', strtotime( 'today' ) ) );
        wp_send_json_success();

    }

}