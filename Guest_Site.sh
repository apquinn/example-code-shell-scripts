export TERM=xterm
ScriptsDir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
source "$ScriptsDir/Core_Common.sh"


if [ "$1" == "GetName" ]; then
	DatabaseResults="$ScriptsDir/../../temp/AvailableSites.txt"
	ConversionType="beef"

	### CONVERSION TYPE
	while [ "$ConversionType" != 'initial' ] && [ "$ConversionType" != 'final' ] && [ "$ConversionType" != 'fix' ] && [ "$ConversionType" != 'uuid' ]; do
	   	echo -n "What kind of conversion? [initial/final/uuid/fix] "; read ConversionType;
	done


	if [ "$ConversionType" == 'initial' ] || [ "$ConversionType" == 'final' ]; then 
		if [ "$ConversionType" == 'initial' ]; then FirstMsg="copy"; else FirstMsg="finalize"; fi

		### ORIGINAL SITE NAME
		Vars_GetVar "Sites_LastSynced"; 
		SecondMsg="Which site would you like to $FirstMsg"
		if [ "$Sites_LastSynced" != "" ]; then echo -n "$SecondMsg (blank for $Sites_LastSynced)? "; else echo -n "$SecondMsg? "; fi
		read OldSiteName;

		if [ "$OldSiteName" == "" ] && [ "$Sites_LastSynced" == "" ]; then echo "Site to $FirstMsg is required"; echo; exit 14; fi
		if [ "$OldSiteName" == "" ]; then OldSiteName="$Sites_LastSynced"; fi
		Vars_SaveVar "Sites_LastSynced" $OldSiteName

	
		### GET NEW SITE AND DB NAMES
		NewSiteName=$(echo ${OldSiteName,,} | sed -e "s/drupal//g")
		NewSiteNameDatabase=$OldSiteName


		### VARIFY SITE EXISTS
		MYSQL_PARM=" --user=$DBUser --password=$DBPass"
		if [ "$ConversionType" == 'initial' ]; then MYSQL_PARM="$MYSQL_PARM --host=$Address_Charlie"; else MYSQL_PARM="$MYSQL_PARM --host=$Address_Franklin"; fi

		/usr/bin/mysql $MYSQL_PARM -N -B -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=\"$OldSiteName\";" > $DatabaseResults 2>&1;
		Found=$(wc -l $DatabaseResults | awk '{print $1}')
		if [ $Found != 1 ]; then echo "No site named $OldSiteName found."; echo; exit 14; fi

		if [ "$ConversionType" == 'initial' ]; then
			MYSQL_PARM=" --user=$DBUser --password=$DBPass --host=localhost"
			/usr/bin/mysql $MYSQL_PARM -N -B -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=\"$NewSiteNameDatabase\";" > $DatabaseResults 2>&1;
			Found=$(wc -l $DatabaseResults | awk '{print $1}')
			if [ $Found != 0 ]; then
				echo -n "$NewSiteName already exists. Overwrite? (Y/n) "; read Overwrite;
				if [ "$Overwrite" == "n" ]; then echo "Quitting"; echo; exit 14; fi
			fi
		fi


		if [ "$ConversionType" == 'initial' ]; then 
			echo ""
			echo "---New site name: $NewSiteName";
			echo "---New database name: $NewSiteNameDatabase";
			echo "---Conversion type: $ConversionType";
		else
			echo ""
			echo "---Finalize: $NewSiteName";
		fi

		Vars_SaveVar "OldSiteName" $OldSiteName
		Vars_SaveVar "NewSiteName" $NewSiteName
		Vars_SaveVar "NewSiteNameDatabase" $NewSiteNameDatabase
		Vars_SaveVar "ConversionType" $ConversionType
	else
		echo ""
		echo "---Running Job: $ConversionType";

		Vars_SaveVar "OldSiteName" "--"
		Vars_SaveVar "NewSiteName" "--"
		Vars_SaveVar "NewSiteNameDatabase" "--"
		Vars_SaveVar "ConversionType" $ConversionType
	fi
fi


if [ "$1" == "ProcessSiteStep1" ]; then
	Vars_GetVar "OldSiteName"
	Vars_GetVar "NewSiteName"
	Vars_GetVar "NewSiteNameDatabase"
	Vars_GetVar "ConversionType"

	if [ "$OldSiteName" != "" ] && [ "$NewSiteName" != "" ] || [ "$NewSiteNameDatabase" != "" ] || [ "$ConversionType" != "" ]; then
		echo "";
		/usr/local/bin/php $ScriptsDir/Guest_Site.php $OldSiteName $NewSiteName $NewSiteNameDatabase $ConversionType
	fi
fi
