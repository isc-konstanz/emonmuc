![emonmuc header](img/emonmuc-logo.png)

This document describes how to prepare the serial port for emonmuc (**e**nergy **mon**itoring **m**ulty **u**tility **c**ommunication), an open-source protocoll driver project to enable the communication with a variety of metering or other devices, developed based on the [OpenMUC](https://www.openmuc.org/) project.


---------------

# 1 Prepare the Serial Port

To use any serial port with the emonmuc framework, e.g. to communicate via Modbus RTU, the open-source project [jRxTx](https://github.com/openmuc/jrxtx) is used. This, as well as some additional steps if the UART Pins of the Raspberry Pi Platform should be used, need to be prepared.


## 1.1 Install RXTX 

RXTX is a Java native library providing serial and parallel communication for the Java Development Toolkit (JDK). It is a necessary dependency for many communication devices, using e.g. RS485.

To install, download the binaries via debian repository:

~~~
sudo apt-get install librxtx-java
~~~


# 2 Raspberry Pi

By default, the serial port is configured as a console port for interacting with the Linux OS shell. To use the serial port in a software program, it must be disabled for the OS to use.
To do this, `sudo raspi-config` can be used under **Interfacing Options > Serial**. This will promt two questions, of which the first should be answered `<No>` to disable the shell interfacing, and `<Yes>` for the second one, to still allow serial connections in general.

Make sure, the console does not connect to */dev/ttyAMA0/* in `/boot/cmdline.txt`

>     dwc_otg.lpm_enable=0 console=tty1 root=/dev/mmcblk0p2 rootfstype=ext4 elevator=deadline fsck.repair=yes rootwait

and `/boot/config.txt` to have UART enabled

>     enable_uart=1

Disable any reference, getty may have to it

~~~
sudo systemctl stop serial-getty@ttyAMA0.service
sudo systemctl disable serial-getty@ttyAMA0.service
sudo systemctl mask serial-getty@ttyAMA0.service
~~~

As soon as the system will be rebooted after completing the dependency configurations, the serial port will be prepared to use.

## 2.1 Raspberry Pi v1 and v2 Compatibility

**Caution**: When using a SCC module, non-priviledged access only works since the Raspberry Pi 3 and onwards. Older platforms need to start the emonmuc framework with root permissions.  
To do so, simply change the ownership of the runscript and restart the framework:

~~~
sudo chown root -R /opt/emonmuc/bin
sudo systemctl restart emonmuc
~~~
## 2.2 Raspberry Pi v3 Compatibility


**This section only applies to Raspberry Pi v3 and later.**

To avoid UART conflicts, it's necessary to disable Pi3 Bluetooth and restore UART0/ttyAMA0 over GPIOs 14 & 15;

~~~
sudo nano /boot/config.txt
~~~

Add to the end of the file

>     dtoverlay=pi3-disable-bt

Also, stop the Bluetooth modem trying to use UART

~~~
sudo systemctl disable hciuart
~~~

See the [RasPi device tree commit](https://github.com/raspberrypi/firmware/commit/845eb064cb52af00f2ea33c0c9c54136f664a3e4) for `pi3-disable-bt` and the [forum thread discussion](https://www.raspberrypi.org/forums/viewtopic.php?f=107&t=138223).

