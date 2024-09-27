# LS Comment Stopword Checker for Multisite

## Description
LS Comment Stopword Checker is a WordPress multisite plugin that prevents users from posting comments containing predefined stopwords. It allows network admins to configure a stopword list that blocks specific terms from appearing in comments across all subsites.

## Features
- Supports multisite WordPress networks.
- Blocks comments containing prohibited words (defined in `stopwords.txt`).
- Sends an email notification to the super admin when a comment is blocked.
- Provides a network admin settings page to manage stopwords.
- Displays a notice to subsite admins about the stopword configuration.

## Installation
1. Download the plugin files or clone the repository into your `/wp-content/plugins/` directory.
2. Upload the `ls-comment-stopword-checker` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Create or edit the `stopwords.txt` file in the plugin directory to define the prohibited words.

## Configuration
- Define the stopwords in the `stopwords.txt` file (one word per line).
- Add the email address of the Super Admin in the `LS_SUPER_ADMIN_EMAIL` constant in the `ls-comment-stopword-checker.php` file.
- A settings page is available under Network Admin > Stopword Checker to view or manage stopwords.

## License
This plugin is licensed under the GPL-2.0+ license.
