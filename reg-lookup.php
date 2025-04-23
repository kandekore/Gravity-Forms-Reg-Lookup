<?php
/**
 * Plugin Name:  JSON Reg Look Up (Refactored for Hidden Fields)
 * Plugin URI:   https://darrenk.uk
 * Description:  A plugin that uses Gravity Forms (Form ID #5) to look up vehicle data from ukvehicledata, and forcibly populates both visible and hidden fields.
 * Version:      1.0
 * Author:       D Kandekore
 * Author URI:   https://darrenk.uk
 * License:      GPL-2.0+
 * Text Domain:  reglookup
 */

if ( ! defined( 'WPINC' ) ) {
    die; 
}

/* ------------------------------------------------------------------
 * Constants (Field & Form IDs)
 * ------------------------------------------------------------------ */
define('REGLOOKUP_FORM_ID',        5);   // Our multi-page Gravity Form ID

// Fields user enters on Page 1:
define('REGLOOKUP_REG_ID',         1);   // "REGISTRATION" field

// Visible fields on Page 2:
define('REGLOOKUP_MAKE_ID',        2);   // "CAR MAKE"
define('REGLOOKUP_MODEL_ID',       10);  // "CAR MODEL"
define('REGLOOKUP_DATE_ID',        3);   // "YEAR"
define('REGLOOKUP_COLOUR_ID',      4);   // "COLOUR"
define('REGLOOKUP_MILEAGE_ID',     39);  // "LAST MOT MILAGE"
define('REGLOOKUP_MOTD_ID',        40);  // "MOT Expiry"

// Hidden fields (but we still populate them):
define('REGLOOKUP_VIN_ID',         16);
define('REGLOOKUP_DATEFR_ID',      17);
define('REGLOOKUP_DATE2_ID',       18);
define('REGLOOKUP_CYLINDER_ID',    19);
define('REGLOOKUP_CO2_ID',         21);
define('REGLOOKUP_FUEL_ID',        20);
define('REGLOOKUP_TRANS_ID',       31);
define('REGLOOKUP_DOORS_ID',       32);
define('REGLOOKUP_KEEPERS_ID',     34);
define('REGLOOKUP_WHEEL_ID',       25);
define('REGLOOKUP_WEIGHT_ID',      26);

/**
 * Hook: gform_post_paging
 * Runs when user moves from Page 1 to Page 2 of Form #5.
 * Use a high priority so no other code overrides our changes.
 */
add_action('gform_post_paging', 'reglookup_init', 9999);

/**
 * Main function: performs the vehicle lookup and populates all fields.
 */
function reglookup_init($form) {

    error_log("REGLOOKUP_INIT: Form ID is " . $form["id"]);

    // Only handle form #5
    if ($form["id"] != REGLOOKUP_FORM_ID) {
        error_log("REGLOOKUP_INIT: Not form #5, stopping.");
        return;
    }

    // Must be page 2 for this logic to run
    $current_page = GFFormDisplay::get_current_page($form["id"]);
    error_log("REGLOOKUP_INIT: Current page is {$current_page} for form #5.");
    if ($current_page != 2) {
        error_log("REGLOOKUP_INIT: Not page 2, stopping.");
        return;
    }

    // Reset fields so old data won't persist
    reglookup_reset_fields($form);

    // Grab VRM from field #1
    $registration = reglookup_get_field(REGLOOKUP_REG_ID);
    $registration = preg_replace('/\s+/', '', $registration);
    error_log("REGLOOKUP_INIT: VRM from user is [{$registration}]");

    if (empty($registration)) {
        error_log("REGLOOKUP_INIT: Registration empty, showing validation message.");
        reglookup_validation_message();
        return;
    }

    // Perform API call
    $output = reglookup_perform_lookup($registration);
    if (!$output) {
        error_log("REGLOOKUP_INIT: API call returned false or invalid JSON.");
        reglookup_validation_message();
        return;
    }

    // Debug: show the entire array in log
    error_log("REGLOOKUP_INIT: API output -> " . print_r($output, true));

    // Extract the data we want
    $make   = $output['Response']['DataItems']['VehicleRegistration']['Make']            ?? '';
    $model  = $output['Response']['DataItems']['VehicleRegistration']['MakeModel']       ?? '';
    $year   = $output['Response']['DataItems']['VehicleRegistration']['YearOfManufacture'] ?? '';
    $color  = $output['Response']['DataItems']['VehicleRegistration']['Colour']          ?? '';
    $miles  = $output['Response']['DataItems']['MotHistory']['RecordList'][0]['OdometerInMiles'] ?? '';
    $motEx  = $output['Response']['DataItems']['MotHistory']['RecordList'][0]['ExpiryDate'] ?? '';

    // Hidden fields
    $vin    = $output['Response']['DataItems']['VehicleRegistration']['Vin']             ?? '';
    $dateFR = $output['Response']['DataItems']['VehicleRegistration']['DateFirstRegistered'] ?? '';
    $cyl    = $output['Response']['DataItems']['VehicleRegistration']['EngineCapacity']   ?? '';
    $co2    = $output['Response']['DataItems']['VehicleRegistration']['Co2Emissions']     ?? '';
    $fuel   = $output['Response']['DataItems']['VehicleRegistration']['FuelType']         ?? '';
    $trans  = $output['Response']['DataItems']['VehicleRegistration']['TransmissionType'] ?? '';
    $doors  = $output['Response']['DataItems']['SmmtDetails']['NumberOfDoors']           ?? '';
    $keeper = $output['Response']['DataItems']['VehicleHistory']['NumberOfPreviousKeepers'] ?? '';
    $wheel  = $output['Response']['DataItems']['MotHistory']['RecordList'][0]['OdometerReading'] ?? '';
    $weight = $output['Response']['DataItems']['TechnicalDetails']['Dimensions']['GrossVehicleWeight'] ?? '';

    // Log the extracted fields
    error_log("REGLOOKUP_INIT: Setting MAKE (#2) to {$make}");
    error_log("REGLOOKUP_INIT: Setting MODEL (#10) to {$model}");
    error_log("REGLOOKUP_INIT: Setting YEAR (#3) to {$year}");
    error_log("REGLOOKUP_INIT: Setting COLOUR (#4) to {$color}");
    error_log("REGLOOKUP_INIT: Setting MILEAGE (#39) to {$miles}");
    error_log("REGLOOKUP_INIT: Setting MOT EXPIRY (#40) to {$motEx}");
    error_log("REGLOOKUP_INIT: Hidden fields => VIN=$vin, DateFirstReg=$dateFR, Cylinder=$cyl, CO2=$co2, Fuel=$fuel, Trans=$trans, Doors=$doors, Keepers=$keeper, Wheel=$wheel, Weight=$weight");

    // Populate both visible and hidden fields forcibly
    reglookup_set_field_and_post($form, REGLOOKUP_MAKE_ID,    $make);
    reglookup_set_field_and_post($form, REGLOOKUP_MODEL_ID,   $model);
    reglookup_set_field_and_post($form, REGLOOKUP_DATE_ID,    $year);
    reglookup_set_field_and_post($form, REGLOOKUP_COLOUR_ID,  $color);
    reglookup_set_field_and_post($form, REGLOOKUP_MILEAGE_ID, $miles);
    reglookup_set_field_and_post($form, REGLOOKUP_MOTD_ID,    $motEx);

    // Hidden
    reglookup_set_field_and_post($form, REGLOOKUP_VIN_ID,        $vin);
    reglookup_set_field_and_post($form, REGLOOKUP_DATEFR_ID,     $dateFR);
    reglookup_set_field_and_post($form, REGLOOKUP_DATE2_ID,      $year);  // Maybe you want to store the same Year here
    reglookup_set_field_and_post($form, REGLOOKUP_CYLINDER_ID,   $cyl);
    reglookup_set_field_and_post($form, REGLOOKUP_CO2_ID,        $co2);
    reglookup_set_field_and_post($form, REGLOOKUP_FUEL_ID,       $fuel);
    reglookup_set_field_and_post($form, REGLOOKUP_TRANS_ID,      $trans);
    reglookup_set_field_and_post($form, REGLOOKUP_DOORS_ID,      $doors);
    reglookup_set_field_and_post($form, REGLOOKUP_KEEPERS_ID,    $keeper);
    reglookup_set_field_and_post($form, REGLOOKUP_WHEEL_ID,      $wheel);
    reglookup_set_field_and_post($form, REGLOOKUP_WEIGHT_ID,     $weight);

    // Log the final form object
    error_log("REGLOOKUP_INIT: Form object AFTER set -> " . print_r($form['fields'], true));
}

/**
 * Helper: Retrieve a posted field from Gravity Forms submission
 */
function reglookup_get_field($field_id) {
    $input_name = 'input_' . $field_id;
    return isset($_POST[$input_name]) ? sanitize_text_field($_POST[$input_name]) : '';
}

/**
 * Helper: Assign a defaultValue to a Gravity Forms field
 * AND forcibly set $_POST, so GF sees it as user input.
 */
function reglookup_set_field_and_post(&$form, $field_id, $value) {
    // 1) Standard GF approach: set defaultValue
    foreach ($form['fields'] as &$field_obj) {
        if ($field_obj->id === floatval($field_id)) {
            $field_obj->defaultValue = esc_html($value);
        }
    }
    // 2) Force it into $_POST (so GF definitely records it)
    $_POST['input_' . $field_id] = $value;
}

/**
 * Perform the remote GET request to ukvehicledata API
 * Return decoded JSON array or false on failure
 */
function reglookup_perform_lookup($registration) {
    $site_tag = site_url();

    $base = 'https://uk1.ukvehicledata.co.uk/api/datapackage/VehicleAndMotHistory'
          . '?v=2&api_nullitems=1'
          . '&auth_apikey=****'
          . '&user_tag=' . urlencode($site_tag)
          . '&key_VRM='  . urlencode($registration);

    $args = array(
        'timeout'     => 30,
        'redirection' => 10,
        'sslverify'   => false,
        'headers'     => array(
            'Content-Type' => 'application/json',
        ),
    );

    $response = wp_remote_get($base, $args);

    if ( is_wp_error($response) ) {
        error_log("REGLOOKUP: API ERROR => " . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        error_log("REGLOOKUP: Empty response from ukvehicledata");
        return false;
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded) || empty($decoded['Response'])) {
        error_log("REGLOOKUP: Invalid JSON structure from API");
        return false;
    }

    return $decoded;
}

/**
 * Unset previously submitted data for the fields we populate
 * so old data doesn't remain if user resubmits.
 */
function reglookup_reset_fields(&$form) {

    // For every field we plan to populate:
    $fields_to_clear = array(
        REGLOOKUP_MAKE_ID,
        REGLOOKUP_MODEL_ID,
        REGLOOKUP_DATE_ID,
        REGLOOKUP_COLOUR_ID,
        REGLOOKUP_MILEAGE_ID,
        REGLOOKUP_MOTD_ID,
        REGLOOKUP_VIN_ID,
        REGLOOKUP_DATEFR_ID,
        REGLOOKUP_DATE2_ID,
        REGLOOKUP_CYLINDER_ID,
        REGLOOKUP_CO2_ID,
        REGLOOKUP_FUEL_ID,
        REGLOOKUP_TRANS_ID,
        REGLOOKUP_DOORS_ID,
        REGLOOKUP_KEEPERS_ID,
        REGLOOKUP_WHEEL_ID,
        REGLOOKUP_WEIGHT_ID
        // Add or remove if needed
    );

    foreach ($fields_to_clear as $fid) {
        unset($_POST['input_' . $fid]);
    }
}

/**
 * Output a small JS snippet to show a validation error if the lookup fails.
 */
function reglookup_validation_message() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function() {
        var message = "<div class='validation_error'>We couldn’t find your vehicle details, please add them below.</div>";
        jQuery('.gform_body').before(message);
    });
    </script>
    <?php
}
