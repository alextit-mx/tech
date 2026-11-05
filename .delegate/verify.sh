#!/bin/bash
# Verificación de sistema/. Uso: bash .delegate/verify.sh [migrate|build|types|check|test|all]
# Desde PowerShell: wsl -d Ubuntu -e bash -lic "bash /home/tit/Projects/techcomputer/.delegate/verify.sh all"
cd "$(dirname "${BASH_SOURCE[0]}")/../sistema" || exit 1
export PATH="$PWD/bin:$PATH" npm_config_cache=/tmp/npm-cache
what="${1:-all}"
if [[ $what == all || $what == migrate ]]; then
    php artisan migrate:fresh --seed --force > /tmp/v-mig.log 2>&1 && SEED_DEMO=1 php artisan db:seed --class=DemoSeeder --force >> /tmp/v-mig.log 2>&1
    echo "migrate: $?"; grep -iE 'error|exception|contraseña' /tmp/v-mig.log | head -5
fi
if [[ $what == all || $what == build ]]; then
    npm run build > /tmp/v-build.log 2>&1; echo "build: $?"; grep -iE 'error|failed|could not' /tmp/v-build.log | head -12
fi
if [[ $what == all || $what == types ]]; then
    npm run types:check > /tmp/v-types.log 2>&1; echo "types: $?"; grep -E 'error TS' /tmp/v-types.log | head -25
fi
if [[ $what == all || $what == check ]]; then
    npm run check:fix > /tmp/v-fix.log 2>&1
    npm run check > /tmp/v-check.log 2>&1; echo "check: $?"; grep -E '^\s+! |,-\[' /tmp/v-check.log | head -20
fi
if [[ $what == all || $what == test ]]; then
    vendor/bin/pint > /dev/null 2>&1
    grep -q '"test:js"' package.json && { npm run test:js > /tmp/v-js.log 2>&1; echo "test:js: $?"; grep -E 'FAIL|✗|×|Tests ' /tmp/v-js.log | head -15; }
    php artisan test 2>&1 | grep -E 'FAIL|⨯|Tests:|Error|Exception' | head -30
fi
