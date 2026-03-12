=== 3task Polls – Surveys, Quizzes & Voting ===
Contributors: 3task
Tags: poll, voting, survey, quiz, rating
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create polls, surveys, quizzes and voting for WordPress. AJAX-based, GDPR-compliant, Gutenberg block included. 4 poll types and 5 themes.

== Description ==

3task Polls lets you create engaging polls, surveys, quizzes and voting for your WordPress site. Built with performance and privacy in mind.

= 4 Poll Types =

* **Single Choice** – Classic radio button poll
* **Multiple Choice** – Checkbox-based, configurable max selections
* **Star Rating** – 1-5 star rating with average display
* **Up/Down Voting** – Thumbs up/down with live count

= Key Features =

* AJAX-based voting without page reload
* GDPR-compliant: IP addresses are hashed before storage
* Gutenberg block and shortcode support
* 5 built-in themes (Default, Minimal, Rounded, Square, Pill)
* Dark mode with auto-detect option
* Animated result bars
* Schedule polls with start and end dates
* Randomize answer order
* Duplicate polls with one click
* Responsive design for all devices
* Translation-ready with full i18n support

= Shortcode Usage =

`[tpoll id="123"]`

You can also customize the display per shortcode:

`[tpoll id="123" show_results="always" theme="minimal" dark_mode="yes"]`

= Gutenberg Block =

Search for "3task Polls" in the block inserter and select your poll from the dropdown.

== Installation ==

1. Upload the `3task-polls` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to 3task Polls in the admin menu to create your first poll
4. Use `[tpoll id="123"]` or the Gutenberg block to display polls

== Frequently Asked Questions ==

= How do I embed a poll? =

Use the shortcode `[tpoll id="123"]` where 123 is your poll ID. You can find the shortcode on the poll edit screen. The Gutenberg block is also available in the block inserter.

= Is 3task Polls GDPR compliant? =

Yes. IP addresses are hashed (SHA-256) before storage. No personal data is stored in plain text. No external resources are loaded.

= Does it work with caching plugins? =

Yes. 3task Polls uses AJAX for voting, so it works with all major caching plugins including WP Super Cache, W3 Total Cache, and LiteSpeed Cache.

= Can I customize the colors? =

Yes. You can set the results bar color globally in Settings or per poll in the poll editor. The color picker supports any hex color.

= Can I allow multiple votes? =

Use the "Multiple Choice" poll type and optionally set a maximum number of selections.

= How do I show results before voting? =

In the poll settings, change "Show Results" to "Always". By default, results are shown after voting.

= Is it translation-ready? =

Yes. 3task Polls uses standard WordPress i18n functions and is fully translation-ready.

== Screenshots ==

1. Dashboard with stats overview and quick actions
2. Poll editor with type selection and answer management
3. Frontend poll display with default theme
4. Results view with animated progress bars
5. Settings page with appearance and voting options

== Changelog ==

= 1.0.2 =
* Fix: text domain corrected to match plugin slug (3task-polls)
* Fix: stable tag synced with plugin version
* Fix: SQL prepare() array spreading for PHPCS compliance
* Fix: block.json name aligned with registered block namespace

= 1.0.1 =
* Fix: text domain aligned for WordPress plugin checker compatibility
* Fix: vote replacement SQL paths improved for plugin check compliance
* Fix: admin redirect behavior after save/duplicate

= 1.0.0 =
* Initial release
* 4 poll types: Single Choice, Multiple Choice, Rating, Voting
* 5 built-in themes
* AJAX voting
* GDPR-compliant IP hashing
* Gutenberg block
* Shortcode with parameters
* Dark mode support
* Poll scheduling
* Answer randomization
* Poll duplication
* Admin dashboard with statistics

== Upgrade Notice ==

= 1.0.2 =
Text domain and plugin check compliance fixes.

= 1.0.1 =
Minor compatibility and checker fixes.

= 1.0.0 =
Initial release of 3task Polls.

== About ==

3task Polls is developed by [3task](https://www.3task.de), a WordPress development agency based in Germany. We build lightweight, privacy-focused plugins for the WordPress community.
