#!/bin/bash
#Description: Setup script to install the WebUI
OWNER="isc-konstanz"
PROJECT="OpenMUC"

install() {
  # Verify, if the specific version does exists already
  files=("$EMONMUC_DIR"/bundles/openmuc-webui-*-"$OPENMUC_VERSION.jar" ];
  if [ ! ${#files[@]} -gt 1 ]; then
    remove
    github "$OWNER" "$PROJECT" "$OPENMUC_VERSION"
    install_bundle "$PROJECT" "openmuc-webui-spi" "$OPENMUC_VERSION"
    install_bundle "$PROJECT" "openmuc-webui-base" "$OPENMUC_VERSION"
    install_bundle "$PROJECT" "openmuc-webui-channelconfigurator" "$OPENMUC_VERSION"
    install_bundle "$PROJECT" "openmuc-webui-channelaccesstool" "$OPENMUC_VERSION"
    install_bundle "$PROJECT" "openmuc-webui-userconfigurator" "$OPENMUC_VERSION"
    install_bundle "$PROJECT" "openmuc-webui-dataexporter" "$OPENMUC_VERSION"
    install_bundle "$PROJECT" "openmuc-webui-dataplotter" "$OPENMUC_VERSION"
    install_bundle "$PROJECT" "openmuc-webui-mediaviewer" "$OPENMUC_VERSION"
  fi
}

remove() {
  remove_bundle "openmuc-webui"
}
