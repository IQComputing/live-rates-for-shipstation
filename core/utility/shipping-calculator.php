<?php
/**
 * Calculate Shipping based on...
 *
 * @todo This needs a 2nd pass to clean up and organize.
 *
 * :: Base API Request Allocation
 * :: Packages / Packing
 * :: API Request Fulfillment
 * :: Utility
 */
namespace IQLRSS\Core\Utility;
use IQLRSS\Core\Traits;

if( ! defined( 'ABSPATH' ) ) {
	return;
}

Class Shipping_Calculator {

    /**
	 * Inherit logger traits
	 */
	use Traits\Logger;


    /**
     * Calculator datatype: cart|products
     *
     * @var String
     */
    protected $datatype;


    /**
     * Array of packed products.
     *
     * @var Array
     */
    protected $packed = array();


    /**
     * Array of API Requests.
     *
     * @var Array
     */
    protected $requests = array(
        'base'      => array(),
        'requests'  => array(),
    );


    /**
     * Array of args to augment object.
     *
     * @var Array(
     *      'method' => WC_Shipping_Zone
     *
     *      // Cart Specifics
     *      'weight_dim' => 'Store Weight Unit String'
     *      'dim_unit'   => 'Store Dimensions Unit String'
     *      'cart'       => array( 'quantity' => 2 ), // See WC_Cart cart_contens (Entirely optional, quantity always defaults to 1)
     *      'items'      => array( // See WC_Cart cart_contens (Entirely optional, quantity always defaults to 1)
     *          product_id => array(
     *              'quantity' => 1,
     *          )
     *      ),
     *      'customboxes' => array(),
     *      'minweight'   => '',
     *      'packing_sub' => 'weightonly',
     * )
     */
    protected $args = array();


    /**
     * Array of cart_contents (mocked).
     *
     * @var Array
     */
    protected $cart = array();


    /**
     * Array of processed, probably finalized rates.
     *
     * @var Array
     */
    protected $rates = array();


    /**
     * Set the data we need for calculations.
     *
     * @param Array $dataset - Cart Contents or an Array of Products.
     * @param Array $args - Array(
     *      'method'     => WC_Shipping_Zone
     *      'weight_dim' => 'Store Weight Unit String'
     *      'dim_unit'   => 'Store Dimensions Unit String'
     *      'cart'       => array( 'quantity' => 2 ), // See WC_Cart cart_contens (Entirely optional, quantity always defaults to 1) [Acts as a Global]
     *      'items'      => array( // See WC_Cart cart_contens (Entirely optional, quantity always defaults to 1) [Acts as a "Specific" or Global Override]
     *          product_id => array(
     *              'quantity' => 1,
     *          )
     *      ),
     * )
     */
    public function __construct( $dataset, $args = array() ) {

        $this->args = array_merge( array(
            'weight_dim'    => get_option( 'woocommerce_weight_unit', '' ),
            'dim_unit'      => get_option( 'woocommerce_dimension_unit', '' ),
            'cart'          => array(),
            'items'         => array(),
        ), (array)$args );

        $this->determine_dataset( $dataset );
        $this->associate_cart_content( $dataset );

    }


    /**
     * Magic Call for chained methods.
     *
     * All setup_*() methods should return $this for ez chaining.
     * This ensures that they do even if they don't.
     *
     * @param String $method
     * @param Mixed $args - Array(
     *   'instance_id' => 123 - The Shipping Method Instance ID (Usually associated with an Order Item).
     *   'method' => WC_Shipping_Method - Uses instance_id to create IQLRSS Shipping Method instance.
     * )
     *
     * @return Mixed
     */
    public function __call( $method, $args ) {

        if( 0 === strpos( $method, 'setup_' ) ) {
            $this->$method( ...$args );
            return $this;
        }

        return $this->$method( ...$args );

    }


    /**
     * Return an argument value.
     * 'method' - Returns the Shipping Zone Method if it exists.
     *
     * @param String $key
     * @param Mixed $default
     *
     * @return Mixed
     */
    public function get( $key, $default = '' ) {

        // Maybe set the shipping method object.
        if( isset( $this->args['instance_id'] ) && ! isset( $this->args['method'] ) ) {
            $this->args['method'] = \WC_Shipping_Zones::get_shipping_method( $this->args['instance_id'] );
        }

        // Friendly deep array traversal.
        if( false !== strpos( $key, '.' ) || false !== strpos( $key, '/' ) ) {

            $value      = (array)$this->args;
            $delimiter  = ( false !== strpos( $key, '/' ) ) ? '/' : '.';
            $keyways    = explode( $delimiter, $key );

            // Shortcircut  ShipStation Option
            if( false !== strpos( $key, 'ssopt' ) ) {

                // Allow $this->args override.
                $argname = str_replace( 'ssopt' . $delimiter, '', $key );
                if( $value = $this->get( $argname, null ) ) return $value;
                return \IQLRSS\Driver::get_ss_opt( $argname, $default );
            }

            array_walk( $keyways, function( $slug ) use( &$value, $default ) {
                if( $default === $value ) return;
                $value = ( is_array( $value ) && isset( $value[ $slug ] ) ) ? $value[ $slug ] : $default;
            } );
        }

        // Arg key.
        if( isset( $this->args[ $key ] ) ) {
            return $this->args[ $key ];

        // Shipping Method optoin maybe?
        } else if( isset( $this->args['method'] ) && is_a( $this->args['method'], 'WC_Shipping_Method' ) ) {

            if( 'serivces_enabled' === $key ) {

                $enabled  = array();
                $services = $this->get( 'services', array() );

                foreach( $services as $c => $sa ) {
                    foreach( $sa as $sk => $s ) {
                        if( ! isset( $s['enabled'] ) || ! $s['enabled'] ) continue;
                        $enabled[ $c ][ $sk ] = $s;
                    }
                }

                return ( ! empty( $enabled ) ) ? $enabled : $default;

            }

            return $this->args['method']->get_option( $key, $default );
        }

        return $default;

    }


    /**
     * Return an array of rates
     *
     * @return Array
     */
    public function get_rates() {

        $this->setup_base()		// Setup base request args.
			->setup_packages()  // Pack items.
			->setup_rates();	// Run API requests.

        return $this->prepare_rates( $this->rates );

    }



    /**------------------------------------------------------------------------------------------------ **/
	/** :: Base API Request Allocation :: **/
	/**------------------------------------------------------------------------------------------------ **/
    /**
     * Setup Array of API requests.
     * This will bring in warehouses and
     * any other globals or defautls.
     *
     * @return $this
     */
    public function setup_requests() {

        $to_arr   = $this->get_requestval( 'to', array() );
        $from_arr = $this->get_requestval( 'from', array() );

        // Return Early - Did not have all the necessary fields to run an API request on.
        if( empty( $to_arr['to_country_code'] ) && empty( $to_arr['to_postal_code'] ) ) {
            $this->log( esc_html__( 'Request missing a To Country Code and/or To Postal Code.', 'live-rates-for-shipstation' ) );
			return $this;

        // Return Early - Did not have all the necessary fields to run an API request on.
        } else if( empty( $from_arr['from_country_code'] ) && empty( $to_arr['from_postal_code'] ) ) {
			$this->log( esc_html__( 'Request missing a From Country Code and/or From Postal Code.', 'live-rates-for-shipstation' ) );
			return $this;
		}

        $this->requests['base'] = array_merge(
            array( 'address_residential_indicator' => 'unknown'),
            $to_arr,
            $from_arr,
        );

        return $this;

    }



    /**------------------------------------------------------------------------------------------------ **/
	/** :: Packages / Packing :: **/
	/**------------------------------------------------------------------------------------------------ **/
    /**
     * Pack items into the custom packages.
     *
     * @return $this
     */
    public function setup_packages() {

        // Call the packing method
        $packtype = $this->get( 'packing', 'individual' );
		$requests = array();
        $callback = sprintf( 'get_requestsby_%s', str_replace( '-', '_', $packtype ) );
		if( method_exists( $this, $callback ) ) {
			$requests = call_user_func( array( $this, $callback ) );
		}


        /**
		 * Allow filtering the packages before requesting estimates.
		 *
		 * The returned array should follow this format:
		 * Multi-dimensional Array
		 *
		 * $requests = Array( Array(
		 * ~ Required Fields:
		 * 		'_name' => '$productID|$productName', - This format makes it easy to show the Shop Manager what's packed into the box.
		 * 		'dimensions' => array(
		 * 			'length => 123,
		 * 			'width' => 123,
		 * 			'height' => 123,
		 * 			'unit' => 'inch', - ShipStation expects a specific string. See \IQLRSS\Core\Api\Shipstation::convert_unit_term( $unit )
		 * 		),
		 * 		'weight' => array(
		 * 			'value' => 123,
		 * 			'unit' => 'pound',  - ShipStation expects a specific string. See \IQLRSS\Core\Api\Shipstation::convert_unit_term( $unit )
		 * 		),
		 *
		 * ~ Entirely optional, but the system will try to read them if available.
		 * 		'packed' => Array( '$productID|$productName', '$productID|$productName' ),
		 *		'price'	 => 123,
		 *		'nickname' => 'String' - Displayed to the Shop Owner on the Edit Order page.
		 *		'box_weight' => 123,
		 *		'box_max_weight'=> 123,
		 *		'package_code' => 'ups_ground',
		 *		'carrier_code' => 'ups', - Carrier Code should match what ShipStation expects. I.E. fedex_walleted. This is to group packages with carriers for discounts.
		 * ) )
		 *
		 * @hook filter
		 *
		 * @param Array $requests - Array of Package dimensions that the API will use to get rates on. Multidimensional Array.
         * @param Array $cart - Array of cart_contents (or a mock of). Always check isset().
		 * @param Array $packtype - The packaging type used.
		 * @param \IQLRSS\Core\Utility\Shipping_Calculator $this
		 *
		 * @return Array $settings
		 */
		$filtered_requests = apply_filters( 'iqlrss/shipping/packages', $requests, $this->cart, $packtype, $this );

        // Return Early - No packed items?
        if( empty( $filtered_requests ) ) return;

        // IF the hash doesn't match what was given to the filter, note it in the logs so the store owner will know.
		if( $filtered_requests !== $requests ) {
			$this->log( esc_html__( 'The Shipping packages were modified by a 3rd party using the `iqlrss/shipping/packages` filter hook.', 'live-rates-for-shipstation' ), 'notice' );
		}

        $this->packed = $filtered_requests;

    }


    /**
	 * Return an array of API requests for individual products.
	 *
	 * @param Array $items
	 *
	 * @return Array $requests
	 */
	public function get_requestsby_individual() {

		$requests = array();
        $products = $this->get_products();
        $default_weight = $this->get( 'minweight', '' );

        foreach( $products as $key => $product ) {

			// Continue - No shipping needed for product.
			if( ! $product->needs_shipping() ) continue;

			$request = array(
				'_name' => sprintf( '%s|%s',
					$product->get_id(),
					$product->get_name(),
				),
				'weight' => ( ! empty( $product->get_weight() ) ) ? $product->get_weight() : $default_weight,
			);
			$physicals = array_filter( array(
				'length'	=> $product->get_length(),
				'width'		=> $product->get_width(),
				'height'	=> $product->get_height(),
			) );

			// Return Early - Product missing one of the 4 key dimensions.
			if( count( $physicals ) < 3 || empty( $request['weight'] ) ) {
				$this->log( sprintf(

					/* translators: %1$d is the Product ID. %2$s is the Product Dimensions separated by a comma. */
					esc_html__( 'Product ID #%1$d missing (%2$s) dimensions. Weight is a minimum requirement. Shipping calculations terminated.', 'live-rates-for-shipstation' ),
					$product->get_id(),
					implode( ', ', array_diff_key( array(
						'length'	=> 'length',
						'width'		=> 'width',
						'height'	=> 'height',
						'weight'	=> 'weight',
					), $physicals + array( 'weight' => $request['weight'] ) ) )
				) );

				return array();
			}

			// Set rate request dimensions.
			sort( $physicals );
			if( 3 == count( $physicals ) ) {
				$request['dimensions'] = array(
					'length'	=> round( wc_get_dimension( $physicals[2], $this->get( 'dim_unit' ) ), 2 ),
					'width'		=> round( wc_get_dimension( $physicals[1], $this->get( 'dim_unit' ) ), 2 ),
					'height'	=> round( wc_get_dimension( $physicals[0], $this->get( 'dim_unit' ) ), 2 ),
					'unit'		=> $this->api()->convert_unit_term( $this->get( 'dim_unit' ) ),
				);
			}

			// Set rate request weight.
			if( ! empty( $request['weight'] ) ) {
				$request['weight'] = array(
					'value' => (float)round( wc_get_weight( $request['weight'], $this->get( 'weight_unit' ) ), 2 ),
					'unit'	=> $this->api()->convert_unit_term( $this->get( 'weight_unit' ) ),
				);
			}

			$requests[ $product->get_id() ] = $request;

		}

		return $requests;

	}


	/**
	 * One Big Box
	 * Group all the products by weight and get rates by total weight.
	 *
	 * @param Array $items
	 *
	 * @return Array $requests
	 */
	public function get_requestsby_onebox() {

        $products       = $this->get_products();
		$default_weight = $this->get( 'minweight', 0 );
		$subtype 		= $this->get( 'packing_sub', 'weightonly' );
		$dimensions = array(
			'running' => array_combine( array( 'length', 'width', 'height', 'weight' ), array_fill( 0, 4, 0 ) ),
			'largest' => array_combine( array( 'length', 'width', 'height', 'weight' ), array_fill( 0, 4, 0 ) ),
		);

		foreach( $products as $key => $product ) {

			// Continue - No shipping needed for product.
			if( ! $product->needs_shipping() ) continue;

			$request = array(
				'_name' => sprintf( '%s|%s',
					$product->get_id(),
					$product->get_name(),
				),
				'weight' => ( ! empty( $product->get_weight() ) ) ? $product->get_weight() : $default_weight,
			);

			// Return Early - Missing minimum requirement: weight.
			if( empty( $request['weight'] ) ) {

				$this->log( sprintf(

					/* translators: %1$d is the Product ID. */
					esc_html__( 'Product ID #%1$d missing weight. Shipping Zone weight fallback could not be used. Shipping calculations terminated.', 'live-rates-for-shipstation' ),
					$product->get_id()
				) );

				return array();

			}

			$dimensions['running']['weight'] = $dimensions['running']['weight'] + ( floatval( $request['weight'] ) * $this->get_cartitem_value( $key, 'quantity', 1 ) );
			$dimensions['running']['height'] = $dimensions['running']['height'] + ( floatval( $product->get_height() ) * $this->get_cartitem_value( $key, 'quantity', 1 ) );
			$dimensions['largest'] = array(
				'length'	=> ( $dimensions['largest']['length'] < $product->get_length() ) ? $product->get_length() : $dimensions['largest']['length'],
				'width'		=> ( $dimensions['largest']['width'] < $product->get_width() )   ? $product->get_width()  : $dimensions['largest']['width'],
				'height'	=> ( $dimensions['largest']['height'] < $product->get_height() ) ? $product->get_height() : $dimensions['largest']['height'],
				'weight'	=> ( $dimensions['largest']['weight'] < $request['weight'] )     ? $request['weight']	  : $dimensions['largest']['weight'],
			);

		}

		// Return Early - Rates by total weight.
		if( 'weightonly' == $subtype ) {

			return array( array(
				'weight' => array(
					'value' => (float)round( wc_get_weight( $dimensions['running']['weight'], $this->get( 'weight_unit' ) ), 2 ),
					'unit'	=> $this->api()->convert_unit_term( $this->get( 'weight_unit' ) ),
				),
			) );

		}

		$physicals = array_filter( array(
			'length'	=> $dimensions['largest']['length'],
			'width'		=> $dimensions['largest']['width'],
			'height'	=> $dimensions['running']['height'],
			'weight'	=> $dimensions['running']['weight'],
		) );

		// Return Early - Error - Missing dimensions to work with.
		if( $physicals < 4 ) {

			$this->log( sprintf(

				/* translators: %1$d is the Product ID. %2$s is the Product Dimensions separated by a comma. */
				esc_html__( 'OneBox rate requestion missing dimensions (%1$s). Weight is a minimum requirement. Shipping calculations terminated.', 'live-rates-for-shipstation' ),
				implode( ', ', array_diff_key( array(
					'length'	=> 'length',
					'width'		=> 'width',
					'height'	=> 'height',
					'weight'	=> 'weight',
				), $physicals ) )
			) );

			return array();

		}

		// Default - Stacked Verticially
		return array( array(
			'weight' => array(
				'unit'	=> $this->api()->convert_unit_term( $this->get( 'weight_unit' ) ),
				'value' => (float)round( wc_get_weight( $physicals['weight'], $this->get( 'weight_unit' ) ), 2 ),
			),
			'dimensions' => array(
				'unit'		=> $this->api()->convert_unit_term( $this->get( 'dim_unit' ) ),

				// Largest
				'length'	=> round( wc_get_dimension( $physicals['length'], $this->get( 'dim_unit' ) ), 2 ),
				'width'		=> round( wc_get_dimension( $physicals['width'], $this->get( 'dim_unit' ) ), 2 ),

				// Running
				'height'	=> round( wc_get_dimension( $physicals['height'], $this->get( 'dim_unit' ) ), 2 ),
			),
		) );

	}


	/**
	 * Return an array of API requests for custom packed boxes.
	 * Shoutout to Mike Jolly & Co.
	 *
	 * @return Array $requests
	 */
	public function get_requestsby_wc_box_packer() {

		$requests 	    = array();
		$boxes 			= $this->get( 'customboxes', array() );
		$default_weight = $this->get( 'minweight', '' );

		/* Return Early - No custom boxes found. */
		if( empty( $boxes ) ) {
			$this->log( esc_html__( 'Custom Boxes selected, but no boxes found. Items packed individually', 'live-rates-for-shipstation' ), 'warning' );
			return $this->group_requestsby_individual();
		}

		if( ! class_exists( '\IQLRSS\Core\WC_Box_Packer\WC_Boxpack' ) ) {
			include_once '../wc-box-packer/class-wc-boxpack.php';
		} else {
            $wc_boxpack = new \IQLRSS\Core\WC_Box_Packer\WC_Boxpack();
        }

		// Setup the WC_Boxpack boxes based on user submitted custom boxes.
		foreach( $boxes as $box ) {
			if( empty( $box['active'] ) ) continue;
			$wc_boxpack->add_box( $box );
		}

		// Loop the items, grabs their dimensions, and assocaite them with WC_Boxpack for future packing.
		foreach( $this->cart as $key => $product ) {

			if( ! $product->needs_shipping() ) continue;

			$weight	= ( ! empty( $product->get_weight() ) ) ? $product->get_weight() : $default_weight;
			$data	= array(
				'weight' => (float)round( wc_get_weight( $weight, $this->get( 'weight_unit' ) ), 2 ),
			);
			$physicals = array_filter( array(
				'length'	=> $product->get_length(),
				'width'		=> $product->get_width(),
				'height'	=> $product->get_height(),
			) );

			// Return Early - Product missing one of the 4 key dimensions.
			if( count( $physicals ) < 3 && empty( $data['weight'] ) ) {
				$this->log( sprintf(

					/* translators: %1$d is the Product ID. %2$s is the Product Dimensions separated by a comma. */
					esc_html__( 'Product ID #%1$d missing (%2$s) dimensions and no weight found. Shipping calculations terminated.', 'live-rates-for-shipstation' ),
					$product->get_id(),
					implode( ', ', array_diff_key( array(
						'width'		=> 'width',
						'height'	=> 'height',
						'length'	=> 'length',
					), $physicals ) )
				) );
				return array();
			}

			sort( $physicals );
			$data = array(
				'length'	=> round( wc_get_dimension( $physicals[2], $this->get( 'dim_unit' ) ), 2 ),
				'width'		=> round( wc_get_dimension( $physicals[1], $this->get( 'dim_unit' ) ), 2 ),
				'height'	=> round( wc_get_dimension( $physicals[0], $this->get( 'dim_unit' ) ), 2 ),
				'weight'	=> round( wc_get_weight( $data['weight'], $this->get( 'weight_unit' ) ), 2 ),
			);

			// Pack Products
			for( $i = 0; $i < $item['quantity']; $i++ ) {
				$wc_boxpack->add_item(
					$data['length'],
					$data['width'],
					$data['height'],
					$data['weight'],
					$product->get_price(),
					array(
						'_name' => sprintf( '%s|%s',
							$product->get_id(),
							$product->get_name(),
						),
					),
				);
			}
		}

		// Pack it up, missions over.
		$wc_boxpack->pack();
		$wc_box_packages = $wc_boxpack->get_packages();
		$box_log = array();

		// Delivery!
		foreach( $wc_box_packages as $key => $package ) {

			$packed_items = ( is_array( $package->packed ) ) ? array_map( function( $item ) { return $item->meta['_name']; }, $package->packed ) : array();
			$requests[] = array(
				'weight' => array(
					'value' => round( $package->weight, 2 ),
					'unit'	=> $this->api()->convert_unit_term( $this->get( 'weight_unit' ) ),
				),
				'dimensions' => array(
					'length'	=> round( $package->length, 2 ),
					'width'		=> round( $package->width, 2 ),
					'height'	=> round( $package->height, 2 ),
					'unit'		=> $this->api()->convert_unit_term( $this->get( 'dim_unit' ) ),
				),
				'packed' => $packed_items,
				'price'	 => ( ! empty( $package->data ) ) ? $package->data['price'] : 0,
				'nickname'		=> ( ! empty( $package->data ) ) ? $package->data['nickname'] : '',
				'box_weight'	=> ( ! empty( $package->data ) ) ? $package->data['weight'] : 0,
				'box_max_weight'=> ( ! empty( $package->data ) ) ? $package->data['weight_max'] : 0,
				'package_code'	=> ( ! empty( $package->data ) ) ? $package->data['preset'] : '',
				'carrier_code'	=> ( ! empty( $package->data ) ) ? $package->data['carrier_code'] : '',
			);

			$box_log[] = array(
				'is_packed'		 => boolval( empty( $package->unpacked ) ),
				'item_count'	 => count( $package->packed ),
				'items'			 => $packed_items,
				'box_dimensions' => sprintf( '%s x %s x %s | %s | %s', $package->length, $package->width, $package->height, $package->weight, $package->volume ),
				'box_dim_key'	 => sprintf( '%s x %s x %s | %s | %s',
					esc_html__( 'Length', 'live-rates-for-shipstation' ),
					esc_html__( 'Width', 'live-rates-for-shipstation' ),
					esc_html__( 'Height', 'live-rates-for-shipstation' ),
					esc_html__( 'Weight', 'live-rates-for-shipstation' ),
					esc_html__( 'Volume', 'live-rates-for-shipstation' ),
				),
				'max_volume' => floatval( $package->width * $package->height * $package->length ),
				'data' => ( ! empty( $package->data ) ) ? $package->data : array(),
			);

		}

		if( ! empty( $box_log ) ) {
			$this->log( esc_html__( 'Custom Boxes Packed', 'live-rates-for-shipstation' ), 'info', $box_log );
		}

		return $requests;

	}



    /**------------------------------------------------------------------------------------------------ **/
	/** :: API Request Fulfillment :: **/
	/**------------------------------------------------------------------------------------------------ **/
    /**
     * Pack items into the custom packages.
     *
     * @return $this
     */
    public function setup_rates() {

        // Return Early - No items to work with.
        if( empty( $this->packed ) ) {
            $this->log( esc_html__( 'Setup Rates tried to run with no packed items to work with.', 'live-rates-for-shipstation' ), 'warning' );
            return $this;

        // Return Early - No base API arguments.
        } else if( empty( $this->requests['base'] ) || ! isset( $this->requests['base']['to_country_code'] ) ) {
            $this->log( esc_html__( 'Setup Rates tried to run with no base API args set.', 'live-rates-for-shipstation' ), 'warning' );
            return $this;
        }

        // Return Early - No enabled carriers.
        $carrier_ids = $this->get_requestval( 'carrier_ids', array() );
        if( empty( $carrier_ids ) ) {
            $this->log( esc_html__( 'Setup Rates tried to run but could not determine enabled carriers.', 'live-rates-for-shipstation' ), 'warning' );
            return $this;
        }

        // item_id is a unique identifier, not a WP/WC ID.
        $rates = array();
        foreach( $this->packed as $item_id => $request ) {

            // API Request!
            $available_rates = $this->api()->get_shipping_estimates( array_merge(
                $request,
                $this->requests['base'],
                array( 'carrier_ids' => $carrier_ids ),
            ) );

            // Continue - Something's wrong with rates.
            if( is_wp_error( $available_rates ) || empty( $available_rates ) ) {
                $this->log( sprintf( '%s [%s]',
                    esc_html__( 'Could not retrieve rates for packed item while processing rates.', 'live-rates-for-shipstation' ),
                    $item_id,
                ), 'warning' );
                continue;
            }

            $rate_name = ( isset( $request['_name'] ) ) ? $request['_name'] : '';
            $rate_name = ( empty( $rate_name ) && isset( $request['nickname'] ) ) ? $request['nickname'] : $rate_name;
            $rateObj   = $this->create_rate_reference( array(
                'hash'      => null,
                'item_id'   => $item_id,
                'name'      => $rate_name, // Item Product (ID|Name) or Box Nickname.
                'cost'      => 0,
                'meta'      => array(),
                'request'   => $request,
                'rates_available' => $available_rates,
            ) );

            // A Reference Rabbit Hole, Alice.
            $this->process_available_rates( $rateObj, $available_rates );

            // Hash n Cache.
            if( ! isset( $rates[ $rateObj->hash ] ) ) {

                $rates[ $rateObj->hash ] = array(
                    'id'		=> $rateObj->hash,
                    'label'		=> ( ! empty( $service_arr['nickname'] ) ) ? $service_arr['nickname'] : $rateObj->meta['name'],
                    'package'	=> $request,
                    'cost'		=> array( $rateObj->cost ),
                    'meta_data' => (array)$rateObj->meta,
                    'rates'     => array(),
                    'boxes'     => array(),
                );

            } else {
                $rates[ $rateObj->hash ]['cost'][] = $rateObj->cost;
            }

            // Merge in the item rates so they persist.
            $rates[ $rateObj->hash ]['meta_data']['rates'] = $rates[ $rateObj->hash ]['meta_data']['rates'] ?? array();
            $rates[ $rateObj->hash ]['meta_data']['rates'] = array_merge(
                $rates[ $rateObj->hash ]['meta_data']['rates'],
                array(
                    'qty'           => $rateObj->meta['qty'] ?? 1,
                    '_name'         => $rateObj->meta['_name'] ?? '',
                    'adjustment'    => (array)( $rateObj->meta['adjustments'] ?? array() ),
                    'other_costs'   => (array)( $rateObj->meta['other_costs'] ?? array() ),
                )
            );

            // Merge in the boxes so they persist.
            $rates[ $rateObj->hash ]['meta_data']['boxes'] = $rates[ $rateObj->hash ]['meta_data']['boxes'] ?? array();
            $rates[ $rateObj->hash ]['meta_data']['boxes'] = array_merge(
                $rates[ $rateObj->hash ]['meta_data']['boxes'],
                array( $request ),
            );

        }

        // Set rates for whatever.
        $this->rates = $rates;

    }


    /**
     * Process the available rates and return the WC Rate metadata.
     *
     * @param Object rateObj - Custom reference object.
     * @param Array $rates - ShipStation API results.
     *
     * @return Array $shiprates
     */
    protected function process_available_rates( &$rateObj, $rates ) {

        $services = $this->get( 'serivces_enabled', array() );

        // Loop the returned API estimates.
        foreach( $rates as $shiprate ) {

            // Continue - The available rates has a carrier which is not enabled on this shipping method instance.
            if( ! isset( $services[ $shiprate['carrier_id'] ][ $shiprate['code'] ] ) ) {
                $this->log( sprintf( '%s [%s]',
                    esc_html__( 'Wrong shipping carrier found while processing rates.', 'live-rates-for-shipstation' ),
                    $rateObj->item_id,
                ), 'warning' );
                continue;
            }

            $rateObj->cost = floatval( $shiprate['cost'] );
            $rateObj->hash = $this->get_rate_hash( array( 'shiprate' => $shiprate ) );

            // Append shiprate specific metadata.
            // @todo Reconsider this whole plugin_prefix method / accessor idea [everywhere].
            $rateObj->meta = array_merge( (array)$rateObj->meta, array(
                'carrier' => $shiprate['carrier_name'],
                'service' => $shiprate['name'],
                sprintf( '_%s', \IQLRSS\Driver::plugin_prefix( 'carrier_id' ) )   => $shiprate['carrier_id'],
                sprintf( '_%s', \IQLRSS\Driver::plugin_prefix( 'carrier_code' ) ) => $shiprate['carrier_code'],
                sprintf( '_%s', \IQLRSS\Driver::plugin_prefix( 'service_code' ) ) => $shiprate['code'],
            ) );

            // Individual Items get quantities applied.
            if( 'individual' === $this->get( 'packing', 'individual' ) ) {
                $quantity = absint( $this->get_cartitem_val( $rateObj->item_id, 'quantity', 1 ) );
                $rateObj->cost *= $quantity;
				$rateObj->meta['qty'] = $quantity;
            }

            // Add the Service Adjustment.
            $this->process_service_adjustments( $rateObj, $shiprate );

            // Add any Other Costs.
            $this->process_other_adjustments( $rateObj, $shiprate );

        }

    }


    /**
     * Return an array of service costs metadata.
     * 'cost' key is required more or less.
     *
     * @param Object rateObj - Custom reference object.
     * @param Array $shiprate
     *
     * @return void
     */
    protected function process_service_adjustments( &$rateObj, $shiprate ) {

        $services    = $this->get( 'serivces_enabled', array() );
        $service_arr = ( isset( $services[ $shiprate['carrier_id'] ] ) ) ? $services[ $shiprate['carrier_id'] ][ $shiprate['code'] ] : array();

        // Service Specific
        if( isset( $service_arr['adjustment'] ) ) {

            $adjustment 	 = floatval( $service_arr['adjustment'] );
            $adjustment_type = ( isset( $service_arr['adjustment_type'] ) ) ? $service_arr['adjustment_type'] : 'percentage';

            if( ! empty( $adjustment_type ) && $adjustment > 0 ) {

                $adjustment_cost = ( 'percentage' == $adjustment_type ) ? ( $rateObj->cost * ( floatval( $adjustment ) / 100 ) ) : floatval( $adjustment );
                $rateObj->cost   = $adjustment_cost;
                $rateObj->meta['adjustment'] = array(
                    'type' => $adjustment_type,
                    'rate' => $adjustment,
                    'cost' => $adjustment_cost,
                    'global'=> false,
                );
            }

        // Global
        } else {

            $global_adjustment 		= floatval( $this->get( 'ssopt.global_asjustment', 0 ) );
            $global_adjustment_type = $this->get( 'ssopt.global_adjustment_type' );
            $global_adjustment_type = ( empty( $global_adjustment_type ) && ! empty( $global_adjustment ) ) ? 'percentage' : $global_adjustment_type;

            if( ! empty( $global_adjustment_type ) && $global_adjustment > 0 ) {

                $adjustment_cost = ( 'percentage' == $global_adjustment_type ) ? ( $rateObj->cost * ( floatval( $global_adjustment ) / 100 ) ) : floatval( $global_adjustment );
                $rateObj->cost   = $adjustment_cost;
                $rateObj->meta['adjustment'] = array(
                    'type' => $global_adjustment_type,
                    'rate' => $global_adjustment,
                    'cost' => $adjustment_cost,
                    'global'=> true,
                );
            }
        }

    }


    /**
     * Return an array of other costs metadata.
     * 'cost' key is required more or less.
     *
     * @param Object rateObj - Custom reference object.
     * @param Array $shiprate
     *
     * @return void
     */
    protected function process_other_adjustments( &$rateObj, $shiprate ) {

        // Loop and add any other shipment amounts.
        if( ! empty( $shiprate['other_costs'] ) ) {
            foreach( $shiprate['other_costs'] as $slug => $cost_arr ) {

                if( empty( $cost_arr['amount'] ) ) continue;

                $rateObj->cost += floatval( $cost_arr['amount'] );
                if( ! is_array( $rateObj->meta['other_costs'] ) ) {
                    $rateObj->meta['other_costs'] = array();
                }

                $rateObj->meta['other_costs'][ $slug ] = $cost_arr['amount'];
            }
        }

        // Maybe a package price
        if( 'wc-box-packer' == $this->get( 'packing', 'individual' ) && isset( $rateObj->request['price'] ) && ! empty( $rateObj->request['price'] ) ) {
            $rateObj->cost += floatval( $rateObj->request['price'] );
            $rateObj->meta['other_costs']['box_price'] = $rateObj->request['price'];
        }

    }


    /**
     * Create an easy reference object to pass around.
     *
     * @param Array $props
     *
     * @return Object
     */
    protected function create_rate_reference( $props ) {

        $rateObj = new \stdClass();
        foreach( $props as $p => $v ) {
            if( ! is_string( $p ) ) continue;
            $rateObj->$p = $v;
        }

        return $rateObj;

    }



    /**------------------------------------------------------------------------------------------------ **/
	/** :: Utility :: **/
	/**------------------------------------------------------------------------------------------------ **/
    /**
     * Return a hashed rate key.
     *
     * @param Array $args - Helps determine keyvals.
     *
     * @return String
     */
    public function get_rate_hash( $args = array() ) {
        return md5( sprintf( '%s%s', $args['shiprate']['code'], $args['shiprate']['carrier_id'] ) );
    }


    /**
     * Return an array of Products in the cart.
     *
     * @return Array
     */
    public function get_products() {
        if( empty( $this->cart ) ) return array();
        return array_column( $this->cart, 'data' );
    }


    /**
     * Return a cart item value by key.
     *
     * @param Mixed $id (hash or product_id)
     * @param String $slug
     * @param Mixed $default
     *
     * @return Mixed
     */
    public function get_cartitem_val( $id, $slug, $default = '' ) {

        // Neat.
        if( empty( $this->cart ) ) return $default;
        if( ! isset( $this->cart[ $id ] ) ) return $default;
        if( ! isset( $this->cart[ $id ][ $slug ] ) ) return $default;
        return $this->cart[ $id ][ $slug ];

    }


    /**
     * This should return ShipStation API compatible values.
     *
     * @param String $key
     * @param Mixed $default
     *
     * @return Mixed
     */
    public function get_requestval( $key, $default = '' ) {

        $value = $this->get( $key, $default );
        switch( $key ) {

            // destination.* are WC_Cart data
            // to.* are custom args for custom calculations.
            case 'to':
                if( ! ( is_array( $value ) && isset( $value['to_country_code'] ) ) ) {
                    $value = array(
                        'to_country_code'	 => $this->get( 'to.country', $this->get( 'destination.country' ) ),
                        'to_postal_code'	 => $this->get( 'to.postcode', $this->get( 'destination.postcode' ) ),
                        'to_city_locality'	 => $this->get( 'to.city', $this->get( 'destination.city' ) ),
                        'to_state_province'	 => $this->get( 'to.state', $this->get( 'destination.state' ) ),
                    );
                }
            break;

            // to.* are custom args for custom calculations.
            // Check for a warehouse override as well.
            case 'from':

                $value = $this->get_requestval( 'warehouse', $value );
                if( ! ( is_array( $value ) && isset( $value['from_country_code'] ) ) ) {
                    $value = array(
                        'from_country_code'	 => $this->get( 'from.country', WC()->countries->get_base_country() ),
                        'from_postal_code'	 => $this->get( 'from.postcode', WC()->countries->get_base_postcode() ),
                        'from_city_locality' => $this->get( 'from.city', WC()->countries->get_base_city() ),
                        'to_state_province'	 => $this->get( 'from.state', WC()->countries->get_base_state() ),
                    );
                }
            break;

            // Warehouse
            case 'warehouse':
                 // Grab the Warehouse / shipping from location.
                $global_warehouse = $this->get( 'ssopt.global_warehouse' );
                $zone_warehouse   = $this->get( 'warehouse', $global_warehouse );

                // Maybe grab it from a ShipStation known address.
                if( ! empty( $zone_warehouse ) && '_woo_default' !== $zone_warehouse ) {

                    $warehouse = $this->api()->get_warehouse( $zone_warehouse );
                    if( ! is_wp_error( $warehouse ) && isset( $warehouse['origin_address'] ) ) {
                        $value = array(
                            'from_country_code'	 => $warehouse['origin_address']['country_code'],
                            'from_postal_code'	 => $warehouse['origin_address']['postal_code'],
                            'from_city_locality' => $warehouse['origin_address']['city_locality'],
                            'from_state_province'=> $warehouse['origin_address']['state_province'],
                            'address_residential_indicator' => $warehouse['origin_address']['address_residential_indicator'],
                        );
                    }
                }

                // Carriers
                case 'carrier_ids':

                    $enabled  = array();
                    $carriers = $this->get( 'ssopt.carriers', array() );
                    $saved_services = $this->get( 'services', array() );

                    if( ! empty( $saved_services ) ) {
                        foreach( $saved_services as $c => $sa ) {
                            foreach( $sa as $sk => $s ) {
                                if( ! isset( $s['enabled'] ) || ! $s['enabled'] ) continue;
                                $enabled[] = $c;
                            }
                        }

                        if( ! empty( $carriers ) && ! empty( $enabled ) ) {
                            $value = array_values( array_intersect( $enabled, $carriers ) );
                        }
                    }

                break;
            break;
        }

        return $value;

    }


    /**
     * Prepare rates for return.
     *
     * @param Array $rates
     *
     * @return void
     */
    protected function prepare_rates( $rates ) {

        // Maybe process the single lowest rates.
        if( 'yes' === $this->get( 'ssopt.return_lowest', 'no' ) ) {
            $rates = $this->prepare_single_lowest_rate( $rates );

        // Sort and remove anything but the cheapest rates.
        } else {
            $rates = $this->prepare_sorted_rates( $rates );
        }

        // WooCommerce skips serialized data when outputting order item meta, this is a workaround. Thanks JSON!
        foreach( $rates as $k => $rate ) {
            if( empty( $rate['meta_data'] ) || ! is_array( $rate['meta_data'] ) ) continue;
            foreach( $rate['meta_data'] as $mk => $val ) {
                if( ! is_array( $val ) ) continue;
                $rate[ $k ]['meta_data'][ $mk ] = wp_json_encode( $rate['meta_data'][ $mk ] );
            }
        }

        return $rates;

    }


    /**
     * Reprocess the known rates to only set the single lowest rates.
     *
     * @param Array $rates
     *
     * @return Array $rates
     */
    protected function prepare_single_lowest_rate( $rates ) {

        $lowest = 0;
        $label  = $this->get( 'ssopt.return_lowest_label' );
        $lowest_service = array_key_first( $rates );

        foreach( $rates as $service_id => $rate_arr ) {

            $total = array_sum( $rate_arr['cost'] );
            if( 0 == $lowest || $total < $lowest ) {
                $lowest = $total;
                $lowest_service = $service_id;
            }
        }

        return array_merge(
            (array)$rates[ $lowest_service ],
            array( 'label' => ( ! empty( $label ) ) ? $label : $rates[ $lowest_service ]['label'] ),
        );

    }


    /**
     * Prepare the rates array to sort and
     * remove anything than the cheapest.
     *
     * @param Array $rates
     *
     * @return Array $rates
     */
    protected function prepare_sorted_rates( $rates ) {

        foreach( $rates as $rate_arr ) {

            // If more than 1 rate, add the cheapest.
            if( count( $rate_arr['cost'] ) > 1 ) {
                usort( $rate_arr['cost'], fn( $r1, $r2 ) => ( (float)$r1 < (float)$r2 ) ? -1 : 1 );
                $rate_arr['cost'] = (array)array_shift( $rate_arr['cost'] );
            }
        }

        return $rates;

    }


    /**
     * Determine the calcualtor type by given dataset.
     * Associate the cart contents.
     * Otherwise, get the product objects.
     *
     * @param Array $dataset
     *
     * @return void
     */
    protected function determine_dataset( $dataset ) {

        // Return Early - Not an Array
        if( ! is_array( $dataset ) ) return;

        // Return Early - Dataset is a full Cart Array (probably)
        if( is_array( $dataset ) && isset( $dataset['contents'], $dataset['destination'] ) ) {

                $this->datatype = 'cart';
                $this->cart = $dataset['contents'];
                return; //!

        // Return Early - WC_Cart Dataset - first key is a hash, not numeric.
        } else if( ! is_numeric( array_key_first( $dataset ) ) ) {

            $this->datatype = 'cart';
            $this->cart = $dataset;
            return; // !

        }

        // Dataset is [hopefully] an Array of Products.
        $this->datatype = 'products';
        $dataval    = array_shift( $dataset );
        $products   = array();

        // Array of Product IDs.
        if( is_numeric( $dataval ) ) {

            $products = wc_get_products( array(
                'include' => array_map( 'absint', $dataset ),
            ) );

        // Array of WC_Products.
        } else if( is_a( $dataval, '\WC_Product' ) ) {
            $products = array_filter( $dataset, fn( $obj ) => is_a( $obj, '\WC_Product' ) );
        }

        // Set Cart
        if( ! empty( $products ) ) {
            foreach( $products as $product ) {
                $this->cart[ $product->get_id() ] = $product;
            }
        }

    }


    /**
     * Try to mock the WC_Cart cart_contents with the
     * given set of products, 'cart' as the base set
     * of cart_content keys, then 'items' as overrides.
     *
     * Mainly used for quantity. Always optional.
     * Quantity will default to 1.
     *
     * @param Array $dataset
     *
     * @return void
     */
    protected function associate_cart_content( $dataset ) {

        if( empty( $this->cart ) ) return;

        // Return Early - Assocate Cart Content, except some array keys.
        if( 'cart' === $this->datatype ) {

            $this->args = array_merge( array_diff_key( $dataset, array(
                'contents'  => array(),
                'rates'     => array(),
            ) ), $this->args );
            return; // !

        // Return Early - What?
        } else if( 'products' !== $this->datatype ) {
            return;
        }

        // Associate cart args with products.
        $cart   = $this->get( 'cart', array() );   // These act as cart globals.
        $items  = $this->get( 'items', array() );  // These override globals using product_id as the key assocation.

        // Items and Cart
        if( ! empty( $items ) && is_array( $items ) ) {

            foreach( $this->cart as $product ) {

                if( ! is_a( $product, 'WC_Product' ) ) continue;
                if( ! isset( $items[ $product->get_id() ] ) ) continue;

                $this->cart[ $product->get_id() ] = array_merge(
                    array( 'quantity' => 1 ),
                    (array)$cart,
                    (array)$items[ $product->get_id() ],
                    array( 'data' => $product )
                );

            }

        // Just Cart
        } else if( ! empty( $cart ) && is_array( $cart ) ) {

            foreach( $this->cart as $product ) {

                if( ! is_a( $product, 'WC_Product' ) ) continue;
                $this->cart[ $product->get_id() ] = array_merge(
                    array( 'quantity' => 1 ),
                    (array)$cart,
                    array( 'data' => $product )
                );
            }

        } else {

            foreach( $this->cart as $product ) {

                if( ! is_a( $product, 'WC_Product' ) ) continue;
                $this->cart[ $product->get_id() ] = array(
                    'quantity' => 1,
                    'data' => $product
                );
            }
        }

    }


    /**
     * Return an instance of the ShipStation API.
     *
     * @return \IQLRSS\Core\Api\Shipstation
     */
    protected function api() {

        $api = wp_cache_get( 'iqlrss_api', 'iqlrss' );
        if( empty( $api ) ) {
            $api = new \IQLRSS\Core\Api\Shipstation();
            wp_cache_set( 'iqlrss_api', $api, 'iqlrss' );
        }

        return $api;
    }

}