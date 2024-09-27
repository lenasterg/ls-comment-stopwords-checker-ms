<?php
defined('ABSPATH') or die('Hi you');

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

class LS_Comment_Stopword_Checker {
    /**
     * Predefined email for the Super Admin to receive notifications.
     * 
     * This email address will be used to notify the Super Admin whenever a comment 
     * is blocked due to the presence of prohibited stopwords. If not defined or 
     * left empty, the plugin will use the admin email configured for the WordPress
     * multisite network (or single site) as a fallback.
     */
    const SUPER_ADMIN_EMAIL = ''; // Predefined email

    public function __construct() {
        // Hook to check comments before they are saved
        add_filter('preprocess_comment', [$this, 'check_comment_for_stopwords']);
        
        // Admin menu for multisite
        if (is_multisite()) {
            add_action('network_admin_menu', [$this, 'add_network_menu']);
        }

        // Admin notices for subsite admins
        add_action('admin_notices', [$this, 'show_stopword_notice']);
    }

    /**
     * Retrieves stopwords from an external file.
     *
     * @return array List of stopwords, or an empty array if the file can't be read.
     */
    public function get_stopwords() {
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
     * @param array $commentdata The comment data array.
     * @return array $commentdata The original or modified comment data.
     */
    public function check_comment_for_stopwords($commentdata) {
        $stopwords = $this->get_stopwords();
        
        // Set the maximum number of stopwords to include in each regex check
        $max_batch_size = 1000; // Adjust this value as necessary

        // Split stopwords into batches only once
        $batches = array_chunk($stopwords, $max_batch_size);

        // Combine the fields to check into an array for easier iteration
        $fields_to_check = [
            'content' => $commentdata['comment_content'],
            'author' => $commentdata['comment_author'],
            'email' => $commentdata['comment_author_email'],
            'url' => $commentdata['comment_author_url'],
            'ip' => $commentdata['comment_author_IP']
        ];

        // Iterate over each field
        foreach ($fields_to_check as $field => $value) {
            // Check each batch
            foreach ($batches as $batch) {
                // Create a regex pattern for the current batch
                $pattern = '/' . implode('|', array_map(function($stopword) {
                    return preg_quote($stopword, '/'); // Escape the delimiter '/'
                }, $batch)) . '/i'; // Case insensitive
                
                // Check if the value matches any stopword in the current batch
                if (preg_match($pattern, $value, $matches)) {
                    // Extract the first matched stopword
                    $matched_stopword = htmlspecialchars($matches[0]);

                    // Create a hook for other plugins to add additional functionality or data
                    do_action('ls_before_send_email_notification', $commentdata, $matched_stopword);

                    // Send an email to the Super Admin with the specific stopword
                    $this->send_email_to_super_admin($commentdata, $matched_stopword);
                    
                    // Prevent comment submission
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
     * Sends an email to the admin when a comment is blocked due to prohibited words.
     *
     * @param array $commentdata The comment data.
     * @param string $matched_stopword The stopword that triggered the block.
     */
    public function send_email_to_super_admin($commentdata, $matched_stopword) {
        // Retrieve post data (only when a stopword is found)
        $post_id    = $commentdata['comment_post_ID'];
        $post_title = get_the_title($post_id);
        $post_url   = get_permalink($post_id);

        // Prepare the email subject and message
        $subject = __('Blocked Comment Notification - Prohibited Words Detected', 'ls-comment-stopwords-checker-ms');
        $message = sprintf(
            __("A comment was blocked due to prohibited words.\n\n".
            "Blocked Stopword: %s\n\n".
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
            $matched_stopword,
            htmlspecialchars($commentdata['comment_author']),
            htmlspecialchars($commentdata['comment_author_email']),
            htmlspecialchars($commentdata['comment_author_url']),
            htmlspecialchars($commentdata['comment_content']),
            htmlspecialchars($commentdata['comment_author_IP']),
            htmlspecialchars($post_title),
            htmlspecialchars($post_url),
            $post_id
        );

        // Check if the SUPER_ADMIN_EMAIL constant is defined and not empty
        if (!empty(self::SUPER_ADMIN_EMAIL)) {
            $email_to_send = self::SUPER_ADMIN_EMAIL;
        } else {
            // Fallback to the default super admin email
            $email_to_send = is_multisite() ? get_site_option('admin_email') : get_option('admin_email');
        }

        // Send the email to the super admin 
        wp_mail($email_to_send, $subject, $message);
    }

    /**
     * Adds a settings page to the network admin menu for managing the stopword checker.
     */
    public function add_network_menu() {
        add_menu_page(
            __('Stopword Checker Settings', 'ls-comment-stopwords-checker-ms'),
            __('Stopword Checker', 'ls-comment-stopwords-checker-ms'),
            'manage_network_options',
            'ls-comment-stopwords-checker-ms',
            [$this, 'stopword_checker_network_page'],
            '',
            20
        );
    }

    /**
     * Displays the Stopword Checker settings page in the network admin dashboard.
     */
    public function stopword_checker_network_page() {
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
                $last_modified = date('F j, Y, g:i a', $last_modified_time);
            }

            // Load the file lines
            $lines = file($stopwords_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $stopwords = !empty($lines) ? $lines : [];
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Stopword Checker Settings', 'ls-comment-stopwords-checker-ms') . '</h1>';
        echo '<p>' . esc_html__('Below is the list of stopwords that will prevent a comment from being posted if found.', 'ls-comment-stopwords-checker-ms') . '</p>';
        
        if ($last_modified) {
            echo '<p>' . esc_html__('Last modified on: ', 'ls-comment-stopwords-checker-ms') . esc_html($last_modified) . '</p>';
        }

        $stopword_count = count($stopwords);
        
        if ($stopword_count > 50) {
            echo '<p>' . esc_html(sprintf(__('There are currently %d stopwords configured. The list is too long to display.', 'ls-comment-stopwords-checker-ms'), $stopword_count)) . '</p>';
        } else {
            sort($stopwords);
            echo '<table class="widefat">';
            echo '<thead><tr><th>' . esc_html__('Stopword', 'ls-comment-stopwords-checker-ms') . '</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($stopwords as $stopword) {
                echo '<tr><td>' . esc_html($stopword) . '</td></tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /**
     * Displays a notice on the comment settings page for subsite admins.
     */
    public function show_stopword_notice() {
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
}

// Initialize the plugin
new LS_Comment_Stopword_Checker();
