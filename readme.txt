=== 3task Polls: Cookieless Polls, Star Rating & Voting ===
Contributors: 3task
Tags: poll, quiz, voting, star rating, cookieless
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Polls, quizzes, questionnaires, star ratings and voting that stay on your own server. No third-party service, no cookie by default.

== Description ==

Most voting widgets are third-party services. That means another entry in your consent banner and your visitors' answers on someone else's server. 3task Polls keeps everything in your own WordPress database.

In the default mode ("one vote per IP address") no cookie is set at all. The plain IP address is never stored, only a salted SHA-256 hash that is different for every poll, so votes in different polls cannot be linked. These hashes are removed automatically after a poll has ended (30 days by default, adjustable).

= 7 poll types =

* **Single choice**: one answer per voter, results as bars or donut chart
* **Multiple choice**: checkboxes with an optional maximum
* **Star rating**: 1 to 5 stars with average
* **Up/down voting**: thumbs up and thumbs down, counted separately
* **Reactions**: emoji buttons, one click votes
* **Quiz question**: one correct answer and an optional explanation. After the vote, visitors see whether they were right and how many answered correctly
* **Questionnaire**: several polls in one form, answered step by step and sent together at the end. With quiz questions inside, visitors see how many they got right (for example "4 of 5 correct")

= Feedback below posts =

Ask "Was this post helpful?" or show reactions below every post, with one setting. The answers are counted per post, and the tab "Feedback" lists which posts readers found helpful.

= Features =

* Block "3task Polls" for the block editor, plus the shortcode `[tpoll id="123"]`
* Works with page caching: the poll HTML is the same for everyone, the visitor's state is loaded afterwards
* Three ways to prevent duplicate votes: per IP address (hashed, no cookie), per browser (cookie) or per user account
* Rate limit, a hidden field against bots and a check against votes sent from other websites
* Lightweight: the CSS and JavaScript load only on pages that show a poll
* Results after voting, always or never (never means: only you see them in the admin)
* Donut chart without any chart library (inline SVG)
* Start and end date, with a notice before the poll opens
* Voters can change their vote, if you allow it
* 5 themes, optional dark mode, your own bar color
* Keyboard accessible (also the stars), screen reader announcements for results
* Personal data export and erase tools of WordPress are supported, suggested text for your privacy policy included
* Duplicate polls with one click
* Translation-ready, German included

= Shortcode =

`[tpoll id="123"]`

Optional parameters:

`[tpoll id="123" show_results="always" theme="minimal" dark_mode="no"]`

= Block =

Search for "3task Polls" in the block inserter and select your poll in the block settings.

= Looking for more? =

3task Polls works on its own, with no locked features and no time limit. A paid companion is described here: https://www.3task.de/3task-polls-pro/

== Installation ==

1. Upload the `3task-polls` folder to `/wp-content/plugins/` or install it from the plugin directory
2. Activate the plugin
3. Go to 3task Polls in the admin menu and create your first poll
4. Add the block "3task Polls" or the shortcode `[tpoll id="123"]` to a page

== Frequently Asked Questions ==

= How do I embed a poll? =

Use the block "3task Polls" or the shortcode `[tpoll id="123"]`. The shortcode of each poll is shown in the poll list and on the edit screen.

= Does 3task Polls set cookies? =

Only if you choose the restriction "one vote per browser (cookie)". Then a cookie named `tpoll_voted_` plus the poll number is set for one year after voting. The default mode "one vote per IP address" sets no cookie.

= What does it store about voters? =

The answer, the time, and a salted SHA-256 hash of the IP address and of the browser identifier. These hashes are needed to prevent duplicate votes while the poll is open and are removed after the poll has ended and the retention period has passed. If the poll is restricted to logged-in users, the vote is linked to the user account. Nothing is sent to other servers.

= Is it GDPR compliant? =

The plugin is built for data minimisation: no third-party service, no plain IP addresses, automatic removal of the hashes, support for the WordPress export and erase tools. Whether your site as a whole complies depends on more than one plugin, so please add the suggested text (Settings > Privacy) to your privacy policy.

= Does it work with caching plugins? =

Yes. The poll HTML is identical for every visitor, so page caches can store it. After the page has loaded, the plugin asks the server whether this visitor has already voted and gets a fresh security token.

= Can people in the same office vote? =

With "one vote per IP address", everybody behind the same internet connection counts as one voter. For offices and schools, use "logged-in users only" or the cookie mode.

= Why do multiple choice percentages add up to more than 100? =

For multiple choice, each percentage shows the share of voters who picked that answer. Voters can pick several answers.

= My site is behind a proxy or load balancer =

The plugin only trusts REMOTE_ADDR, because headers like X-Forwarded-For can be faked by visitors. If your proxy sets a trusted header, return the real client IP with the filter `tpoll_client_ip`.

= How does the quiz question work? =

Choose the type "Quiz question", mark the correct answer and add an explanation if you like. Before the vote, the page contains neither the solution nor the explanation, so it cannot be read from the source code and the page can be cached. After the vote, visitors see whether they were right, the correct answer, the explanation and the share of correct answers. This happens whatever is set under "Show Results".

= How does the questionnaire work? =

Create and publish the single questions first (single choice, multiple choice, quiz question or star rating). Then create a poll of the type "Questionnaire" and add the questions in the order you want. Visitors answer one question after the other and send all answers together at the end. If they stop in the middle, nothing is counted. Each question keeps its own results, and all answers of one visitor share one ID, so add-ons can evaluate them per participant.

= Can I allow changing a vote? =

Yes, enable "Allow voters to change their vote" in the poll settings.

== Screenshots ==

1. A poll on the front end, voting without page reload
2. Dashboard with an overview of polls and votes
3. All polls at a glance, with shortcode, duplicate and delete
4. Results with vote counts and percentages per answer
5. Settings for voting rules, privacy and appearance

== Changelog ==

= 1.1.0 =
* New: poll type "Questionnaire". Bundles published polls into one form, step by step with progress, all answers are sent together at the end. With quiz questions inside, visitors see how many they got right, the solutions and the explanations.
* New: poll type "Quiz question". Mark the correct answer, add an optional explanation (links, bold, italic). After the vote, visitors see whether they were right, the correct answer, the explanation and how many answered correctly. Before the vote, none of this is in the page.
* New: extension points `tpoll_poll_saved`, `tpoll_results_visible`, `tpoll_vote_response`, `tpoll_submit_button_text`, `tpoll_has_voted` and `tpoll_survey_step_types` for add-ons. `TPoll_Vote::cast()` accepts a shared ballot ID.
* Fix: when two identical votes arrived at the same moment, removing the second one now only touches that poll.
* Changed: "Tested up to" is only in the readme now.

= 1.0.7 =
* Fix: the dashboard always showed "Create Your First Poll", also when polls already existed. It now lists the five latest polls with status and number of voters.

= 1.0.6 =
* Fix: the button "Add Answer" in the poll editor did nothing since 1.0.4 (a script dependency was missing).
* New: poll type "Reactions" (emoji buttons, one click votes).
* New: feedback below posts ("Was this post helpful?" or reactions), counted per post, with a "Feedback" tab in the admin.
* New: donut chart as result view for single choice polls, without a chart library.
* New: hidden field against bots.
* Privacy: the IP hash is now different for every poll. Votes from before the update are still recognised.
* Changed: the CSS and JavaScript still load only on pages that show a poll (now also mentioned in the readme).

= 1.0.5 =
* New: extension points for add-ons (poll types, extra form fields, results, admin tabs, designs). Used by 3task Polls Pro 2.0.
* Changed: the free plugin only pauses next to the old Pro 1.0.x. Pro 2.0 and newer is an add-on and runs together with it.
* New: answers can carry an image (used by add-on poll types).

= 1.0.4 =
* Fix: editing a poll no longer removes its votes. Answers are now updated instead of being recreated. Removing an answer that has votes asks for confirmation first.
* Security: the IP restriction now only trusts REMOTE_ADDR (headers like X-Forwarded-For could be faked). New filter `tpoll_client_ip` for trusted proxies.
* Security: rate limit for votes, and votes sent from other websites are rejected.
* Fix: results are only delivered when the poll settings allow it ("never" now really means never, drafts stay private).
* Fix: the block was missing in the block editor of WordPress 7.1. It is now registered from block.json and is called "3task Polls".
* Fix: works with page caching. The visitor state and a fresh security token are loaded after the page.
* Privacy: no cookie anymore in the IP and user modes. Voter hashes are removed after the poll has ended (default 30 days). Support for the WordPress personal data export and erase tools, suggested privacy policy text.
* Fix: up/down voting counted thumbs down as thumbs up. Both are now counted and shown separately.
* Fix: multiple choice percentages are now based on voters, single choice percentages add up to exactly 100.
* Fix: voter count is correct for multiple choice (one ballot per vote).
* Fix: the global settings are now used for new polls, the bar colors and the animation setting work, "allow voters to change their vote" can be set per poll.
* Fix: polls with a start date in the future show when they open, polls for logged-in users show a login link to guests.
* Fix: no duplicate results after voting when results are always visible.
* Accessibility: stars are keyboard accessible, visible focus, results are announced to screen readers, stronger message colors.
* Fix: the free plugin pauses itself while 3task Polls Pro 1.0.x is active instead of causing a fatal error.
* Fix: "Delete data on uninstall" is now a setting, Multisite is handled, data is kept while Pro is installed.
* Changed: dark mode default is now "light" for new installations (auto dark mode made polls dark on light themes). Font is inherited from the theme.
* Changed: default texts are translated when shown, German translation included.
* Removed unused code (old list table, media library loading).

= 1.0.3 =
* Performance: removed the unused jQuery UI Sortable dependency from the admin pages
* Fix: removed a non-functional drag handle from the answer rows
* Tested up to WordPress 7.1

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

== Upgrade Notice ==

= 1.1.0 =
New poll types: quiz question with correct answer and explanation, and questionnaires with several questions.

= 1.0.7 =
The dashboard shows your latest polls instead of "Create Your First Poll".

= 1.0.6 =
New: reactions, feedback below posts, donut chart, protection against bots.

= 1.0.5 =
Extension points for add-ons. Required for 3task Polls Pro 2.0.

= 1.0.4 =
Important fix: editing a running poll removed its votes. Also: block editor fix for WordPress 7.1, page cache support, no cookie in the default mode, safer duplicate vote check.

= 1.0.3 =
Small performance cleanup, tested with WordPress 7.1.

= 1.0.0 =
Initial release of 3task Polls.

== About ==

3task Polls is developed by [3task](https://www.3task.de), Frank Stemmler, Germany.
