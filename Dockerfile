FROM drupal:11.4.4-php8.5-apache

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN usermod -u ${USER_ID} www-data && groupmod -g ${GROUP_ID} www-data

RUN pecl install xdebug && docker-php-ext-enable xdebug

# xdebug設定追記
RUN  <<EOF
  cat <<-CMD >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
    xdebug.mode = debug,develop
    xdebug.client_port=9003
    ;xdebug.start_with_request=yes
    xdebug.log=/tmp/xdebug.log
CMD
EOF

RUN apt update -y && apt update -y
RUN apt install unzip vim git -y

# Drush インストール
# composer install しないと正常に初回起動しない
RUN composer require drush/drush \
  && composer install \
  && ln -s /opt/drupal/vendor/bin/drush /usr/local/bin/drush

# add path drupal scripts
RUN echo '#!/bin/bash\nphp /opt/drupal/web/core/scripts/drupal $@' > /usr/local/bin/drupal \
  && chmod +x /usr/local/bin/drupal

ENV PATH=${PATH}:/opt/drupal/vendor/bin

# Mailpitコンテナにメール送信できるようにするため mailpit sendmail コマンドのインストール
RUN curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh | bash
RUN touch /usr/local/etc/php/conf.d/custom.ini

# MTA設定
RUN echo "sendmail_path = /usr/local/bin/mailpit sendmail -S mailpit:1025" >> /usr/local/etc/php/conf.d/custom.ini

# vim:set ft=dockerfile:

