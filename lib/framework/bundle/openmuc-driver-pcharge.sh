#!/bin/bash
#Description: Setup script to install OpenHomeMatic Driver
OWNER="isc-konstanz"
PROJECT="OpenPCharge"
ID="pcharge"

VERSION="0.2.3"

install() {
  # Verify, if the specific version does exists already
  if ! installed "openmuc-driver-$ID" "$VERSION"; then
    remove
    github "$OWNER" "$PROJECT" "$VERSION"
    install_bundle "$PROJECT" "openmuc-app-$ID" "$VERSION"
    install_bundle "$PROJECT" "openmuc-driver-$ID" "$VERSION"
    #install_lib "$PROJECT" "device/$ID"
  fi
}

remove() {
  remove_bundle "openmuc-app-$ID"
  remove_bundle "openmuc-driver-$ID"
  #remove_lib "device/$ID"
}
