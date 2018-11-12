#!/bin/bash
#Description: Setup script to install IEC 62056 part 21 Driver
OWNER="isc-konstanz"
PROJECT="OpenMUC"
SERVICE="driver"
ID="iec62056p21"

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
