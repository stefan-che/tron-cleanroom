# syntax=docker/dockerfile:1
#
# Production nginx: static files and proxying to php-fpm. TLS is not terminated here:
# only port 80 is exposed (TLS is terminated by the front proxy, see README).
#
# Base is alpine, not bookworm: the debian variant pulls in libxml2, libxslt, libgd,
# libtiff, gnutls, krb5 and more for modules we do not need, and produces
# over a hundred CVEs in the scanner.

FROM nginx:1.29-alpine

RUN set -eux; \
    apk upgrade --no-cache; \
    rm -f /etc/nginx/conf.d/default.conf

# Main config and vhost template. The stock nginx entrypoint runs files from
# /etc/nginx/templates through envsubst when the container starts,
# so the domain is set via the APP_DOMAIN variable rather than by editing the config.
COPY ./images/nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./images/nginx/templates/ /etc/nginx/templates/
COPY ./images/nginx/geo/ /etc/nginx/geo/
# Snippets are pulled in by an explicit include from the vhost, so they live outside
# conf.d (which nginx.conf includes by the *.conf mask) and outside templates
# (the stock entrypoint copies only files with the .template suffix from there).
COPY ./images/nginx/snippets/ /etc/nginx/snippets/

EXPOSE 80

STOPSIGNAL SIGQUIT

CMD ["nginx", "-g", "daemon off;"]
