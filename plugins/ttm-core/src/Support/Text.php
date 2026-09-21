<?php
/**
 * Pure text helpers: word counting, sentence-aware excerpts, curly quotes, reading time.
 *
 * @package TTM\Core\Support
 */

declare( strict_types=1 );

namespace TTM\Core\Support;

/**
 * Pure text helpers.
 */
class Text {

	/**
	 * Count words in post content, ignoring code/preformatted blocks, shortcodes and markup.
	 *
	 * @param string $content Raw post content.
	 * @return int
	 */
	public static function word_count( string $content ): int {
		$content = (string) preg_replace( '#<!--\s*wp:code[^>]*-->.*?<!--\s*/wp:code\s*-->#s', ' ', $content );
		$content = (string) preg_replace( '#<!--\s*wp:preformatted[^>]*-->.*?<!--\s*/wp:preformatted\s*-->#s', ' ', $content );
		$content = (string) preg_replace( '#<pre[^>]*>.*?</pre>#s', ' ', $content );
		$content = (string) preg_replace( '#\[/?[a-zA-Z0-9_-]+[^\]]*\]#', ' ', $content );
		$content = (string) preg_replace( '#<!--.*?-->#s', ' ', $content );
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );

		preg_match_all( '/\S+/u', $content, $matches );

		return count( $matches[0] );
	}

	/**
	 * Sentence-aware excerpt of roughly $words words.
	 *
	 * @param string $text  Raw content (may include tags).
	 * @param int    $words Target word count.
	 * @return string
	 */
	public static function sentence_excerpt( string $text, int $words ): string {
		$plain = trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) );

		preg_match_all( '/\S+/u', $plain, $matches, PREG_OFFSET_CAPTURE );
		$tokens = $matches[0];

		if ( count( $tokens ) <= $words ) {
			return $plain;
		}

		$terminator = '/[.!?…]([\'"”’)\]]*)$/u';

		// 1. Exact cut ends a sentence already.
		$slice = self::join_tokens( $tokens, 0, $words );
		if ( preg_match( $terminator, $slice ) ) {
			return $slice;
		}

		// 2. Extend up to $words + 15 to find the next terminator.
		$max_extend = min( count( $tokens ), $words + 15 );
		for ( $i = $words; $i < $max_extend; $i++ ) {
			$candidate = self::join_tokens( $tokens, 0, $i + 1 );
			if ( preg_match( $terminator, $candidate ) ) {
				return $candidate;
			}
		}

		// 3. Cut back to an earlier terminator, as long as at least half the words remain.
		$min_words = intdiv( $words, 2 );
		for ( $i = $words - 1; $i >= $min_words; $i-- ) {
			$candidate = self::join_tokens( $tokens, 0, $i );
			if ( preg_match( $terminator, $candidate ) ) {
				return $candidate;
			}
		}

		// 4. No boundary found: hard cut with an ellipsis.
		return self::join_tokens( $tokens, 0, $words ) . '…';
	}

	/**
	 * Join a token slice (from preg_match_all with PREG_OFFSET_CAPTURE) back into text.
	 *
	 * @param array<int, array{0:string,1:int}> $tokens Captured tokens.
	 * @param int                               $start  Start index.
	 * @param int                               $end    End index (exclusive).
	 * @return string
	 */
	private static function join_tokens( array $tokens, int $start, int $end ): string {
		$slice = array_slice( $tokens, $start, $end - $start );

		return implode( ' ', array_map( static fn ( array $token ): string => $token[0], $slice ) );
	}

	/**
	 * Convert straight quotes to curly quotes, leaving markup untouched.
	 *
	 * @param string $s Input string.
	 * @return string
	 */
	public static function curly_quotes( string $s ): string {
		$tags = [];

		// Protect tags with a placeholder that cannot match the "opening quote" context below,
		// so quote direction is decided by real surrounding text, not tag boundaries.
		$protected = (string) preg_replace_callback(
			'/<[^>]*>/',
			static function ( array $m ) use ( &$tags ): string {
				$tags[] = $m[0];
				return "\x01" . ( count( $tags ) - 1 ) . "\x01";
			},
			$s
		);

		$protected = preg_replace( '/(^|[\s(\[])"/u', '$1“', $protected );
		$protected = preg_replace( '/"/u', '”', $protected );
		$protected = preg_replace( "/(\\w)'/u", '$1’', $protected );
		$protected = preg_replace( "/'/u", '‘', $protected );

		return (string) preg_replace_callback(
			"/\x01(\d+)\x01/",
			static fn ( array $m ): string => $tags[ (int) $m[1] ],
			$protected
		);
	}

	/**
	 * Reading time in whole minutes, minimum 1.
	 *
	 * @param int $words           Word count.
	 * @param int $words_per_minute Reading speed.
	 * @return int
	 */
	public static function reading_minutes( int $words, int $words_per_minute ): int {
		return max( 1, (int) ceil( $words / $words_per_minute ) );
	}
}
