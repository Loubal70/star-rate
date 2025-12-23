=== Star Rate ===
Contributors: loubal70
Tags: rating, stars, review, seo, schema
Requires at least: 6.7
Tested up to: 6.9
Requires PHP: 8.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

High-performance star rating with WordPress Interactivity API. GDPR compliant, SEO optimized with Schema.org.

== Description ==

Star Rate is a lightweight star rating plugin that uses WordPress Interactivity API for smooth, reactive user experience.

**Features:**

* Star rating widget (1-5 stars)
* Schema.org JSON-LD for SEO
* GDPR compliant (anonymized IP hashing)
* Anti-fraud protection (cookie + database)
* Fully customizable via CSS variables
* Translation ready
* Shortcode support

**Customization:**

Override CSS variables in your theme:

`
:root {
    --star-rate-color-star-active: #fbde20;
    --star-rate-color-star-inactive: #d3daeb;
    --star-rate-color-background: #ffffff;
    --star-rate-color-text: #1a1f2c;
    --star-rate-color-text-muted: #5b647a;
    --star-rate-radius: 16px;
    --star-rate-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}
`

**Shortcode:**

`[star-rate]` - Displays the rating widget for the current post.
`[star-rate post_id="123"]` - Displays the rating widget for a specific post.

**Filters:**

* `star_rate_schema_type` - Change Schema.org type (default: Article)
* `star_rate_schema` - Modify the complete JSON-LD schema

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/star-rate/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure settings under Settings > Star Rate

== Frequently Asked Questions ==

= How does the anti-fraud protection work? =

Star Rate uses a combination of cookies and anonymized IP hashing to prevent duplicate votes from the same user.

= Is it GDPR compliant? =

Yes. Star Rate only stores an anonymized hash of the user's IP address combined with their user agent. No personal data is stored.

= Can I customize the appearance? =

Yes. All visual aspects can be customized using CSS variables. See the Customization section.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
