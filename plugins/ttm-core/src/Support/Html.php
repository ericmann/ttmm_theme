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
}
