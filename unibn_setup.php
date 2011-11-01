#!/usr/bin/php
#!/bin/bash
# vim: ft=sh

###############################################################################
#                       License (Modified BSD License)                        #
###############################################################################
#
# Copyright (c) 2011 Martin Ueding <dev@martin-ueding.de>
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# * Redistributions of source code must retain the above copyright notice, this
#   list of conditions and the following disclaimer.
#
# * Redistributions in binary form must reproduce the above copyright notice,
#   this list of conditions and the following disclaimer in the documentation
#   and/or other materials provided with the distribution.
#
# * Neither the name of Martin Ueding nor the names of its contributors may be
#   used to endorse or promote products derived from this software without
#   specific prior written permission.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
# AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
# ARE DISCLAIMED. IN NO EVENT SHALL MARTIN UEDING BE LIABLE FOR ANY DIRECT,
# INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
# (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
# ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
# SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


###############################################################################
#                                 Description                                 #
###############################################################################
#
# Several packages needed in the "EDV für Physiker" class at Bonn University
# are installed with this script. The ROOT statistics package does not have a
# Debian package and is installed manually instead.
#
# To ease the use of the "bonnet" wireless network, a script for the
# NetworkManager dispatcher deamon is installed which automatically connects
# the user to the VPN of the university. For that, the university user account
# is needed and polled in the script.
#
# This script is intended to run on Ubuntu and Fedora, but it should work on
# every Debian or Red Hat derivative where the user can run commands with
# `sudo`. Ubuntu and Fedora have this as a default, Debian uses a separate root
# account instead of sudo by default.
#
#
# In order to run, this script needs:
# * apt-get or yum
# * awk
# * base64
# * bash
# * cat
# * chmod
# * chown
# * grep
# * md5sum
# * sudo
# * tar
# * tee
# * wget (at least on non-Fedora)
# * which


###############################################################################
#                                  Changelog                                  #
###############################################################################
#
<?php readfile('CHANGELOG'); ?>


###############################################################################
#                               Global Settings                               #
###############################################################################

set -e
set -u

version=3.0

vpn_dispatcher_file=/etc/NetworkManager/dispatcher.d/99bonnet
vpn_config_file="$HOME/.vpnc/unibn-wlan.conf"
vpn_restarter_file="/sbin/vpnc-restarter"

TEXTDOMAIN=unibn_setup


###############################################################################
#                         Handle --version and --help.                        #
###############################################################################

case "${1:-}" in
	--version)
		echo "$version"
		exit 0
		;;

	--help)
		echo $"This script installs software needed for the EDV (computer science) class."
		exit 0
		;;
esac


###############################################################################
#                         Install localisation files.                         #
###############################################################################

cat << EOF | base64 -d | sudo tee "/usr/share/locale/de/LC_MESSAGES/unibn_setup.mo" > /dev/null
<?php readfile('de.mo.asc'); ?>
EOF


###############################################################################
#                   Print welcome message and instructions.                   #
###############################################################################

echo '+-----------------------------------------------------------------------------+'
echo '|              Installation von Software für die EDV Vorlesung                |'
echo '|                       (emacs, LaTeX, ROOT, gnuplot)                         |'
echo '|                                                                             |'
echo '|               Einrichtung des VPN für das Netzwerk "bonnet"                 |'
echo '+-----------------------------------------------------------------------------+'
echo
echo $"To abort at any time, press Ctrl-C."
echo
echo $"To revert anything this script did, use these commands:"
echo
echo $"Removal of the software installed by package management:"

if which apt-get > /dev/null 2>&1
then
	echo '    sudo apt-get -y remove emacs texlive-latex-base gnuplot'
	echo '    sudo apt-get -y autoremove'
elif which yum > /dev/null 2>&1
then
	echo '    sudo yum -y erase emacs texlive-latex gnuplot'
fi

echo
echo $"Removal of VPN access:"
echo "    sudo rm -f $vpn_config_file"
echo "    sudo rm -f $vpn_dispatcher_file"

if which apt-get > /dev/null 2>&1
then
	echo '    sudo apt-get -y remove vpnc'
elif which yum > /dev/null 2>&1
then
	echo '    sudo yum -y erase vpnc'
fi

echo
echo $"Removal of ROOT:"

if which apt-get > /dev/null 2>&1
then
	echo '    sudo rm -rf /opt/root'
elif which yum > /dev/null 2>&1
then
	echo '    sudo yum -y erase root'
fi

echo


###############################################################################
#                         Ask for installation scope.                         #
###############################################################################


read -r -p $"-> Set up VPN? [Y/n] " answer

if [[ "$answer" = [yYjJ] || -z "$answer" ]]
then
	install_vpn=true
else
	install_vpn=false
fi

echo


read -r -p $"-> Install ROOT statistics? [Y/n] " answer

if [[ "$answer" = [yYjJ] || -z "$answer" ]]
then
	install_root=true
else
	install_root=false
fi

read -r -p $"-> Install other software? [Y/n] " answer

if [[ "$answer" = [yYjJ] || -z "$answer" ]]
then
	install_other=true
else
	install_other=false
fi

echo


###############################################################################
#                        Install packages via apt-get.                        #
###############################################################################

packages_debian=( )
packages_fedora=( )

if [[ "$install_other" == "true" ]]
then
	packages_debian=( emacs texlive-latex-base gnuplot )
	packages_fedora=( emacs texlive-latex gnuplot )
fi

if [[ "$install_vpn" == "true" ]]
then
	packages_debian=( vpnc ${packages_debian[@]:-} )
	packages_fedora=( vpnc ${packages_fedora[@]:-} )
fi

# Fedora has a ROOT package, so this is used instead of the self-made install
# from the ROOT website.
if [[ "$install_root" == "true" ]]
then
	packages_fedora=( root ${packages_fedora[@]:-} )
fi

echo
echo "Installing ${packages_debian[@]} …"

# Install the packages with whatever package manager can be found.
if which apt-get > /dev/null 2>&1
then
	sudo apt-get -y install ${packages_debian[@]}
elif which yum > /dev/null 2>&1
then
	sudo yum -y install ${packages_fedora[@]}
fi

echo


###############################################################################
#                          Create VPN login script.                           #
###############################################################################

if [[ "$install_vpn" == "true" ]]
then
	cat << EOF | sudo tee "$vpn_dispatcher_file" > /dev/null
<?php
$s = file_get_contents('99bonnet');
$s = str_replace('$', '\$', $s);
echo $s
?>
EOF

	sudo chmod 755 "$vpn_dispatcher_file"
	sudo chown root:root "$vpn_dispatcher_file"

	cat << EOF | sudo tee "$vpn_restarter_file" > /dev/null
<?php
$s = file_get_contents('vpnc-restarter');
$s = str_replace('$', '\$', $s);
echo $s
?>
EOF
	sudo chmod 755 "$vpn_restarter_file"
	sudo chown root:root "$vpn_restarter_file"

###############################################################################
#                    Ask for Bonn university user account.                    #
#                        Create config file for vpnc.                         #
###############################################################################

	answer=yes

	if [[ -f "$vpn_config_file" ]]
	then
		echo $"There is a VPN config file."
		echo -n $"The user is: "
		grep username "$vpn_config_file" | awk '{print $3}'

		read -r -p $"-> Recreate file? [y/N] " answer
	fi

	if [[ "$answer" = [yY] ]]
	then
		echo $"Please enter your Uni Bonn credentials:"
		echo $"Your password will not be shown during typing."

		user=
		password=

		# Poll the user for a user name until he enters one.
		while [[ -z "$user" ]]
		do
			read -r -p $"-> Uni Bonn username: " user
		done

		# Poll the user for a password until he enters one.
		while [[ -z "$password" ]]
		do
			read -r -s -p $"-> Uni Bonn password: " password
		done

		mkdir -p "$(dirname "$vpn_config_file")"

		cat > "$vpn_config_file" << EOF
IPSec gateway 131.220.224.201
IPSec ID unibn-wlan
IPSec secret Ne\$e!
Xauth username $user
Xauth password $password
EOF

		sudo chown root:root "$vpn_config_file"
	fi

	echo
fi


###############################################################################
#                      Install ROOT statistics package.                       #
###############################################################################

# Do not perform this installation on a Fedora machine since there is a RPM for
# ROOT.
if [[ "$install_root" == "true" ]] && ! which yum > /dev/null 2>&1
then
	if [[ -d /opt/root ]]
	then
		echo $"It seems that ROOT is installed."
		read -r -p $"-> Reinstall ROOT? [y/N] " answer

		if [[ "$answer" = [nN] || -z "$answer" ]]
		then
			install_root=false
		fi
	fi

	if [[ "$install_root" == "true" ]]
	then
		root_file=root_v5.30.02.Linux-slc5-gcc4.3.tar.gz

		# Test whether there is any need to download the root package. The
		# checksum is needed to detect an aborted download.
		if [[ ! -f "$root_file" || ! $(md5sum "$root_file" | awk '{print $1}') == 53b311f490e7673e19c493ccb7216748 ]]
		then
			# Delete the old download so that wget does not try to preserve the
			# broken download.
			rm -f "$root_file"
			wget ftp://root.cern.ch/root/$root_file
		fi

		tar -xzf "$root_file"
		rm -f "$root_file"
		sudo rm -rf /opt/root
		sudo mv root /opt/
		sudo chown -R root:root /opt/root
	fi

	# Check whether the environment setting script of the ROOT package is
	# already included in the .bashrc of the user. This assumes that the user
	# uses bash, but someone who does this on a fresh Ubuntu installation will
	# certainly do so.
	profile_file="/etc/bash.bashrc"
	if ! fgrep '/opt/root/bin/thisroot.sh' "$profile_file" > /dev/null 2>&1
	then
		if [[ -f "$profile_file" ]]
		then
			sudo cp "$profile_file" "$profile_file.bak"
		fi
		cat << EOF | sudo tee "$profile_file"  > /dev/null

# Setup ROOT environment.
if [[ -f /opt/root/bin/thisroot.sh ]]
then
	. /opt/root/bin/thisroot.sh
fi
EOF

	fi

	echo
fi
