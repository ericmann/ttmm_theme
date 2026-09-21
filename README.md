# These Things Matter

The newspaper-style rebuild of [eric.mann.blog](https://eric.mann.blog): a WordPress block theme (`themes/ttm-theme`) and its companion plugin (`plugins/ttm-core`), built to be served almost entirely from cache.

- `docs/SPEC.md` — the engineering specification (what the Foundry pipeline builds from)
- `docs/SETUP.md` — local development with wp-env, tests, CI
- `docs/MIGRATION.md` — moving the live site's content to the new model, including classic → block conversion
- `docs/DEPLOYMENT.md` — production configuration: Cloudflare, Batcache, constants, purge
- `docs/README.md` and `docs/01`–`07` — the design handoff

```bash
npm ci && composer install
npx wp-env start && npm run env:seed     # http://localhost:8888  (admin / password)
composer lint && composer test:unit && npm run lint && npm test
```
