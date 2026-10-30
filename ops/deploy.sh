#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
archive=.deploy/images.tar.gz
trap 'rm -f -- "$archive"' EXIT
revision=${1:?Usage: bash ops/deploy.sh SOURCE_SHA}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]] || { echo 'Invalid source revision' >&2; exit 1; }
[[ "$(git rev-parse HEAD)" == "$revision" ]] || { echo 'Checkout revision mismatch' >&2; exit 1; }
[[ -s "$archive" ]] || { echo 'Missing image archive' >&2; exit 1; }

dc() { docker compose -f docker-compose.yml -f docker-compose.prod.yml "$@"; }
image_revision() {
    docker image inspect --format '{{index .Config.Labels "org.opencontainers.image.revision"}}' "$1"
}

# Read-only preflight. Do not dump resolved Compose config or application logs.
uptime
free -m
df -h .
docker stats --no-stream --format '{{.Name}} {{.MemUsage}} {{.CPUPerc}}'
dc config --quiet
dc ps

# Preserve the images actually serving traffic, rather than a failed latest tag.
# Re-running the same revision must retain the previous rollback release.
for service in backend frontend; do
    container=$(dc ps -q "$service")
    if [[ -n "$container" ]]; then
        if [[ "$service" == backend ]]; then port=8080; path=up; else port=3000; path=login; fi
        curl --fail --silent --show-error --output /dev/null --max-time 15 "http://127.0.0.1:$port/$path"
        current=$(docker container inspect --format '{{.Image}}' "$container")
        if [[ "$(image_revision "$current")" != "$revision" ]]; then
            docker image tag "$current" "nutriscope-$service:rollback"
        fi
    fi
done

gzip -dc "$archive" | docker image load
for service in backend frontend; do
    [[ "$(image_revision "nutriscope-$service:latest")" == "$revision" ]] || {
        echo "Loaded $service image revision mismatch" >&2
        exit 1
    }
done

# The existing release includes migrations. Never automatically roll back data.
dc --profile release run --rm --pull never backend_release
dc up -d --no-build --pull never backend backend_worker backend_scheduler frontend
for url in http://127.0.0.1:8080/up http://127.0.0.1:3000/login; do
    curl --fail --silent --show-error --output /dev/null --max-time 15 \
        --retry 12 --retry-all-errors --retry-delay 5 "$url"
done
for service in backend backend_worker backend_scheduler frontend; do
    container=$(dc ps -q "$service")
    [[ -n "$container" ]] || { echo "Missing running service: $service" >&2; exit 1; }
    image=nutriscope-backend:latest
    [[ "$service" != frontend ]] || image=nutriscope-frontend:latest
    [[ "$(docker container inspect --format '{{.Image}}' "$container")" == \
       "$(docker image inspect --format '{{.Id}}' "$image")" ]] || {
        echo "Running $service image mismatch" >&2
        exit 1
    }
done

dc ps
free -m
# On this dedicated host, include legacy unlabeled dangling builds after a
# 24-hour grace period. Tagged/container-referenced images and volumes stay intact.
docker image prune --force --filter until=24h
