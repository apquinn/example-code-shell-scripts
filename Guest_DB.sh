export TERM=xterm
ScriptsDir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
source "$ScriptsDir/Core_Common.sh"

if [ -f "$BaseDir_Remote/temp/error.log" ]; then rm "$BaseDir_Remote/temp/error.log"; fi
DatabaseListFileName="DatabaseList.txt"

# $1: Command choices: dbdump, dbload, dbdup
# $2: Option for dump only, dbname; blank for others
# $3: Option for dump only, table; blank for others
# $4: Optional, Dump folder name


Command=$1
DB=$2
Table=$3
DumpLocation=$TempDumpDir/$4
Host_Address="localhost"

if [ "$4" == "" ]; then DumpLocation=$DumpLocation"StdDumpLoc"; fi
if [ "$Command" == "dbload" ]; then DB=""; Table=""; fi

GetDiskSpace $TempDumpDir
if [ $DiskSpace -gt 70 ]; then echo "not enough disk space in: $TempDumpDir"; exit 914; fi


MYSQL_PARM=" --user=$DBUser --password=$DBPass --host=$Host_Address"

if [ "$Command" == "dbdump" ]; then
    if [ -d $DumpLocation ]; then rm -rf $DumpLocation; fi; 
    mkdir -p $DumpLocation $DumpLocation/TableLists $DumpLocation/TableContents $DumpLocation/DBScripts;  
    chmod -R 777 "$DumpLocation";


	DBRestrictions='"PERFORMANCE_SCHEMA","information_schema","mysql","pureftpd","#mysql560#lost+found","www_tracking","www_images","www_images2"'

    # GET A LIST OF DATABASES TO DUMP
    MYSQL_OPT="--lock-tables=false --quick --add-locks "
    if [ "$DB" != "" ]; then 
    	DBSpecification="AND SCHEMA_NAME LIKE '"$DB"'"; 
    else 
		DBSpecification=""; 
	fi

    /usr/bin/mysql $MYSQL_PARM -N -B -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME NOT IN ($DBRestrictions) $DBSpecification ORDER BY SCHEMA_NAME;" > $DumpLocation/$DatabaseListFileName  
    SyncDBError "$?" "$DumpLocation" "Issue dumping list of DBs to sync: $DatabaseListFileName" "" "" "" ""; 
    if [ ! -s "$DumpLocation/$DatabaseListFileName" ]; then echo "No database names found."; exit 20; fi

    # GET A LIST OF TABLE FROM EACH DATABASE TO DUMP
    TableRestrictions=' AND TABLE_NAME != "cms_sports_play_for" '
    if [ "$Table" != "" ]; then TableRestrictions="$TableRestrictions AND SCHEMA_NAME LIKE '"$Table"'"; fi

    cat $DumpLocation/$DatabaseListFileName | while read dbname
	do
		/usr/bin/mysql $MYSQL_PARM -N -B -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=\"$dbname\" $TableRestrictions ORDER BY TABLE_SCHEMA, TABLE_NAME;" >> $DumpLocation/TableLists/$dbname 2>&1; 
		SyncDBError "$?" "$DumpLocation" "Issue dumping list of tables to sync: $dbname" "" "" "" ""; 
	done


    # DUMP EACH TABLE
    cat $DumpLocation/$DatabaseListFileName | while read dbname; 
    do
        echo -e "DROP DATABASE IF EXISTS $dbname; \nCREATE DATABASE $dbname DEFAULT CHARACTER SET utf8;" > "$DumpLocation/DBScripts/$dbname.sql"
    
        cat $DumpLocation/TableLists/$dbname | while read tablename; 
        do
            echo -ne "--- Dumping $(hostname):\\t$dbname.$tablename                                         \\r"
            {
                echo -e "USE $dbname; \nSET FOREIGN_KEY_CHECKS=0; \n\n" > "$DumpLocation/TableContents/$dbname-$tablename.sql"
                /usr/bin/mysqldump $MYSQL_PARM $MYSQL_OPT $dbname $tablename >> "$DumpLocation/TableContents/$dbname-$tablename.sql"
                SyncDBError "$?" "$DumpLocation" "Issue dumping: $dbname" "" "" ""
                echo -e "SET FOREIGN_KEY_CHECKS=1; \n\n" >> "$DumpLocation/TableContents/$dbname-$tablename.sql"
            } &> $VM_Log;
            CheckErrors
        done
    done
    if [ "$ErrorFound" == "true" ]; then echo -ne "--- Dumping server:\\tan error occured, see $DumpLocation/error.log \\r"; echo; exit; else echo -ne "--- Dumping $(hostname):\\tdone                                              "; echo; fi
	chmod -R 777 $DumpLocation/
fi


if [ "$Command" == "dbload" ]; then 
	# BEGIN PROCESSING PUSH

	if [ -f "$DumpLocation/$DatabaseListFileName" ]; then
		cat $DumpLocation/$DatabaseListFileName | while read dbname; do
			{ /usr/bin/mysql $MYSQL_PARM < "$DumpLocation/DBScripts/$dbname.sql"; } &> $VM_Log; CheckErrors;

			cat $DumpLocation/TableLists/$dbname | while read tablename; do
				echo -ne "--- Loading $(hostname):\\t$dbname.$tablename                                                                            \\r"

				{
					/usr/bin/mysql --max_allowed_packet=999M $MYSQL_PARM < "$DumpLocation/TableContents/$dbname-$tablename.sql"
					SyncDBError "$?" "$DumpLocation" "Loading: $dbname" "" ""
				} &> $VM_Log;
				CheckErrors
			done

			if [ "$ErrorFound" == "true" ]; then exit; fi;
		done
	fi
	echo -ne "--- Loading db\\t\\tdone                                                                            "; echo;
fi



