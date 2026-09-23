#!/usr/bin/env bash
# npm run env:drill (SPEC §6.9, P0-01). The real backup/restore drill body lands in P5-01;
# until then this only reports. Runs in CI's integration job (SPEC §7), so it must always
# exit 0 until P5-01 gives it real work to do.
set -euo pipefail

echo "drill.sh: lands in P5-01; nothing done"
exit 0
