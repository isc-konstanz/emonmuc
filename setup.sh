#!/bin/bash
#Description: Setup script to install the EmonMUC framework
GIT_SERVER="https://github.com/emoncms"
GIT_BRANCH="stable"

# Set the targeted location of the emonmuc framework and the emoncms webserver.
# If a specified directory is empty, the component will be installed.
#EMONCMS_DIR="/var/www/html/emoncms"
#EMONMUC_DIR="/opt/emonmuc"
EMONCMS_USER="www-data"
EMONMUC_USER="pi"
EMONMUC_PORT=8080


if [[ $EUID -ne 0 ]]; then
  echo "Please make sure to run the emonmuc setup as root user"
  exit 1
fi

if type -p java >/dev/null 2>&1; then
  JAVA_CMD=java
elif [[ -n "$JAVA_HOME" ]] && [[ -x "$JAVA_HOME/bin/java" ]]; then
  JAVA_CMD="$JAVA_HOME/bin/java"
else
  apt-get install -y -qq openjdk-8-jre-headless
fi

if [[ "$JAVA_CMD" ]]; then
  JAVA_VERS=$("$JAVA_CMD" -version 2>&1 | awk -F '"' '/version/ {print $2}')
  if [[ "$JAVA_VERS" < "1.8" ]]; then
    echo "Installed java version is below 1.8 and not compatible with emonmuc"
    exit 1
  fi
fi

find_emonmuc_dir() {
  # Attempt to set EMONMUC_DIR
  # Resolve links: $0 may be a link
  PRG="$0"
  # Need this for relative symlinks.
  while [ -h "$PRG" ] ; do
    ls=`ls -ld "$PRG"`
    link=`expr "$ls" : '.*-> \(.*\)$'`
    if expr "$link" : '/.*' > /dev/null; then
      PRG="$link"
    else
      PRG=`dirname "$PRG"`"/$link"
    fi
  done
  SAVED="`pwd`"
  cd "`dirname \"$PRG\"`" >/dev/null
  EMONMUC_DIR="`pwd -P`"
  cd "$SAVED" >/dev/null
}

download_emonmuc() {
  echo "Downloading emonmuc framework"
  apt-get install -y -qq git-core

  git clone -b $GIT_BRANCH "https://github.com/isc-konstanz/emonmuc.git" "$EMONMUC_DIR"
}

install_emonmuc() {
  echo "Installing emonmuc framework"

  mkdir -p /var/{lib,run}/emonmuc /var/log/emoncms
  chown $EMONMUC_USER:root /var/{lib,run}/emonmuc /var/log/emoncms
  chown $EMONMUC_USER:root -R "$EMONMUC_DIR"
  chown $EMONCMS_USER:root -R "$EMONMUC_DIR"/www

  ln -sf "$EMONMUC_DIR"/bin/emonmuc /usr/local/bin/emonmuc
  ln -sf "$EMONMUC_DIR"/lib/systemd/emonmuc.service /lib/systemd/system/emonmuc.service
  echo "d /var/run/emonmuc 0755 $EMONMUC_USER root -" | sudo tee /usr/lib/tmpfiles.d/emonmuc.conf >/dev/null 2>&1

  bash "$EMONMUC_DIR"/bin/emonmuc update >/dev/null 2>&1

  systemctl enable emonmuc.service
  systemctl restart emonmuc.service
  wait=0
  while ! nc -z localhost $EMONMUC_PORT && [ $wait -lt 200 ]; do
    wait=$((wait + 1))
    sleep 0.1
  done

  if [ "$CLEAN" ] && [ -e "/var/tmp/emonmuc/setup/conf" ]; then
    rm -rf "$EMONMUC_DIR"/conf
    mv /var/tmp/emonmuc/setup/conf "$EMONMUC_DIR"/conf
    rm -rf /var/tmp/emonmuc/setup
  fi
  if [ -n "$EMONCMS_DIR" ]; then
    sudo -u $EMONCMS_USER ln -sf "$EMONMUC_DIR"/www/modules/channel "$EMONCMS_DIR"/Modules/
    sudo -u $EMONCMS_USER ln -sf "$EMONMUC_DIR"/www/modules/muc "$EMONCMS_DIR"/Modules/
    sudo -u $EMONCMS_USER ln -sf "$EMONMUC_DIR"/www/themes/seal "$EMONCMS_DIR"/Theme/

    # Wait a while for the server to be available.
    # TODO: Explore necessity. May be necessary for Raspberry Pi V1
    sleep 10

    php "$EMONMUC_DIR"/setup.php --dir "$EMONCMS_DIR" --apikey $API_KEY
    chown $EMONMUC_USER -R "$EMONMUC_DIR"/conf
  fi
  systemctl stop emonmuc.service
  sleep 10
  rm /var/log/emoncms/emonmuc* >/dev/null 2>&1
  systemctl start emonmuc.service
}

install_emoncms() {
  echo "Installing emoncms webserver"
  apt-get install -y -qq apache2 php7.0 libapache2-mod-php7.0 php7.0-mysql php7.0-gd php7.0-opcache php7.0-curl php7.0-dev php7.0-mcrypt php7.0-common php-pear php-redis

  a2enmod rewrite
  pear channel-discover pear.swiftmailer.org
  pecl install swift/swift

  mkdir -p /var/log/emoncms /var/lib/emoncms/{phpfiwa,phpfina,phptimeseries}
  touch /var/log/emoncms/emoncms.log
  chmod 666 /var/log/emoncms/emoncms.log

  sudo git clone -b $GIT_BRANCH $GIT_SERVER/emoncms.git "$EMONCMS_DIR"
  chown $EMONCMS_USER:root /var/log/emoncms/emoncms.log
  chown $EMONCMS_USER:root -R "$EMONCMS_DIR" /var/lib/emoncms

  sudo -u $EMONCMS_USER git clone -b seal "https://github.com/isc-konstanz/device.git" $EMONCMS_DIR/Modules/device
  #sudo -u $EMONCMS_USER git clone -b master $GIT_SERVER/device.git $EMONCMS_DIR/Modules/device
  sudo -u $EMONCMS_USER git clone -b $GIT_BRANCH $GIT_SERVER/graph.git $EMONCMS_DIR/Modules/graph
  #sudo -u $EMONCMS_USER git clone -b $GIT_BRANCH $GIT_SERVER/app.git $EMONCMS_DIR/Modules/app
  if [ "$EMONCMS_DIR" != "/var/www/html/emoncms" ]; then
    sudo chown $EMONCMS_USER:root -R /var/www/html
    sudo -u $EMONCMS_USER ln -sf "$EMONCMS_DIR" /var/www/html/emoncms
  fi

  cp -f "$EMONMUC_DIR"/conf/emoncms.apache2.conf /etc/apache2/sites-available/emoncms.conf
  a2ensite emoncms
  systemctl reload apache2

  if [ "$CLEAN" ] && [ -f "/var/tmp/emonmuc/setup/settings.php" ]; then
    mv -f /var/tmp/emonmuc/setup/settings.php "$EMONCMS_DIR"/settings.php >/dev/null 2>&1

  else
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mariadb-server mariadb-client redis-server

    mysql -uroot --execute="\
CREATE DATABASE emoncms DEFAULT CHARACTER SET utf8;\
CREATE USER 'emoncms'@'localhost' IDENTIFIED BY 'emoncms';\
GRANT ALL ON emoncms.* TO 'emoncms'@'localhost';"

    cp -f "$EMONMUC_DIR"/conf/emoncms.settings.php "$EMONCMS_DIR"/settings.php
    install_passwords
  fi
  php "$EMONMUC_DIR"/lib/www/upgrade.php
}

install_passwords() {
  sudo apt-get install -y -qq pwgen

  SQL_ROOT=$(pwgen -s1 32)
  #SQL_ROOT=$(echo "$SQL_ROOT" | tr \\\´\`\'\"\$\@\( $(pwgen -1 1))

  SQL_EMONMUC_USER=$(pwgen -s1 32)
  #SQL_EMONMUC_USER=$(echo "$SQL_EMONMUC_USER" | tr \\\´\`\'\"\$\@\( $(pwgen -1 1))

  mysql -uroot --execute="\
SET PASSWORD FOR 'root'@'localhost' = PASSWORD('$SQL_ROOT');\
SET PASSWORD FOR 'emoncms'@'localhost' = PASSWORD('$SQL_EMONMUC_USER');\
FLUSH PRIVILEGES;"

  sed -i "7s/.*password = .*$/    \$password = \"$SQL_EMONMUC_USER\";/" "$EMONCMS_DIR"/settings.php

  echo "[Database]" > "$EMONMUC_DIR"/setup.conf
  echo "root:$SQL_ROOT" >> "$EMONMUC_DIR"/setup.conf
  echo "emoncms:$SQL_EMONMUC_USER" >> "$EMONMUC_DIR"/setup.conf
}

API_KEY=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    -e | --emoncms)
      EMONCMS_DIR="$2"
      shift
      shift
      ;;
    -a | --apikey)
      API_KEY="$2"
      shift
      shift
      ;;
    -c | --clean)
      CLEAN=true
      shift
      ;;
    -r | --reset)
      RESET=true
      shift
      ;;
    *)
      echo "Synopsis: setup.sh [-e|--emoncms location] [-a|--apikey authentication] [-c|--clean] [-r|--reset]"
      exit 1
      ;;
  esac
done

if [ -z ${EMONMUC_DIR+x} ]; then
  find_emonmuc_dir
fi
if [ "$CLEAN" ]; then
  mkdir -p /var/tmp/emonmuc/setup
  mv -f "$EMONMUC_DIR"/conf /var/tmp/emonmuc/setup/ >/dev/null 2>&1
  mv -f "$EMONCMS_DIR"/settings.php /var/tmp/emonmuc/setup/ >/dev/null 2>&1
  rm -rf "$EMONMUC_DIR" >/dev/null 2>&1
  rm -rf "$EMONCMS_DIR" >/dev/null 2>&1
  rm -rf /srv/www/emoncms* >/dev/null 2>&1
  rm -rf /var/www/emoncms* >/dev/null 2>&1
  rm -rf /var/www/html/emoncms* >/dev/null 2>&1
fi

if [ ! -d "$EMONMUC_DIR" ]; then
  download_emonmuc
fi
#echo -e "\e[96m\e[1m$(cat $EMONMUC_DIR/lib/framework/welcome.txt)\e[0m"

if [ -n "$EMONCMS_DIR" ]; then
  if [ ! -d "$EMONCMS_DIR" ]; then
    install_emoncms
  fi
  if [ "$RESET" ]; then
    # TODO: reset all configurations and passwords
    install_passwords
  fi
fi
install_emonmuc

echo "Successfully installed the emonmuc framework"

exit 0
