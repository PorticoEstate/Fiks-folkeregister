FROM php:8.2-apache

RUN apt-get update -y
RUN apt-get install -y libpq-dev cron curl git unzip openssl

COPY startup.sh /usr/local/bin/
RUN chmod +x  /usr/local/bin/startup.sh

RUN cd ~
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php
RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer
EXPOSE 8210

ENTRYPOINT /usr/local/bin/startup.sh
