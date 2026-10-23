#!/usr/bin/env bash
set -euo pipefail

# External Docker/HTTP boundaries are faked; the real deployment script runs.
root=$(cd "$(dirname "$0")/../.." && pwd)
sha=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
export sha
passed=0
for scenario in healthy mismatch release_failure start_failure health_failure running_mismatch preflight_failure same_revision checkout_mismatch invalid missing corrupt; do
    sandbox=$(mktemp -d)
    mkdir -p "$sandbox/ops" "$sandbox/.deploy"
    cp "$root/ops/deploy.sh" "$sandbox/ops/deploy.sh"
    printf 'fixture image archive' | gzip > "$sandbox/.deploy/images.tar.gz"
    export TRACE="$sandbox/trace" SCENARIO="$scenario"
    : > "$TRACE"
    docker() {
        printf '%s\n' "$*" >> "$TRACE"
        case "$*" in
            'compose '*" ps -q backend") echo backend-container ;;
            'compose '*" ps -q backend_worker"|'compose '*" ps -q backend_scheduler") echo backend-container ;;
            'compose '*" ps -q frontend") echo frontend-container ;;
            'container inspect '*backend-container)
                if [[ -f "$TRACE.started" ]]; then echo new-backend; else echo old-backend; fi ;;
            'container inspect '*frontend-container)
                if [[ -f "$TRACE.started" && "$SCENARIO" != running_mismatch ]]; then echo new-frontend; else echo old-frontend; fi ;;
            'image inspect '*org.opencontainers.image.revision*)
                if [[ "$SCENARIO" == mismatch ]]; then echo wrong;
                elif [[ "$*" == *old-* && "$SCENARIO" != same_revision ]]; then echo previous;
                else echo "$sha"; fi ;;
            'image inspect '*nutriscope-backend:latest) echo new-backend ;;
            'image inspect '*nutriscope-frontend:latest) echo new-frontend ;;
            'image load') cat > /dev/null ;;
            'compose '*"run "*) [[ "$SCENARIO" != release_failure ]] || return 1 ;;
            'compose '*"up "*)
                [[ "$SCENARIO" != start_failure ]] || return 1
                touch "$TRACE.started" ;;
        esac
        return 0
    }
    curl() {
        printf 'curl %s\n' "$*" >> "$TRACE"
        [[ "$SCENARIO" != preflight_failure ]] || return 1
        [[ "$SCENARIO" != health_failure || ! -f "$TRACE.started" ]]
    }
    git() { if [[ "$SCENARIO" == checkout_mismatch ]]; then echo wrong; else echo "$sha"; fi; }
    free() { :; }
    df() { :; }
    uptime() { :; }
    export -f docker curl git free df uptime
    argument=$sha
    [[ "$scenario" != invalid ]] || argument=invalid
    [[ "$scenario" != missing ]] || rm "$sandbox/.deploy/images.tar.gz"
    [[ "$scenario" != corrupt ]] || printf 'corrupt gzip' > "$sandbox/.deploy/images.tar.gz"
    result=0
    bash "$sandbox/ops/deploy.sh" "$argument" > "$sandbox/result" 2>&1 || result=$?
    if [[ "$scenario" == healthy || "$scenario" == same_revision ]]; then
        [[ "$result" == 0 ]] || { cat "$sandbox/result"; exit 1; }
        grep -q 'image prune --force --filter label=io.nutriscope.app=true --filter until=168h' "$TRACE"
        grep -q 'run --rm --no-build --pull never backend_release' "$TRACE"
        grep -q 'up -d --no-build --pull never' "$TRACE"
        if [[ "$scenario" == same_revision ]]; then
            if grep -q 'image tag .*:rollback' "$TRACE"; then exit 1; fi
        else
            grep -q 'image tag old-backend nutriscope-backend:rollback' "$TRACE"
            grep -q 'image tag old-frontend nutriscope-frontend:rollback' "$TRACE"
        fi
    else
        [[ "$result" != 0 ]] || { echo "Expected failure: $scenario"; exit 1; }
        if grep -q 'image prune' "$TRACE"; then exit 1; fi
        if [[ "$scenario" == mismatch || "$scenario" == invalid || "$scenario" == missing || "$scenario" == checkout_mismatch || "$scenario" == corrupt || "$scenario" == preflight_failure ]]; then
            if grep -q 'run --rm' "$TRACE"; then exit 1; fi
        fi
    fi
    if grep -Eq 'system prune|volume (rm|prune)|builder prune|image prune .*--all| compose .* build ' "$TRACE"; then exit 1; fi
    [[ ! -f "$sandbox/.deploy/images.tar.gz" ]]
    printf 'PASS %s\n' "$scenario"
    passed=$((passed + 1))
    # Only the test-created directory is removed.
    [[ "$sandbox" == "${TMPDIR:-/tmp}/"* ]] || exit 1
    rm -rf -- "$sandbox"
done
printf '%s deployment scenarios passed\n' "$passed"
