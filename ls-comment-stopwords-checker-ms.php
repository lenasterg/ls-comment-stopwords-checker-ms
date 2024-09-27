<?php
/**
 * Plugin Name: LS Comment Stopword Checker for Multisite
 * Plugin URI:  
 * Description: Prevents comments containing specific stopwords from being posted across the multisite.
 * Version:     1.0
 * Author:      lenasterg
 * Author URI:  
 * License:     GPL-2.0+
 * Network:     true
 */

/**
 * Predefined email for the Super Admin to send notifications.
 */
define('LS_SUPER_ADMIN_EMAIL', 'YOUR_MAIL@ADDRESS HERE'); // Predefined email
/**
 * Retrieves stopwords from an external file.
 *
 * The stopwords are loaded from 'stopwords.txt', which should be located in the plugin directory.
 *
 * @return array List of stopwords, or an empty array if the file can't be read.
 */
function ls_get_stopwords() {
    $stopwords_file = plugin_dir_path(__FILE__) . 'stopwords.txt';
    
    // Check if the stopwords file exists and is readable
    if (file_exists($stopwords_file) && is_readable($stopwords_file)) {
        // Load the stopwords file contents
        $stopwords = file($stopwords_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        // Ensure all stopwords are in lowercase for uniform comparison
        return array_map('strtolower', $stopwords);
    }

    // Return an empty array if the file can't be read
    return [];
}

/**
 * Checks if a comment contains any prohibited words from a predefined list.
 *
 * This function hooks into the 'preprocess_comment' filter, and if a prohibited word 
 * is found in the comment content, author name, email, or URL, the submission is blocked 
 * and the user is shown an error message.
 *
 * It processes stopwords in batches to optimize performance, especially for large lists.
 *
 * @param array $commentdata The comment data array containing 'comment_content', 
 *                           'comment_author', 'comment_author_email', 
 *                           and 'comment_author_url'.
 * 
 * @return array $commentdata The original or modified comment data. 
 *                             If a prohibited word is found, the function terminates
 *                             execution with an error message.
 */
function ls_check_comment_for_stopwords($commentdata) {
    $stopwords = ls_get_stopwords();
    
    // Set the maximum number of stopwords to include in each regex check
    $max_batch_size = 1000; // Adjust this value as necessary

    // Split stopwords into batches only once
    $batches = array_chunk($stopwords, $max_batch_size);

    // Combine the fields to check into an array for easier iteration
    $fields_to_check = [
        'content' => $commentdata['comment_content'],
        //'author' => $commentdata['comment_author'],
       // 'email' => $commentdata['comment_author_email'],
        'url' => $commentdata['comment_author_url'],
    ];

    // Iterate over each field
    foreach ($fields_to_check as $field => $value) {
        // Check each batch
        foreach ($batches as $batch) {
            // Create a regex pattern for the current batch, using preg_quote to escape regex special characters
            $pattern = '/' . implode('|', array_map(function($stopword) {
                return preg_quote($stopword, '/'); // Escape the delimiter '/'
            }, $batch)) . '/i'; // Case insensitive
            
            // Check if the value matches any stopword in the current batch
            if (preg_match($pattern, $value)) 
	    {
		// Once a stopword is found, retrieve the post data and send an email
                ls_send_email_to_super_admin($commentdata);
		
                // If a stopword is found, prevent comment submission
                wp_die(
                    __('Your comment contains prohibited words and cannot be posted.', 'ls-comment-stopwords-checker-ms'),
                    __('Comment Blocked', 'ls-comment-stopwords-checker-ms'),
                    array('response' => 403)
                );
            }
        }
    }

    // If no stopword is found, allow comment submission
    return $commentdata;
}

/**
 * Sends an email to the Super Admin when a comment is blocked due to prohibited words.
 *
 * @param array $commentdata The comment data.
 */
function ls_send_email_to_super_admin($commentdata) {
    // Retrieve post data (only when a stopword is found)
    $post_id    = $commentdata['comment_post_ID'];
    $post_title = get_the_title($post_id);
    $post_url   = get_permalink($post_id);

    // Prepare the email subject and message
    $subject = __('Blocked Comment Notification - Prohibited Words Detected', 'ls-comment-stopwords-checker-ms');
    $message = sprintf(
        __("A comment was blocked due to prohibited words.\n\n".
        "Commenter Details:\n".
        "Comment Author: %s\n".
        "Author Email: %s\n".
        "Author URL: %s\n".
        "Comment Content: %s\n".
        "Author IP: %s\n\n".
        "Post Details:\n".
        "Post Title: %s\n".
        "Post URL: %s\n".
        "Post ID: %d\n", 'ls-comment-stopwords-checker-ms'),
        $commentdata['comment_author'],
        $commentdata['comment_author_email'],
        $commentdata['comment_author_url'],
        $commentdata['comment_content'],
        $commentdata['comment_author_IP'], // IP address of the commenter
        $post_title,
        $post_url,
        $commentdata['comment_post_ID']
    );

    // Send the email to the super admin (using the predefined LS_SUPER_ADMIN_EMAIL)
    wp_mail(LS_SUPER_ADMIN_EMAIL, $subject, $message);
}



/**
 * Adds a filter to check comments before they are saved in the database.
 */
add_filter('preprocess_comment', 'ls_check_comment_for_stopwords');

/**
 * Checks if the site is part of a multisite network, and if so, adds a network admin menu.
 */
if (is_multisite()) {
    add_action('network_admin_menu', 'ls_comment_stopword_checker_network_menu');
}

/**
 * Adds a settings page to the network admin menu for managing the stopword checker.
 */
function ls_comment_stopword_checker_network_menu() {
    add_menu_page(
        __('Stopword Checker Settings', 'ls-comment-stopwords-checker-ms'),   // Page title.
        __('Stopword Checker', 'ls-comment-stopwords-checker-ms'),            // Menu title.
        'manage_network_options',      // Capability required to view the page.
        'ls-comment-stopwords-checker-ms', // Menu slug.
        'ls_stopword_checker_network_page', // Callback function to display the content of the page.
        '',                            // Icon URL (optional).
        20                             // Position of the menu item in the network admin menu.
    );
}

/**
 * Displays the Stopword Checker settings page in the network admin dashboard.
 *
 * This page shows the list of stopwords currently being used, or a message if there are too many.
 */
function ls_stopword_checker_network_page() {
    // Path to the stopwords file
    $stopwords_file = plugin_dir_path(__FILE__) . 'stopwords.txt';
    
    // Initialize an empty array for stopwords and last modified date
    $stopwords = [];
    $last_modified = '';

    // Check if the stopwords file exists and is readable
    if (file_exists($stopwords_file) && is_readable($stopwords_file)) {
        // Get the last modified time
        $last_modified_time = filemtime($stopwords_file);
        if ($last_modified_time) {
            // Format the last modified time to a human-readable format
            $last_modified = date('F j, Y, g:i a', $last_modified_time);
        }

        // Load the file lines
        $lines = file($stopwords_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Assign the remaining lines as stopwords
        if (!empty($lines)) {
            $stopwords = $lines; // All lines after the last modified date
        }
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Stopword Checker Settings', 'ls-comment-stopwords-checker-ms') . '</h1>';
    
    // Description of the settings page
    echo '<p>' . esc_html__('Below is the list of stopwords that will prevent a comment from being posted if found.', 'ls-comment-stopwords-checker-ms') . '</p>';
    
    // Show the last modified date
    if ($last_modified) {
        echo '<p>' . esc_html__('Last modified on: ', 'ls-comment-stopwords-checker-ms') . esc_html($last_modified) . '</p>';
    }

    // Check the count of stopwords
    $stopword_count = count($stopwords);
    
    if ($stopword_count > 50) {
        // Show a count message if there are more than 50 stopwords
        echo '<p>' . esc_html(sprintf(__('There are currently %d stopwords configured. The list is too long to display.', 'ls-comment-stopwords-checker-ms'), $stopword_count)) . '</p>';
    } else {
        // Sort the stopwords for display
        sort($stopwords);
        
        // Display the stopword list in an HTML table
        if (!empty($stopwords)) {
            echo '<table class="widefat">';
            echo '<thead><tr><th>' . esc_html__('Stopword', 'ls-comment-stopwords-checker-ms') . '</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($stopwords as $stopword) {
                echo '<tr><td>' . esc_html($stopword) . '</td></tr>';
            }

            echo '</tbody></table>';
        } else {
            // If no stopwords are available
            echo '<p>' . esc_html__('No stopwords found.', 'ls-comment-stopwords-checker-ms') . '</p>';
        }
    }

    echo '</div>';
}

// Hook into the admin_notices action to display the notice on comment settings
add_action('admin_notices', 'ls_stopword_notice_for_subsite_admin');

/**
 * Displays a notice on the comment settings page for subsite admins.
 *
 * This notice informs admins that a stopword list is configured for the multisite,
 * and that comments containing these stopwords will be blocked from being posted.
 *
 * @return void
 */
function ls_stopword_notice_for_subsite_admin() {
    // Get the current screen object
    $screen = get_current_screen();
    
    // Check if we are on the discussion settings page
    if ($screen && $screen->id === 'options-discussion') {
        // Check if the user has the capability to manage comments
        if (current_user_can('manage_comments')) {
            ?>
            <div class="notice notice-info updated">
                <p>
                    <?php 
                    echo esc_html__('Notice: A stopword list is configured for this multisite. Comments containing these stopwords will be blocked from being posted.', 'ls-comment-stopwords-checker-ms'); 
                    ?>
                </p>
            </div>
            <?php
        }
    }
}
