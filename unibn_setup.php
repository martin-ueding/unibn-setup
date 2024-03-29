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
# In order to run, this script needs:
#
# * apt-get or yum
# * awk
# * bash
# * cat
# * chmod
# * chown
# * grep
# * md5sum
# * sudo
# * tar
# * wget (at least on non-Fedora)
# * which

###############################################################################
#                               Global Settings                               #
###############################################################################

set -e
set -u

version=2.1

vpn_config_file=/etc/unibn-wlan.conf
vpn_dispatcher_file=/etc/NetworkManager/dispatcher.d/99bonnet

###############################################################################
#                         Handle --version and --help.                        #
###############################################################################

case "${1:-}" in
	--version)
		echo "$version"
		exit 0
		;;

	--help)
		echo "This script installs software needed for the EDV (computer science) class."
		exit 0
		;;
esac

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
echo 'I: Falls Sie die Installation aus irgendeinem Grund abbrechen möchten, drücken'
echo 'I: Sie Strg-C.'
echo
echo 'I: Um die Aktionen dieses Skripts rückgängig zu machen, führen Sie folgende'
echo 'I: Befehle aus:'
echo
echo 'I: Entfernen der per Paketmanagement installierten Software:'

if which apt-get > /dev/null 2>&1
then
	echo 'I:     sudo apt-get -y remove emacs texlive-latex-base gnuplot'
	echo 'I:     sudo apt-get -y autoremove'
elif which yum > /dev/null 2>&1
then
	echo 'I:     sudo yum -y erase emacs texlive-latex gnuplot'
fi

echo
echo 'I: Entfernen des VPN Zugangs:'
echo "I:     sudo rm -f $vpn_config_file"
echo "I:     sudo rm -f $vpn_dispatcher_file"

if which apt-get > /dev/null 2>&1
then
	echo 'I:     sudo apt-get -y remove vpnc'
elif which yum > /dev/null 2>&1
then
	echo 'I:     sudo yum -y erase vpnc'
fi

echo
echo 'I: Entfernen von ROOT:'

if which apt-get > /dev/null 2>&1
then
	echo 'I:     sudo rm -rf /opt/root'
elif which yum > /dev/null 2>&1
then
	echo 'I:     sudo yum -y erase root'
fi

echo

###############################################################################
#                         Ask for installation scope.                         #
###############################################################################

read -r -p '-> VPN Zugang einrichten? [Y/n] ' answer

if [[ "$answer" = [yYjJ] || -z "$answer" ]]
then
	install_vpn=true
	echo 'I: VPN wird eingerichtet.'
else
	install_vpn=false
	echo 'I: VPN *nicht* wird eingerichtet.'
fi

echo

read -r -p '-> ROOT Statistik installieren? [Y/n] ' answer

if [[ "$answer" = [yYjJ] || -z "$answer" ]]
then
	install_root=true
	echo 'I: ROOT wird installiert.'
else
	install_root=false
	echo 'I: ROOT *nicht* wird installiert.'
fi

echo

read -r -p '-> gnuplot installieren? [Y/n] ' answer

if [[ "$answer" = [yYjJ] || -z "$answer" ]]
then
	install_gnuplot=true
	echo 'I: gnuplot wird eingerichtet.'
else
	install_gnuplot=false
	echo 'I: gnuplot *nicht* wird eingerichtet.'
fi

echo

read -r -p '-> LaTeX installieren? [Y/n] ' answer

if [[ "$answer" = [yYjJ] || -z "$answer" ]]
then
	install_latex=true
	echo 'I: LaTeX wird eingerichtet.'
else
	install_latex=false
	echo 'I: LaTeX *nicht* wird eingerichtet.'
fi

echo

read -r -p '-> Emacs installieren? [Y/n] ' answer

if [[ "$answer" = [yYjJ] || -z "$answer" ]]
then
	install_emacs=true
	echo 'I: Emacs wird eingerichtet.'
else
	install_emacs=false
	echo 'I: Emacs *nicht* wird eingerichtet.'
fi

echo

###############################################################################
#                        Install packages via apt-get.                        #
###############################################################################

echo 'I: Dieses Skript wird gleich nach dem [sudo] Passwort fragen.'
echo 'I: Dies ist das Passwort für Ihren Benutzeraccount auf diesem Rechner.'

packages_debian=( "" )
packages_fedora=( "" )

if [[ "$install_latex" == "true" ]]
then
	packages_debian=( texlive-latex-base ${packages_debian[@]} )
	packages_fedora=( texlive-latex ${packages_fedora[@]} )
fi

if [[ "$install_gnuplot" == "true" ]]
then
	packages_debian=( gnuplot ${packages_debian[@]} )
	packages_fedora=( gnuplot ${packages_fedora[@]} )
fi

if [[ "$install_emacs" == "true" ]]
then
	packages_debian=( emacs ${packages_debian[@]} )
	packages_fedora=( emacs ${packages_fedora[@]} )
fi

if [[ "$install_vpn" == "true" ]]
then
	packages_debian=( vpnc ${packages_debian[@]} )
	packages_fedora=( vpnc ${packages_fedora[@]} )
fi

# Fedora has a ROOT package, so this is used instead of the self-made install
# from the ROOT website.
if [[ "$install_root" == "true" ]]
then
	packages_fedora=( root ${packages_fedora[@]} )
fi

echo

# Install the packages with whatever package manager can be found.
if which apt-get > /dev/null 2>&1
then
	if [[ -z "${packages_debian[@]}" ]]
	then
		echo "W: Keine Pakete zur Installation ausgewählt."
	else
		echo "I: Installiere ${packages_debian[@]} je nach Auswahl."
		sudo apt-get -y install "${packages_debian[@]}"
	fi
elif which yum > /dev/null 2>&1
then
	if [[ -z "${packages_debian[@]}" ]]
	then
		echo "W: Keine Pakete zur Installation ausgewählt."
	else
		echo "I: Installiere ${packages_fedora[@]} je nach Auswahl."
		sudo yum -y install "${packages_fedora[@]}"
	fi
fi

echo

###############################################################################
#                          Create VPN login script.                           #
###############################################################################

if [[ "$install_vpn" == "true" ]]
then
	echo 'I: Erstelle Skript für automatische Einwahl ins VPN wenn im WLAN bonnet …'

	cat << EOF | sudo tee "$vpn_dispatcher_file" > /dev/null
<?php
$s = file_get_contents('99bonnet');
$s = str_replace('$', '\$', $s);
echo $s
?>
EOF

	sudo chmod 755 "$vpn_dispatcher_file"
	sudo chown root:root "$vpn_dispatcher_file"

###############################################################################
#                    Ask for Bonn university user account.                    #
#                        Create config file for vpnc.                         #
###############################################################################

	answer=yes

	if [[ -f "$vpn_config_file" ]]
	then
		echo 'I: Es existiert bereits eine vpnc Konfigurationsdatei.'
		echo -n 'I: Benutzer ist '
		grep username "$vpn_config_file" | awk '{print $3}'

		read -r -p '-> Datei neu anlegen? [y/N] ' answer
	fi

	if [[ "$answer" = [nN] || -z "$answer" ]]
	then
		echo 'I: Konfigurationsdatei wird *nicht* neu angelegt.'
	else
		echo 'I: Konfigurationsdatei wird neu angelegt.'

		echo 'I: Bitte geben Sie Ihre Uni Bonn Zugangsdaten ein:'
		echo 'I: Ihr Passwort wird beim Tippen nicht angezeigt.'

		user=
		password=

		# Poll the user for a user name until he enters one.
		while [[ -z "$user" ]]
		do
			read -r -p '-> Uni Bonn (HRZ) Benutzername: ' user

			if [[ -z "$user" ]]
			then
				echo 'E: Es wurde kein Benutzername eingegeben.'
			fi
		done

		# Poll the user for a password until he enters one.
		while [[ -z "$password" ]]
		do
			read -r -s -p '-> Uni Bonn (HRZ) Passwort: ' password
			echo

			if [[ -z "$password" ]]
			then
				echo 'E: Es wurde kein Passwort eingegeben.'
			fi
		done

		echo 'I: Erstelle Konfigurationsdatei für vpnc, den VPN client …'

		cat << EOF | sudo tee "$vpn_config_file" > /dev/null
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
		echo 'I: Es scheint, als seie ROOT schon installiert.'
		read -r -p '-> ROOT neu installieren? [y/N] ' answer

		if [[ "$answer" = [nN] || -z "$answer" ]]
		then
			echo 'I: ROOT wird *nicht* neu installiert.'
			install_root=false
		fi
	fi

	if [[ "$install_root" == "true" ]]
	then
		echo 'I: Installiere ROOT …'

		root_file=root_v5.30.02.Linux-slc5-gcc4.3.tar.gz

		# Test whether there is any need to download the root package. The checksum is
		# needed to detect an aborted download.
		if [[ -f "$root_file" && $(md5sum "$root_file" | awk '{print $1}') == 53b311f490e7673e19c493ccb7216748 ]]
		then
			echo 'I: ROOT wurde bereits heruntergeladen.'
		else
			echo 'I: Lade ROOT herunter …'
			# Delete the old download so that wget does not try to preserve the broken
			# download.
			rm -f "$root_file"
			wget ftp://root.cern.ch/root/$root_file
		fi

		echo 'I: Entpacke ROOT …'
		tar -xzf "$root_file"

		echo 'I: Lösche ROOT Download …'
		rm -f "$root_file"

		echo 'I: Lösche alte ROOT installation …'
		sudo rm -rf /opt/root

		echo 'I: Verschiebe ROOT nach /opt …'
		sudo mv root /opt/

		echo 'I: Passe Rechte der ROOT Installation an …'
		sudo chown -R root:root /opt/root

	fi

	# Check whether the environment setting script of the ROOT package is already
	# included in the .bashrc of the user. This assumes that the user uses bash,
	# but someone who does this on a fresh Ubuntu installation will certainly do
	# so.
	profile_file="/etc/bash.bashrc"
	if fgrep '/opt/root/bin/thisroot.sh' "$profile_file" > /dev/null 2>&1
	then
		echo 'I: ROOT ist bereits im Environment.'
	else
		echo 'I: Füge ROOT zum Environment hinzu …'
		if [[ -f "$profile_file" ]]
		then
			sudo cp "$profile_file" "$profile_file.bak"
			echo "I: Sicherungskopie von $profile_file in $profile_file.bak angelegt."
		fi
		cat << EOF | sudo tee "$profile_file"  > /dev/null

# Setup ROOT environment.
if [[ -f /opt/root/bin/thisroot.sh ]]
then
	. /opt/root/bin/thisroot.sh
fi
EOF

		echo 'I: ROOT wird erst funktionieren, wenn Sie ein neues Terminal öffnen.'
		echo "I: Sie können auch mit . $profile_file die Konfiguration neu laden."
	fi

	echo
fi

###############################################################################
#                                   Finish.                                   #
###############################################################################

echo 'I: Die Installation und Konfiguration ist abgeschlossen.'
