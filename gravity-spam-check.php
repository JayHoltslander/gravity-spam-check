<?php
/**
 * Plugin Name:       Gravity Forms Disallowed Keywords Blocker
 * Description:       Blocks Gravity Forms submissions if they contain any keywords from WordPress' "Disallowed Comment Keys".
 * Version:           1.0.0
 * Author:            Jay Holtslander + Google Gemini
 * Author URI:        [https://gemini.google.com](https://gemini.google.com)
 * License:           GPL-2.0+
 * License URI:       [http://www.gnu.org/licenses/gpl-2.0.txt](http://www.gnu.org/licenses/gpl-2.0.txt)
 */

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filter Gravity Forms submissions to check against WordPress's Disallowed Comment Keys.
 *
 * @param array $validation_result The current validation result array.
 * @return array The modified validation result.
 */
add_filter( 'gform_validation', 'gfkdb_validate_gravity_form_for_disallowed_keywords' );

function gfkdb_validate_gravity_form_for_disallowed_keywords( $validation_result ) {
    // Get the current form object from the validation result
    $form = $validation_result['form'];

    // Retrieve the list of disallowed keys from WordPress settings (Settings > Discussion)
    // The disallowed_keys option stores them as a newline-separated string.
    $disallowed_keys_string = get_option( 'disallowed_keys' );

    // If there are no disallowed keys configured, or the string is empty,
    // there's nothing to check, so return the original validation result.
    if ( empty( $disallowed_keys_string ) ) {
        return $validation_result;
    }

    // Convert the newline-separated string of disallowed keys into an array.
    // array_map('trim', ...) removes leading/trailing whitespace from each key.
    // array_filter(...) removes any empty entries that might result from extra newlines.
    $disallowed_keywords = array_filter( array_map( 'trim', explode( "\n", $disallowed_keys_string ) ) );

    // If after processing, no valid keywords remain, return the original validation.
    if ( empty( $disallowed_keywords ) ) {
        return $validation_result;
    }

    // Set a flag to track if any field failed validation due to keywords
    $form_is_invalid = false;

    // Loop through each field in the Gravity Form.
    // We use '&' before $field to modify the field object directly within the loop.
    foreach ( $form['fields'] as &$field ) {
        // Define which field types should be checked for disallowed keywords.
        // These are typically text-based fields where users input free-form content.
        $field_types_to_check = array(
            'text',         // Single Line Text field
            'textarea',     // Paragraph Text field
            'email',        // Email field
            'website',      // Website/URL field
            'post_title',   // Post Title field (if used in Post Fields)
            'post_content', // Post Body field (if used in Post Fields)
            'post_excerpt', // Post Excerpt field (if used in Post Fields)
            'phone',        // Phone field
            'name'          // Name field (though often split into first/last, still worth checking)
            // Add or remove other field types as needed based on your form's structure.
        );

        // Check if the current field's type is one we want to validate.
        if ( in_array( $field->type, $field_types_to_check ) ) {
            // Get the submitted value for the current field.
            // rgpost() is a Gravity Forms helper function for getting submitted data.
            $field_value = rgpost( 'input_' . $field->id );

            // If the field is empty, there's nothing to check, so skip to the next field.
            if ( empty( $field_value ) ) {
                continue;
            }

            // Iterate through each disallowed keyword.
            foreach ( $disallowed_keywords as $disallowed_word ) {
                // Use stripos() for a case-insensitive check to see if the keyword exists within the field value.
                // stripos() returns the position of the first occurrence of the substring, or false if not found.
                // WordPress's 'Disallowed Comment Keys' does a "contains" check, not an exact word match.
                // For example, if "spam" is disallowed, "spammer" will also be flagged.
                if ( stripos( $field_value, $disallowed_word ) !== false ) {
                    // A disallowed keyword was found in this field!
                    // Mark the individual field as failed validation.
                    $field->failed_validation = true;
                    // Set a custom validation message for this field.
                    $field->validation_message = 'This field contains content that is not allowed. Please remove it.';

                    // Mark the overall form as invalid.
                    $validation_result['is_valid'] = false;
                    $form_is_invalid = true; // Set the flag

                    // No need to check other keywords for this field, or other fields for this form.
                    // Break out of both the inner and outer loops.
                    break 2; // Breaks out of the current `foreach ($disallowed_keywords as ...)` AND the outer `foreach ($form['fields'] as ...)`
                }
            }
        }
    }

    // If the form was found to be invalid, ensure the overall validation result reflects this.
    // This redundant check is good practice, though the `break 2` handles it if a match is found.
    if ( $form_is_invalid ) {
        $validation_result['is_valid'] = false;
        // Optionally, you can set a general form-level message, but field-specific messages are better for user experience.
        // $validation_result['form']['confirmations'][0]['message'] = 'Your submission contains disallowed content. Please correct the highlighted fields.';
    }

    // Return the (potentially modified) validation result.
    return $validation_result;
}