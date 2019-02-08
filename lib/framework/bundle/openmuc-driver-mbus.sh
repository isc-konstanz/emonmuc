#!/bin/bash
#Description: Setup script to install M-Bus (Wired) Driver
OWNER="isc-konstanz"
PROJECT="OpenMUC"
SERVICE="driver"
ID="mbus"

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
