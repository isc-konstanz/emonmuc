#!/bin/bash
#Description: Setup script to install OpenHomeMatic Driver
OWNER="isc-konstanz"
PROJECT="OpenSmartMeter"
SERVICE="driver"
ID="smartmeter"

VERSION="2.0.0"

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
