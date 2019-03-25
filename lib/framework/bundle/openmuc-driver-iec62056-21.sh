#!/bin/bash
#Description: Setup script to install OpenHomeMatic Driver
OWNER="isc-konstanz"
PROJECT="OpenIEC62056-21"
SERVICE="driver"
ID="iec62056-21"

VERSION="1.0.2"

install() {
  # Verify, if the specific version does exists already
  if ! installed "openmuc-$SERVICE-$ID" "$VERSION"; then
    remove
    github "$OWNER" "$PROJECT" "$VERSION"
    install_bundle "$PROJECT" "openmuc-$SERVICE-$ID" "$VERSION"
    install_lib "$PROJECT" "device/$ID"
  fi
}

remove() {
  remove_bundle "openmuc-$SERVICE-$ID"
  remove_lib "device/$ID"
}
