#!/bin/bash
#Description: Setup script to install Modbus driver
OWNER="isc-konstanz"
PROJECT="OpenMUC"
SERVICE="driver"
ID="modbus"

install() {
  # Verify, if the specific version does exists already
  if ! installed "openmuc-$SERVICE-$ID" "$OPENMUC_VERSION"; then
    github "$OWNER" "$PROJECT" "$OPENMUC_VERSION"
    remove_bundle "openmuc-$SERVICE-$ID"
    install_bundle "$PROJECT" "openmuc-$SERVICE-$ID" "$OPENMUC_VERSION"
  fi
  remove_lib "device/$ID"
  install_lib core "device/$ID"
}

remove() {
  remove_bundle "openmuc-$SERVICE-$ID"
  remove_lib "device/$ID"
}
