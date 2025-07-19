<?php
/**
 * Reglookup Settings Class
 * Handles the creation of the admin settings page and managing settings.
 */

if ( ! defined( 'WPINC' ) ) {
    die; // Abort if accessed directly
}

class Reglookup_Settings {

    private $option_group = 'reglookup_settings_group'; // Option group for register_setting()
    private $option_name  = 'reglookup_settings';     // Option name in wp_options table

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page() {
        add_options_page(
            'RegLookup Settings', // Page title
            'RegLookup',          // Menu title
            'manage_options',     // Capability required
            'reglookup-settings', // Menu slug
            array( $this, 'create_admin_page' ) // Callback to render the page
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h2>RegLookup Plugin Settings</h2>
            <form method="post" action="options.php">
                <?php
                    settings_fields( $this->option_group );
                    do_settings_sections( 'reglookup-settings' ); // Page slug
                    submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init() {
        register_setting(
            $this->option_group, // Option group
            $this->option_name,  // Option name
            array( $this, 'sanitize' ) // Sanitize callback
        );

        add_settings_section(
            'reglookup_api_entry_section', // ID
            'API & Entry ID Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'reglookup-settings' // Page slug
        );

        add_settings_field(
            'api_key', // ID
            'ukvehicledata API Key', // Title
            array( $this, 'api_key_callback' ), // Callback
            'reglookup-settings', // Page slug
            'reglookup_api_entry_section' // Section
        );

        add_settings_field(
            'entry_id_prefix', // ID
            'Entry ID Prefix', // Title
            array( $this, 'entry_id_prefix_callback' ), // Callback
            'reglookup-settings', // Page slug
            'reglookup_api_entry_section' // Section
        );

        add_settings_section(
            'reglookup_form_ids_section', // ID
            'Gravity Forms IDs', // Title
            array( $this, 'print_form_ids_section_info' ), // Callback
            'reglookup-settings' // Page slug
        );

        add_settings_field(
            'main_form_id', // ID
            'Main Form ID', // Title
            array( $this, 'main_form_id_callback' ), // Callback
            'reglookup-settings', // Page slug
            'reglookup_form_ids_section' // Section
        );

        add_settings_field(
            'mobile_form_id', // ID
            'Mobile Form ID', // Title
            array( $this, 'mobile_form_id_callback' ), // Callback
            'reglookup-settings', // Page slug
            'reglookup_form_ids_section' // Section
        );

        add_settings_field(
            'sidebar_form_id', // ID
            'Sidebar Form ID', // Title
            array( $this, 'sidebar_form_id_callback' ), // Callback
            'reglookup-settings', // Page slug
            'reglookup_form_ids_section' // Section
        );
    }

    /**
     * Print the Section text for API & Entry ID settings.
     */
    public function print_section_info() {
        print 'Enter your API key and custom entry ID prefix below:';
    }

    /**
     * Print the Section text for Form IDs.
     */
    public function print_form_ids_section_info() {
        print 'Enter the IDs of the Gravity Forms this plugin should interact with. <br>
               (You can find a form\'s ID in the Gravity Forms list or when editing a form in the URL.)';
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function api_key_callback() {
        $options = get_option( $this->option_name );
        $api_key = '';

        // Decrypt the API key for display, but only if it's not empty
        if ( ! empty( $options['api_key'] ) ) {
            $api_key = self::decrypt_data( $options['api_key'] );
        }
        ?>
        <input type="text" id="api_key" name="<?php echo esc_attr($this->option_name); ?>[api_key]" value="<?php echo esc_attr( $api_key ); ?>" class="large-text" placeholder="Enter your ukvehicledata API key" />
        <p class="description">Your API key for ukvehicledata. This will be encrypted in the database.</p>
        <?php
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function entry_id_prefix_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="text" id="entry_id_prefix" name="<?php echo esc_attr($this->option_name); ?>[entry_id_prefix]" value="<?php echo esc_attr( $options['entry_id_prefix'] ?? '' ); ?>" class="regular-text" placeholder="e.g., VCM-" />
        <p class="description">Prefix to add to your Gravity Forms entry IDs (e.g., VCM-, REF-).</p>
        <?php
    }

    /**
     * Callback for Main Form ID field.
     */
    public function main_form_id_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="number" id="main_form_id" name="<?php echo esc_attr($this->option_name); ?>[main_form_id]" value="<?php echo esc_attr( $options['main_form_id'] ?? '' ); ?>" class="small-text" min="1" placeholder="e.g., 2" />
        <p class="description">The ID for your primary registration lookup form.</p>
        <?php
    }

    /**
     * Callback for Mobile Form ID field.
     */
    public function mobile_form_id_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="number" id="mobile_form_id" name="<?php echo esc_attr($this->option_name); ?>[mobile_form_id]" value="<?php echo esc_attr( $options['mobile_form_id'] ?? '' ); ?>" class="small-text" min="1" placeholder="e.g., 10" />
        <p class="description">The ID for your mobile/smaller registration lookup form.</p>
        <?php
    }

    /**
     * Callback for Sidebar Form ID field.
     */
    public function sidebar_form_id_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="number" id="sidebar_form_id" name="<?php echo esc_attr($this->option_name); ?>[sidebar_form_id]" value="<?php echo esc_attr( $options['sidebar_form_id'] ?? '' ); ?>" class="small-text" min="1" placeholder="e.g., 11" />
        <p class="description">The ID for your sidebar/widget registration lookup form.</p>
        <?php
    }

    /**
     * Sanitize and encrypt settings callback for register_setting
     *
     * @param array $input Contains all settings fields as array keys
     * @return array
     */
    public function sanitize( $input ) {
        $new_input = array();
        $current_options = get_option( $this->option_name ); // Get current saved options for comparison

        // Sanitize and encrypt API Key
        if ( isset( $input['api_key'] ) ) {
            $api_key = sanitize_text_field( $input['api_key'] );
            $current_decrypted_api_key = '';
            if ( ! empty( $current_options['api_key'] ) ) {
                 $current_decrypted_api_key = self::decrypt_data( $current_options['api_key'] );
            }

            if ( $api_key !== $current_decrypted_api_key ) {
                $new_input['api_key'] = self::encrypt_data( $api_key );
            } else {
                // If it hasn't changed, keep the existing encrypted value from current_options
                $new_input['api_key'] = $current_options['api_key'] ?? '';
            }
        }

        // Sanitize Entry ID Prefix
        if ( isset( $input['entry_id_prefix'] ) ) {
            $new_input['entry_id_prefix'] = sanitize_text_field( $input['entry_id_prefix'] );
        }

        // Sanitize Form IDs (ensure they are integers)
        if ( isset( $input['main_form_id'] ) ) {
            $new_input['main_form_id'] = absint( $input['main_form_id'] );
        }
        if ( isset( $input['mobile_form_id'] ) ) {
            $new_input['mobile_form_id'] = absint( $input['mobile_form_id'] );
        }
        if ( isset( $input['sidebar_form_id'] ) ) {
            $new_input['sidebar_form_id'] = absint( $input['sidebar_form_id'] );
        }

        return $new_input;
    }

    /**
     * Simple Encryption for API Key
     * Uses OpenSSL for better security if available.
     * Fallback to base64 encoding (not truly encrypted, but obscures) if not.
     */
    public static function encrypt_data( $data ) {
        if ( empty( $data ) ) {
            return '';
        }

        $key = defined('AUTH_KEY') ? AUTH_KEY : NONCE_KEY;
        // Ensure key is 32 bytes for aes-256-cbc
        $key = substr(hash('sha256', $key), 0, 32); 

        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($iv_length);

        if ( function_exists('openssl_encrypt') ) {
            $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
            if ($encrypted === false) {
                error_log('RegLookup Encryption Error: openssl_encrypt failed.');
                return base64_encode($data); // Fallback to base64 if encryption fails
            }
            // Store IV with encrypted data
            return base64_encode($iv . $encrypted);
        } else {
            error_log('RegLookup Warning: openssl_encrypt not available. Falling back to base64 encoding (not truly secure).');
            return base64_encode($data); // Fallback for servers without OpenSSL
        }
    }

    /**
     * Simple Decryption for API Key
     */
    public static function decrypt_data( $data ) {
        if ( empty( $data ) ) {
            return '';
        }

        $decoded = base64_decode($data);
        if ($decoded === false) {
            return ''; // Invalid base64
        }

        $key = defined('AUTH_KEY') ? AUTH_KEY : NONCE_KEY;
        // Ensure key is 32 bytes for aes-256-cbc
        $key = substr(hash('sha256', $key), 0, 32);

        $iv_length = openssl_cipher_iv_length('aes-256-cbc');

        if ( function_exists('openssl_decrypt') && strlen($decoded) >= $iv_length ) {
            $iv = substr($decoded, 0, $iv_length);
            $encrypted_data = substr($decoded, $iv_length);
            $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
            if ($decrypted === false) {
                error_log('RegLookup Decryption Error: openssl_decrypt failed.');
                return $data; // Return original data if decryption fails (may be base64 only)
            }
            return $decrypted;
        } else {
            // Assume it was just base64 encoded if openssl not available or data too short
            return $decoded;
        }
    }
}