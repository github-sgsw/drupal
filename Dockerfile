FROM drupal:10.2.3-php8.3-apache

ARG USER_ID=33
ARG GROUP_ID=33
RUN echo USER_ID = ${USER_ID} : GROUP_ID = ${GROUP_ID}

RUN usermod -u ${USER_ID} www-data && groupmod -g ${GROUP_ID} www-data
RUN pecl install xdebug

# Drush インストール
# composer install しないと正常に初回起動しない
RUN composer require drush/drush
RUN composer install
RUN ln -s /opt/drupal/vendor/bin/drush /usr/local/bin/drush

# add path drupal scripts
RUN echo '#!/bin/bash\nphp /opt/drupal/web/core/scripts/drupal $@' > /usr/local/bin/drupal \
&& chmod +x /usr/local/bin/drupal

# install Git
RUN apt-get update -y && apt-get install -y git

ENV PATH=${PATH}:/opt/drupal/vendor/bin

# vim:set ft=dockerfile:
