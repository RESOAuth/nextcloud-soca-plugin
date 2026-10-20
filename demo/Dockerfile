FROM docker.io/library/composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS dependencies
WORKDIR /plugin
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts
COPY . .
FROM docker.io/library/nextcloud:33-apache@sha256:5c121a3b6be3391ff078a5b69cea922f34a7470f1dfb2ca45d4521d8772d076c
COPY --from=dependencies /plugin /opt/soca
COPY demo/install-app.sh /docker-entrypoint-hooks.d/before-starting/soca.sh
COPY demo/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod ssl rewrite && chmod +x /docker-entrypoint-hooks.d/before-starting/soca.sh && mkdir -p /run/soca
