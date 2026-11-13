# Trusted front proxy subnets: HAProxy, Cloudflare, etc.
#
# Why: the application determines the client IP as
#   request()->server('HTTP_CF_CONNECTING_IP', request()->ip())
# (see App\Helpers\Development::getRealIpUser), and the whitelist in
# AccessApiIpMiddleware depends on that IP. Nginx passes the application not what the
# client sent, but $remote_addr: the address the connection actually came from.
#
# Each line below allows replacing $remote_addr with the value from
# X-Forwarded-For when the connection came from that subnet. For all other
# sources the header is ignored, so spoofing does not work.
#
# IMPORTANT: the proxy must add the header itself, otherwise the application sees the proxy
# address on every request. In HAProxy this is "option forwardfor",
# in nginx it is proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for.
#
# Format: set_real_ip_from <subnet or address>;

# HAProxy in front of this stack.
# set_real_ip_from 10.0.0.0/24;

# Cloudflare (https://www.cloudflare.com/ips/), if it is ever added.
# set_real_ip_from 173.245.48.0/20;
# set_real_ip_from 103.21.244.0/22;
# set_real_ip_from 103.22.200.0/22;
# set_real_ip_from 103.31.4.0/22;
# set_real_ip_from 141.101.64.0/18;
# set_real_ip_from 108.162.192.0/18;
# set_real_ip_from 190.93.240.0/20;
# set_real_ip_from 188.114.96.0/20;
# set_real_ip_from 197.234.240.0/22;
# set_real_ip_from 198.41.128.0/17;
# set_real_ip_from 162.158.0.0/15;
# set_real_ip_from 104.16.0.0/13;
# set_real_ip_from 104.24.0.0/14;
# set_real_ip_from 172.64.0.0/13;
# set_real_ip_from 131.0.72.0/22;
