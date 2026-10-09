<?php
/**
 * Concurrent, read-only rate requests using WordPress's bundled Requests library.
 */
namespace IQLRSS\Core\Api;

if( ! defined( 'ABSPATH' ) ) {
	return;
}

class Rate_Transport {

	/** Whether the host can send concurrent HTTPS requests. */
	public static function supports_parallel() {
		_wp_http_get_object();
		return function_exists( 'curl_multi_init' )
			&& class_exists( '\WpOrg\Requests\Transport\Curl' )
			&& \WpOrg\Requests\Transport\Curl::test( array( 'ssl' => true ) );
	}

	/**
	 * Send one bounded batch, preserving the caller's package keys.
	 * WordPress still prepares arguments and runs pre_http_request for every call.
	 * Hosts without cURL multi use the normal WordPress HTTP transport.
	 *
	 * @param Array $requests Keyed arrays containing url and args.
	 * @return Array WordPress HTTP responses or WP_Error objects.
	 */
	public static function post_multiple( $requests ) {
		$responses = array();
		$http = _wp_http_get_object();
		$parallel = count( $requests ) > 1 && self::supports_parallel();

		if( ! $parallel ) {
			foreach( $requests as $key => $request ) {
				$responses[ $key ] = wp_remote_post( $request['url'], $request['args'] );
			}
			return $responses;
		}

		$pending = array();
		foreach( $requests as $key => $request ) {
			$prepared = null;
			$marker = new \stdClass();
			$capture = function( $pre, $args, $url ) use ( &$prepared, $marker ) {
				if( false !== $pre || ( $args['_iqlrss_rate_batch'] ?? null ) !== $marker ) {
					return $pre;
				}
				$prepared = $args;
				return new \WP_Error( 'iqlrss_rate_queued', 'Rate request queued.' );
			};
			add_filter( 'pre_http_request', $capture, PHP_INT_MAX, 3 );
			try {
				$response = wp_remote_post( $request['url'], $request['args'] + array( '_iqlrss_rate_batch' => $marker ) );
			} finally {
				remove_filter( 'pre_http_request', $capture, PHP_INT_MAX );
			}

			if( ! is_wp_error( $response ) || 'iqlrss_rate_queued' !== $response->get_error_code() || null === $prepared ) {
				$responses[ $key ] = $response;
				continue;
			}
			unset( $prepared['_iqlrss_rate_batch'] );
			$url = $request['url'];

			// These checks normally run after pre_http_request in WP_Http.
			if( $http->block_request( $url ) ) {
				$responses[ $key ] = new \WP_Error( 'http_request_not_executed', __( 'User has blocked requests through HTTP.' ) );
				continue;
			}
			if( ! empty( $prepared['reject_unsafe_urls'] ) && ! wp_http_validate_url( $url ) ) {
				$responses[ $key ] = new \WP_Error( 'http_request_failed', __( 'A valid URL was not provided.' ) );
				continue;
			}
			// Let core handle unusual transport options added by another plugin.
			if( 'POST' !== strtoupper( $prepared['method'] ) || ! empty( $prepared['stream'] ) || ! empty( $prepared['compress'] ) || empty( $prepared['blocking'] ) ) {
				$responses[ $key ] = wp_remote_post( $url, $prepared );
				continue;
			}

			$headers = $prepared['headers'];
			if( ! is_array( $headers ) ) {
				$headers = \WP_Http::processHeaders( $headers )['headers'];
			}
			$hooks = new \WP_HTTP_Requests_Hooks( $url, $prepared );
			$hooks->register( 'requests.before_redirect', array( '\WP_Http', 'browser_redirect_compatibility' ) );
			if( ! empty( $prepared['reject_unsafe_urls'] ) ) {
				$hooks->register( 'requests.before_redirect', array( '\WP_Http', 'validate_redirects' ) );
			}
			$options = array(
				'timeout'          => $prepared['timeout'],
				'connect_timeout'  => $prepared['timeout'],
				'useragent'        => $prepared['user-agent'],
				'protocol_version'=> $prepared['httpversion'],
				'follow_redirects'=> ! empty( $prepared['redirection'] ),
				'redirects'        => $prepared['redirection'],
				'verify'           => apply_filters( 'https_ssl_verify', $prepared['sslverify'] ? $prepared['sslcertificates'] : false, $url ),
				'verifyname'       => (bool)$prepared['sslverify'],
				'data_format'      => 'body',
				'hooks'            => $hooks,
			);
			if( isset( $prepared['limit_response_size'] ) ) {
				$options['max_bytes'] = $prepared['limit_response_size'];
			}
			if( ! empty( $prepared['cookies'] ) ) {
				$options['cookies'] = \WP_Http::normalize_cookies( $prepared['cookies'] );
			}
			$proxy = new \WP_HTTP_Proxy();
			if( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) ) {
				$options['proxy'] = new \WpOrg\Requests\Proxy\Http( $proxy->host() . ':' . $proxy->port() );
				if( $proxy->use_authentication() ) {
					$options['proxy']->use_authentication = true;
					$options['proxy']->user = $proxy->username();
					$options['proxy']->pass = $proxy->password();
				}
			}
			$pending[ $key ] = array( 'url' => $url, 'headers' => $headers, 'data' => $prepared['body'], 'type' => 'POST', 'options' => $options );
			$requests[ $key ]['args'] = $prepared;
		}

		if( empty( $pending ) ) {
			return $responses;
		}
		try {
			$results = \WpOrg\Requests\Requests::request_multiple( $pending, array( 'transport' => new \WpOrg\Requests\Transport\Curl() ) );
		} catch( \WpOrg\Requests\Exception $e ) {
			$results = array_fill_keys( array_keys( $pending ), $e );
		}
		foreach( $pending as $key => $request ) {
			$result = $results[ $key ] ?? null;
			if( $result instanceof \WpOrg\Requests\Response ) {
				$wrapper = new \WP_HTTP_Requests_Response( $result );
				$response = $wrapper->to_array();
				$response['http_response'] = $wrapper;
			} else {
				$response = new \WP_Error( 'http_request_failed', $result instanceof \Exception ? $result->getMessage() : 'Missing rate response.' );
			}
			do_action( 'http_api_debug', $response, 'response', 'WpOrg\\Requests\\Requests', $requests[ $key ]['args'], $request['url'] );
			$responses[ $key ] = is_wp_error( $response ) ? $response : apply_filters( 'http_response', $response, $requests[ $key ]['args'], $request['url'] );
		}
		return $responses;
	}
}
