#!/usr/bin/env node
/**
 * Fixture for scripts/test/demo-server-process.test.js (R2-01). Spawns a grandchild the same way
 * `@wp-playground/cli`'s real process tree does under `npx`/`sh` (this process -> `sh -c` ->
 * `node`), so `stopServer()`'s process-group kill can be tested against a real multi-level tree
 * without booting the real CLI. Prints `GRANDCHILD_PID=<pid>` (the `node` process's own pid, as
 * reported by `sh`'s `$!`) to stdout as soon as it's known.
 *
 * `--trap`: both this process and the grandchild install a no-op SIGTERM handler, so a SIGTERM
 * to the group leaves everything alive and `stopServer()` must escalate to SIGKILL.
 */
import { spawn } from 'node:child_process';

const trap = process.argv.includes( '--trap' );

if ( trap ) {
	process.on( 'SIGTERM', () => {} );
}

const shCommand = trap
	? 'trap "" TERM; node -e "process.on(\'SIGTERM\', () => {}); setInterval(() => {}, 1000)" & echo GRANDCHILD_PID=$! ; wait'
	: 'node -e "setInterval(() => {}, 1000)" & echo GRANDCHILD_PID=$! ; wait';

const sh = spawn( 'sh', [ '-c', shCommand ], {
	stdio: [ 'ignore', 'inherit', 'inherit' ],
} );

sh.on( 'exit', ( code ) => process.exit( code ?? 0 ) );
