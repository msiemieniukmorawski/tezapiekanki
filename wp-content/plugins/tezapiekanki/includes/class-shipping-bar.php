<?php
/**
 * Bar above the header, default: "Wysyłamy tylko 2 razy w tygodniu. Sprawdź dlaczego →".
 *
 * - Texts and click behavior (popup, URL, nothing) are set in Appearance → Customize → "Belka o wysyłce"
 *   (class-shipping-bar-customizer.php), option tzp_shipping_bar.
 * - Printed on wp_body_open (Woodmart calls it in header.php before .wd-page-wrapper). The header is absolutely positioned
 *   inside .wd-page-wrapper, so it moves down by the bar height together with the content.
 * - Popup: the Woodmart popup selected in the settings gets an "On selector click" = .tzp-bar-link trigger on the fly
 *   (get_post_metadata filter, front end only, nothing is saved to the database). So changing the popup in the settings
 *   needs no fiddling with popup settings. If the popup doesn't open, the link goes to the URL from the settings.
 * - The close button hides the bar until the end of the day (date in localStorage, WordPress timezone). A script in <head>
 *   checks it before body renders, so a closed bar doesn't flash on LiteSpeed-cached pages.
 * - Hidden on checkout (also the thank-you page).
 *
 * @package tezapiekanki
 */

namespace TZP;

defined( 'ABSPATH' ) || exit;

class Shipping_Bar {

	const OPTION         = 'tzp_shipping_bar';
	const STORAGE_KEY    = 'tzp_bar_closed';
	const POPUP_SELECTOR = '.tzp-bar-link';
	const ACTIONS        = array( 'popup', 'url', 'none' );

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'head_script' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_body_open', array( __CLASS__, 'render' ), 5 );
		add_filter( 'get_post_metadata', array( __CLASS__, 'popup_trigger' ), 10, 4 );
	}

	public static function defaults() {
		return array(
			'enabled'   => true,
			'text'      => __( 'Wysyłamy tylko 2 razy w tygodniu.', 'tezapiekanki' ),
			'link_text' => __( 'Sprawdź dlaczego', 'tezapiekanki' ),
			'action'    => 'popup',
			'popup_id'  => 0,
			'url'       => home_url( '/sposoby-dostawy/' ),
		);
	}

	/**
	 * Settings from the option. A popup that doesn't exist or isn't published turns into a link to the URL.
	 * No static-variable cache: in the Customize preview the values are replaced by option filters.
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		$s     = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );

		$s['enabled']  = (bool) $s['enabled'];
		$s['popup_id'] = absint( $s['popup_id'] );
		$s['action']   = in_array( $s['action'], self::ACTIONS, true ) ? $s['action'] : 'popup';

		if ( 'popup' === $s['action'] && ! self::is_popup( $s['popup_id'] ) ) {
			$s['action'] = 'url';
		}

		if ( 'url' === $s['action'] && '' === trim( (string) $s['url'] ) ) {
			$s['action'] = 'none';
		}

		return $s;
	}

	public static function is_popup( $id ) {
		return $id && 'wd_popup' === get_post_type( $id ) && 'publish' === get_post_status( $id );
	}

	/**
	 * Whether to show the bar on the current page.
	 */
	public static function is_visible() {
		$s = self::settings();

		$visible = $s['enabled']
			&& '' !== trim( $s['text'] )
			&& ! is_admin()
			&& ! wp_doing_ajax()
			&& ! is_feed()
			&& ! ( function_exists( 'is_checkout' ) && is_checkout() )
			&& ! self::is_elementor_preview();

		/**
		 * Allows hiding the bar on selected pages.
		 *
		 * @param bool $visible
		 */
		return (bool) apply_filters( 'tzp_shipping_bar_visible', $visible );
	}

	private static function is_elementor_preview() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		$elementor = \Elementor\Plugin::$instance;

		return ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() )
			|| ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() );
	}

	/**
	 * A regular page request (not admin, AJAX, REST or WP-CLI). Only here do we attach the popup trigger, so the Elementor
	 * editor doesn't see it and save it permanently (after switching popups the old one would still open from the bar).
	 */
	private static function is_frontend_request() {
		return ! is_admin()
			&& ! wp_doing_ajax()
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& ! ( defined( 'WP_CLI' ) && \WP_CLI );
	}

	/**
	 * Attaches a bar-click trigger to the selected popup's settings (Elementor, meta _elementor_page_settings).
	 * Also disables its automatic open after a delay (enabled by default in Woodmart, 0 s) and "persistent close"
	 * (enabled by default: after the first close the popup would no longer open from the bar).
	 *
	 * @param mixed  $value     Value from other filters (null = read from the database).
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Whether a single value is requested.
	 * @return mixed
	 */
	public static function popup_trigger( $value, $object_id, $meta_key, $single ) {
		static $busy = false;

		if ( '_elementor_page_settings' !== $meta_key || $busy || ! did_action( 'init' ) || ! self::is_frontend_request() ) {
			return $value;
		}

		$s = self::settings();

		if ( ! $s['enabled'] || 'popup' !== $s['action'] || (int) $object_id !== $s['popup_id'] ) {
			return $value;
		}

		$busy     = true;
		$settings = get_post_meta( $object_id, '_elementor_page_settings', true );
		$busy     = false;
		$settings = is_array( $settings ) ? $settings : array();

		$selectors = array();
		if ( ! empty( $settings['wd_popup_is_on_selector_click_enabled'] ) && ! empty( $settings['wd_popup_selector'] ) ) {
			$selectors = array_filter( array_map( 'trim', explode( ',', $settings['wd_popup_selector'] ) ) );
		}
		$selectors[] = self::POPUP_SELECTOR;

		$settings['wd_popup_is_on_selector_click_enabled'] = '1';
		$settings['wd_popup_selector']                     = implode( ', ', array_unique( $selectors ) );
		$settings['wd_popup_selector_click_once']          = '';
		$settings['wd_popup_is_some_time_enabled']         = '';
		$settings['wd_popup_persistent_close']             = '';

		// With $single, get_metadata() returns the first array element.
		return array( $settings );
	}

	/**
	 * Timezone for computing "today" in the browser. With an offset like "+02:00" (no zone name)
	 * the JS falls back to the browser's local time.
	 */
	private static function timezone() {
		return wp_timezone_string();
	}

	/**
	 * Hides a closed bar before the browser paints it. data-no-optimize: LiteSpeed doesn't move
	 * or defer this script (JS defer in production). In the Customize preview the bar is always visible.
	 */
	public static function head_script() {
		if ( ! self::is_visible() ) {
			return;
		}

		echo '<style data-no-optimize="1">.tzp-bar-off .tzp-bar{display:none!important}</style>' . "\n";

		if ( is_customize_preview() ) {
			return;
		}

		$script = sprintf(
			'(function(){try{var t=%1$s,d,p,o={};try{p=new Intl.DateTimeFormat("en-GB",{timeZone:t,year:"numeric",month:"2-digit",day:"2-digit"}).formatToParts(new Date());p.forEach(function(x){o[x.type]=x.value});d=o.year+"-"+o.month+"-"+o.day}catch(e){d=new Date().toDateString()}if(localStorage.getItem(%2$s)===d){document.documentElement.classList.add("tzp-bar-off")}}catch(e){}})();',
			wp_json_encode( self::timezone() ),
			wp_json_encode( self::STORAGE_KEY )
		);

		echo '<script data-no-optimize="1">' . $script . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed code, values via wp_json_encode.
	}

	public static function assets() {
		if ( ! self::is_visible() ) {
			return;
		}

		wp_enqueue_style( 'tzp-shipping-bar', TZP_URL . 'assets/css/shipping-bar.css', array(), self::asset_version( 'assets/css/shipping-bar.css' ) );
		wp_enqueue_script( 'tzp-shipping-bar', TZP_URL . 'assets/js/shipping-bar.js', array(), self::asset_version( 'assets/js/shipping-bar.js' ), true );
	}

	public static function asset_version( $file ) {
		return asset_version( $file );
	}

	public static function render() {
		if ( ! self::is_visible() ) {
			return;
		}

		$s = self::settings();

		/**
		 * Link URL: the target for the "url" action, or the fallback for the popup (popup not on the page,
		 * theme scripts not loaded yet).
		 *
		 * @param string $url
		 * @param array  $s   Bar settings.
		 */
		$url = apply_filters( 'tzp_shipping_bar_link', $s['url'], $s );

		$text = '<span class="tzp-bar__text">' . esc_html( $s['text'] ) . '</span>';
		$cta  = sprintf(
			'<span class="tzp-bar__cta"%s><span class="tzp-bar__cta-text">%s</span><span class="tzp-bar__arrow" aria-hidden="true">&rarr;</span></span>',
			'' === trim( $s['link_text'] ) ? ' hidden' : '',
			esc_html( $s['link_text'] )
		);

		if ( 'none' === $s['action'] ) {
			$content = '<span class="tzp-bar__link tzp-bar__link--static">' . $text . '</span>';
		} else {
			$content = sprintf(
				'<a class="tzp-bar__link%s" href="%s">%s %s</a>',
				'popup' === $s['action'] ? ' ' . esc_attr( ltrim( self::POPUP_SELECTOR, '.' ) ) : '',
				esc_url( $url ),
				$text,
				$cta
			);
		}

		?>
		<div class="tzp-bar" role="region" aria-label="<?php esc_attr_e( 'Informacja o wysyłce', 'tezapiekanki' ); ?>" data-tz="<?php echo esc_attr( self::timezone() ); ?>" data-key="<?php echo esc_attr( self::STORAGE_KEY ); ?>">
			<div class="tzp-bar__inner">
				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_html / esc_url / esc_attr above. ?>
				<button type="button" class="tzp-bar__close" aria-label="<?php esc_attr_e( 'Zamknij do końca dnia', 'tezapiekanki' ); ?>">
					<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
				</button>
			</div>
		</div>
		<?php
	}
}
