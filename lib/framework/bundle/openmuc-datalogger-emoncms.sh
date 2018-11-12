#!/bin/bash
#Description: Setup script to install EmonCMS Datalogger
OWNER="isc-konstanz"
PROJECT="emonjava"
SERVICE="datalogger"
ID="emoncms"

VERSION="1.1.5"

install() {
  # Verify, if the specific version does exists already
  if ! installed "openmuc-$SERVICE-$ID" "$VERSION"; then
    remove
    github "$OWNER" "$PROJECT" "$VERSION"
    install_bundle "$PROJECT" "openmuc-$SERVICE-$ID" "$VERSION"
  fi
}

remove() {
  remove_bundle "openmuc-$SERVICE-$ID"
}
