#!/bin/bash
#Description: Setup script to install EmonMUC bundles

BUNDLE_DIR="$EMONMUC_DIR/lib/bundle"
BUNDLE_CONFIGS="$EMONMUC_DIR/conf/bundle.d"

bundles() {
  service="driver"

  for bundle in "${@:2}"; do
    case "$2" in
      -a | --app)
        service="app"
        continue
        ;;
      -d | --driver)
        service="driver"
        continue
        ;;
      -l | --logger | --datalogger)
        service="datalogger"
        continue
        ;;
      -s | --server)
        service="server"
        continue
        ;;
      *)
        bundle "$1" "$service" "$bundle"
        ;;
    esac
  done
  php "$EMONMUC_DIR"/lib/www/reload.php
}

bundle() {
  bundle="openmuc-$2-$3.gradle"

  if ! [ -f "$BUNDLE_CONFIGS/$bundle" ]; then
	echo "Unable to $1 unknown $2: $3"
	return 1
  fi
  case "$1" in
    install)
      ln -sf "$BUNDLE_DIR/$bundle" "$BUNDLE_CONFIGS/"
      ;;
    remove)
      rm -f "$BUNDLE_CONFIGS/$bundle"*
      ;;
  esac
}
