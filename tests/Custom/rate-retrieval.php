<?php
/**
 * Rate retrieval regression tests using real WordPress HTTP classes.
 *
 * Run: IQLRSS_WP_ROOT=/path/to/wordpress php tests/rate-retrieval.php
 * No WordPress database or ShipStation credentials are used.
 */
namespace IQLRSS {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    class Driver {
        public static $options = array();
        public static function get( $key ) { return 'version' === $key ? '1.2.17' : 'iqlrss'; }
        public static function get_opt( $key, $default = '' ) { return $default; }
        public static function get_ss_opt( $key, $default = '', $skip = false ) { return self::$options[$key] ?? $default; }
        public static function plugin_prefix( $key ) { return 'iqlrss_' . $key; }
    }
}
namespace {
    $wp_root = getenv('IQLRSS_WP_ROOT');
    if (!$wp_root || !is_file($wp_root . '/wp-includes/class-wp-http.php')) {
        fwrite(STDERR, "Set IQLRSS_WP_ROOT to an extracted WordPress installation.\n");
        exit(1);
    }
    define('ABSPATH', rtrim(str_replace('\\', '/', $wp_root), '/') . '/');
    define('WPINC', 'wp-includes');
    define('WP_DEBUG', false);

    require ABSPATH . WPINC . '/compat.php';
    require ABSPATH . WPINC . '/plugin.php';
    require ABSPATH . WPINC . '/class-wp-error.php';
    require ABSPATH . WPINC . '/class-wp-http.php';
    require ABSPATH . WPINC . '/class-wp-http-response.php';
    require ABSPATH . WPINC . '/class-wp-http-requests-response.php';
    require ABSPATH . WPINC . '/class-wp-http-requests-hooks.php';
    require ABSPATH . WPINC . '/class-wp-http-cookie.php';
    require ABSPATH . WPINC . '/class-wp-http-proxy.php';
    require ABSPATH . WPINC . '/class-wp-http-encoding.php';
    require ABSPATH . WPINC . '/http.php';

    function __($s, $domain = '') { return $s; }
    function esc_html__($s, $domain = '') { return $s; }
    function esc_url($s) { return $s; }
    function get_status_header_desc($code) { return $code === 200 ? 'OK' : 'Error'; }
    function absint($n) { return abs((int)$n); }
    function is_wp_error($value) { return $value instanceof \WP_Error; }
    function get_bloginfo($key) { return $key === 'name' ? 'Test store' : ($key === 'version' ? '6.5' : 'https://example.test'); }
    function get_option($key, $default = false) { return $GLOBALS['test_options'][$key] ?? $default; }
    function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, $args); }
    function wp_json_encode($data) { return json_encode($data); }
    function get_transient($key) { return $GLOBALS['test_transients'][$key] ?? false; }
    function set_transient($key, $value, $ttl) { $GLOBALS['test_transients'][$key] = $value; $GLOBALS['test_ttls'][$key] = $ttl; return true; }
    function delete_transient($key) { unset($GLOBALS['test_transients'][$key]); }
    function mbstring_binary_safe_encoding() {}
    function reset_mbstring_encoding() {}
    function wp_is_stream($path) { return false; }
    function WC() { return (object) array('customer' => null); }

    require dirname(__DIR__) . '/core/traits/logger.php';
    require dirname(__DIR__) . '/core/api/rate-transport.php';
    require dirname(__DIR__) . '/core/api/shipstation.php';

    require dirname(__DIR__) . '/core/classes/shipping-calculator.php';

    set_error_handler(function($level, $message, $file, $line) {
        if (!(error_reporting() & $level)) return false;
        // WordPress 6.5 predates PHP 8.4; ignore only upstream deprecations.
        if ($level === E_DEPRECATED && strpos(str_replace('\\', '/', $file), ABSPATH) === 0) return true;
        throw new \ErrorException($message, 0, $level, $file, $line);
    });

    function check($condition, $message) {
        if (!$condition) throw new \RuntimeException($message);
    }
    function equal($actual, $expected, $message) {
        check($actual === $expected, $message . ': ' . var_export($actual, true) . ' != ' . var_export($expected, true));
    }
    function rate($amount = 10, $carrier = 'se-ups', $service = 'ups_ground') {
        return array(
            'service_type' => 'Ground', 'service_code' => $service, 'carrier_id' => $carrier,
            'carrier_code' => $carrier === 'se-ups' ? 'ups' : 'fedex', 'carrier_friendly_name' => 'Carrier',
            'shipping_amount' => array('amount' => $amount, 'currency' => 'usd'),
            'other_amount' => array('amount' => 2, 'currency' => 'usd'),
        );
    }
    function response($body, $status = 200, $headers = array()) {
        return array('body' => json_encode($body), 'response' => array('code' => $status, 'message' => ''),
            'headers' => $headers, 'cookies' => array(), 'filename' => null);
    }
    function service($overrides = array()) {
        return array_merge(array('enabled' => true, 'service_code' => 'ups_ground', 'carrier_id' => 'se-ups',
            'carrier_code' => 'ups', 'service_name' => 'Ground'), $overrides);
    }
    function package($overrides = array()) {
        return array_merge(array('quantity' => 1, 'weight' => array('value' => 2, 'unit' => 'pound'),
            'dimensions' => array('length' => 10, 'width' => 5, 'height' => 4, 'unit' => 'inch')), $overrides);
    }
    class Fixture_API extends \IQLRSS\Core\Api\Shipstation {
        public $carriers = array();
        public function get_carrier($id) {
            return $this->carriers[$id] ?? array(
                'carrier' => array('carrier_code' => 'ups'),
                'services' => array(array('service_code' => 'ups_ground', 'is_multi_package_supported' => true)),
            );
        }
        public function get_warehouse($id) {
            return array('origin_address' => array('name' => 'Warehouse', 'phone' => '5555550100',
                'address_line1' => '10 Warehouse St', 'postal_code' => '63101', 'country_code' => 'US',
                'city_locality' => 'St Louis', 'state_province' => 'MO'));
        }
    }
    class Fixture_Calculator extends \IQLRSS\Core\Classes\Shipping_Calculator {
        public $client;
        public function __construct($packages, $settings = array(), $client = null) {
            $this->client = $client ?: new Fixture_API();
            $this->packed = $packages;
            $this->datatype = 'products';
            $this->args = array_merge(array(
                'carrier_ids' => array('se-ups'),
                'services_enabled' => array('se-ups' => array('ups_ground' => service())),
                'from' => array('name' => 'Sender', 'phone' => '5555550100', 'address_1' => '10 Main St'),
                'to' => array('name' => 'Recipient', 'phone' => '5555550101', 'address_1' => '20 Main St'),
            ), $settings);
            $this->requests['base'] = array(
                'from_country_code' => 'US', 'from_postal_code' => '63101', 'from_city_locality' => 'St Louis', 'from_state_province' => 'MO',
                'to_country_code' => 'US', 'to_postal_code' => '60601', 'to_city_locality' => 'Chicago', 'to_state_province' => 'IL',
                'address_residential_indicator' => 'unknown',
            );
        }
        public function get($key, $default = '') {
            if (array_key_exists($key, $this->args)) return $this->args[$key];
            return parent::get($key, $default);
        }
        protected function api() { return $this->client; }
        public function run() { $this->setup_rates(); return array_values($this->rates); }
        public function base($key, $value) { $this->requests['base'][$key] = $value; }
    }

    class WC_Logger {
        public function log($level, $message, $context) {
            $GLOBALS['test_logs'][] = array('level' => $level, 'message' => $message, 'context' => $context);
        }
    }
    function wc_get_logger() { return new WC_Logger(); }
    function timing_logs($message) {
        return array_values(array_filter($GLOBALS['test_logs'], fn($entry) => $entry['message'] === $message));
    }
    // Four-package fixture based on the reported cart before mixed-product consolidation.
    function maximum_linear_packages() {
        $rows = array(
            array('Maul', 13.65, 36, 8.75, 2.5, 1),
            array('Crook', 3, 62, 7, 2, 1),
            array('Hammer handle', .53, 16, 1.75, 1.25, 1),
            array('Rake', 6, 48.5, 24.5, 5, 2),
        );
        $packages = array();
        foreach ($rows as $row) $packages[] = array(
            'weight' => array('value' => $row[1], 'unit' => 'pound'),
            'dimensions' => array('length' => $row[2], 'width' => $row[3], 'height' => $row[4], 'unit' => 'inch'),
            'packed' => array_fill(0, $row[5], $row[0]),
        );
        return $packages;
    }
    class Timing_Calculator extends Fixture_Calculator {
        public function setup_base() {}
        public function setup_packages() {}
    }
    class Local_Fixture_API extends Fixture_API {
        public $address;
        protected function get_endpoint_url($endpoint) { return 'http://' . $this->address . '/'; }
        protected function request_args($args = null) {
            $args['delay'] = .3;
            $args['response'] = array(rate($args['weight']['value']));
            return parent::request_args($args);
        }
    }

    $tests = array();
    $tests['estimates preserve package keys, filter presets, and charge every box'] = function() {
        $GLOBALS['mock_http'] = function($url, $args) {
            equal(strpos($url, 'rates/estimate') !== false, true, 'Estimate endpoint');
            $body = json_decode($args['body'], true);
            $r = rate($body['weight']['value'] * 10);
            return response(array($r));
        };
        $calc = new Fixture_Calculator(array('box-a' => package(), 8 => package(array('weight' => array('value' => 3, 'unit' => 'pound')))),
            array('to' => array()));
        $rates = $calc->run();
        equal(count($rates), 1, 'One service');
        equal(array_sum($rates[0]['cost']), 54.0, 'Each package cost and surcharge');
        equal(count($rates[0]['meta_data']['boxes']), 2, 'All boxes covered');
    };
    $tests['missing one package never publishes an incomplete rate'] = function() {
        $GLOBALS['mock_http'] = function($url, $args) {
            $body = json_decode($args['body'], true);
            return response($body['weight']['value'] === 3 ? array() : array(rate()));
        };
        $calc = new Fixture_Calculator(array(package(), package(array('weight' => array('value' => 3, 'unit' => 'pound')))), array('to' => array()));
        equal($calc->run(), array(), 'Incomplete service discarded');
    };
    $tests['Quick Rates filters services and charges an aggregate exactly once'] = function() {
        $GLOBALS['mock_http'] = function($url, $args) {
            $body = json_decode($args['body'], true);
            equal($body['rate_options']['rate_type'], 'quick', 'Quick mode');
            equal($body['rate_options']['service_codes'], array('ups_ground'), 'Only enabled service');
            equal(count($body['shipment']['packages']), 2, 'Combined packages');
            check(!isset($body['shipment']['packages'][0]['price']), 'No local box fee sent');
            check(!isset($body['shipment_id']), 'No shipment persisted');
            $r = rate(25);
            $r['rate_id'] = null;
            $r['confirmation_amount'] = array('amount' => 3, 'currency' => 'usd');
            $r['insurance_amount'] = array('amount' => 4, 'currency' => 'usd');
            return response(array('rate_response' => array('rates' => array($r))));
        };
        $calc = new Fixture_Calculator(array(package(array('price' => 2)), package(array('price' => 3))), array(
            'packing' => 'wc-box-packer', 'services_enabled' => array('se-ups' => array('ups_ground' => service(array('adjustment_type' => 'flat', 'adjustment' => 5)))),
        ));
        $rates = $calc->run();
        equal(array_sum($rates[0]['cost']), 49.0, '25 + 2 + 3 + 4 + box fees 5 + fixed fees 10');
        equal(count($rates[0]['meta_data']['rates']), 1, 'One real shipment quote');
        equal(count($rates[0]['meta_data']['boxes']), 2, 'Retain physical boxes');
        equal($rates[0]['meta_data']['rates'][0]['adjustment']['cost'], 10.0, 'Fixed fee per box');
    };
    $tests['aggregate percentage applies once to shipping, not surcharges'] = function() {
        $GLOBALS['mock_http'] = fn($url, $args) => response(array('rate_response' => array('rates' => array(rate(30)))));
        $calc = new Fixture_Calculator(array(package(), package()), array(
            'services_enabled' => array('se-ups' => array('ups_ground' => service(array('adjustment_type' => 'percentage', 'adjustment' => 10)))),
        ));
        $rates = $calc->run();
        equal(array_sum($rates[0]['cost']), 35.0, '30 + 10% + 2');
    };
    $tests['quantity groups use per-package Quick Rates and retain multiplication'] = function() {
        $GLOBALS['mock_http'] = function($url, $args) {
            $body = json_decode($args['body'], true);
            equal(count($body['shipment']['packages']), 1, 'Separate quantity group');
            return response(array('rate_response' => array('rates' => array(rate()))));
        };
        $calc = new Fixture_Calculator(array(package(array('quantity' => 3)), package()));
        $rates = $calc->run();
        equal(array_sum($rates[0]['cost']), 48.0, 'Four physical boxes, each 10 + 2');
    };
    $tests['unsupported multi-package service uses concurrent individual Quick Rates'] = function() {
        $api = new Fixture_API();
        $api->carriers['se-ups'] = array('carrier' => array('carrier_code' => 'ups'),
            'services' => array(array('service_code' => 'ups_ground', 'is_multi_package_supported' => false)));
        $GLOBALS['mock_http'] = function($url, $args) {
            equal(count(json_decode($args['body'], true)['shipment']['packages']), 1, 'Single package only');
            return response(array('rate_response' => array('rates' => array(rate()))));
        };
        $rates = (new Fixture_Calculator(array(package(), package()), array(), $api))->run();
        equal(array_sum($rates[0]['cost']), 24.0, 'Both separate packages charged');
    };
    $tests['validation failure falls back to estimates'] = function() {
        $GLOBALS['mock_http'] = function($url, $args) {
            return strpos($url, 'estimate') !== false ? response(array(rate())) : response(array('errors' => array(array('message' => 'Unsupported quick shipment'))), 422);
        };
        $rates = (new Fixture_Calculator(array(package(), package())))->run();
        equal(array_sum($rates[0]['cost']), 24.0, 'Estimate fallback');
        equal(count($GLOBALS['http_calls']), 3, 'One combined attempt then two estimates');
    };
    $tests['authentication failures do not generate fallback traffic'] = function() {
        $GLOBALS['mock_http'] = fn($url, $args) => response(array(), 401);
        equal((new Fixture_Calculator(array(package(), package())))->run(), array(), 'No rates');
        equal(count($GLOBALS['http_calls']), 1, 'No retries for invalid credentials');
    };
    $tests['missing carrier falls back without duplicating successful shipment totals'] = function() {
        $api = new Fixture_API();
        $api->carriers['se-fedex'] = array('carrier' => array('carrier_code' => 'fedex'),
            'services' => array(array('service_code' => 'fedex_ground', 'is_multi_package_supported' => true)));
        $GLOBALS['mock_http'] = function($url, $args) {
            $body = json_decode($args['body'], true);
            if (strpos($url, 'estimate') !== false) {
                equal($body['carrier_ids'], array('se-fedex'), 'Only missing carrier retried');
                return response(array(rate(15, 'se-fedex', 'fedex_ground')));
            }
            return response(array('rate_response' => array('rates' => array(rate(25)))));
        };
        $calc = new Fixture_Calculator(array(package(), package()), array('carrier_ids' => array('se-ups', 'se-fedex'),
            'services_enabled' => array('se-ups' => array('ups_ground' => service()), 'se-fedex' => array('fedex_ground' => service(array('carrier_id' => 'se-fedex', 'carrier_code' => 'fedex', 'service_code' => 'fedex_ground'))))), $api);
        $rates = $calc->run();
        equal(count($rates), 2, 'Both carriers remain available');
        equal(array_sum($rates[0]['cost']), 27.0, 'UPS shipment once');
        equal(array_sum($rates[1]['cost']), 34.0, 'FedEx all boxes');
    };
    $tests['international and incomplete checkout use estimates directly'] = function() {
        $GLOBALS['mock_http'] = function($url, $args) {
            check(strpos($url, 'estimate') !== false, 'Estimate without customs');
            return response(array(rate()));
        };
        $calc = new Fixture_Calculator(array(package()));
        $calc->base('to_country_code', 'CA');
        $calc->run();
        equal(count($GLOBALS['http_calls']), 1, 'No speculative Quick Rates attempt');
    };
    $tests['unknown extension options survive estimate fallback'] = function() {
        $GLOBALS['mock_http'] = function($url, $args) {
            $body = json_decode($args['body'], true);
            equal($body['custom_option'], 'keep-me', 'Extension option retained');
            check(strpos($url, 'estimate') !== false, 'Avoid silently dropping extension field');
            return response(array(rate()));
        };
        (new Fixture_Calculator(array(package(array('custom_option' => 'keep-me')))))->run();
    };
    $tests['rate limit stops later batches and honors Retry-After'] = function() {
        $GLOBALS['mock_http'] = fn($url, $args) => response(array(), 429, array('retry-after' => '90'));
        $api = new Fixture_API();
        $results = $api->get_shipping_rates_batch(array_fill(0, 8, array('weight' => array('value' => 1, 'unit' => 'pound'))));
        equal(count($GLOBALS['http_calls']), 3, 'Only initial bounded batch sent');
        equal(count($results), 8, 'Every request represented');
        foreach ($results as $r) equal($r->get_error_data()['status'], 429, 'Rate limit preserved');
        check(max($GLOBALS['test_ttls']) >= 89, 'Honor cooldown');
        $api->get_shipping_rates_batch(array(array()));
        equal(count($GLOBALS['http_calls']), 3, 'Subsequent calculation waits for cooldown');
    };
    $tests['HTTP filters can preempt concurrent calls'] = function() {
        $GLOBALS['mock_http'] = fn($url, $args) => new \WP_Error('blocked-by-filter', 'Blocked');
        $results = (new Fixture_API())->get_shipping_rates_batch(array('first' => array(), 'second' => array()));
        equal($results['first']->get_error_code(), 'blocked-by-filter', 'Respect pre_http_request');
        equal($results['second']->get_error_code(), 'blocked-by-filter', 'Respect every preemption');
    };
    $tests['malformed and zero-dollar quotes are excluded'] = function() {
        $GLOBALS['mock_http'] = fn($url, $args) => response(array(array(), 'bad', rate(0), rate(12)));
        $results = (new Fixture_API())->get_shipping_rates_batch(array('a' => array()));
        equal(count($results['a']), 1, 'Only valid positive quote');
        equal($results['a'][0]['cost'], 12, 'Correct remaining quote');
    };


    $tests['real concurrent transport preserves keys and WordPress HTTP hooks'] = function() {
        if (!function_exists('curl_multi_init')) return false;
        $process = proc_open(array(PHP_BINARY, __DIR__ . '/http-fixture.php'),
            array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        check(is_resource($process), 'Fixture started');
        try {
            $address = trim(fgets($pipes[1]));
            check((bool)preg_match('/^127\.0\.0\.1:\d+$/', $address), 'Local fixture address');
            $GLOBALS['mock_http'] = fn($url, $args) => false;
            $args_hook = function($args, $url) {
                $args['headers']['X-Test-Filter'] = 'preserved';
                return $args;
            };
            $response_hook = function($response, $args, $url) {
                $response['headers']['x-response-filter'] = 'preserved';
                return $response;
            };
            $debug_count = 0;
            $debug_hook = function() use (&$debug_count) { $debug_count++; };
            add_filter('http_request_args', $args_hook, 10, 2);
            add_filter('http_response', $response_hook, 10, 3);
            add_action('http_api_debug', $debug_hook);
            $requests = array();
            foreach (array('slow' => 0.6, 'fast' => 0.2, 9 => 0.4) as $id => $delay) {
                $requests[$id] = array('url' => 'http://' . $address . '/', 'args' => array(
                    'headers' => array('Content-Type' => 'application/json'),
                    'body' => json_encode(array('id' => $id, 'delay' => $delay)), 'timeout' => 3,
                ));
            }
            $start = microtime(true);
            $parallel = \IQLRSS\Core\Api\Rate_Transport::post_multiple($requests);
            $parallel_time = microtime(true) - $start;
            $active = 0;
            foreach ($requests as $id => $request) {
                check(!is_wp_error($parallel[$id]), 'Successful concurrent response');
                $body = json_decode($parallel[$id]['body'], true);
                equal($body['id'], $id, 'Response stays with original key');
                check(strpos($body['headers'], 'X-Test-Filter: preserved') !== false, 'Request filter honored');
                equal($parallel[$id]['headers']['x-response-filter'], 'preserved', 'Response filter honored');
                $active = max($active, $body['active']);
            }
            equal($active, 3, 'Three simultaneously active requests');
            equal($debug_count, 3, 'One WordPress debug hook per response');
            $start = microtime(true);
            foreach ($requests as $request) wp_remote_post($request['url'], $request['args']);
            $serial_time = microtime(true) - $start;
            check($parallel_time < $serial_time * 0.8, 'Parallel requests reduce accumulated wait');
            printf("Timing: concurrent %.3fs; sequential %.3fs (local simulated latency).\n", $parallel_time, $serial_time);
            remove_filter('http_request_args', $args_hook, 10);
            remove_filter('http_response', $response_hook, 10);
            remove_action('http_api_debug', $debug_hook, 10);
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) fclose($pipe);
            proc_close($process);
        }
    };
    $tests['configured concurrency caps each dispatched batch'] = function() {
        // The first response limits the account; exactly the configured first
        // batch may complete, while later jobs receive the shared cooldown.
        $limit = fn() => 2;
        add_filter('iqlrss/api/rate_concurrency', $limit);
        $GLOBALS['mock_http'] = fn($url, $args) => response(array(), 429, array('retry-after' => '30'));
        (new Fixture_API())->get_shipping_rates_batch(array_fill(0, 7, array()));
        equal(count($GLOBALS['http_calls']), 2, 'Configured batch size');
        remove_filter('iqlrss/api/rate_concurrency', $limit);
    };
    $tests['missing required phone bypasses Quick Rates instead of making a failing call'] = function() {
        $GLOBALS['mock_http'] = function($url, $args) {
            check(strpos($url, 'estimate') !== false, 'Use estimates for incomplete contact');
            return response(array(rate()));
        };
        (new Fixture_Calculator(array(package()), array('from' => array('name' => 'Sender', 'address_1' => '10 Main St'))))->run();
        equal(count($GLOBALS['http_calls']), 1, 'No wasted Quick Rates request');
    };
    $tests['store phone setting enables full store-address Quick Rates'] = function() {
        \IQLRSS\Driver::$options['store_phone'] = '5555550100';
        $GLOBALS['test_options']['woocommerce_store_address'] = '1 Store St';
        $GLOBALS['mock_http'] = function($url, $args) {
            $body = json_decode($args['body'], true);
            equal($body['shipment']['ship_from']['phone'], '5555550100', 'Use configured store contact');
            equal($body['shipment']['ship_from']['address_line1'], '1 Store St', 'Use store address');
            return response(array('rate_response' => array('rates' => array(rate()))));
        };
        (new Fixture_Calculator(array(package()), array('from' => array())))->run();
    };


    $tests['single-package partial carrier response merges only missing-carrier estimates'] = function() {
        $GLOBALS['mock_http'] = function($url, $args) {
            $body = json_decode($args['body'], true);
            if (strpos($url, 'estimate') !== false) {
                equal($body['carrier_ids'], array('se-fedex'), 'Only unquoted carrier requested');
                return response(array(rate(15, 'se-fedex', 'fedex_ground')));
            }
            return response(array('rate_response' => array('rates' => array(rate(10)))));
        };
        $settings = array('carrier_ids' => array('se-ups', 'se-fedex'), 'services_enabled' => array(
            'se-ups' => array('ups_ground' => service()),
            'se-fedex' => array('fedex_ground' => service(array('carrier_id' => 'se-fedex', 'carrier_code' => 'fedex', 'service_code' => 'fedex_ground'))),
        ));
        $rates = (new Fixture_Calculator(array(package()), $settings))->run();
        equal(count($rates), 2, 'Both carrier quotes retained');
        equal(array_sum($rates[0]['cost']), 12.0, 'Quick Rate retained');
        equal(array_sum($rates[1]['cost']), 17.0, 'Missing carrier estimate added');
    };
    $tests['embedded throttling does not trigger estimate retries'] = function() {
        $GLOBALS['mock_http'] = fn($url, $args) => response(array('rate_response' => array(
            'rates' => array(), 'errors' => array(array('error_code' => 'rate_limit_exceeded')),
        )));
        equal((new Fixture_Calculator(array(package(), package())))->run(), array(), 'No available quotes');
        equal(count($GLOBALS['http_calls']), 1, 'No retries during embedded cooldown');
    };
    $tests['global fixed adjustment is retained per packed box on aggregate quote'] = function() {
        \IQLRSS\Driver::$options['global_adjustment_type'] = 'flat';
        \IQLRSS\Driver::$options['global_adjustment'] = 4;
        $GLOBALS['mock_http'] = fn($url, $args) => response(array('rate_response' => array('rates' => array(rate(30)))));
        $rates = (new Fixture_Calculator(array(package(), package(), package())))->run();
        equal(array_sum($rates[0]['cost']), 44.0, '30 + 2 + three fixed adjustments');
    };
    $tests['warehouse contact and street are used together with its origin'] = function() {
        $GLOBALS['mock_http'] = function($url, $args) {
            $body = json_decode($args['body'], true);
            equal($body['shipment']['ship_from']['address_line1'], '10 Warehouse St', 'Warehouse street');
            equal($body['shipment']['ship_from']['phone'], '5555550100', 'Warehouse phone');
            equal($body['shipment']['ship_from']['postal_code'], '63101', 'Warehouse postcode');
            return response(array('rate_response' => array('rates' => array(rate()))));
        };
        (new Fixture_Calculator(array(package()), array('warehouse' => 'se-warehouse')))->run();
    };
    $tests['a package named shipment cannot masquerade as an aggregate'] = function() {
        $GLOBALS['mock_http'] = fn($url, $args) => response(array('rate_response' => array('rates' => array(rate()))));
        $rates = (new Fixture_Calculator(array('shipment' => package(array('quantity' => 2)))))->run();
        equal(array_sum($rates[0]['cost']), 24.0, 'Quantity group preserved');
    };

    $tests['Quick Rates retains service matching after carrier account IDs change'] = function() {
        $GLOBALS['mock_http'] = function($url, $args) {
            $body = json_decode($args['body'], true);
            equal($body['rate_options']['carrier_ids'], array('se-ups'), 'Current carrier ID');
            equal($body['rate_options']['service_codes'], array('ups_ground'), 'Saved service retained');
            return response(array('rate_response' => array('rates' => array(rate(25)))));
        };
        $settings = array('services_enabled' => array('se-old' => array('ups_ground' => service(array('carrier_id' => 'se-old')))));
        $rates = (new Fixture_Calculator(array(package(), package()), $settings))->run();
        equal(count($rates), 1, 'Reconnected account still resolves');
        equal(array_sum($rates[0]['cost']), 27.0, 'Shipment quoted once');
    };
    $tests['duplicate Quick Rates choose one complete shipment quote'] = function() {
        $GLOBALS['mock_http'] = fn($url, $args) => response(array('rate_response' => array('rates' => array(rate(35), rate(25)))));
        $rates = (new Fixture_Calculator(array(package(), package())))->run();
        equal(count($rates), 1, 'One carrier/service');
        equal(array_sum($rates[0]['cost']), 27.0, 'Lowest complete shipment quote');
        equal(count($rates[0]['meta_data']['boxes']), 2, 'Coverage unchanged by duplicate quotes');
    };

    $tests['four package quotes retain every item across three-request batches'] = function() {
        \IQLRSS\Driver::$options['logging_enabled'] = 'yes';
        \IQLRSS\Driver::$options['log_types'] = array('debug');
        $GLOBALS['mock_http'] = function($url, $args) {
            check(strpos($url, 'rates/estimate') !== false, 'Incomplete checkout uses estimates');
            return response(array(rate(json_decode($args['body'], true)['weight']['value'])));
        };
        $calc = new Timing_Calculator(maximum_linear_packages(), array('to' => array(), 'packing' => 'max_linear_measurement'));
        $rates = $calc->get_rates();
        equal(count($rates), 1, 'One complete service');
        check(abs(array_sum($rates[0]['cost']) - 31.18) < .00001, 'All four fixture quotes and surcharges included');
        $boxes = json_decode($rates[0]['meta_data']['boxes'], true);
        equal(count($boxes), 4, 'Four physical boxes');
        equal(array_sum(array_map(fn($box) => count($box['packed']), $boxes)), 5, 'All five items preserved');
        $retrieval = timing_logs('ShipStation rate retrieval timing');
        equal(count($retrieval), 1, 'One retrieval summary');
        $timing = $retrieval[0]['context'];
        equal($timing['request_count'], 4, 'Four distinct requests');
        equal($timing['dispatched_requests'], 4, 'All four requests dispatched');
        equal($timing['batch_count'], 2, 'Fourth request waits for the next batch');
        equal($timing['concurrency_limit'], 3, 'Default concurrency');
        equal($timing['error_count'], 0, 'No errors');
        $calculation = timing_logs('Shipping calculation timing');
        equal(count($calculation), 1, 'One calculation summary');
        $timing = $calculation[0]['context'];
        equal($timing['packing_mode'], 'max_linear_measurement', 'Custom packing mode reported');
        equal($timing['packed_packages'], 4, 'Box count reported');
        equal($timing['returned_services'], 1, 'Service count reported');
        foreach (array('base_ms', 'packing_ms', 'rates_ms', 'total_ms') as $key) {
            check(is_numeric($timing[$key]) && $timing[$key] >= 0, 'Nonnegative phase time: ' . $key);
        }
        check(!isset($timing['args'], $timing['api_key'], $timing['address']), 'Summary does not include payloads');
    };
    $tests['timing summaries respect disabled logging and selected levels'] = function() {
        $GLOBALS['mock_http'] = fn($url, $args) => response(array(rate()));
        foreach (array(array('no', array('debug')), array('yes', array('error'))) as $settings) {
            \IQLRSS\Driver::$options['logging_enabled'] = $settings[0];
            \IQLRSS\Driver::$options['log_types'] = $settings[1];
            (new Timing_Calculator(maximum_linear_packages(), array('to' => array())))->get_rates();
            equal($GLOBALS['test_logs'], array(), 'Logging preferences honored');
        }
    };
    $tests['Quick Rates fallback has separate retrieval timing summaries'] = function() {
        \IQLRSS\Driver::$options['logging_enabled'] = 'yes';
        \IQLRSS\Driver::$options['log_types'] = array('debug');
        $GLOBALS['mock_http'] = fn($url, $args) => strpos($url, 'estimate') !== false
            ? response(array(rate())) : response(array('errors' => array(array('message' => 'Unsupported shipment'))), 422);
        (new Fixture_Calculator(maximum_linear_packages()))->run();
        $logs = timing_logs('ShipStation rate retrieval timing');
        equal(count($logs), 2, 'Quick attempt and estimate fallback are visible');
        equal(array_column(array_column($logs, 'context'), 'rate_type'), array('quick', 'estimate'), 'Correct endpoints');
        equal(array_column(array_column($logs, 'context'), 'dispatched_requests'), array(1, 4), 'One shipment then four package estimates');
        equal(array_column(array_column($logs, 'context'), 'batch_count'), array(1, 2), 'One Quick Rates batch and two estimate batches');
    };
    $tests['three-request batches retrieve all four real HTTP package quotes'] = function() {
        if (!\IQLRSS\Core\Api\Rate_Transport::supports_parallel()) return false;
        $process = proc_open(array(PHP_BINARY, __DIR__ . '/http-fixture.php'),
            array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        check(is_resource($process), 'Fixture started');
        $limit = fn() => 1;
        try {
            $address = trim(fgets($pipes[1]));
            check((bool)preg_match('/^127\\.0\\.0\\.1:\\d+$/', $address), 'Local fixture address');
            $GLOBALS['mock_http'] = fn($url, $args) => false;
            $api = new Local_Fixture_API();
            $api->address = $address;
            $start = microtime(true);
            $current = (new Fixture_Calculator(maximum_linear_packages(), array('to' => array()), $api))->run();
            $current_time = microtime(true) - $start;
            add_filter('iqlrss/api/rate_concurrency', $limit);
            $start = microtime(true);
            $previous = (new Fixture_Calculator(maximum_linear_packages(), array('to' => array()), $api))->run();
            $previous_time = microtime(true) - $start;
            equal(count($current), 1, 'Complete quote returned over real HTTP');
            equal($current, $previous, 'Changing concurrency preserves every rate and box');
            check($current_time < $previous_time * .8, 'Three simultaneous requests reduce the wait compared with sequential requests');
            printf("Four-box timing: default three %.3fs; sequential %.3fs (local simulated latency).\n", $current_time, $previous_time);
        } finally {
            remove_filter('iqlrss/api/rate_concurrency', $limit);
            proc_terminate($process);
            foreach ($pipes as $pipe) fclose($pipe);
            proc_close($process);
        }
    };

    $tests['WordPress external-request blocking also applies to concurrent transport'] = function() {
        $GLOBALS['mock_http'] = fn($url, $args) => false;
        $GLOBALS['test_options']['siteurl'] = 'https://example.test';
        define('WP_HTTP_BLOCK_EXTERNAL', true);
        $requests = array(
            'a' => array('url' => 'https://blocked.invalid/rates', 'args' => array('body' => '{}')),
            'b' => array('url' => 'https://blocked.invalid/rates', 'args' => array('body' => '{}')),
        );
        $responses = \IQLRSS\Core\Api\Rate_Transport::post_multiple($requests);
        foreach ($responses as $result) equal($result->get_error_code(), 'http_request_not_executed', 'Core outbound block honored');
    };

    add_filter('pre_http_request', function($pre, $args, $url) {
        if (false !== $pre) return $pre;
        $GLOBALS['http_calls'][] = array($url, $args);
        return ($GLOBALS['mock_http'])($url, $args);
    }, 10, 3);
    $count = 0;
    foreach ($tests as $name => $test) {
        $GLOBALS['test_transients'] = $GLOBALS['test_ttls'] = $GLOBALS['http_calls'] = $GLOBALS['test_logs'] = array();
        \IQLRSS\Driver::$options = array('api_key' => 'test-key', 'logging_enabled' => 'no');
        if ($test() === false) {
            echo "SKIP: $name (cURL extension unavailable)\n";
            continue;
        }
        echo "PASS: $name\n";
        $count++;
    }
    echo "$count rate retrieval scenarios passed.\n";
}
