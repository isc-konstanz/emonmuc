#!/bin/bash
#Description: Setup script to install EmonMUC bundles

BUNDLE_DIR="$EMONMUC_DIR/lib/bundle"
BUNDLE_CONFIGS="$EMONMUC_DIR/conf/bundle.d"

bundles() {
    service="driver"

    for bundle in "${@:2}"; do
        case "$bundle" in
            -a | --app)
                service="app"
                ;;
            -d | --driver)
                service="driver"
                ;;
            -l | --logger | --datalogger)
                service="datalogger"
                ;;
            -s | --server)
                service="server"
                ;;
            *)
                bundle "$1" "$service" "$bundle"
                ;;
        esac
    done
}

bundle() {
    bundle="openmuc-$2-$3.gradle"

    if ! [ -f "$BUNDLE_DIR/$bundle" ]; then
        echo "Unable to $1 unknown $2: $3"
        return 1
    fi
    case "$1" in
        install)
            if [ ! -d "$BUNDLE_CONFIGS" ]; then
                mkdir -p "$BUNDLE_CONFIGS"
            fi
            ln -sf "$BUNDLE_DIR/$bundle" "$BUNDLE_CONFIGS/"
            ;;
        remove)
            if [ -f "$BUNDLE_CONFIGS" ]; then
                rm -f "$BUNDLE_CONFIGS/$bundle"*
            fi
            ;;
    esac
}
