#!/bin/bash
#Description: Setup script to install Raspberry Pi GPIO Driver
OWNER="isc-konstanz"
PROJECT="OpenMUC"
SERVICE="driver"
ID="rpi-gpio"

install() {
  # Verify, if the specific version does exists already
  if ! installed "openmuc-$SERVICE-$ID" "$OPENMUC_VERSION"; then
    github "$OWNER" "$PROJECT" "$OPENMUC_VERSION"
    remove_bundle "openmuc-$SERVICE-$ID"
    install_bundle "$PROJECT" "openmuc-$SERVICE-$ID" "$OPENMUC_VERSION"
  fi
}

remove() {
  remove_bundle "openmuc-$SERVICE-$ID"
}
