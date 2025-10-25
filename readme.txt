=== Ratings Card ===
Contributors: yourusername
Tags: movies, ratings, imdb, metacritic, rotten tomatoes, letterboxd
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display movie and TV show ratings from multiple sources with server-side caching. No user login required.

== Description ==

Ratings Card is a WordPress plugin that displays comprehensive movie and TV show ratings from multiple trusted sources including IMDb, Metacritic, Rotten Tomatoes, and Letterboxd.

**Key Features:**

* Server-side API calls (RapidAPI key never exposed to browsers)
* WordPress transient caching for optimal performance
* Support for both IMDb and TMDB IDs
* Responsive rating card design
* Shortcode and Gutenberg block support
* Public REST API endpoint with rate limiting
* No user login required to view ratings
* Configurable cache TTL
* Admin cache management

**Data Sources:**

* IMDb ratings and vote counts
* Metacritic scores
* Rotten Tomatoes (Tomatometer and Audience scores)
* Letterboxd ratings

== Installation ==

1. Upload the `ratings-card` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Ratings Card
4. Enter your RapidAPI credentials (host and key)
5. Configure cache settings as desired
6. Use the shortcode or Gutenberg block to display ratings

== Configuration ==

1. Sign up for RapidAPI at https://rapidapi.com
2. Subscribe to the Movies Ratings API
3. Copy your API key
4. In WordPress admin, go to Settings → Ratings Card
5. Enter your RapidAPI host and key
6. Set your preferred cache TTL (default: 360 minutes / 6 hours)

== Usage ==

**Shortcode:**

Display ratings using IMDb ID:
`[ratings_card id="tt0111161" type="imdb"]`

Display ratings using TMDB movie ID:
`[ratings_card id="278" type="tmdb" mediaType="movie"]`

Display ratings using TMDB TV show ID:
`[ratings_card id="1396" type="tmdb" mediaType="show"]`

**Gutenberg Block:**

1. Add a new block in the editor
2. Search for "Ratings Card"
3. Enter the movie/show ID
4. Select the ID type (IMDb or TMDB)
5. For TMDB IDs, select media type (movie or show)

**REST API:**

Public endpoint for AJAX requests:
`GET /wp-json/ratings-card/v1/ratings?id=tt0111161&type=imdb`

Rate limited to 30 requests per IP per minute.

== Frequently Asked Questions ==

= Do I need a RapidAPI account? =

Yes, you need to subscribe to the Movies Ratings API on RapidAPI and obtain an API key.

= Is the API key exposed to website visitors? =

No. All API calls are made server-side using PHP cURL. The API key is never sent to the browser.

= How long are ratings cached? =

By default, ratings are cached for 6 hours (360 minutes). You can configure this in Settings → Ratings Card.

= Can I clear the cache? =

Yes. Go to Settings → Ratings Card and click "Clear All Cache" to force fresh API calls.

= Does this require users to log in? =

No. The ratings cards are displayed to all visitors without requiring any login.

= What happens if the API is down? =

If the API is unreachable and cached data is available, the cached version will be displayed. If no cache exists, a friendly error message is shown.

== Screenshots ==

1. Ratings card display with poster, scores, and source badges
2. Admin settings page
3. Gutenberg block editor
4. Shortcode usage example

== Changelog ==

= 1.0.0 =
* Initial release
* IMDb and TMDB ID support
* Server-side API fetching with caching
* Shortcode and Gutenberg block
* Public REST endpoint with rate limiting
* Admin settings page
* Cache management

== Upgrade Notice ==

= 1.0.0 =
Initial release of Ratings Card plugin.

== Security ==

* All API calls are made server-side
* Input sanitization on all user inputs
* Output escaping on all displayed data
* Rate limiting on public REST endpoint
* Nonce verification on admin actions
* Capability checks for admin functions

== Performance ==

* WordPress transient caching (default 6 hours)
* Lazy loading for poster images
* Assets enqueued only when shortcode/block is present
* Configurable cache TTL
* Efficient database queries

== Support ==

For support, please visit the plugin's GitHub repository or WordPress support forums.

== Credits ==

* Movies Ratings API by RapidAPI
* Data sources: IMDb, Metacritic, Rotten Tomatoes, Letterboxd
