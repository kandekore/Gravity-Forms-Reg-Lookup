<?php
/**
 * Plugin Name:  JSON Reg Look Up (Multi-Form, Refactored)
 * Plugin URI:   https://kandeshop.com
 * Description:  A plugin that uses Gravity Forms (IDs 2,5,7) to look up vehicle data and image URL from ukvehicledata, and forcibly populates both visible and hidden fields.
 * Version:      2.0
 * Author:       Darren Kandekore
 * Author URI:   https://kandeshop.com
 * License:      GPL-2.0+
 * Text Domain:  reglookup
 */

if ( ! defined( 'WPINC' ) ) {
    die; // Abort if accessed directly
}

// Define plugin constants
define( 'REGLOOKUP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REGLOOKUP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'REGLOOKUP_VERSION', '2.0' );

/* ------------------------------------------------------------------
 * Form Field Constants (These are field IDs within a form, less likely to change across installs)
 * ------------------------------------------------------------------ */
// Fields user enters on Page 1:
define('REGLOOKUP_REG_ID',           1);    // "REGISTRATION" field

// Visible fields on Page 2:
define('REGLOOKUP_MAKE_ID',          2);    // "CAR MAKE" (often combined with model from API)
define('REGLOOKUP_MODEL_ID',         10);   // "CAR MODEL" - Target for API or Manual Make/Model
define('REGLOOKUP_DATE_ID',          3);    // "YEAR" - Target for API or Manual Year
define('REGLOOKUP_COLOUR_ID',        4);    // "COLOUR"
define('REGLOOKUP_MILEAGE_ID',       39);   // "LAST MOT MILLAGE"
define('REGLOOKUP_MOTD_ID',          40);   // "MOT Expiry"

define('REGLOOKUP_IMAGE_ID',         44);   // "VEHICLE IMAGE URL"

// **NEW CONSTANTS for Manual Input Fields**
define('REGLOOKUP_MANUAL_MAKE_MODEL_ID', 47); // <--- UPDATE THIS TO YOUR ACTUAL FIELD ID!
define('REGLOOKUP_MANUAL_YEAR_ID',       41); // <--- UPDATE THIS TO YOUR ACTUAL FIELD ID!

// **NEW CONSTANT for Phone Number Field**
define('REGLOOKUP_PHONE_ID',             9); // <--- UPDATE THIS TO YOUR ACTUAL PHONE FIELD ID!

// **NEW CONSTANT for Prefixed Entry ID Field**
define('REGLOOKUP_PREFIXED_ENTRY_ID', 60); // <--- UPDATE THIS TO YOUR ACTUAL HIDDEN FIELD ID!

// Hidden fields (but we still populate them):
define('REGLOOKUP_VIN_ID',           16); // "VIN" - Target for API or Phone Number fallback
define('REGLOOKUP_DATEFR_ID',        17);
define('REGLOOKUP_DATE2_ID',         18);
define('REGLOOKUP_CYLINDER_ID',      19);
define('REGLOOKUP_CO2_ID',           21);
define('REGLOOKUP_FUEL_ID',          20);
define('REGLOOKUP_TRANS_ID',         31);
define('REGLOOKUP_DOORS_ID',         32);
define('REGLOOKUP_KEEPERS_ID',       34);
define('REGLOOKUP_WHEEL_ID',         25);
define('REGLOOKUP_WEIGHT_ID',        26);

/* ------------------------------------------------------------------
 * Plugin Includes
 * ------------------------------------------------------------------ */
// Include the settings class
require_once REGLOOKUP_PLUGIN_DIR . 'includes/class-reglookup-settings.php';
// Initialize settings
new Reglookup_Settings();


/* ------------------------------------------------------------------
 * Core Plugin Functions (modified to use settings)
 * ------------------------------------------------------------------ */

/**
 * Helper function to get the list of form IDs from settings.
 */
function reglookup_get_form_ids() {
    $options = get_option( 'reglookup_settings' );
    $main_id    = (int) ( $options['main_form_id'] ?? 0 );
    $mobile_id  = (int) ( $options['mobile_form_id'] ?? 0 );
    $sidebar_id = (int) ( $options['sidebar_form_id'] ?? 0 );

    $form_ids = array();
    if ( $main_id > 0 ) $form_ids[] = $main_id;
    if ( $mobile_id > 0 ) $form_ids[] = $mobile_id;
    if ( $sidebar_id > 0 ) $form_ids[] = $sidebar_id;

    return array_unique( $form_ids ); // Ensure no duplicate IDs
}


/**
 * Hook: gform_post_paging
 * Runs when user moves from Page 1 -> Page 2 in any of the forms we handle.
 */
add_action('gform_post_paging', 'reglookup_init', 9999);

/**
 * Main function that checks form ID, current page, does API lookups,
 * and forces data into the entry for both visible & hidden fields.
 */
function reglookup_init($form) {
    // Only handle our configured forms (IDs retrieved from settings)
    $valid_forms = reglookup_get_form_ids();
    if (! in_array($form['id'], $valid_forms)) {
        return;
    }

    // Only on Page 2
    $current_page = GFFormDisplay::get_current_page($form['id']);
    if ($current_page != 2) {
        return;
    }

    // Clear previous data
    reglookup_reset_fields($form);

    // Get submitted registration
    $registration = preg_replace('/\s+/', '', reglookup_get_field(REGLOOKUP_REG_ID));

    // Initialize variables to empty for consistency
    $make = $model = $year = $color = $miles = $motEx = '';
    $vin = $dateFR = $cyl = $co2 = $fuel = $trans = $doors = $keeper = $wheel = $weight = '';
    $imageUrl = '';
    $lookup_successful = false;

    if (!empty($registration)) {
        // Vehicle & MOT lookup
        $output = reglookup_perform_lookup($registration);
        $imgOutput = reglookup_perform_image_lookup($registration);

        // Check if lookup returned enough data to be considered successful
        if (is_array($output) && !empty($output['Response']) &&
            !empty($output['Response']['DataItems']['VehicleRegistration']['Vin']) &&
            !empty($output['Response']['DataItems']['MotHistory']['RecordList'][0]['ExpiryDate'])
        ) {
            $lookup_successful = true;

            // Extract data
            $make   = $output['Response']['DataItems']['VehicleRegistration']['Make']          ?? '';
            $model  = $output['Response']['DataItems']['VehicleRegistration']['MakeModel']     ?? '';
            $year   = $output['Response']['DataItems']['VehicleRegistration']['YearOfManufacture'] ?? '';
            $color  = $output['Response']['DataItems']['VehicleRegistration']['Colour']        ?? '';
            $miles  = $output['Response']['DataItems']['MotHistory']['RecordList'][0]['OdometerInMiles'] ?? '';
            $motEx  = $output['Response']['DataItems']['MotHistory']['RecordList'][0]['ExpiryDate']      ?? '';

            // Hidden data
            $vin    = $output['Response']['DataItems']['VehicleRegistration']['Vin']           ?? '';
            $dateFR = $output['Response']['DataItems']['VehicleRegistration']['DateFirstRegistered'] ?? '';
            $cyl    = $output['Response']['DataItems']['VehicleRegistration']['EngineCapacity']  ?? '';
            $co2    = $output['Response']['DataItems']['VehicleRegistration']['Co2Emissions']    ?? '';
            $fuel   = $output['Response']['DataItems']['VehicleRegistration']['FuelType']        ?? '';
            $trans  = $output['Response']['DataItems']['VehicleRegistration']['TransmissionType'] ?? '';
            $doors  = $output['Response']['DataItems']['SmmtDetails']['NumberOfDoors']          ?? '';
            $keeper = $output['Response']['DataItems']['VehicleHistory']['NumberOfPreviousKeepers'] ?? '';
            $wheel  = $output['Response']['DataItems']['MotHistory']['RecordList'][0]['OdometerReading'] ?? '';
            $weight = $output['Response']['DataItems']['TechnicalDetails']['Dimensions']['GrossVehicleWeight'] ?? '';

            // Image URL
            $imageUrl  = $imgOutput['Response']['DataItems']['VehicleImages']['ImageDetailsList'][0]['ImageUrl'] ?? '';

        } else {
            // Lookup failed or returned insufficient data
            reglookup_validation_message(); // Display validation message
        }
    } else {
        // No registration submitted (e.g., initial load of page 2 without previous page)
        // No validation message here, just let manual fields appear.
    }

    // --- Populate Fields based on Lookup Success or Failure ---
    if ($lookup_successful) {
        // Populate all fields from successful API lookup
        reglookup_set_field_and_post($form, REGLOOKUP_MAKE_ID,     $make);
        reglookup_set_field_and_post($form, REGLOOKUP_MODEL_ID,    $model);
        reglookup_set_field_and_post($form, REGLOOKUP_DATE_ID,     $year);
        reglookup_set_field_and_post($form, REGLOOKUP_COLOUR_ID,   $color);
        reglookup_set_field_and_post($form, REGLOOKUP_MILEAGE_ID,  $miles);
        reglookup_set_field_and_post($form, REGLOOKUP_MOTD_ID,     $motEx);

        reglookup_set_field_and_post($form, REGLOOKUP_VIN_ID,          $vin);
        reglookup_set_field_and_post($form, REGLOOKUP_DATEFR_ID,       $dateFR);
        reglookup_set_field_and_post($form, REGLOOKUP_DATE2_ID,        $year); // Re-populate DATE2_ID with year from lookup
        reglookup_set_field_and_post($form, REGLOOKUP_CYLINDER_ID,     $cyl);
        reglookup_set_field_and_post($form, REGLOOKUP_CO2_ID,          $co2);
        reglookup_set_field_and_post($form, REGLOOKUP_FUEL_ID,         $fuel);
        reglookup_set_field_and_post($form, REGLOOKUP_TRANS_ID,        $trans);
        reglookup_set_field_and_post($form, REGLOOKUP_DOORS_ID,        $doors);
        reglookup_set_field_and_post($form, REGLOOKUP_KEEPERS_ID,      $keeper);
        reglookup_set_field_and_post($form, REGLOOKUP_WHEEL_ID,        $wheel);
        reglookup_set_field_and_post($form, REGLOOKUP_WEIGHT_ID,       $weight);
        reglookup_set_field_and_post($form, REGLOOKUP_IMAGE_ID,        $imageUrl);

        // Ensure manual input fields are empty if lookup was successful
        reglookup_set_field_and_post($form, REGLOOKUP_MANUAL_MAKE_MODEL_ID, '');
        reglookup_set_field_and_post($form, REGLOOKUP_MANUAL_YEAR_ID,       '');

    } else {
        // Lookup failed, or no registration was provided.
        // Main fields remain empty, allowing conditional logic to show manual input fields.
        // Ensure main fields are indeed empty for the display
        reglookup_set_field_and_post($form, REGLOOKUP_MAKE_ID,     '');
        reglookup_set_field_and_post($form, REGLOOKUP_MODEL_ID,    '');
        reglookup_set_field_and_post($form, REGLOOKUP_DATE_ID,     '');
        reglookup_set_field_and_post($form, REGLOOKUP_COLOUR_ID,   '');
        reglookup_set_field_and_post($form, REGLOOKUP_MILEAGE_ID,  '');
        reglookup_set_field_and_post($form, REGLOOKUP_MOTD_ID,     '');

        reglookup_set_field_and_post($form, REGLOOKUP_VIN_ID,          '');
        reglookup_set_field_and_post($form, REGLOOKUP_DATEFR_ID,       '');
        reglookup_set_field_and_post($form, REGLOOKUP_DATE2_ID,        '');
        reglookup_set_field_and_post($form, REGLOOKUP_CYLINDER_ID,     '');
        reglookup_set_field_and_post($form, REGLOOKUP_CO2_ID,          '');
        reglookup_set_field_and_post($form, REGLOOKUP_FUEL_ID,         '');
        reglookup_set_field_and_post($form, REGLOOKUP_TRANS_ID,        '');
        reglookup_set_field_and_post($form, REGLOOKUP_DOORS_ID,        '');
        reglookup_set_field_and_post($form, REGLOOKUP_KEEPERS_ID,      '');
        reglookup_set_field_and_post($form, REGLOOKUP_WHEEL_ID,        '');
        reglookup_set_field_and_post($form, REGLOOKUP_WEIGHT_ID,       '');
        reglookup_set_field_and_post($form, REGLOOKUP_IMAGE_ID,        '');
    }
}

/**
 * Retrieve a posted field from Gravity Forms submission
 */
function reglookup_get_field($field_id) {
    $input_name = 'input_' . $field_id;
    return isset($_POST[$input_name]) ? sanitize_text_field($_POST[$input_name]) : '';
}

/**
 * Assign a defaultValue to a Gravity Forms field
 * AND forcibly set $_POST, so GF sees it as user input.
 */
function reglookup_set_field_and_post(&$form, $field_id, $value) {
    foreach ($form['fields'] as &$field_obj) {
        if ($field_obj->id === floatval($field_id)) {
            $field_obj->defaultValue = esc_html($value);
        }
    }
    // Also ensure the $_POST value is set, which is crucial for
    // subsequent steps like gform_pre_submission and saving the entry.
    $_POST['input_' . $field_id] = $value;
}

/**
 * Perform the remote GET request to ukvehicledata VehicleAndMotHistory API
 * MODIFIED: To use API key from settings.
 */
function reglookup_perform_lookup($registration) {
    $api_key = reglookup_get_api_key(); // Get API key from settings
    if (empty($api_key)) {
        error_log('RegLookup: API Key not set in plugin settings.');
        return false;
    }

    $site_tag = site_url();
    $base = 'https://uk1.ukvehicledata.co.uk/api/datapackage/VehicleAndMotHistory'
          . '?v=2&api_nullitems=1'
          . '&auth_apikey=' . urlencode($api_key) // Use the dynamic API key
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
        error_log('RegLookup API Error (VehicleAndMotHistory): ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        error_log('RegLookup API Empty Body (VehicleAndMotHistory) for reg: ' . $registration);
        return false;
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded) || empty($decoded['Response'])) {
        error_log('RegLookup API Decoding Error (VehicleAndMotHistory) for reg: ' . $registration);
        return false;
    }

    return $decoded;
}

/**
 * Perform the remote GET request to ukvehicledata VehicleImageData API
 * MODIFIED: To use API key from settings.
 */
function reglookup_perform_image_lookup($registration) {
    $api_key = reglookup_get_api_key(); // Get API key from settings
    if (empty($api_key)) {
        // Error already logged by reglookup_perform_lookup, or can be logged here specifically for image lookup
        return false;
    }

    $site_tag = site_url();
    $base = 'https://uk1.ukvehicledata.co.uk/api/datapackage/VehicleImageData'
          . '?v=2&api_nullitems=1'
          . '&auth_apikey=' . urlencode($api_key) // Use the dynamic API key
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
        error_log('RegLookup API Error (VehicleImageData): ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        error_log('RegLookup API Empty Body (VehicleImageData) for reg: ' . $registration);
        return false;
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded) || empty($decoded['Response'])) {
        error_log('RegLookup API Decoding Error (VehicleImageData) for reg: ' . $registration);
        return false;
    }

    return $decoded;
}

/**
 * Unset previously submitted data for the fields we populate
 */
function reglookup_reset_fields(&$form) {
    $fields_to_clear = array(
        REGLOOKUP_MAKE_ID,
        REGLOOKUP_MODEL_ID,
        REGLOOKUP_DATE_ID,
        REGLOOKUP_COLOUR_ID,
        REGLOOKUP_MILEAGE_ID,
        REGLOOKUP_MOTD_ID,
        REGLOOKUP_IMAGE_ID,
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
        REGLOOKUP_WEIGHT_ID,
        REGLOOKUP_MANUAL_MAKE_MODEL_ID, // Clear manual input field
        REGLOOKUP_MANUAL_YEAR_ID,       // Clear manual input field
        REGLOOKUP_PREFIXED_ENTRY_ID, // Clear this too, will be populated on submission
    );

    foreach ($fields_to_clear as $fid) {
        unset($_POST['input_' . $fid]);
    }
}

/**
 * On form pre-render/validation, replace HTML blocks 45 & 46 for configured forms.
 * MODIFIED: To get form IDs from settings dynamically.
 */
// Get form IDs from settings to apply filters dynamically
$configured_form_ids = reglookup_get_form_ids();
foreach ( $configured_form_ids as $form_id ) {
    add_filter( 'gform_pre_render_' . $form_id,       'reglookup_render_fields_html' );
    add_filter( 'gform_pre_validation_' . $form_id,   'reglookup_render_fields_html' );
}

function reglookup_render_fields_html( $form ) {
    foreach ( $form['fields'] as &$f ) {
        // only care about HTML fields
        if ( $f->type !== 'html' ) {
            continue;
        }

        switch ( (int)$f->id ) {

            // ─── HTML #45: the image ───────────────────────────────────────────────────
            case 45:
                $image = rgpost( 'input_' . REGLOOKUP_IMAGE_ID ); // Get image from REGLOOKUP_IMAGE_ID
                if ( $image ) {
                    $f->content = sprintf(
                        '<img src="%s" alt="Vehicle image" style="max-width:100%%;height:auto;" />',
                        esc_url( $image )
                    );
                } else {
                    $f->content = '';
                }
                break;

            // ─── HTML #46: all the other fields ───────────────────────────────────────
            case 46:
                // Get values from the _POST array (where they are set by reglookup_init or manual input)
                $model  = rgpost( 'input_' . REGLOOKUP_MODEL_ID ) ?: ''; // Use empty string for better conditional
                $year   = rgpost( 'input_' . REGLOOKUP_DATE_ID )  ?: '';
                $colour = rgpost( 'input_' . REGLOOKUP_COLOUR_ID ) ?: '';
                $miles  = rgpost( 'input_' . REGLOOKUP_MILEAGE_ID ) ?: '';
                $mot    = rgpost( 'input_' . REGLOOKUP_MOTD_ID ) ?: '';

                // If no model or year (meaning lookup failed), display a message for manual entry
                if ( empty($model) || empty($year) ) {
                    $html = '<h3>Please enter your vehicle details below.</h3>';
                } else {
                    // build your custom HTML if lookup was successful
                    $html  = '<h3>Is this your vehicle?</h3><ul>';
                    
                    $html .= '<li><h5>'  . esc_html( $model )  . '</h5></li>';
                    $html .= '<li><strong>Year:</strong> '    . esc_html( $year )    . '</li>';
                    $html .= '<li><strong>Colour:</strong> ' . esc_html( $colour ) . '</li>';
                    $html .= '<li><strong>Mileage:</strong> ' . esc_html( $miles ) . ' miles</li>';
                    $html .= '<li><strong>MOT Expiry:</strong> ' . esc_html( $mot ) . '</li>';
                    $html .= '</ul>';
                }
                $f->content = $html;
                break;
        }
    }
    return $form;
}

/**
 * Show a short JS validation error if the lookup fails or returns no data
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

/**
 * Hook: gform_pre_submission
 * This runs just before Gravity Forms processes the form submission,
 * before saving the entry or running feeds.
 * We use it to copy manual input to the main fields and populate VIN.
 * MODIFIED: To get form IDs from settings dynamically.
 */
add_action( 'gform_pre_submission', 'reglookup_handle_submission_data' );
function reglookup_handle_submission_data( $form ) {
    // Only process for your specific forms (IDs retrieved from settings)
    $valid_forms = reglookup_get_form_ids();
    if (! in_array($form['id'], $valid_forms)) {
        return;
    }

    // --- 1. Handle Manual Make & Model and Year Input ---

    $api_model_value = rgpost( 'input_' . REGLOOKUP_MODEL_ID );
    $manual_make_model_value = rgpost( 'input_' . REGLOOKUP_MANUAL_MAKE_MODEL_ID );

    // If API model is empty, and manual make/model has a value, use manual value
    if ( empty( $api_model_value ) && ! empty( $manual_make_model_value ) ) {
        $_POST[ 'input_' . REGLOOKUP_MODEL_ID ] = sanitize_text_field( $manual_make_model_value );
    }
    // Always unset the manual input field so it doesn't create a separate entry
    unset( $_POST[ 'input_' . REGLOOKUP_MANUAL_MAKE_MODEL_ID ] );


    $api_year_value = rgpost( 'input_' . REGLOOKUP_DATE_ID );
    $manual_year_value = rgpost( 'input_' . REGLOOKUP_MANUAL_YEAR_ID );

    // If API year is empty, and manual year has a value, use manual value
    if ( empty( $api_year_value ) && ! empty( $manual_year_value ) ) {
        $_POST[ 'input_' . REGLOOKUP_DATE_ID ] = sanitize_text_field( $manual_year_value );
    }
    // Always unset the manual input field
    unset( $_POST[ 'input_' . REGLOOKUP_MANUAL_YEAR_ID ] );


    // --- 2. Handle VIN Fallback ---
    $current_vin = rgpost( 'input_' . REGLOOKUP_VIN_ID );

    // If VIN is empty, populate it with the phone number (no spaces)
    if ( empty( $current_vin ) ) {
        $phone_number = rgpost( 'input_' . REGLOOKUP_PHONE_ID );
        if ( ! empty( $phone_number ) ) {
            $cleaned_phone_number = preg_replace('/\s+/', '', $phone_number); // Remove all spaces
            $_POST[ 'input_' . REGLOOKUP_VIN_ID ] = sanitize_text_field( $cleaned_phone_number );
        }
    }
}

/**
 * Hook: gform_after_submission
 * This runs just after Gravity Forms has saved the entry.
 * We use it to add the prefixed entry ID to our custom hidden field.
 * MODIFIED: To get form IDs from settings dynamically.
 */
add_action( 'gform_after_submission', 'reglookup_add_prefix_to_entry_id_field', 10, 2 );
function reglookup_add_prefix_to_entry_id_field( $entry, $form ) {
    // Only process for your specific forms (IDs retrieved from settings)
    $valid_forms = reglookup_get_form_ids();
    if ( ! in_array( $form['id'], $valid_forms ) ) {
        return;
    }

    $prefixed_id_field_id = REGLOOKUP_PREFIXED_ENTRY_ID;
    $prefix = reglookup_get_entry_id_prefix(); // Get prefix from settings

    // Ensure we have a prefix from settings, otherwise use a default
    if ( empty( $prefix ) ) {
        $prefix = 'ENTRY-'; // Default prefix if not set in admin
    }

    // The raw entry ID is available as $entry['id']
    $current_entry_id = $entry['id'];

    // Construct the new prefixed ID
    $prefixed_entry_id = $prefix . $current_entry_id;

    // Update the hidden field with the new prefixed value
    GFAPI::update_entry_field( $entry['id'], $prefixed_id_field_id, $prefixed_entry_id );
}

// Helper function to get the decrypted API key from settings
function reglookup_get_api_key() {
    $options = get_option( 'reglookup_settings' );
    $encrypted_api_key = $options['api_key'] ?? '';
    if ( empty( $encrypted_api_key ) ) {
        return '';
    }
    return Reglookup_Settings::decrypt_data( $encrypted_api_key );
}

// Helper function to get the entry ID prefix from settings
function reglookup_get_entry_id_prefix() {
    $options = get_option( 'reglookup_settings' );
    return $options['entry_id_prefix'] ?? '';
}