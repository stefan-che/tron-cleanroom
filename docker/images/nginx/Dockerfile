FROM nginx:latest

ARG ENVIRONMENT

RUN rm -f /etc/nginx/conf.d/default.conf
RUN rm -f /etc/nginx/conf.d/local.conf
RUN rm -f /etc/nginx/conf.d/production.conf
RUN rm -f /etc/nginx/conf.d/staging.conf

RUN rm -rf /etc/nginx/ssl
RUN mkdir -p /etc/nginx/ssl/

COPY ./images/nginx/${ENVIRONMENT}/${ENVIRONMENT}.conf /etc/nginx/conf.d/${ENVIRONMENT}.conf
COPY ./images/nginx/${ENVIRONMENT}/ssl/* /etc/nginx/ssl/

# Install Certbot
RUN apt-get update && \
    apt-get install -y certbot && \
    rm -rf /var/lib/apt/lists/*

COPY ./images/nginx/certbot.sh /usr/local/bin/certbot.sh
RUN chmod +x /usr/local/bin/certbot.sh

EXPOSE 80
EXPOSE 443

CMD ["nginx", "-g", "daemon off;"]
