<?php
/**
 * Field & Form Constants
 */

// We only need one main form ID if you’re using form #5 now.
// If you still have mobile/sidebar forms, you can add them back.
define('REGLOOKUP_FORM_ID', 5);

// The user enters:
define('REGLOOKUP_REG_ID', 1);      // “REGISTRATION” field
define('REGLOOKUP_POSTCODE_ID', 8); // “POST CODE” field

// Visible on Page 2:
define('REGLOOKUP_MAKE_ID', 2);     // “CAR MAKE”
define('REGLOOKUP_MODEL_ID', 10);   // “CAR MODEL”
define('REGLOOKUP_DATE_ID', 3);     // “YEAR” (ID #3)
define('REGLOOKUP_COLOUR_ID', 4);   // “COLOUR”
define('REGLOOKUP_MILEAGE_ID', 39); // “LAST MOT MILAGE”
define('REGLOOKUP_MOTD_ID', 40);    // “MOT Expiry”
define('REGLOOKUP_CONTACT_ID', 9);  // “PHONE NUMBER” (though plugin doesn’t populate it)

// Hidden fields (if you want to capture them):
define('REGLOOKUP_MOT_ID', 28);     // Hidden field “Mot” (for TestResult)
define('REGLOOKUP_MAKE2_ID', 14);
define('REGLOOKUP_MODEL2_ID', 15);
define('REGLOOKUP_VIN_ID', 16);
define('REGLOOKUP_DATEFR_ID', 17);
define('REGLOOKUP_DATE2_ID', 18);
define('REGLOOKUP_CYLINDER_ID', 19);
define('REGLOOKUP_CO2_ID', 21);
define('REGLOOKUP_FUEL_ID', 20);
define('REGLOOKUP_TAXS_ID', 22);
define('REGLOOKUP_COLOUR2_ID', 23);
define('REGLOOKUP_TYPEA_ID', 24);
define('REGLOOKUP_WHEEL_ID', 25);
define('REGLOOKUP_WEIGHT_ID', 26);
define('REGLOOKUP_TAXD_ID', 27);
define('REGLOOKUP_TAX_ID', 29);
define('REGLOOKUP_TRANS_ID', 31);
define('REGLOOKUP_DOORS_ID', 32);
define('REGLOOKUP_KEEPERS_ID', 34);
