![emonmuc header](docs/img/emonmuc-logo.png)

EmonMUC (**e**nergy **mon**itoring **m**ulti **u**tility **c**ommunication) is based on the open-source project [OpenMUC](https://www.openmuc.org/), a Java OSGi software framework, that simplifies the development of customized *monitoring, logging and control* systems. It can be used as a basis to flexibly implement anything from simple data loggers to complex SCADA systems. The OpenMUC framework is getting developed at [Fraunhofer ISE](https://ise.fraunhofer.de/) and used as a basis in various smart grid projects. Among other tasks it is used in energy management gateways to readout smart meters, control CHP units, monitor PV systems and control electric vehicle charging. Therefore the OpenMUC framework includes mostly communication protocol drivers from the energy domain.

This project focuses on the easy setup and configuration of hardware such as household metering devices and the visualisation of collected data. All configurations of *OpenMUC* metering devices and further handling of monitored data is therefore managed by [emoncms](https://emoncms.org/), an open-source web application for processing, logging and visualising energy, temperature and other environmental data. Utilizing emoncms possibility to add standalone extensions, a designated PHP module is part of this project, to configure the device communication within the emoncms web views and to incorporate as much helpful tooltips, descriptions and templates as possible, to allow an easy configuration of metering devices.


----------

# Features

This project is developed as an extension to [emoncms](https://emoncms.org/) and adds additional features to the versatile web application. In summary, those are the following highlights:

- **Easy application development:** OpenMUC offers an abstract service for accessing data. Developers can focus on the applications logic rather than the details of the communication and data logging technology.

- **Modularity:** Drivers, data loggers etc. are all individual components. By selecting only the components you need you can create a very light weight system.

- **Drivers:** With a default installation, support for several popular communication protocols, e.g. Modbus TCP/IP and RTU.

- **Embedded systems:** The framework is designed to run on low-power embedded devices. It is currently being used on embedded x86 and ARM systems. Because OpenMUC is based on Java and OSGi it is platform independent.


----------

# Installation

These setup instructions were documented for Debian Linux based platforms, specifically for a user *pi* on **Raspbian** stretch, but may work for other Linux systems with slight adjustments to the setup script. Further guides may follow in the future.

First, the framework can be downloaded either via git or simply copied into a directory like `/opt/emonmuc`.  
Git is a source code management and revision control system, but here it is used to download and update the emonmuc application.

~~~
sudo apt-get update
sudo apt-get install git-core
~~~

After downloading, permissions should changed:

~~~
sudo git clone -b stable https://github.com/isc-konstanz/emonmuc.git /opt/emonmuc
sudo chown pi -R /opt/emonmuc
~~~

If the webserver already got installed, the setup may be finished by passing the servers **directory** and a registered users **apikey**.

~~~
sudo /opt/emonmuc/setup.sh --emoncms /var/www/emoncms --apikey <apikey>
~~~

Both parameters are optional and the webserver may be installed to a specified directory. This will be done automatically, if the directory does not exist already.

~~~
sudo /opt/emonmuc/setup.sh -e /var/www/emoncms
~~~

If no API key was specified, the first existing user will be used to register a controller. Empty installations will be initialized with a default user "*admin*", authenticated with a temporary password: *admin*, that should be replaced with a secure password immediately in the Account configurations.  
*If the database was created automatically, a `setup.conf` file can be found at the emonmuc root directory, containing database user passwords. This file needs to be removed from the system, to avoid compromising security.*

A more detailed installation guide, containing the separate steps that will be executed in the setup script, can be found here:

- [Ubuntu / Debian Linux via git](docs/LinuxInstall.md)


## Drivers

With a default installation, no drivers are enabled and need to be installed separately. As a first step, a set of protocol drivers ought to be used should be selected.  
This can be done with their unique ID, e.g. to install the **CSV** driver:

~~~
emonmuc install csv
~~~

To disable the driver, use

~~~
emonmuc remove csv
~~~

Several drivers can be enabled at once, while each needs to be selected individually. A list of possible integrated drivers are:

  - **csv**: Read CSV files
  - **dlms**: [DLMS/COSEM](https://www.openmuc.org/openmuc/user-guide/#_dlmscosem)
  - **ehz**: [eHz for SML and IEC 62056-21](https://www.openmuc.org/openmuc/user-guide/#_ehz)
  - **homematic-cc1101**: [HomeMatic (CC1101)](https://github.com/isc-konstanz/OpenHomeMatic)
  - **iec60870**: [IEC 60870-5-104](https://www.openmuc.org/openmuc/user-guide/#_iec_60870_5_104)
  - **iec61850**: [IEC 61850](https://www.openmuc.org/openmuc/user-guide/#_iec_61850)
  - **iec62056p21**: [IEC 62056 part 21](https://www.openmuc.org/openmuc/user-guide/#_iec_62056_part_21)
  - **knx**: [KNX](https://www.openmuc.org/openmuc/user-guide/#_knx)
  - **mbus**: [M-Bus (Wired)](https://www.openmuc.org/openmuc/user-guide/#_m_bus_wired)
  - **wmbus**: [M-Bus (Wireless)](https://www.openmuc.org/openmuc/user-guide/#_m_bus_wireless)
  - **modbus**: [Modbus (RTU and TCP/IP)](https://www.openmuc.org/openmuc/user-guide/#_modbus)
  - **pcharge**: [P-CHARGE](https://github.com/isc-konstanz/OpenPCharge)
  - **rpi-gpio**: GPIO (Raspberry Pi)
  - **rpi-w1**: 1-Wire (Raspberry Pi)
  - **snmp**: [SNMP](https://www.openmuc.org/openmuc/user-guide/#_snmp)
  - **solaredge**: [SolarEdge API](https://github.com/isc-konstanz/OpenSolarEdge)

Details about drivers and specific information about their usage and configuration may be found by clicking corresponding links or for most of them in the [OpenMUC User Guide](https://www.openmuc.org/openmuc/user-guide/).


## Serial Port

To use any serial port with the emonmuc framework, e.g. to communicate via Modbus RTU, the open-source project [jRxTx](https://github.com/openmuc/jrxtx) is used. This, as well as some additional steps if the UART Pins of the Raspberry Pi Platform should be used, need to be prepared.  
The [Serial Port preparation guide](docs/LinuxSerialPort.md) may be followed to do so.


----------

# Guides

With the system being prepared, some first steps can be taken to learn about the features of emonmuc.  
For this purpose, a [First Steps guide](docs/FirstSteps.md) was documented to be followed.

To get accustomed with the some of the drivers or the general emonmuc framework, a set of specified guides were written:

  - [Switching Raspberry Pi GPIOs](docs/FirstStepsGpio.md)
  - [Scanning HomeMatic Smart Plugs](https://github.com/isc-konstanz/OpenHomeMatic/blob/master/docs/FirstSteps.md)


----------

# Contact

To get in contact with the developers of the OpenMUC project, visit their homepage at [openmuc.org](https://www.openmuc.org/).  
This fork is maintained by:

![ISC logo](docs/img/isc-logo.png)

- **[ISC Konstanz](http://isc-konstanz.de/)** (International Solar Energy Research Center)
- **Adrian Minde**: adrian.minde@isc-konstanz.de
