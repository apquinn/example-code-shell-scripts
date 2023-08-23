ScriptsDir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
source "$ScriptsDir/Core_Common.sh"

if [[ $(ps aux | grep -i VM-Mgmt.sh) == *"linode/scripts/ShellScripts/VM-Mgmt.sh"* ]]; then exit; fi 


DestPath="/volume4/ServerBackups"; 
Franklin="archive_franklin"
Charlie="archive_charlie"
Drew="archive_drew"

if [ "$1" == "backup" ]; then
	TempFile="$TempDumpDir/filename_temp.txt"
	host=$(echo $(hostname) |  sed -e 's/.nmu.edu//g')

	if [ "$host" == "franklin" ]; then
		Server="$Franklin"
	elif [ "$host" == "charlie" ]; then
		Server="$Charlie"
	elif [ "$(hostname)" == "li1971-96.members.linode.com" ]; then
		Server="$Drew"
	fi

	BypassKnowHostWarning $Address_Synology
	GetDiskSpace $DestPath $Address_Synology
	ssh $Username@$Address_Synology "if [ -f $TempFile ]; then rm $TempFile; fi; ls -l $DestPath/$Server > filename_temp.txt"
	{ scp -o StrictHostKeyChecking=no $Username@$Address_Synology:~/filename_temp.txt $TempDumpDir; } &> $VM_Log;


	while [ $DiskSpace -gt 85 ] && [ -f $TempFile ]; do
		Line=$(head -n 1 $TempFile)
		sed -i '1d' $TempFile
		if [ $(wc -l $TempFile | awk '{ print $1}') == 0 ]; then rm $TempFile; fi
		FileName=$(echo $Line | awk '{ print $9}')
		FileName=${FileName%--*}

		if [ "$FileName" != "$LastDump" ] && [[ "$FileName" == "Dump_"* ]]; then
			ssh $Username@$Address_Synology "rm -rf $DestPath/$Server/$FileName*"
			LastDump=$FileName
		fi

		GetDiskSpace $DestPath $Address_Synology
	done
	if [ -f $TempFile ]; then rm $TempFile; fi


	{ 
		DumpDirToday=Dump_$(date "+%Y-%m-%d--%H-%M-%S")_$host
		if [ "$host" != "franklin" ] && [ "$host" != "charlie" ]; then
			$BaseDir/scripts/ShellScripts/Guest_DB.sh dbdump 'www_*' '' $DumpDirToday ''
		else
			$BaseDir/scripts/ShellScripts/Guest_DB.sh dbdump '' '' $DumpDirToday ''
		fi
		if [ -d $TempDumpDir/$DumpDirToday ]; then
			scp -r -o StrictHostKeyChecking=no $TempDumpDir/$DumpDirToday $Username@$Address_Synology:$DestPath
		fi
	} &> $VM_Log;


	if [ "$DumpDirToday" != "" ] && [ -d $TempDumpDir/$DumpDirToday ]; then
		rm -rf $TempDumpDir/$DumpDirToday
	fi
elif [ "$1" == "exec_cleanup" ]; then
	ssh $Username@$Address_Synology "/volume1/TechTeam/Linode/scripts/ShellScripts/Guest_Backup.sh cleanup"
elif [ "$1" == "cleanup" ]; then
	if [ ! -d $DestPath/$Franklin ]; then mkdir $DestPath/$Franklin; chmod 777 $DestPath/$Franklin; fi
	if [ ! -d $DestPath/$Charlie ]; then mkdir $DestPath/$Charlie; chmod 777 $DestPath/$Charlie; fi
	if [ ! -d $DestPath/$Drew ]; then mkdir $DestPath/$Drew; chmod 777 $DestPath/$Drew; fi

	ContentsFile="$BaseDir/temp/cleanup.txt"
	ls -l $DestPath > $ContentsFile
	cat $ContentsFile | while read file_entry; do
		file_name=$(echo $file_entry | awk '{print $9 }' )
		IFS='_' read -ra file_parts <<< "$file_name"
		if [ "${file_parts[0]}" == "Dump" ]; then
			if [ "${file_parts[2]}" == "franklin" ]; then
				Server="$Franklin"
			elif [ "${file_parts[2]}" == "charlie" ]; then
				Server="$Charlie"
			elif [ "${file_parts[2]}" == "drew" ]; then
				Server="$Drew"
			fi

			IFS='-' read -ra file_date <<< "${file_parts[1]}"
			Day="${file_date[0]}-${file_date[1]}-${file_date[2]}"

			if [ ! -d $DestPath/$Server/$Day ]; then mkdir $DestPath/$Server/$Day; chmod 777 $DestPath/$Server/$Day; fi
			{ tar -czvf $DestPath/$Server/$Day/$file_name.tar.gz $DestPath/$file_name; } &> $VM_Log;

			rm -rf $DestPath/$file_name
		fi
	done

	chmod -R 777 $DestPath;
fi


