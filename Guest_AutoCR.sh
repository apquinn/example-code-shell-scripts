export TERM=xterm
ScriptsDir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
source "$ScriptsDir/Core_Common.sh"

Action=$1


if [ "$Action" == "start" ]; then 
	if [ "$2" != "" ]; then 
		ModuleDir=/$2
	else
		ModuleDir=""
	fi	

	GuestAutoCR="$BaseDir/temp/AutoCR.sh"

	Status=$(which inotifywait)
	if [ "$Status" != "/usr/bin/inotifywait" ]; then
		echo -n "--- Installing Inotify"
		{ sudo yum install -y inotify-tools; } &> $VM_Log;

		echo "		100%"
		CheckErrors
	fi


	echo '
		#!/bin/sh

		LastTime=0;
		# %e = event (comma sep)
		# %f = filename
		# %w = directory being watched
		# /htdocs/Drupal/web/modules/custom
		inotifywait -mr -e modify -e delete --format "%f %T" --timefmt "%s" /htdocs/Drupal/web/modules/custom/'$ModuleDir' |
		while read Filename EventTime
		do
				if [[ $Filename != ._* ]]; then
						if [[ $EventTime > $LastTime ]]; then
								echo "$Filename change. "
								/htdocs/Drupal/vendor/drush/drush/drush cr
								LastTime=$EventTime
						fi
				fi
		done' > $GuestAutoCR

	sudo chmod 777 $GuestAutoCR

	($GuestAutoCR)
else
	Pids=$(/usr/sbin/pidof inotifywait)
	if [ "$Pids" != "" ]; then kill -9 $Pids; fi

	Pids=$(/usr/sbin/pidof bash -c /home/aquinn/Linode/scripts/ShellScripts/Guest_AutoCR.sh)
	if [ "$Pids" != "" ]; then kill -9 $Pids; fi
fi


