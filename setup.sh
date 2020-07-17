#!/bin/bash
#Description: Setup script to install the EmonMUC framework

# Set the targeted directories of the emonmuc framework.
# If a specified directory is empty, the component will be created.
EMONMUC_PORT=8080
EMONMUC_DIR="/opt/emonmuc"
EMONMUC_DATA="/var/opt/emonmuc"
EMONCMS_DIR="/var/www/emoncms"
EMONCMS_LOG="/var/log/emoncms"

if [[ $EUID -ne 0 ]]; then
  echo "Please make sure to run the emonmuc setup as root user"
  exit 1
fi
echo "Starting emonmuc setup"

if type -p java >/dev/null 2>&1; then
  JAVA_CMD=java
elif [ -n "$JAVA_HOME" ] && [ -x "$JAVA_HOME/bin/java" ]; then
  JAVA_CMD="$JAVA_HOME/bin/java"
else
  apt-get install -y default-jre-headless
fi
#JAVA_VERS=$("$JAVA_CMD" -version 2>&1 | awk -F '"' '/version/ {print $2}')
#if [ "$JAVA_VERS" < "1.8" ]; then
#  echo "Installed java version is below 1.8 and not compatible with emonmuc"
#  exit 1
#fi

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

find_emonmuc_user() {
  EMONMUC_USER=`stat -c "%U" "$EMONMUC_DIR"/setup.sh`
}

download_emonmuc() {
  echo "Downloading emonmuc framework"
  apt-get install -y -qq git-core

  git clone "https://github.com/isc-konstanz/emonmuc.git" "$EMONMUC_DIR"
}

install_emonmuc() {
  echo "Installing emonmuc framework"

  apt-get install -y -qq git-core gradle

  mkdir -p /var/run/emonmuc /var/log/emonmuc "$EMONMUC_DATA" "$EMONCMS_LOG"
  chown $EMONMUC_USER /var/run/emonmuc /var/log/emonmuc "$EMONMUC_DATA" "$EMONCMS_LOG"
  chown $EMONMUC_USER -R "$EMONMUC_DIR"

  if [ ! -f "$EMONMUC_DIR"/conf/system.properties ]; then
    cp -p "$EMONMUC_DIR"/conf/system.default.properties "$EMONMUC_DIR"/conf/system.properties
  fi
  if [ ! -f "$EMONMUC_DIR"/conf/config.properties ]; then
    cp -p "$EMONMUC_DIR"/conf/config.default.properties "$EMONMUC_DIR"/conf/config.properties
  fi

  ln -sf "$EMONMUC_DIR"/bin/emonmuc /usr/local/bin/emonmuc
  ln -sf "$EMONMUC_DIR"/lib/systemd/emonmuc.service /lib/systemd/system/emonmuc.service
  echo "d /var/run/emonmuc 0755 $EMONMUC_USER root -" | sudo tee /usr/lib/tmpfiles.d/emonmuc.conf >/dev/null 2>&1

  if [ -n "$EMONCMS_DIR" ]; then
    sudo -u $EMONMUC_USER mkdir -p "$EMONMUC_DATA"/device
    sudo -u $EMONMUC_USER ln -sf "$EMONMUC_DIR"/lib/device/* "$EMONMUC_DATA"/device/

    sudo -u $EMONMUC_USER ln -sf "$EMONMUC_DIR"/www/modules/channel "$EMONCMS_DIR"/Modules/
    sudo -u $EMONMUC_USER ln -sf "$EMONMUC_DIR"/www/modules/muc "$EMONCMS_DIR"/Modules/
    sudo -u $EMONMUC_USER ln -sf "$EMONMUC_DIR"/www/themes/seal "$EMONCMS_DIR"/Theme/

    php "$EMONMUC_DIR"/lib/www/upgrade.php
  fi

  if [ "$CLEAN" ] && [ -e "$EMONMUC_TMP/conf" ]; then
    rm "$EMONMUC_TMP"/conf/{*.default.*,logback.xml,shadow} >/dev/null 2>&1
    cp -rpf "$EMONMUC_TMP"/conf/* "$EMONMUC_DIR"/conf/
  fi
  bash "$EMONMUC_DIR"/bin/emonmuc install --datalogger emoncms --server restws

  if [ -n "$EMONCMS_DIR" ]; then
    # Wait a while for the server to be available.
    # TODO: Explore necessity. May be necessary for Raspberry Pi V1
    printf "Restarting emonmuc service\nPlease wait"
    wait=0
    while ! nc -z localhost $EMONMUC_PORT && [ $wait -lt 60 ]; do
      wait=$((wait + 3))
      sleep 3
      printf "."
    done
    while [ $wait -lt 15 ]; do
      wait=$((wait + 3))
      sleep 3
      printf "."
    done
    printf "\n"

    php "$EMONMUC_DIR"/lib/www/setup.php --dir "$EMONCMS_DIR" --apikey $API_KEY
    chown $EMONMUC_USER -R "$EMONMUC_DIR"/conf
  fi
  rm "$EMONCMS_LOG"/emonmuc* >/dev/null 2>&1

  systemctl enable emonmuc.service
  systemctl restart emonmuc.service
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
    *)
      echo "Synopsis: setup.sh [-e|--emoncms location] [-a|--apikey authentication] [-c|--clean]"
      exit 1
      ;;
  esac
done

if [ -z ${EMONMUC_DIR+x} ]; then
  find_emonmuc_dir
fi
find_emonmuc_user

if [ "$CLEAN" ]; then
  EMONMUC_TMP="/var/tmp/emonmuc/backup"
  mkdir -p $EMONMUC_TMP/lib
  rm -rf /var/tmp/emonmuc/bundle "$EMONMUC_DATA"/device
  cp -rpf "$EMONMUC_DATA"/* $EMONMUC_TMP/lib/
  cp -rpf "$EMONMUC_DIR"/conf $EMONMUC_TMP/
  rm -rf "$EMONMUC_DIR"
fi

if [ ! -d "$EMONMUC_DIR" ]; then
  download_emonmuc
fi
#echo -e "\e[96m\e[1m$(cat $EMONMUC_DIR/lib/framework/welcome.txt)\e[0m"
install_emonmuc

echo "Successfully installed the emonmuc framework"
exit 0
