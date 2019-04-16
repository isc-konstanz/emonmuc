<img src="img/rpi-gpio/rpi.jpg" alt="Raspberry Pi" width="500">

This document describes the preparation of the Raspberry Pi GPIO pins for the emonmuc (**E**nergy **mon**itoring **M**ulty **U**tility **C**ommunication controller).

The GPIO access is based on the [Pi4J](https://www.pi4j.com/) library, that links to the [Wiring Pi](http://wiringpi.com/) debian package binaries. This has to be installed first

~~~
sudo apt-get install wiringpi
~~~

To allow drivers to have access to GPIO pins of the Raspberry Pi even when not root, non-privileged accessed can be enabled since the Raspberry Pi 3.  
Non-privileged access for GPIO is not enabled by default due to the fact that some functions such as PWM are not yet supported in a non-privileged context.

This can be done by simply appending a value to the users path variable in `~/.bashrc`:  
*Beware that this change needs the user to logout to take effect*

>    export WIRINGPI_GPIOMEM=1

To make sure, check that `ls -l /dev/gpiomem` returns the correct permissions

>    crw-rw---- 1 root gpio 248, 0 MM DD HH:mm /dev/gpiomem

If it doesn't, set the correct permissions:

~~~
sudo chown root.gpio /dev/gpiomem
sudo chmod g+rw /dev/gpiomem
~~~

**Caution**: Non-priviledged access only works since the Raspberry Pi 3 and onwards. Older platforms need to start the emonmuc framework with root permissions.  
To do so, simply change the ownership of the runscript and restart the framework:

~~~
sudo chown root -R /opt/emonmuc
sudo systemctl restart emonmuc
~~~
