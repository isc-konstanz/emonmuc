#!/bin/bash
#Description: Setup script to update the EmonMUC framework

# Set the targeted location of the emonmuc framework and the emoncms webserver.
# If a specified directory is empty, the component will be installed.
#EMONCMS_DIR="/var/www/html/emoncms"
#EMONMUC_DIR="/opt/emonmuc"
EMONCMS_USER="www-data"
EMONMUC_USER="pi"


if [[ $EUID -ne 0 ]]; then
  echo "Please make sure to run the emonmuc update as root user"
  exit 1
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
  cd "`dirname \"$PRG\"`/.." >/dev/null
  EMONMUC_DIR="`pwd -P`"
  cd "$SAVED" >/dev/null
}

update_emonmuc() {
  echo "Updating emonmuc framework"
  sudo git -C "$EMONMUC_DIR" pull
  bash "$EMONMUC_DIR"/bin/emonmuc update >/dev/null 2>&1

  sudo chown $EMONMUC_USER:root -R "$EMONMUC_DIR"
  sudo chown $EMONCMS_USER:root -R "$EMONMUC_DIR"/www

  systemctl restart emonmuc
}

update_emoncms() {
  echo "Updating emoncms webserver"
  pear update-channels
  pear upgrade
  pecl update-channels
  pecl upgrade

  sudo -u $EMONCMS_USER git -C "$EMONCMS_DIR" pull

  echo "Updating emoncms modules"
  for dir in "$EMONCMS_DIR"/Modules/*/; do
      if [ -d "$dir"/.git ]; then
        sudo -u $EMONCMS_USER git -C "$dir" pull
      fi
  done

  php "$EMONMUC_DIR"/lib/www/upgrade.php
  php "$EMONMUC_DIR"/lib/www/reload.php
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    -e | --emoncms)
      EMONCMS_DIR="$2"
      shift
      shift
      ;;
    *)
      echo "Synopsis: update.sh [-e|--emoncms location]"
      exit 1
      ;;
  esac
done

if [ -z ${EMONMUC_DIR+x} ]; then
  find_emonmuc_dir
fi
update_emonmuc

if [ -n "$EMONCMS_DIR" ] && [ -d "$EMONCMS_DIR" ]; then
  update_emoncms
fi

echo "Successfully updated the emonmuc framework"

exit 0
