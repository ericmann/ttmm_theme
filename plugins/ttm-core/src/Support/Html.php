<?php
/**
 * Escaped HTML fragment builders. Attributes are escaped on the way out; $inner is pre-escaped.
 *
 * @package TTM\Core\Support
 */

declare( strict_types=1 );

namespace TTM\Core\Support;

/**
 * Escaped HTML fragment builders.
 */
class Html {

	/**
	 * Build an element with escaped attributes.
	 *
	 * @param string               $tag   Tag name.
	 * @param array<string, mixed> $attrs Attributes; href/src are URL-escaped, others attribute-escaped.
	 * @param string               $inner Already-escaped inner HTML.
	 * @return string
	 */
	public static function el( string $tag, array $attrs, string $inner = '' ): string {
		$html = '<' . $tag;

		foreach ( $attrs as $name => $value ) {
			if ( null === $value || false === $value ) {
				continue;
			}
			$escaped = in_array( $name, [ 'href', 'src' ], true ) ? esc_url( (string) $value ) : esc_attr( (string) $value );
			$html   .= ' ' . $name . '="' . $escaped . '"';
		}

		$html .= '>' . $inner . '</' . $tag . '>';

		return $html;
	}

	/**
	 * Escape plain text for HTML output.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function text( string $text ): string {
		return esc_html( $text );
	}

	/**
	 * Build an escaped anchor.
	 *
	 * @param string               $url   Link target.
	 * @param string               $label Visible (already plain) label; escaped here.
	 * @param array<string, mixed> $attrs Extra attributes.
	 * @return string
	 */
	public static function link( string $url, string $label, array $attrs = [] ): string {
		$attrs['href'] = $url;

		return self::el( 'a', $attrs, self::text( $label ) );
	}

	/**
	 * Join the keys whose values are truthy into a class string.
	 *
	 * @param array<string, mixed> $flags Class name => condition.
	 * @return string
	 */
	public static function classes( array $flags ): string {
		return implode(
			' ',
			array_keys(
				array_filter( $flags, static fn ( $value ): bool => (bool) $value )
			)
		);
	}

	/**
	 * Every `<img src="…">` URL in an HTML fragment, in document order (P2-04, SPEC §6.7).
	 *
	 * @param string $html HTML fragment (e.g. post_content).
	 * @return string[]
	 */
	public static function image_srcs( string $html ): array {
		if ( ! preg_match_all( '/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			return [];
		}

		return $matches[1];
	}

	/**
	 * Rewrite a Photon URL (`https://iN.wp.com/<host>/<path>`) to its origin
	 * (`https://<host>/<path>`), only when `<host>` equals `$match_host`. Returns null for
	 * anything else (not a Photon URL, or a Photon URL for a different host) — P2-04, SI-13,
	 * SPEC §6.7. With `$home` given (SI-13: this site's own `home_url()`), the scheme and host
	 * are replaced by `$home` instead of `$match_host`'s own scheme+host, so a rewritten image
	 * points at wherever this WordPress install actually is (e.g. a local wp-env host), not
	 * necessarily the live production domain.
	 *
	 * @param string $src        Candidate image URL.
	 * @param string $match_host Expected Photon origin host (`migration.photon_origin`).
	 * @param string $home       This site's `home_url()`; `''` keeps the old `https://$match_host` behaviour.
	 * @return string|null
	 */
	public static function photon_origin_url( string $src, string $match_host, string $home = '' ): ?string {
		if ( ! preg_match( '#^https?://i[0-9]\.wp\.com/([^/]+)/(.+)$#i', $src, $matches ) ) {
			return null;
		}

		if ( $matches[1] !== $match_host ) {
			return null;
		}

		if ( '' === $home ) {
			return 'https://' . $match_host . '/' . $matches[2];
		}

		return untrailingslashit( $home ) . '/' . $matches[2];
	}

	/**
	 * Replace every literal occurrence of a URL in an HTML fragment with another — covers both
	 * an `<img src>` and a wrapping `<a href>` pointing at the same file (P2-04, SPEC §6.7).
	 *
	 * @param string $html HTML fragment.
	 * @param string $from URL to replace.
	 * @param string $to   Replacement URL.
	 * @return string
	 */
	public static function replace_url( string $html, string $from, string $to ): string {
		return str_replace( $from, $to, $html );
	}
}
