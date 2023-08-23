#!/usr/bin/php
<?php

require_once "/htdocs/cmsphp/Includes/AccountPwds.php";
require_once "/Linode/scripts/ShellScripts/Core_Common.php";

function NMU_AutoLoad($strClassName)
{
	if(preg_match("/^[A-Za-z_]+$/", $strClassName)) {
		require_once "/htdocs/cmsphp/Includes/Classes/NMU_$strClassName.class.php";
	}
}

spl_autoload_register('NMU_AutoLoad');


$GLOBALS['Address_Guest'] = $argv[1];
$GLOBALS['DBUser'] = $argv[2];
$GLOBALS['DBPass'] = $argv[3];

function Main($argv)
{
	try
	{
		$strLive = "www.nmu.edu";
		$strQuery = "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'www_%' || SCHEMA_NAME='BackupDrupal' ORDER BY SCHEMA_NAME";
 
		$classM1SqlQuery = new SqlDataQueries();
		$classM1SqlQuery->SpecifyDB($strLive, '', $GLOBALS['DBUser'], $GLOBALS['DBPass']);
		$aResults1 = $classM1SqlQuery->MySQL_Queries($strQuery);
		$aDBs1 = [];
		foreach($aResults1 as $aRow)
			$aDBs1[] = $aRow['SCHEMA_NAME'];


		$classM2SqlQuery = new SqlDataQueries();
		$classM2SqlQuery->SpecifyDB($GLOBALS['Address_Guest'], '', "BackupAccount", "SecretBackupCode");
		$aResults2 = $classM2SqlQuery->MySQL_Queries($strQuery);
		$aDBs2 = [];
		foreach($aResults2 as $aRow)
			$aDBs2[] = $aRow['SCHEMA_NAME'];


		$aDiffs = [];
		$aTableDiffs1 = [];
		foreach($aDBs1 as $strDB)
		{
			if(!in_array($strDB, $aDBs2))
				$aDiffs[$strLive][$strDB]['Msg'] = $strDB." exists only on ".$strLive;
			else
				$aDiffs = CompareTables($aDiffs, $classM1SqlQuery, $classM2SqlQuery, $strLive, $strDB);
		}

		foreach($aDBs2 as $strDB)
		{
			if(!in_array($strDB, $aDBs1))
				$aDiffs[$GLOBALS['Address_Guest']][$strDB]['Msg'] = $strDB." exists only on ".$GLOBALS['Address_Guest'];
			else
				$aDiffs = CompareTables($aDiffs, $classM2SqlQuery, $classM1SqlQuery, $GLOBALS['Address_Guest'], $strDB);
		}

		PrintResults($aDiffs, $strLive);
		PrintResults($aDiffs, $GLOBALS['Address_Guest']);
	}
	catch (Exception $ex)
	{
		HandleError($ex->getMessage());
	}
}


function PrintResults($aDiffs, $strServer)
{
	foreach($aDiffs[$strServer] as $strDBName=>$aJunk)
	{
		if(isset($aDiffs[$strServer][$strDBName]['Msg']))
			print $aDiffs[$strServer][$strDBName]['Msg']."\n\n";
		else
		{
			foreach($aDiffs[$strServer][$strDBName] as $strTableName=>$aJunk)
			{
				$aOutput = [];
				if(isset($aDiffs[$strServer][$strDBName][$strTableName]['Msg']))
					$aOutput[$strTableName] = $aDiffs[$strServer][$strDBName][$strTableName]['Msg'];
				else
				{
					foreach($aDiffs[$strServer][$strDBName][$strTableName] as $strColName=>$aJunk)
					{
						if(isset($aDiffs[$strServer][$strDBName][$strTableName][$strColName]['Msg']))
							$aOutput[$strTableName.", ".$strColName] = $aDiffs[$strServer][$strDBName][$strTableName][$strColName]['Msg'];
						else
						{
							foreach($aDiffs[$strServer][$strDBName][$strTableName][$strColName] as $strColAttrName=>$aJunk)
							{
								if(isset($aDiffs[$strServer][$strDBName][$strTableName][$strColName][$strColAttrName]['Msg']))
									$aOutput[$strTableName.", ".$strColName.", ".$strColAttrName] = $aDiffs[$strServer][$strDBName][$strTableName][$strColName][$strColAttrName]['Msg'];
							}				
						}
					}				
				}


				if(count($aOutput) > 0)
				{
					$strCurrentHost = $strServer;
					if(isset($strLastHost) && $strLastHost != "" && $strCurrentHost != $strLastHost)
						print "\n".$strCurrentHost."\n";
					if(!isset($strLastHost) || $strCurrentHost != $strLastHost)
						print $strCurrentHost."\n";
					$strLastHost = $strCurrentHost;

					$strCurrentDB = $strDBName;
					if(!isset($strLastDB) || $strCurrentDB != $strLastDB)
						print "  ".$strCurrentDB."\n";
					$strLastDB = $strCurrentDB;
					
					foreach($aOutput as $strMsgTitle=>$strMsgValue)
					{
						$aParts = ['','','',''];
						if(strstr($strMsgTitle, ", "))
						{
							$aParts = explode(", ", $strMsgTitle);
							if($aParts[0] != "")
								$strCurTable = $aParts[0];
							if($aParts[1] != "")
								$strCurCol = $aParts[1];
							if($aParts[2] != "")
								$strCurColAttr = $aParts[2];
						}
						else
						{
							$strCurTable = $strMsgTitle;
							$strCurCol = "";
							$strCurColAttr = "";
						}


						if($strCurTable != "" && (!isset($strLastTable) || $strCurTable != $strLastTable) && $aParts[1] == "")
							print "    ".$strCurTable." - ".$strMsgValue."\n";
						elseif(strCurTable != "" && (!isset($strLastTable) || $strCurTable != $strLastTable))
							print "    ".$strCurTable."\n";

						if($strCurCol != "" && (!isset($strLastCol) || $strCurCol != $strLastCol) && $aParts[2] == "")
							print "      ".$strCurCol." - ".$strMsgValue."\n";
						elseif($strCurCol != "" && (!isset($strLastCol) || $strCurCol != $strLastCol))
						{
							print "      ".$strCurCol."\n";
							print "        ".$strCurColAttr." - ".$strMsgValue."\n";
						}

						$strLastColAttr = $strCurColAttr;
						$strLastCol = $strCurCol;
						$strLastTable = $strCurTable;
					}
				}
			}
		}
	}
	print"\n";
}


function CompareTables($aDiffs, $classSqlQuery1, $classSqlQuery2, $strHost, $strDBName)
{
	try
	{
		$Output = array();
		$strQuery = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='".$strDBName."' ORDER BY TABLE_NAME";
		$aTableResults1 = $classSqlQuery1->MySQL_Queries($strQuery);
		$aTableResults2 = $classSqlQuery2->MySQL_Queries($strQuery);

		$aTableNames2 = [];
		foreach($aTableResults2 as $aTempRow)
			$aTableNames2[] = $aTempRow['TABLE_NAME'];

		foreach($aTableResults1 as $aRow)
		{
			if(!in_array($aRow['TABLE_NAME'], $aTableNames2))
				$aDiffs[$strHost][$strDBName][$aRow['TABLE_NAME']]['Msg'] = $aRow['TABLE_NAME']." exists only on ".$strHost;
			else
				$aDiffs = CompareTablesCols($aDiffs, $classSqlQuery1, $classSqlQuery2, $strHost, $strDBName, $aRow['TABLE_NAME']);
		}

		return $aDiffs;
	}
	catch (Exception $ex)
	{
		HandleError($ex->getMessage());
	}
}


function CompareTablesCols($aDiffs, $classSqlQuery1, $classSqlQuery2, $strHost, $strDBName, $strTable)
{
	try
	{
		$strQuery = "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".$strDBName."' AND TABLE_NAME='".$strTable."' ORDER BY COLUMN_NAME";
		$aColResults1 = $classSqlQuery1->MySQL_Queries($strQuery);
		$aColResults2 = $classSqlQuery2->MySQL_Queries($strQuery);

		$aColNames2 = [];
		foreach($aColResults2 as $aTempRow)
			$aColNames2[] = $aTempRow['COLUMN_NAME'];

		foreach($aColResults1 as $aRow)
		{
			if(!in_array($aRow['COLUMN_NAME'], $aColNames2))
				$aDiffs[$strHost][$strDBName][$strTable][$aRow['COLUMN_NAME']]['Msg'] = $aRow['COLUMN_NAME']." exists only on ".$strHost;
			else
				$aDiffs = CompareTablesColsAttr($aDiffs, $classSqlQuery1, $classSqlQuery2, $strHost, $strDBName, $strTable, $aRow['COLUMN_NAME']);
		}

		return $aDiffs;
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function CompareTablesColsAttr($aDiffs, $classSqlQuery1, $classSqlQuery2, $strHost, $strDBName, $strTable, $strCol)
{
	try
	{
		$strQuery = "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".$strDBName."' AND TABLE_NAME='".$strTable."' AND COLUMN_NAME='".$strCol."' ORDER BY COLUMN_NAME";
		$aColAttrResults1 = $classSqlQuery1->MySQL_Queries($strQuery);
		$aColAttrResults2 = $classSqlQuery2->MySQL_Queries($strQuery);

		foreach($aColAttrResults2[0] as $strColAttrName=>$strColAttrValue)
			if($strColAttrValue != $aColAttrResults1[0][$strColAttrName] && $strColAttrName!="ORDINAL_POSITION")
				$aDiffs[$strHost][$strDBName][$strTable][$strCol][$strColAttrName]['Msg'] = "Attribute is ".$strColAttrValue." on ".$strHost." and ".$aColAttrResults1[0][$strColAttrName];

		return $aDiffs;
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


Main($argv);

?>