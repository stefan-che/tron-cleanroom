# syntax=docker/dockerfile:1
#
# Production MySQL: the official 8.4 LTS image plus two changes that remove
# CVEs unrelated to mysqld itself.
#
# 8.4 LTS, not 8.0: the 8.0 branch is end-of-life, 8.0.46 is the last tag, and no new
# builds with updated libraries will ship for it.

# The gosu in the Oracle image is built with Go 1.24.6; all 22 critical/high findings in the image
# come from it. Rebuild it with a fresh Go.
FROM golang:1.26-alpine AS gosu
RUN CGO_ENABLED=0 GOFLAGS=-trimpath go install -ldflags '-s -w' github.com/tianon/gosu@1.19

FROM mysql:8.4.11

COPY --from=gosu /go/bin/gosu /usr/local/bin/gosu

# microdnf update: Oracle Linux security updates released after the image
# was built (openssl, glib2, etc.). As with nginx/php, rebuild via
# `make rebuild`, otherwise the layer comes from the cache and updates never arrive.
#
# mysql-shell (mysqlsh) is a separate tool with its own Python and the
# cryptography/urllib3/pyOpenSSL packages: these are all the image's Python CVEs. Neither
# the image entrypoint nor our scripts (backups use mysqldump) call it, and no
# package depends on it, so it is removed.
RUN set -eux; \
    microdnf -y update; \
    microdnf -y remove mysql-shell; \
    microdnf clean all; \
    rm -rf /var/cache/yum /var/cache/dnf
