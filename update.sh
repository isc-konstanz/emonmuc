#!/bin/bash
#Description: Setup script to update the EmonMUC framework

# Set the targeted directories of the emonmuc framework.
EMONMUC_DIR="/opt/emonmuc"
EMONCMS_DIR="/var/www/emoncms"

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
    cd "`dirname \"$PRG\"`" >/dev/null
    EMONMUC_DIR="`pwd -P`"
    cd "$SAVED" >/dev/null
}

find_emonmuc_user() {
    EMONMUC_USER=`stat -c "%U" "$EMONMUC_DIR"/update.sh`
}

update_emonmuc() {
    if [ "$RESET" ]; then
        sudo git -C "$EMONMUC_DIR" reset --hard
    fi
    sudo git -C "$EMONMUC_DIR" branch
    sudo git -C "$EMONMUC_DIR" status
    sudo git -C "$EMONMUC_DIR" pull

    bash "$EMONMUC_DIR"/bin/emonmuc update

    sudo chown $EMONMUC_USER -R "$EMONMUC_DIR"

    if [ -d "$EMONCMS_DIR" ]; then
		php "$EMONMUC_DIR"/lib/www/reload.php
    fi

    systemctl daemon-reload
    systemctl restart openmuc
    state=$(systemctl show $service | grep ActiveState)
    echo "Finished update with service $state"
}

echo "Starting EmonMUC update"

while [[ $# -gt 0 ]]; do
    case "$1" in
        -e | --emoncms)
            EMONCMS_DIR="$2"
            shift
            shift
            ;;
        -r | --reset)
            RESET=true
            shift
            ;;
        -h | --help)
            echo "Synopsis: update.sh [-e|--emoncms location] [-r|--reset]"
            exit 1
            ;;
    esac
done

if [ -z ${EMONMUC_DIR+x} ]; then
    find_emonmuc_dir
fi
find_emonmuc_user

update_emonmuc

echo "Successfully updated the EmonMUC framework"
exit 0
