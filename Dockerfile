FROM debian:jessie-slim

# Housekeep the system
RUN apt-get update -y && apt-get upgrade -y
RUN DEBIAN_FRONTEND=noninteractive apt-get install -y wget locales
RUN (sed -i.bak -e 's/# \(tr_TR.U\)/\1/' -e 's/# \(en_US.U\)/\1/' /etc/locale.gen) && dpkg-reconfigure --frontend noninteractive locales
RUN (echo "Europe/Moscow" | tee /etc/timezone) && dpkg-reconfigure --frontend noninteractive tzdata

# Set up LLMP server
RUN DEBIAN_FRONTEND=noninteractive apt-get install -y lighttpd php5-cgi php5-mysql unzip mysql-server mysql-client php5-intl
RUN lighttpd-enable-mod fastcgi
RUN lighttpd-enable-mod fastcgi-php
RUN rm /var/www/html/index.lighttpd.html

# Install LWT
RUN wget --no-check-certificate -O /tmp/lwt.zip https://sourceforge.net/projects/lwt/files/lwt_v_1_6_2.zip 
RUN unzip /tmp/lwt.zip -d /var/www/html/ && rm /tmp/lwt.zip
RUN mv /var/www/html/connect_xampp.inc.php /var/www/html/connect.inc.php

# Install simplehtmldom for tureng_api
RUN wget --no-check-certificate -O /tmp/dom.zip https://sourceforge.net/projects/simplehtmldom/files/simplehtmldom/1.8.1/simplehtmldom_1_8_1.zip
RUN mkdir /tmp/dom && unzip /tmp/dom.zip -d /tmp/dom/ && cp /tmp/dom/simple_html_dom.php /var/www/html/ && rm /tmp/dom.zip && rm -rf /tmp/dom/

# Permissions and such much
RUN chmod -R 755 /var/www/html

# Install my tureng_api
ADD glosbe_api.php /var/www/html/glosbe_api.php
RUN chmod 755 /var/www/html/glosbe_api.php

EXPOSE 80

CMD /etc/init.d/mysql start && /etc/init.d/lighttpd start && sleep infinity

# docker build -t lwt .
# docker run -d -p 80:80 --name lwt lwt
