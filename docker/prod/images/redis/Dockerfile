# syntax=docker/dockerfile:1
#
# Production Redis: the official image plus apk upgrade. The upstream tag is rebuilt
# with a delay, while Alpine security updates are released earlier.
FROM redis:7.2-alpine

RUN set -eux; \
    apk upgrade --no-cache
