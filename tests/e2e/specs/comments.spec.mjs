/**
 * The comments section (patterns/comments.php, assets/js/comments.js) on the three posts
 * `tests/e2e/fixtures/comments.php` opens: a Discussion on an article, Reflections on a journal
 * entry, Reader notes on a chapter. The words come from the section, the form waits behind the
 * button, Reply moves it inline, collapsed threads survive a reload, and each page passes axe.
 */
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { COMMENT_SCREENS } from '../lib/urls.mjs';

const SERIOUS_IMPACTS = [ 'serious', 'critical' ];

test.describe( 'comments: discussion', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( COMMENT_SCREENS.discussion );
	} );

	test( 'speaks as a discussion, with no "Leave a Reply" banner', async ( {
		page,
	} ) => {
		const section = page.locator( '.ttm-comments' );
		await expect( section.locator( '.ttm-comments__title' ) ).toHaveText(
			'Discussion'
		);
		await expect( section.locator( '.ttm-comments__count' ) ).toHaveText(
			'10 comments'
		);
		await expect( section.locator( '.ttm-comments__open a' ) ).toHaveText(
			'Join the discussion'
		);
		await expect( section ).not.toContainText( /Leave a Reply/i );
		await expect( section.locator( '.ttm-comment__tag' ) ).toHaveCount( 1 );
		await expect( page.locator( '#url' ) ).toHaveCount( 0 );
	} );

	test( 'the form waits behind the button', async ( { page } ) => {
		await expect( page.locator( '#respond' ) ).toBeHidden();
		await page.click( '.ttm-comments__open a' );
		await expect( page.locator( '#respond' ) ).toBeVisible();
		await expect( page.locator( '#comment' ) ).toBeFocused();
	} );

	test( 'Reply opens the form under the comment, Cancel puts it away', async ( {
		page,
	} ) => {
		await page.locator( '.comment-reply-link' ).first().click();
		await expect( page.locator( '#respond' ) ).toBeVisible();
		await expect( page.locator( '#reply-title' ) ).toContainText(
			'Replying to Dana Reyes'
		);
		await expect(
			page.locator( '.ttm-comments__list #respond' )
		).toHaveCount( 1 );
		await page.click( '#cancel-comment-reply-link' );
		await expect( page.locator( '#respond' ) ).toBeHidden();
	} );

	test( 'replies nest three deep and no further', async ( { page } ) => {
		await expect(
			page.locator( '.ttm-comments__list > li > ol > li > ol > li' )
		).toHaveCount( 1 );
		const deepest = page.locator(
			'.ttm-comments__list > li > ol > li > ol > li'
		);
		await expect( deepest.locator( '.comment-reply-link' ) ).toHaveCount(
			0
		);
	} );

	test( 'a collapsed thread stays collapsed after a reload', async ( {
		page,
	} ) => {
		const thread = page.locator( '.ttm-comments__list > li' ).first();
		await thread
			.locator( ':scope > .ttm-comment .ttm-comment__collapse' )
			.click();
		await expect( thread ).toHaveClass( /is-collapsed/ );
		await expect(
			thread.locator( ':scope > .ttm-comment__fold' )
		).toContainText( '3 replies' );
		await page.reload();
		await expect(
			page.locator( '.ttm-comments__list > li' ).first()
		).toHaveClass( /is-collapsed/ );
		await expect(
			page.locator( '.ttm-comments__tools button' )
		).toHaveCount( 2 );
	} );
} );

test.describe( 'comments: reflections', () => {
	test( 'flat, newest first, five shown, no Reply', async ( { page } ) => {
		await page.goto( COMMENT_SCREENS.reflections );
		await expect( page.locator( '.ttm-comments__title' ) ).toHaveText(
			'Reflections'
		);
		await expect( page.locator( '.ttm-comments__note' ) ).toBeHidden();
		await expect( page.locator( '.comment-reply-link' ) ).toHaveCount( 0 );
		await expect(
			page.locator( '.ttm-comments__list > li:visible' )
		).toHaveCount( 5 );
		await expect(
			page.locator( '.ttm-comments__list .ttm-comment__name' ).first()
		).toHaveText( 'Walker 7' );
		await page.click( '.ttm-comments__more' );
		await expect(
			page.locator( '.ttm-comments__list > li:visible' )
		).toHaveCount( 7 );
	} );
} );

test.describe( 'comments: reader notes', () => {
	test( 'closed on arrival, opened on request, two levels deep', async ( {
		page,
	} ) => {
		await page.goto( COMMENT_SCREENS.notes );
		await expect( page.locator( '.ttm-comments__title' ) ).toHaveText(
			'Reader notes'
		);
		await expect( page.locator( '.ttm-comments__list' ) ).toBeHidden();
		await page.click( '.ttm-comments__toggle' );
		await expect( page.locator( '.ttm-comments__list' ) ).toBeVisible();
		await expect(
			page.locator( '.ttm-comments__list li li .comment-reply-link' )
		).toHaveCount( 0 );
	} );
} );

for ( const [ name, path ] of Object.entries( COMMENT_SCREENS ) ) {
	for ( const width of [ 1280, 390 ] ) {
		test( `comments: ${ name } at ${ width } has zero serious/critical axe violations`, async ( {
			page,
		} ) => {
			await page.setViewportSize( { width, height: 900 } );
			await page.goto( path );
			const results = await new AxeBuilder( { page } )
				.include( '.ttm-comments' )
				.analyze();
			const blocking = results.violations.filter( ( violation ) =>
				SERIOUS_IMPACTS.includes( violation.impact )
			);
			expect( blocking, JSON.stringify( blocking, null, 2 ) ).toEqual(
				[]
			);
			expect(
				await page.evaluate(
					() =>
						document.documentElement.scrollWidth > window.innerWidth
				)
			).toBe( false );
		} );
	}
}
