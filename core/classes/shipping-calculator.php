<?php
/**
 * Calculate Shipping based on...
 * Cart Data
 * or Array of Product IDs
 * or Array of WC_Products
 *
 * This can be used with or without a Shipping Method.
 * See the constructor arguments to see arguments that
 * you can either override, or help create a mock cart.
 *
 * get_rates() to run setup and returns rates.
 *
 * :: Construct
 * :: Base API Request Args
 * :: Packing / Packages
 * :: Run API Requests
 * :: Return Rate Methods
 * :: Utility
 */
namespace IQLRSS\Core\Classes;
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
     * Array of args to augment object.
     *
     * @var Array - See __construct
     */
    protected $args = array();


    /**
     * Array of API Requests.
     *
     * @var Array
     */
    protected $requests = array(
        'base'  => array(),
        'reqs'  => array(),
    );


    /**
     * Array of packed products.
     *
     * @var Array
     */
    protected $packed = array();


    /**
     * Array of processed, probably finalized rates.
     *
     * @var Array
     */
    protected $rates = array();


    /**
     * Array of cart_contents (mocked).
     *
     * @var Array
     */
    protected $cart = array();


    /**
     * Shipping Method Object
     *
     * @var WC_Shipping_Method
     */
    protected $method = null;


    /**
     * Set the data we need for calculations.
     *
     * @param Array $dataset - Cart Contents or an Array of Products.
     * @param Array $args - Array(
     *      'shipping_method' => WC_Shipping_Method
     *      'instance_id'=> WC_Shipping_Zone ID
     *      'weight_unit'=> 'Store Weight Unit String'
     *      'dim_unit'   => 'Store Dimensions Unit String'
     *      'cart'       => array( 'quantity' => 2 ), // See WC_Cart cart_contens (Entirely optional, quantity always defaults to 1) [Acts as a Global]
     *      'items'      => array( // See WC_Cart cart_contens (Entirely optional, quantity always defaults to 1) [Acts as a "Specific" or Global Override]
     *          product_id => array(
     *              'quantity' => 1,
     *          )
     *      ),
     *      'customboxes' => array(),
     *      'minweight'   => '',
     *      'packing_sub' => 'weightonly',
     * )
     */
    public function __construct( $dataset, $args = array() ) {

        $this->process_args( $args );
        $this->determine_dataset( $dataset );
        $this->associate_cart_content( $dataset );

    }


    /**
     * Return an argument value.
     * 'shipping_method' - Returns the Shipping Method.
     * 'ssopt.$key' - Returns a ShipStation option value.
     *
     * @param String $key
     * @param Mixed $default
     *
     * @return Mixed
     */
    public function get( $key, $default = '' ) {

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

            return $value;
        }

        // Arg key.
        if( isset( $this->args[ $key ] ) ) {
            return $this->args[ $key ];

        // Shipping Method option maybe?
        } else if( $this->method ) {

            if( 'services_enabled' === $key ) {

                $enabled  = array();
                $services = $this->get( 'services', array() );

                foreach( $services as $c => $sa ) {
                    foreach( $sa as $sk => $s ) {
                        if( ! isset( $s['enabled'] ) || ! $s['enabled'] ) continue;
                        $enabled[ $c ][ $sk ] = $s;
                    }
                }

                return ( ! empty( $enabled ) ) ? $enabled : $default;

            } else if( 'shipping_method' === $key ) {
                return $this->method;
            }

            return $this->method->get_option( $key, $default );

        } else if( 'shipping_method' === $key ) {
            return $this->method;
        }

        return $default;

    }



    /**------------------------------------------------------------------------------------------------ **/
	/** :: Construct :: **/
	/**------------------------------------------------------------------------------------------------ **/
    /**
     * Process and set the calculator args.
     * Set the Shipping Method if it exists
     * and remove it from args to save on [debug] space.
     *
     * @param Array $args
     *
     * @return void
     */
    protected function process_args( $args ) {

        // Maybe set the shipping method object.
        if( isset( $args['shipping_method'] ) && is_a( $args['shipping_method'], 'WC_Shipping_Method' ) ) {
            $this->method = $args['shipping_method'];
        } else if( isset( $args['instance_id'] ) ) {
            $this->method = \WC_Shipping_Zones::get_shipping_method( $args['instance_id'] );
        }

        $args = array_diff_key( $args, array(
            'shipping_method' => '',
            'instance_id'     => '',
        ) );

        $this->args = array_merge( array(
            'weight_unit'   => get_option( 'woocommerce_weight_unit', '' ),
            'dim_unit'      => get_option( 'woocommerce_dimension_unit', '' ),
            'cart'          => array(),
            'items'         => array(),
        ), (array)$args );

    }


    /**
     * Determine the calculator type by given dataset.
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
                $this->cart['data'][ $product->get_id() ] = $product;
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

        // Return Early - Associate Cart Content, except some array keys.
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
        $cart   = $this->get( 'cart', array() );   // These act as order item globals. Every item will have theses.
        $items  = $this->get( 'items', array() );  // These override globals using product_id as the key association.

        // Items and Cart
        if( ! empty( $items ) && is_array( $items ) ) {

            foreach( $this->cart as $cart_item ) {

                if( ! is_a( $cart_item['data'], 'WC_Product' ) ) continue;
                if( ! isset( $items[ $cart_item['data']->get_id() ] ) ) continue;

                $product = $cart_item['data'];
                $this->cart[ $product->get_id() ] = array_merge(
                    array( 'quantity' => 1 ),
                    (array)$cart,
                    (array)$items[ $product->get_id() ],
                    array( 'data' => $product ) // Ensure the product isn't overridable.
                );

            }

        // Just Cart
        } else if( ! empty( $cart ) && is_array( $cart ) ) {

            foreach( $this->cart as $cart_item ) {

                if( ! is_a( $cart_item['data'], 'WC_Product' ) ) continue;
                $this->cart[ $cart_item['data']->get_id() ] = array_merge(
                    array( 'quantity' => 1 ),
                    (array)$cart,
                    array( 'data' => $cart_item['data'] ) // Ensure the product isn't overridable.
                );
            }

        } else {

            foreach( $this->cart as $cart_item ) {

                if( ! is_a( $cart_item['data'], 'WC_Product' ) ) continue;
                $this->cart[ $cart_item['data']->get_id() ] = array(
                    'quantity' => 1,
                    'data' => $cart_item['data']
                );
            }
        }

    }



    /**------------------------------------------------------------------------------------------------ **/
	/** :: Base API Request Args :: **/
	/**------------------------------------------------------------------------------------------------ **/
    /**
     * Setup Array of API requests.
     * This will bring in warehouses and
     * any other globals or defaults.
     *
     * @return void
     */
    public function setup_base() {

        $to_arr   = $this->get_ship_to();
        $from_arr = $this->get_ship_from();

        // Log - Did not have all the necessary fields to run an API request on. This may trigger often on cart, so skip it.
        if( 'cart' !== $this->datatype && empty( $to_arr['to_country_code'] ) && empty( $to_arr['to_postal_code'] ) ) {
            $this->log( esc_html__( 'Request missing a To Country Code and/or To Postal Code.', 'live-rates-for-shipstation' ), 'error' );

        // Log - Did not have all the necessary fields to run an API request on.
        } else if( empty( $from_arr['from_country_code'] ) && empty( $to_arr['from_postal_code'] ) ) {
			$this->log( esc_html__( 'Request missing a From Country Code and/or From Postal Code.', 'live-rates-for-shipstation' ), 'error' );
		}

        $this->requests['base'] = array_merge(
            array( 'address_residential_indicator' => 'unknown'),
            $to_arr,
            $from_arr,
        );

    }


    /**
     * Return an array of where to ship to.
     *
     * @return Array(
     *   'to_country_code' => '',
     *   'to_postal_code' => '',
     *   'to_city_locality' => '',
     *   'to_state_province' => '',
     * )
     */
    public function get_ship_to() {

        // destination.* come from WC_Cart data
        // to.* come from instance $args
        return array(
            'to_country_code'	 => $this->get( 'to.country', $this->get( 'destination.country' ) ),
            'to_postal_code'	 => $this->get( 'to.postcode', $this->get( 'destination.postcode' ) ),
            'to_city_locality'	 => $this->get( 'to.city', $this->get( 'destination.city' ) ),
            'to_state_province'	 => $this->get( 'to.state', $this->get( 'destination.state' ) ),
        );

    }


    /**
     * Return an array of where to ship from.
     * This will also check against global warehouse.
     * Then it will check against Zone warehouse.
     *
     * Zone > Global > WooCommer Store
     *
     * @return Array(
     *   'from_country_code' => '',
     *   'from_postal_code' => '',
     *   'from_city_locality' => '',
     *   'from_state_province' => '',
     * )
     */
    public function get_ship_from() {

        // from.* come from instance $args
        $from_arr = array(
            'from_country_code'	 => $this->get( 'from.country', WC()->countries->get_base_country() ),
            'from_postal_code'	 => $this->get( 'from.postcode', WC()->countries->get_base_postcode() ),
            'from_city_locality' => $this->get( 'from.city', WC()->countries->get_base_city() ),
            'from_state_province'=> $this->get( 'from.state', WC()->countries->get_base_state() ),
        );

        $warehouse = $this->get_apival( 'warehouse', array() );
        if( ! empty( $warehouse ) && is_array( $warehouse ) && count( array_intersect_key( $from_arr, $warehouse ) ) <= 3 ) {
            $this->log( esc_html__( 'Warehosue found, but was missing a required API parameter.', 'live-rates-for-shipstation' ), 'warning', array(
                'warehouse' => $warehouse,
            ) );
        } else if( ! empty( $warehouse ) && is_array( $warehouse ) ) {
            $from_arr = $warehouse;
        }

        return $from_arr;

    }



    /**------------------------------------------------------------------------------------------------ **/
	/** :: Packing / Packages :: **/
	/**------------------------------------------------------------------------------------------------ **/
    /**
     * Pack items into the custom packages.
     *
     * @return void
     */
    public function setup_packages() {

        // Call the packing method
		$requests = array();
        $packtype = $this->get( 'packing', 'individual' );
        switch( $packtype ) {
            case 'individual'   : $requests = $this->get_requestsby_individual(); break;
            case 'onebox'       : $requests = $this->get_requestsby_onebox(); break;
            case 'wc-box-packer': $requests = $this->get_requestsby_wc_box_packer(); break;
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
		 * 			'unit' => 'in', - ShipStation expects a specific string. See \IQLRSS\Core\Api\Shipstation::convert_unit_term( $unit )
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
		 * @param \IQLRSS\Core\Classes\Shipping_Calculator $this
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

        foreach( $products as $product ) {

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
				), 'error' );

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
				), 'error' );

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
			), 'error' );

			return array();

		}

		// Default - Stacked Vertically
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
			return $this->get_requestsby_individual();
		}

        $wc_boxpack = new \IQLRSS\Core\WC_Box_Packer\WC_Boxpack();
		foreach( $boxes as $box ) {
			if( empty( $box['active'] ) ) continue;
			$wc_boxpack->add_box( $box );
		}

		// Loop the items, grabs their dimensions, and associates them with WC_Boxpack for future packing.
		foreach( $this->cart as $key => $cart_item ) {

            if( ! isset( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) continue;
			if( ! $cart_item['data']->needs_shipping() ) continue;

            $product = $cart_item['data'];
			$weight	 = ( ! empty( $product->get_weight() ) ) ? $product->get_weight() : $default_weight;
			$data	 = array(
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
				), 'error' );
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
			for( $i = 0; $i < $cart_item['quantity']; $i++ ) {
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
		foreach( $wc_box_packages as $package ) {

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
			$this->log( esc_html__( 'Custom Boxes Packed', 'live-rates-for-shipstation' ), 'debug', $box_log );
		}

		return $requests;

	}



    /**------------------------------------------------------------------------------------------------ **/
	/** :: Run API Requests :: **/
	/**------------------------------------------------------------------------------------------------ **/
    /**
     * Pack items into the custom packages.
     *
     * @return void
     */
    public function setup_rates() {

        // Return Early - No items to work with.
        if( empty( $this->packed ) ) {
            $this->log( esc_html__( 'Setup Rates tried to run with no packed items to work with.', 'live-rates-for-shipstation' ), 'warning' );
            return;

        // Return Early - No base API arguments.
        } else if( empty( $this->requests['base'] ) || ! isset( $this->requests['base']['to_country_code'] ) ) {
            $this->log( esc_html__( 'Setup Rates tried to run with no base API args set.', 'live-rates-for-shipstation' ), 'warning' );
            return;
        }

        // Return Early - No enabled carriers.
        $carrier_ids = $this->get_apival( 'carrier_ids', array() );
        if( empty( $carrier_ids ) ) {
            $this->log( esc_html__( 'Setup Rates tried to run but could not determine enabled carriers.', 'live-rates-for-shipstation' ), 'warning' );
            return;
        }

        // Run the API Requests.
        foreach( $this->packed as $idx => $package ) {

            // API Request!
            $this->requests['reqs'][ $idx ] = array_merge(
                $package,
                $this->requests['base'],
                array( 'carrier_ids' => $carrier_ids ),
            );
            $available_rates = $this->api()->get_shipping_estimates( $this->requests['reqs'][ $idx ] );

            // Continue - Something's wrong with rates.
            if( is_wp_error( $available_rates ) || empty( $available_rates ) ) {
                $this->log( sprintf( '%s [%s]',
                    esc_html__( 'Could not retrieve rates for packed item while processing rates.', 'live-rates-for-shipstation' ),
                    $idx,
                ), 'warning' );
                continue;
            }

            // Set Rates from the available rates.
            foreach( $available_rates as $shiprate ) {

                $hash = $this->get_rate_hash( array( 'shiprate' => $shiprate ) );
                $rate = $this->process_available_rate( $shiprate, array( $idx => $package ) );

                if( empty( $rate ) ) continue;
                if( ! isset( $rate['id'] ) ) $rate['id'] = $hash;

                // Set rate
                if( ! isset( $this->rates[ $hash ] ) ) {
                    $this->rates[ $hash ] = $rate;

                // Append cost, merge rates, merge boxes.
                } else {

                    // Cost
                    $this->rates[ $hash ]['cost'][] = $rate['cost'];

                    if( ! empty( $this->rates[ $hash ]['meta_data'] ) ) {

                        // Rates
                        if( isset( $this->rates[ $hash ]['meta_data']['rates'] ) && $rate['meta_data']['rates'] ) {
                            $this->rates[ $hash ]['meta_data']['rates'] = array_merge(
                                (array)$this->rates[ $hash ]['meta_data']['rates'],
                                (array)$rate['meta_data']['rates']
                            );
                        }

                        // Boxes
                        if( isset( $this->rates[ $hash ]['meta_data']['boxes'] ) && $rate['meta_data']['boxes'] ) {
                            $this->rates[ $hash ]['meta_data']['boxes'] = array_merge(
                                (array)$this->rates[ $hash ]['meta_data']['boxes'],
                                (array)$rate['meta_data']['boxes']
                            );
                        }
                    }
                }
            }
        }

    }


    /**
     * Loop Method
     * Process the available rates and return the WC Rate metadata.
     *
     * @see Shipping_Calculator::setup_rates()
     *
     * @param Array $shiprate     - ShipStation API Result
     * @param Array $package_arr  - Array( $idx => $package )
     *
     * @param Array $wc_rate - WC_Cart compatible rate.
     */
    protected function process_available_rate( $shiprate, $package_arr ) {

        // Return Early - The available rates has a carrier which is not enabled on this shipping method instance.
        $services = $this->get( 'services_enabled', array() );
        if( ! isset( $services[ $shiprate['carrier_id'] ][ $shiprate['code'] ] ) ) {
            return array();
        }

        $package     = array_first( $package_arr );
        $rate_name	 = ( isset( $shiprate['_name'] ) ) ? $shiprate['_name'] : '';
		$rate_name	 = ( empty( $rate_name ) && isset( $package['nickname'] ) ) ? $package['nickname'] : $rate_name;
        $service_arr = $services[ $shiprate['carrier_id'] ][ $shiprate['code'] ];

        $wc_rate = array(
            'label'		=> ( ! empty( $service_arr['nickname'] ) ) ? $service_arr['nickname'] : $shiprate['name'],
            'cost'		=> floatval( $shiprate['cost'] ),
            'meta_data' => array(
                'carrier' => $shiprate['carrier_name'],
                'service' => $shiprate['name'],
                'rates'   => array(
                    '_name'=> $rate_name, // Item products(ID|Name) or box nickname.
					'rate' => floatval( $shiprate['cost'] ),
                ),
                'boxes'   => array( $package ),
                sprintf( '_%s', \IQLRSS\Driver::plugin_prefix( 'carrier_id' ) )   => $shiprate['carrier_id'],
                sprintf( '_%s', \IQLRSS\Driver::plugin_prefix( 'carrier_code' ) ) => $shiprate['carrier_code'],
                sprintf( '_%s', \IQLRSS\Driver::plugin_prefix( 'service_code' ) ) => $shiprate['code'],
            )
        );

        // Individual items get quantities applied.
        if( 'individual' === $this->get( 'packing', 'individual' ) ) {
            $quantity = $this->get_cartitem_val( array_key_first( $package ), 'quantity', 1 );
            $wc_rate['cost'] = floatval( $shiprate['cost'] ) * absint( $quantity );
            $wc_rate['meta_data']['rates']['qty'] = $quantity;
        }

        // Add the Service Adjustment.
        $this->process_service_adjustments( $wc_rate, $shiprate, $package );

        // Add any Other Costs.
        $this->process_other_adjustments( $wc_rate, $shiprate, $package );

        // Ensure the cost is an array.
        $wc_rate['cost'] = (array)$wc_rate['cost'];

        return $wc_rate;

    }


    /**
     * Process any service specific rate adjustments.
     *
     * @param Array $wc_rate     - WC compatible rate array.
     * @param Array $shiprate    - ShipStation API rate.
     * @param Array $package_arr - Array( $idx => $package )
     *
     * @return void
     */
    protected function process_service_adjustments( &$wc_rate, $shiprate, $package_arr ) {

        $services = $this->get( 'services_enabled', array() );
        $service_arr = ( isset( $services[ $shiprate['carrier_id'] ] ) ) ? $services[ $shiprate['carrier_id'] ][ $shiprate['code'] ] : array();

        // Service Specific - Could be 0.
        if( isset( $service_arr['adjustment'] ) ) {

            $adjustment = floatval( $service_arr['adjustment'] );
            $adjustment_type = ( isset( $service_arr['adjustment_type'] ) ) ? $service_arr['adjustment_type'] : 'percentage';

            if( ! empty( $adjustment_type ) && $adjustment > 0 ) {

                $adjustment_cost = ( 'percentage' == $adjustment_type ) ? ( floatval( $shiprate['cost'] ) * ( floatval( $adjustment ) / 100 ) ) : floatval( $adjustment );
                $wc_rate['cost'] = $adjustment_cost;
                $wc_rate['meta_data']['rates']['adjustment'] = array(
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

                $adjustment_cost = ( 'percentage' === $global_adjustment_type ) ? ( floatval( $shiprate['cost'] ) * ( floatval( $global_adjustment ) / 100 ) ) : floatval( $global_adjustment );
                $wc_rate['cost'] = $adjustment_cost;
                $wc_rate['meta_data']['rates']['adjustment'] = array(
                    'type' => $global_adjustment_type,
                    'rate' => $global_adjustment,
                    'cost' => $adjustment_cost,
                    'global'=> true,
                );
            }
        }

    }


    /**
     * Process any other adjustments.
     *
     * @param Array $wc_rate     - WC compatible rate array.
     * @param Array $shiprate    - ShipStation API rate.
     * @param Array $package_arr - Array( $idx => $package )
     *
     * @return void
     */
    protected function process_other_adjustments( &$wc_rate, $shiprate, $package_arr ) {

        $other = array();
        $package = array_first( $package_arr );

        // Loop and add any other shipment amounts.
        if( ! empty( $shiprate['other_costs'] ) ) {
            foreach( $shiprate['other_costs'] as $slug => $cost_arr ) {
                if( empty( $cost_arr['amount'] ) ) continue;
                $wc_rate['cost'] += floatval( $cost_arr['amount'] );
                $other[ $slug ] = $cost_arr['amount'];
            }
        }

        // Maybe a package price
        if( 'wc-box-packer' === $this->get( 'packing', 'individual' ) && isset( $package['price'] ) && ! empty( $package['price'] ) ) {
            $wc_rate['cost'] += floatval( $package['price'] );
            $other['box_price'] = $package['price'];
        }

        // Set metadata in rates.
        if( ! empty( $other ) && isset( $wc_rate['meta_data']['rates'] ) ) {
            $wc_rate['meta_data']['rates']['other_costs'] = $other;
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
	/** :: Return Rates :: **/
	/**------------------------------------------------------------------------------------------------ **/
    /**
     * Return an array of rates
     *
     * @return Array
     */
    public function get_rates() {

		// Return Early - No enabled services.
		$services_enabled = $this->get( 'services_enabled' );
		if( empty( $services_enabled ) ) {
			$this->log( esc_html__( 'No enabled carrier services found. Please enable carrier services within the shipping zone.', 'live-rates-for-shipstation' ), 'error' );
			return;
		}

        $this->setup_base();
        $this->setup_packages();
        $this->setup_rates();

        return $this->prepare_rates( $this->rates );

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
    public function get_apival( $key, $default = '' ) {

        /* Maybe return from an instance $arg */
        $found = $this->get( $key, null );
        if( $found ) return $found;

        /* Otherwise request it from the API */
        $value = $default;
        switch( $key ) {

            // Warehouse
            case 'warehouse':

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
            break;

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
        }

        return $value;

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