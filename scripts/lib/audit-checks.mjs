// npm audit gate with an explicit, expiring allowlist: pure checks over `npm audit --json`
// output, wired to the real tree by scripts/check-audit.mjs. Fails on any high/critical
// advisory not allowlisted, on an expired allowlist entry, and on an entry that no longer
// matches a reported advisory -- so the list only ever shrinks back to empty.

const GATED = new Set( [ 'high', 'critical' ] );

/**
 * Collect the distinct advisories in an `npm audit --json` report. Transitive entries name
 * their source package as a string in `via`; only the advisory objects are collected.
 *
 * @param {Object} report Parsed `npm audit --json` output.
 * @return {Array<{id: string, name: string, severity: string, title: string}>} Advisories.
 */
export function advisories( report ) {
	const found = new Map();

	for ( const vulnerability of Object.values(
		report.vulnerabilities ?? {}
	) ) {
		for ( const via of vulnerability.via ?? [] ) {
			if ( typeof via !== 'object' || ! via.url ) {
				continue;
			}
			const id = via.url.split( '/' ).pop();
			found.set( id, {
				id,
				name: via.name,
				severity: via.severity,
				title: via.title,
			} );
		}
	}

	return [ ...found.values() ];
}

/**
 * Check a report against the allowlist.
 *
 * @param {Object}                                                              report Parsed `npm audit --json` output.
 * @param {Array<{id: string, package: string, reason: string, until: string}>} allow  Allowlist entries.
 * @param {string}                                                              today  `YYYY-MM-DD`.
 * @return {string[]} Failure messages; empty when clean.
 */
export function checkAudit( report, allow, today ) {
	const failures = [];
	const reported = advisories( report );
	const allowed = new Map( allow.map( ( entry ) => [ entry.id, entry ] ) );

	for ( const advisory of reported ) {
		if ( GATED.has( advisory.severity ) && ! allowed.has( advisory.id ) ) {
			failures.push(
				`${ advisory.severity } ${ advisory.name } ${ advisory.id }: ${ advisory.title }`
			);
		}
	}

	const reportedIds = new Set( reported.map( ( advisory ) => advisory.id ) );
	for ( const entry of allow ) {
		if ( ! entry.reason || ! entry.until ) {
			failures.push(
				`allowlist ${ entry.id }: needs a reason and an until date`
			);
		} else if ( entry.until < today ) {
			failures.push(
				`allowlist ${ entry.id } (${ entry.package }) expired ${ entry.until }: re-check and renew or remove`
			);
		}
		if ( ! reportedIds.has( entry.id ) ) {
			failures.push(
				`allowlist ${ entry.id } (${ entry.package }) is no longer reported: remove it`
			);
		}
	}

	return failures;
}
