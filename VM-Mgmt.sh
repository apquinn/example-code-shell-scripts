#!/usr/bin/env bash
#
export TERM=xterm


if [[ $1 == "--"* ]]; then 
	VM=$(echo $1 | sed -e "s/--//g"); Command=$2; Action=$3; Action2=$4; Action3=$5; Action4=$6; Action5=$7; Action6=$8; 
else 
	VM=""; Command=$1; Action=$2; Action2=$3; Action3=$4; Action4=$5; Action5=$6; Action6=$7;
fi

ScriptsDir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
source "$ScriptsDir/Core_Common.sh"

Found="0"


######################################################################################################
if [ "$Command" == "" ] && [ "$VM" == "" ]; then echo "Please specify command"; exit 914; fi


if [ "$Command" == "mariadb" ]	|| [ "$Command" == "m" ] || 
   [ "$Command" == "httpd" ]	|| [ "$Command" == "h" ] || 
   [ "$Command" == "both" ]		|| [ "$Command" == "b" ]
   [ "$Command" == "fail2ban" ]	|| [ "$Command" == "smb" ] || [ "$Command" == "nmb" ]; then
	## run sudo systemctl on guest follow by requested Action
	## for example "vmf mariadb restart" would ssh to guest and run sudo systemctl restart mariadb
	## VM must be specified
	## Inputs: 1
	## Input 1 choices: start, restart or stop
	## Example: vmf mariadb restart; vmf h s;
	ServiceCommand
fi


if [ "$Command" == "dbdump" ] || [ "$Command" == "dbload" ]; then
	## For moving db data
	## Inputs: 5. Both dbdump and dbload require 5 inputs
	## Input 1: database name. Blank for all. Option for dbname dump only, blank for dbload
	## Input 2: Table name. Blank for all. Option for dump only, table; blank for dbload
	## Input 3: Folder name within your linode temp folder to dump to or load. 
	## Input 4: (TargetServer)	Host address to load data; blank for dbdump
	## Input 5: (DumpLoc) 		Location of the dump to load (ex: did a dump of f, want to load on vm)
	## Input 6: (FlushDump)		Whether or not to remove dump after load (y or n)
	## Example: vm dbdump "DrupalBusiness" "sites" "MyDump" "" "" ""; vm dbload "" "" "MyDump" "c" "f" "n"

	TargetServer="$Action4"
	if [ "$TargetServer" == "g" ]; then TargetServer="$Address_Guest"; fi

	if [ "$TargetServer" == "" ] && [ "$Command" == "dbdump" ]; then TargetServer=$Address_Franklin; fi
	if [ "$TargetServer" == "" ] && [ "$Command" == "dbload" ]; then TargetServer=$Address_Guest; fi
	if [ "$TargetServer" == "" ]; then echo "No host server specified."; exit 914; fi


	DumpLoc="$Action5"
	if [ "$DumpLoc" == "g" ]; then DumpLoc="$Address_Guest"; fi
	if [ "$DumpLoc" == "" ] && [ "$Command" == "dbload" ]; then DumpLoc=$Address_Guest; fi
	if [ "$Command" == "dbload" ] && [ "$DumpLoc" == "" ]; then echo "No host server specified for dump location."; exit 914; fi

	FlushDump="$Action6"
	if [ "$FlushDump" != "y" ]; then FlushDump="n"; fi

	DBService "$Action" "$Action2" "$Action3" "$TargetServer" "$DumpLoc" "$FlushDump"
fi


if [ "$Command" == "dbdiff" ]; then
	## Compares guest server databases to franklin
	## VM must be specified
	## Inputs: 0
	## Example: vmf dbdiff
	DBDiff
fi


if [ "$Command" == "tail" ]; then
	## Tails error log
	## VM must be specified
	## Inputs: 1
	## Input 1: hostname. Optional. Guest by default
	## Example: vmf tail, vm tail c
	TailIt
fi


if [ "$Command" == "g" ] || [ "$Command" == "" ]; then
	if [ "$Command" == "" ] && [ "$VM" == "" ]; then echo "must provide a host when using generic vm command"; exit 914; fi
	if [ "$Command" == "" ]; then Command="g"; fi
	## Opens a new tab, sshs to server, colors for specific server
	## Inputs: 0
	## Example: vm f
	SSHHelper
fi


if [ "$Command" == "cache" ] || [ "$Command" == "ccache" ] || [ "$Command" == "autocr" ]; then
	## All three work against guest host only
	## VM must be specified

	## cache turns caching on or off. 
	## Inputs: 1
	## Input 1: on or off
	## Example: vmf cache on
	
	## ccache clears host drupal cache
	## Inputs 0
	## Example: vmf ccache
	
	## autocr rebuilds cache when directory change occurs
	## Inputs 1-2
	## Input 1: blank to start, "stop" to stop monitoring
	## Input 2: Only valid on start. Name of module directory to monitor
	## Example: vmf autocr nmu_webadmin

	DrupalCacheMgmt
fi


if [ "$Command" == "build" ]; then
	## Builds a new vm
	## Inputs: 1
	## Input 1: Build type: basic or nmu. Default is nmu
	## Example: vm build
	VMBuilder
fi


## These functions are used for vm build only. Do not use them.
if [ "$Command" == "rkh" ]; then RemoveKnownHost; fi
if [ "$Command" == "setkey" ]; then ConfirmAndSetKey "$Action" "$Action2"; fi
if [ "$Command" == "setprofile" ]; then SetupUserWorkstationProfile; fi
if [ "$Command" == "site" ]; then SiteBuilder $Action; fi
if [ "$Command" == "sync" ]; then SyncScripts $Action $Action2; fi
if [ "$Command" == "syncall" ]; then SyncAll; fi



## Specialty Functions. Ask Drew if interested
if [ "$Command" == "mount" ]; then 
	LoadUserVMPreferences
	AddMounts
fi


if [ "$Found" == "0" ]; then echo "Command $Command not found."; fi


