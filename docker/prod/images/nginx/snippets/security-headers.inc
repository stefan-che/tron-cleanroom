# Security headers. Kept in a separate file because in nginx
# add_header directives are NOT inherited into a location that has its own add_header:
# a single add_header Cache-Control in the static block cancelled all four here.
# Verified with curl: /*.css, /*.js and /*.svg did not even get nosniff,
# which matters most for svg and js (otherwise the browser may execute them as HTML).
# So this file is included in EVERY location that has its own add_header.
#
# HSTS is not set here: connections reach nginx over http from the
# front proxy, which must set the header.
add_header X-Content-Type-Options    "nosniff" always;
add_header X-Frame-Options           "SAMEORIGIN" always;
add_header Referrer-Policy           "strict-origin-when-cross-origin" always;
add_header Permissions-Policy        "geolocation=(), microphone=(), camera=()" always;
