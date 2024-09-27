# LS Comment Stopword Checker for Multisite

**Contributors:** lenasterg  
**Tags:** comments, stopwords, multisite, spam  
**Requires at least:** 5.0  
**Tested up to:** 6.3  
**Requires PHP:** 7.0  
**Stable tag:** 1.0  
**License:** GPLv2 or later  
**License URI:** [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)  

LS Comment Stopword Checker is a WordPress multisite plugin that prevents users from posting comments containing predefined stopwords. It allows network admins to configure a stopword list that blocks specific terms from appearing in comments across all subsites.

## Description

LS Comment Stopword Checker is a WordPress plugin specifically designed for multisite networks to prevent users from posting comments containing stopwords. The plugin helps network administrators manage and block comments with predefined prohibited words from being posted across all subsites.

### Features:
- Supports multisite WordPress networks.
- Blocks comments containing prohibited words (defined in `stopwords.txt`).
- Uses a stopword list based on the [WordPress Comment Blacklist](https://github.com/splorp/wordpress-comment-blacklist/blob/master/reference/strings.txt) from GitHub.
- Sends an email notification to the super admin when a comment is blocked.
- Provides a network admin settings page to manage stopwords.
- Displays a notice to subsite admins about the stopword configuration.

## Installation

1. Upload the `ls-comment-stopword-checker` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Create or edit the `stopwords.txt` file in the plugin directory to define the prohibited words. By default, this file uses the stopword list from the [WordPress Comment Blacklist](https://github.com/splorp/wordpress-comment-blacklist/blob/master/reference/strings.txt).

### Configuration:

- Define the stopwords in the `stopwords.txt` file (one word per line).
- Add the email address of the Super Admin in the `LS_SUPER_ADMIN_EMAIL` constant in the `ls-comment-stopword-checker.php` file.
- A settings page is available under Network Admin > Stopword Checker to view or manage stopwords.

## Frequently Asked Questions

### How do I add or edit stopwords?

To add or edit stopwords, open the `stopwords.txt` file located in the plugin folder. Add one stopword per line. Each word in this list will prevent the posting of any comment containing that word.

### Does the plugin come with a predefined stopword list?

Yes, the plugin uses a predefined stopword list based on the [WordPress Comment Blacklist](https://github.com/splorp/wordpress-comment-blacklist/blob/master/reference/strings.txt) from GitHub. You can customize this list by adding or removing words in the `stopwords.txt` file.

### Can I block specific URLs or email addresses in the comments?

Yes, you can add specific URLs or email addresses to the `stopwords.txt` file, and any comment containing those URLs or emails will be blocked.

### Will subsite admins be notified about blocked comments?

No, only the super admin (as defined in the plugin) will receive email notifications about blocked comments.

## Screenshots

1. **Settings Page**: The Stopword Checker settings page in the network admin dashboard.
2. **Stopword Notice**: A notice informing subsite admins about the stopword configuration on their discussion settings page.

## Changelog

### 1.0
- Initial release of LS Comment Stopword Checker.

## License

This plugin is licensed under the GPLv2 or later. You can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation.

For more details, see [GPL-2.0 License](https://www.gnu.org/licenses/gpl-2.0.html).
