#!/bin/bash
#Description: Setup script to install Raspberry Pi 1-Wire Driver
OWNER="isc-konstanz"
PROJECT="OpenMUC"
SERVICE="driver"
ID="rpi-w1"

install() {
  # Verify, if the specific version does exists already
  if ! installed "openmuc-$SERVICE-$ID" "$OPENMUC_VERSION"; then
    remove
    github "$OWNER" "$PROJECT" "$OPENMUC_VERSION"
    install_bundle "$PROJECT" "openmuc-$SERVICE-$ID" "$OPENMUC_VERSION"
  fi
}

remove() {
  remove_bundle "openmuc-$SERVICE-$ID"
}
