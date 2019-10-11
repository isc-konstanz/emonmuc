#!/bin/bash
#Description: Setup script to install EmonMUC bundles
OPENMUC_VERSION="0.17.2"

BUNDLES_DIR="$EMONMUC_DIR/bundle"
CONF_DIR="$EMONMUC_DIR/conf"
LIB_DIR="/var/opt/emonmuc"

TMP_DIR="/var/tmp/emonmuc/bundle"

update() {
  core github "OpenMUC"             "openmuc-core-api"                  $OPENMUC_VERSION
  core github "OpenMUC"             "openmuc-core-spi"                  $OPENMUC_VERSION
  core github "OpenMUC"             "openmuc-core-datamanager"          $OPENMUC_VERSION
  core github "OpenMUC"             "openmuc-server-restws"             $OPENMUC_VERSION

  core github "emonjava"            "openmuc-datalogger-emoncms"        "1.3.0"

  #--------------------------------------------------------------------------------------------------
  # RXTX is a native interface to serial ports in java.
  #--------------------------------------------------------------------------------------------------
  core maven  "org.openmuc"         "jrxtx"                             "1.0.1"

  #--------------------------------------------------------------------------------------------------
  # The Apache Felix Gogo standard shell for OSGi (http://felix.apache.org/site/apache-felix-gogo.html)
  #--------------------------------------------------------------------------------------------------
  core maven  "org.apache.felix"    "org.apache.felix.gogo.runtime"     "1.1.0"
  core maven  "org.apache.felix"    "org.apache.felix.gogo.command"     "1.0.2"
  core maven  "org.apache.felix"    "org.apache.felix.gogo.jline"       "1.1.0"
  core maven  "org.jline"           "jline"                             "3.9.0"

  #--------------------------------------------------------------------------------------------------
  # Adds a telnet server so that the Felix Gogo Shell can be accessed
  # using telnet clients. By default this server only listens on
  # localhost port 6666. Therefor you can on only access it from the
  # same host on which felix is running.
  #--------------------------------------------------------------------------------------------------
  core maven  "org.apache.felix"    "org.apache.felix.shell.remote"     "1.2.0"

  #--------------------------------------------------------------------------------------------------
  # Apache Felix Service Component Runtime that implements the OSGi Declarative Services Specification
  # the OpenMUC core bundles use declarative services and thus depend on them
  #--------------------------------------------------------------------------------------------------
  core maven  "org.apache.felix"    "org.apache.felix.scr"              "2.1.14"

  #--------------------------------------------------------------------------------------------------
  # An implementation of the OSGi HTTP Service Specification, needed by the web bundles
  #--------------------------------------------------------------------------------------------------
  core maven  "org.apache.felix"    "org.apache.felix.http.servlet-api" "1.1.2"
  core maven  "org.apache.felix"    "org.apache.felix.http.api"         "3.0.0"
  core maven  "org.apache.felix"    "org.apache.felix.http.jetty"       "4.0.6"

  core maven  "javax.annotation"    "javax.annotation-api"              "1.3.2"
  core maven  "javax.xml.bind"      "jaxb-api"                          "2.3.1"

  #--------------------------------------------------------------------------------------------------
  # Implementations of the OSGi Event Admin, Configuration Admin and MetaType services, needed by jetty
  #--------------------------------------------------------------------------------------------------
  core maven  "org.apache.felix"    "org.apache.felix.eventadmin"       "1.5.0"
  core maven  "org.apache.felix"    "org.apache.felix.configadmin"      "1.9.10"
  core maven  "org.apache.felix"    "org.apache.felix.metatype"         "1.2.2"

  #--------------------------------------------------------------------------------------------------
  # Adds a web console for felix bundle management
  # http://localhost:8080/system/console/httpservice
  # https://localhost:8443/system/console/httpservice
  #--------------------------------------------------------------------------------------------------
  core maven  "org.apache.felix"    "org.apache.felix.webconsole"       "4.3.8"
  core maven  "org.apache.felix"    "org.apache.felix.log"              "1.2.0"
  core maven  "commons-io"          "commons-io"                        "2.6"
  core maven  "commons-fileupload"  "commons-fileupload"                "1.3.3"

  #--------------------------------------------------------------------------------------------------
  # Message logging libraries, SLF4J is a light-weight logging API,
  # Logback is a message logger implementation that implements SLF4J
  # natively
  #--------------------------------------------------------------------------------------------------
  core maven  "org.slf4j"           "slf4j-api"                         "1.7.25"
  core maven  "ch.qos.logback"      "logback-classic"                   "1.2.3"
  core maven  "ch.qos.logback"      "logback-core"                      "1.2.3"

  #--------------------------------------------------------------------------------------------------
  # The Apache Felix main executable
  #--------------------------------------------------------------------------------------------------
  core framework "org.apache.felix" "org.apache.felix.main"             "6.0.1"

  cp -rf "$EMONMUC_DIR/lib/device" "$LIB_DIR/"
  if [ -f "$EMONMUC_DIR/conf/bundles.conf" ]; then
    read -a bundles < "$EMONMUC_DIR/conf/bundles.conf"
    for bundle in "${bundles[@]}"; do
      source "$EMONMUC_DIR/lib/framework/bundle/$bundle.sh"

      install
    done
  fi
  php "$EMONMUC_DIR"/lib/www/reload.php
}

bundles() {
  for bundle in "${@:2}"; do
    bundle "$1" "$bundle"
  done
  php "$EMONMUC_DIR"/lib/www/reload.php
}

bundle() {
  if ! bundle_exists "$2"; then
    echo "Unable to $1 unknown bundle: $2"
    exit 1
  fi
  bundles=()
  if [ -f "$EMONMUC_DIR/conf/bundles.conf" ]; then
    read -a bundles < "$EMONMUC_DIR/conf/bundles.conf"
  fi
  installed=($(sort <<<"${bundles[*]}"))

  source "$EMONMUC_DIR/lib/framework/bundle/$bundle.sh"
  case "$1" in
    install)
      install

      if [[ ! " ${bundles[@]} " =~ " $bundle " ]]; then
        bundles+=("$bundle")
        IFS=$'\n' installed=($(sort <<<"${bundles[*]}"))
        unset IFS
      fi
      ;;
    remove)
      remove

      if [[ " ${bundles[@]} " =~ " $bundle " ]]; then
        delete=($bundle)
        installed=${bundles[@]/$delete}
      fi
      ;;
  esac
  echo "${installed[@]}" > "$EMONMUC_DIR"/conf/bundles.conf
}

bundle_exists() {
  bundle="$1"
  if [ -f "$EMONMUC_DIR"/lib/framework/bundle/"$bundle".sh ]; then
    return 0
  fi
  files=("$EMONMUC_DIR"/lib/framework/bundle/*"$bundle"*.sh)
  if [ ${#files[@]} -gt 1 ]; then
    files=("$EMONMUC_DIR"/lib/framework/bundle/*"driver-$bundle"*.sh)
  fi

  if [ ${#files[@]} -eq 1 ]; then
    if [ -f ${files[0]} ]; then
      bundle=$(basename -- "${files[0]%.*}")
      return 0
    fi
  else
    echo "Please clarify bundle to install:"
    for file in "${files[@]}"; do
      echo "  $(basename -- "${file%.*}")"
    done
    exit 1
  fi
  return 1
}

installed() {
  [ -f "$BUNDLES_DIR/$1-$2.jar" ]
}

install_bundle() {
  cp -f "$TMP_DIR/$1/libs/$2-$3.jar" "$BUNDLES_DIR/"
}

install_conf() {
  if [ ! -e "$CONF_DIR/$2" ]; then
    mkdir -p "$(dirname "$CONF_DIR/$2")"
    cp -rf "$TMP_DIR/$1/conf/$2" "$CONF_DIR/$2"
  fi
}

install_lib() {
  if [ ! -e "$LIB_DIR/$2" ]; then
    mkdir -p "$(dirname "$LIB_DIR/$2")"
    case "$1" in
      core)
        cp -rf "$EMONMUC_DIR/lib/$2" "$LIB_DIR/$2"
	    ;;
	  *)
        cp -rf "$TMP_DIR/$1/libs/$2" "$LIB_DIR/$2"
	    ;;
	esac
  fi
}

remove_bundle() {
  rm -f "$BUNDLES_DIR/$1"*
}

remove_conf() {
  if [ $# -gt 0 ] && [ -n "$1" ]; then
    rm -rf "$CONF_DIR/$1"
  fi
}

remove_lib() {
  if [ $# -gt 0 ] && [ -n "$1" ]; then
    rm -rf "$LIB_DIR/$1"
  fi
}

core() {
  if [ ! -f  "$BUNDLES_DIR/$3-$4.jar" ]; then
    mkdir -p "$BUNDLES_DIR"
    rm -f "$BUNDLES_DIR/$3"*

    case "$1" in
      github)
        github "isc-konstanz" $2 $4
        mv -f "$TMP_DIR/$2/libs/$3-$4.jar" "$BUNDLES_DIR/"
        ;;
      maven)
        maven  ${@:2}
        ;;
      framework)
        wget --quiet \
             --directory-prefix="$TMP_DIR" \
             "http://central.maven.org/maven2/${2//./\/}/$3/$4/$3-$4.jar"
        mv -f "$TMP_DIR/org.apache.felix.main-"* "$EMONMUC_DIR/bin/felix.jar"
        ;;
    esac
  fi
}

github() {
  # Download and unzip tarball if not already existing
  mkdir -p "$TMP_DIR"
  if [ ! -f  "$TMP_DIR/$2-$3.tar.gz" ]; then
    rm -rf "$TMP_DIR/$2"*
    wget --quiet \
         --show-progress \
         --directory-prefix="$TMP_DIR" \
         "https://github.com/$1/$2/releases/download/v$3/$2-$3.tar.gz"

    tar -xzf "$TMP_DIR/$2-$3.tar.gz" -C "$TMP_DIR/"
  fi
}

maven() {
  wget --quiet \
       --show-progress \
       --directory-prefix="$EMONMUC_DIR/bundle" \
       "http://central.maven.org/maven2/${1//./\/}/$2/$3/$2-$3.jar"
}
