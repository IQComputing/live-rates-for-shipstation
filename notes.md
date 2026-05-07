# Filter Hooks

```
iqlrss/cache/shipstation                - TRUE | Toggle to cache _common_ ShipStation information, such as: Carriers, Serivces, Warehouses. Not every request.
iqlrss/cache/shipstation_expires        - Numeric value for when the transient should expire.
iqlrss/shipping/calculator_object       - Overrides the shipping calculator object as long as it extends the IQLRSS calculator.
iqlrss/shipping/packages                - Multidimensional array of calculated and packed packages before being returned back to WooCommerce.
iqlrss/zone/package_presets             - Multidimensional array of custom package name and dimensions. Ex. Common USPS packages/dimensions.
iqlrss/zone/settings                    - Filters the WC_Shipping_Method settings array.
```

# WC Log Types

```
info		make_request()			args, code, response

debug		Custom Boxes Packed.	    box_log
debug       Calcualtor Processed Rates  get_rates
debug       Lowest Rate                 prepare_rates

notice		Shipping Calculations Object overridden.
notice		The Shipping packages were modified by a 3rd party using the `iqlrss/shipping/packages` filter hook.

warning		Shipping Calculations Object override failed. Class may not inherit "\IQLRSS\Core\Classes\Shipping_Calculator".
warning		Could not find carrier information.
warning		No ShipStation REST API Key found.
warning		Warehosue found, but was missing a required API parameter.
warning		Custom Boxes selected, but no boxes found. Items packed individually.
warning		Product ID #%1$d missing (%2$s) dimensions which may lead to packaging inconsistencies.
warning		Setup Rates tried to run with no packed items to work with.
warning		Setup Rates tried to run with no base API args set.
warning		Setup Rates tried to run but could not determine enabled carriers.
warning		Could not retrieve rates for packed item while processing rates.

error		make_request()	$request
error		Request missing a To Country Code and/or To Postal Code.
error		Request missing a From Country Code and/or From Postal Code.
error		Product ID #%1$d missing (%2$s) dimensions. Weight is a minimum requirement. Shipping calculations terminated.
error		Product ID #%1$d missing weight. Shipping Zone weight fallback could not be used. Shipping calculations terminated.
error		OneBox rate request missing (%1$s) dimensions. Weight is a minimum requirement. Shipping calculations terminated.
error		OneBox rate request missing (%1$s) dimensions. Rate request falling back to Weight only.
error		No enabled carrier services found. Please enable carrier services within the shipping zone. Please ensure carriers are selected within the integration settings.
```