#!/bin/bash
# Corre un encargo con Codex en sistema/. Uso: bash .delegate/run.sh <encargo.md> [esfuerzo]
D=/home/tit/Projects/techcomputer/.delegate
BRIEF="$D/$(basename "$1")"
EFFORT="${2:-medium}"
NAME="$(basename "$BRIEF" .md)"
cd /home/tit/Projects/techcomputer/sistema || exit 1
export PATH="$PWD/bin:$PATH" npm_config_cache=/tmp/npm-cache
# La sesión de Codex se inició en Windows; úsala si WSL no tiene la suya.
[ -f "$HOME/.codex/auth.json" ] || export CODEX_HOME=/mnt/c/Users/tit/.codex
codex exec -s workspace-write -c sandbox_workspace_write.network_access=true \
    -c model_reasoning_effort="$EFFORT" -o "$D/$NAME.result.md" - < "$BRIEF" > "$D/$NAME.log" 2>&1
echo "codex exit $?"
cat "$D/$NAME.result.md" 2>/dev/null || tail -40 "$D/$NAME.log"
