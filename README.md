# 3task Polls: Cookieless Polls, Star Rating & Voting

Polls, star ratings, up/down voting and reactions for WordPress. Everything stays in your own WordPress database: no third-party service, no external fonts, no tracking.

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/3task-polls)](https://wordpress.org/plugins/3task-polls/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/3task-polls)](https://wordpress.org/plugins/3task-polls/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

## Privacy

In the default mode ("one vote per IP address") no cookie is set at all. The plain IP address is never stored, only a salted SHA-256 hash that is different for every poll, so votes in different polls cannot be linked. The hashes are removed automatically after a poll has ended (30 days by default, adjustable).

## Poll types

- **Single choice:** one answer per voter, results as bars or donut chart
- **Multiple choice:** checkboxes with an optional maximum
- **Star rating:** 1 to 5 stars with average
- **Up/down voting:** thumbs up and thumbs down, counted separately
- **Reactions:** emoji buttons, one click votes
- **Quiz question:** one correct answer and an optional explanation. After the vote, visitors see whether they were right and how many answered correctly
- **Questionnaire:** several polls in one form, answered step by step and sent together at the end. With quiz questions inside, visitors see how many they got right (for example "4 of 5 correct")

## Features

- Feedback below posts ("Was this post helpful?" or reactions), counted per post
- Block "3task Polls" for the block editor, plus the shortcode `[tpoll id="123"]`
- Works with page caching: the poll HTML is the same for everyone, the visitor's state is loaded afterwards
- Three ways to prevent duplicate votes: per IP address (hashed, no cookie), per browser (cookie) or per user account
- Rate limit, a hidden field against bots and a check against votes sent from other websites
- CSS and JavaScript load only on pages that show a poll
- Donut chart without any chart library (inline SVG)
- Start and end date, 5 themes, optional dark mode, your own bar color
- Keyboard accessible, screen reader announcements for results
- WordPress personal data export and erase are supported
- German included: informal (du) and formal (Sie)

## Installation

Install it from [WordPress.org](https://wordpress.org/plugins/3task-polls/), or upload the folder `3task-polls` to `/wp-content/plugins/` and activate it. Then go to **3task Polls** in the admin menu.

## Changelog

The full changelog is in [readme.txt](readme.txt). Latest release: **1.1.0** (22 October 2026): two new poll types, quiz question with correct answer and explanation, and questionnaires with several questions.

## Add-on

3task Polls works on its own, with no locked features and no time limit. A paid companion is described at [3task.de](https://www.3task.de/3task-polls-pro/).

## Support

- [WordPress.org Support Forum](https://wordpress.org/support/plugin/3task-polls/)
- [Report a bug](https://github.com/Tribun74/3task-polls/issues)

## License

GPL-2.0, see [LICENSE](LICENSE).

Made by [3task](https://www.3task.de) in Germany.
