<?php

$strSourceDir = dirname(__FILE__); 

require_once $strSourceDir."/Core_Common.php";


function MainSiteMgmt($strOldSiteName, $strNewSiteName, $strNewSiteNameDB, $strConversionType, $strSourceDir)
{
	try
	{
		### Full
		$aSiteInfo = PrepSite($strOldSiteName, $strNewSiteName, $strNewSiteNameDB, $strConversionType, $strSourceDir); echo"";
		### eric, kelsey
		SetupRunType($aSiteInfo, false, false, '');

		if($aSiteInfo["BASICS"]["ConversionType"] == "initial")
		{
			DeleteSite($aSiteInfo);
			ConfigureDirectoriesAndFiles($aSiteInfo);
			SetupSiteConfigurationFiles($aSiteInfo);

			CreateSiteDB($aSiteInfo);
			CreatePages($aSiteInfo);

			UpdateTags($aSiteInfo, $aSiteInfo["BASICS"]["DBName"], "frank");
			CopyUsers($aSiteInfo);
			HandleReport($aSiteInfo);

			print "\n".$strOldSiteName." has been converted. Have a safe and joyous day.\n\n";
		}


		if($aSiteInfo["BASICS"]["ConversionType"] == "final")
		{
			$iStartTime = time();

			FinalConversion($aSiteInfo);
			HandleReport($aSiteInfo);

			$iEndTime = time();
			$iDuration = $iEndTime-$iStartTime;
			print"- PROCESS TOOK ".$iDuration." SECONDS\n";
			print "\nAll sites referencing anything from ".$strOldSiteName." have been updated. Enjoy your summer!\n\n";
		}


		if($aSiteInfo["BASICS"]["ConversionType"] == "uuid")
			GenerateUUIDs($aSiteInfo, "DrupalBusiness", 1);

		if($aSiteInfo["BASICS"]["ConversionType"] == "fix") { Fix($aSiteInfo); }
		
		WrapItUp($aSiteInfo);
	}
	catch (Exception $ex)
	{
		print "\nError: ".$ex->getMessage()."\n\n";
	}
}




############################################################################################################################################################################
########## FINAL ###########################################################################################################################################################
############################################################################################################################################################################


function FinalConversion(&$aSiteInfo)
{
	$strIgnoreFranck = '("'.$aSiteInfo["BASICS"]["OldSiteName"].'", "DrupalDefaultBackkup", "DrupalFoundation", "DrupalMIStem", "DrupalNews", "DrupalDev", "DrupalNMUDev")';
	$strIgnoreCharlie = '("'.$aSiteInfo["BASICS"]["OldSiteName"].'", "DrupalFoundation", "DrupalMIStem", "DrupalNews")';

	$classFranklinSqlQuery = new SqlDataQueries();
	$classFranklinSqlQuery->SpecifyDB("", "Drupal", "", "");
	$strQuery = "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA 
				 WHERE SCHEMA_NAME LIKE 'Drupa%' 
				 AND SCHEMA_NAME NOT IN ".$strIgnoreFranck."
				 ORDER BY SCHEMA_NAME";
	$aFranklinDrupalDBs = $classFranklinSqlQuery->MySQL_Queries($strQuery);

	$classCharlieSqlQuery = new SqlDataQueries();
	$classCharlieSqlQuery->SpecifyDB(Const_connCharlieHost, "Drupal", "", "");
	$strQuery = "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA 
				 WHERE SCHEMA_NAME LIKE 'Drupa%' 
				 AND SCHEMA_NAME NOT IN ".$strIgnoreCharlie."
				 ORDER BY SCHEMA_NAME";
	$aChalrieDrupalDBs = $classCharlieSqlQuery->MySQL_Queries($strQuery);


	print"- ADDING & UPDATING PAGE MAPPINGS\n";
	ImportKelseyInfo($aSiteInfo);

	print"- PROCESSING SITE LINKS\n";
	foreach($aFranklinDrupalDBs as $aDBRow)
		UpdateTags($aSiteInfo, $aDBRow['SCHEMA_NAME'], 'frank');

	foreach($aChalrieDrupalDBs as $aDBRow)
		UpdateTags($aSiteInfo, $aDBRow['SCHEMA_NAME'], 'charlie');
	print"\n";
}


function ImportKelseyInfo(&$aSiteInfo)
{
	$classFranklinSqlQuery = new SqlDataQueries();
	$classFranklinSqlQuery->SpecifyDB("", "www_admin", "", "");


	$strFile = dirname(__FILE__)."/kelsey/".$aSiteInfo["BASICS"]["LowerSiteName"].'.php';
	if(file_exists($strFile))
	{
		require_once $strFile;
		foreach($aKelseyData as $aNewEntry)
		{
			##### OLD #####
			$aOld = GetAliasTrans($aSiteInfo, $aNewEntry[0], 'old');

			##### NEW #####
			if($aNewEntry[1] != "")
				$aNew = GetAliasTrans($aSiteInfo, $aNewEntry[1], 'new');


			$strQuery = "SELECT * FROM drupal8_transition_links
						 WHERE OldURL='".addslashes($aOld['URL'])."' 
						 AND OldAliasedURL='".addslashes($aOld['AliasedURL'])."' 
						 AND NewURL='".addslashes($aNew['URL'])."' 
						 AND NewAliasedURL='".addslashes($aNew['AliasedURL'])."'
						 AND OldSiteName='".$aSiteInfo["BASICS"]["LowerSiteName"]."'";
			$aResultsTemp = $classFranklinSqlQuery->MySQL_Queries($strQuery);
			if(count($aResultsTemp) == 0)
			{
				$strOldStripped = str_replace('/'.$aSiteInfo["BASICS"]["LowerSiteName"], '', $aNewEntry[0]);
				$strQuery = "SELECT * FROM drupal8_transition_links WHERE (OldURL='".addslashes($strOldStripped)."' OR OldAliasedURL='".addslashes($strOldStripped)."') AND LinkType='page' AND OldSiteName='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."'";
				$aResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);
				$strInsert = "INSERT";
				if(count($aResults) > 0)
					$strInsert = "UPDATE";


				if($strInsert == "INSERT")
					$strQuery = "INSERT INTO drupal8_transition_links SET ";
				else
					$strQuery = "UPDATE drupal8_transition_links SET ";
				$strQuery .= "LinkType='page',
							  DateLastUsed=".time().",
							  OldSiteName='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."',
							  OldAliasedURL='".addslashes($aOld['AliasedURL'])."',
							  NewAliasedURL='".addslashes($aNew['AliasedURL'])."',
							  OldURL='".addslashes($aOld['URL'])."',
							  NewURL='".addslashes($aNew['URL'])."'";
				if($strInsert == "UPDATE")
					$strQuery .= " WHERE ID=".$aResults[0]['ID'];

				if($aSiteInfo['BASICS']['RUN_TYPE'] == "Full")
				{
					$aFinalResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);
					if($strInsert == "UPDATE" && $aFinalResults['rows'] != 1) { PrintR("Weird Result."); PrintR($strQuery, "Query"); PrintR($aFinalResults['rows'], "Return"); die; }
				}
			}
		}
		print"\n";
	}
}




############################################################################################################################################################################
########## SHARED ##########################################################################################################################################################
############################################################################################################################################################################


function UpdateTags(&$aSiteInfo, $strSchema, $strServer)
{
	$classFranklinSqlQuery = new SqlDataQueries();
	$classFranklinSqlQuery->SpecifyDB("", "www_admin", "", "");

	$classSqlQuery = new SqlDataQueries();
	if($strServer == "charlie")
	{
		$classSqlQuery->SpecifyDB(Const_connCharlieHost, $strSchema, "", "");
		if(count($classSqlQuery->MySQL_Queries("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='".$aSiteInfo["BASICS"]["DBName"]."' AND TABLE_NAME='field_data_body'")) == 0) { echo "Problem 427!"; die; }
	}
	else
	{
		$classSqlQuery->SpecifyDB("", $strSchema, "", "");
		if(count($classSqlQuery->MySQL_Queries("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='".$aSiteInfo["BASICS"]["DBName"]."' AND TABLE_NAME='paragraph__field_text_area'")) == 0) { echo "Problem 427!"; die; }
	}
	######


	$aReport = [];
	$strSiteName = strtolower(str_replace('Drupal', '', $strSchema));
	$strNewFileURL = '/'.$aSiteInfo["BASICS"]["LowerSiteName"].'/sites/'.$aSiteInfo["BASICS"]["LowerSiteName"].'/files/d7files/';

	PrintWithoutBreak("--- scanning cards and relocating used files", $strSchema, 'UpdateTags');
	foreach(['src="/sites', 'src="/'.$aSiteInfo["BASICS"]["LowerSiteName"], 'href='] as $strType)
	{
		if($strServer == "charlie")
			$strQuery = "SELECT entity_id as ID, body_value as BODY_TEXT FROM field_data_body WHERE body_value LIKE '%".$strType."%'";
		else
			$strQuery = "SELECT entity_id as ID, field_text_area_value as BODY_TEXT FROM paragraph__field_text_area WHERE field_text_area_value LIKE '%".$strType."%' AND deleted=0";
		$aTextBodyResults = $classSqlQuery->MySQL_Queries($strQuery);
		foreach($aTextBodyResults as $aRow)
		{
			$bFound = false;

			$iLastSpot = 0;
			while(($iLastSpot = strpos($aRow['BODY_TEXT'], $strType, $iLastSpot)) !== false)
			{
				$strNew = ""; $strTag = ""; $strUUID = ""; $strRefType = ""; $strOrig = ""; $strHREF_Text = ""; $strReplacement = "";

				$aTemp = GetIt($aRow['BODY_TEXT'], $iLastSpot, $strType, "item");
				$iReplaceStart = $aTemp['iReplaceStart'];
				$iLengthToReplace = $aTemp['iLengthToReplace'];
				$strItem = $aTemp['strItem'];
				$strOrig = $strItem;
				if((strstr($strItem, '/'.$aSiteInfo["BASICS"]["DBName"].'/') || strstr($strItem, '/'.$aSiteInfo["BASICS"]["LowerSiteName"].'/')) && str_replace('/', '', $strItem) != $aSiteInfo["BASICS"]["LowerSiteName"])
				{
					if(substr($strItem, 0, 7) == "mailto:") break;
					if(substr($strItem, 0, 1) == "#") break;
					if(strstr($strItem, "files/d7files")) break;
					if(strstr($strItem, "sites/default/files/UserFiles")) break;

					if(strstr($strType, 'src='))
					{
						if(substr($strItem, 0, strlen('/'.$aSiteInfo["BASICS"]["LowerSiteName"].'/sites/'.$aSiteInfo["BASICS"]["LowerSiteName"])) != '/'.$aSiteInfo["BASICS"]["LowerSiteName"].'/sites/'.$aSiteInfo["BASICS"]["LowerSiteName"])
						{
							$strFile = MakeReplacement($aSiteInfo, $strItem);
							if($strSiteName == $aSiteInfo["BASICS"]["LowerSiteName"])
							{
								$strUUID = MoveAndRegisterFile($aSiteInfo, $strFile, $aRow['ID'], $strServer, '');
								$strReplacement = $strNewFileURL.$strFile;
							}
							else
							{
								$aResponse = DetermineOutsideSiteLocationForFile($aSiteInfo, $strFile, $strServer, $aRow['ID'], $strSchema);
								$strUUID = $aResponse['uuid'];
								$strReplacement = $aResponse['path'];
							}

							$strRefType = GetIt($aRow['BODY_TEXT'], $iLastSpot, $strType, "reftype");
							if($strRefType == "img")
							{
								$aTemp = GetIt($aRow['BODY_TEXT'], $iLastSpot, $strType, "tag");
								$iReplaceStart = $aTemp['iReplaceStart'];
								$iLengthToReplace = $aTemp['iLengthToReplace'];
								$strFullImgTag = $aTemp['strFullImgTag'];

								$strNew = TranslateTag($strFullImgTag, $strReplacement, $strUUID);
							}
							else { PrintR("src doesn't belong to an img tag: ".substr($aRow['BODY_TEXT'], $iReplaceStart-10, 30)); }
						}
					}
					elseif($strType == 'href=')
					{
						StripURL($strItem);
						if(strlen($strItem) == 0) break;

						$aParts = explode("/", $strItem);					
						if(substr($strItem, 0, strlen($aSiteInfo["BASICS"]["LowerSiteName"])+1) == "/".$aSiteInfo["BASICS"]["LowerSiteName"] 
							&& $strItem != '/'.$aSiteInfo["BASICS"]["LowerSiteName"]
							&& $strItem != '/'.$aSiteInfo["BASICS"]["LowerSiteName"].'/')
						{
							if(strstr($strItem, '.'))
							{
								if(substr($strItem, strlen($strItem)-6, 6) == ".shtml") break;

								$strFile = MakeReplacement($aSiteInfo, $strItem);
								if($strSiteName == $aSiteInfo["BASICS"]["LowerSiteName"])
								{
									MoveAndRegisterFile($aSiteInfo, $strFile, $aRow['ID'], $strServer, '');
									$strNew = $strNewFileURL.$strFile;
								}
								else
								{
									$aResponse = DetermineOutsideSiteLocationForFile($aSiteInfo, $strFile, $strServer, $aRow['ID'], $strSchema);
									$strNew = $aResponse['path'];
								}
							}
							else
							{
								$strExt = "";
								if(strstr($strItem, "#"))
								{
									$aMoreParts = explode("#", $strItem);
									$strItem = $aMoreParts[0];
									$strExt = "#".$aMoreParts[1];
									$strItem = str_replace($strExt, "", $strItem);
								}

								$strSubSiteURL = str_replace("/".$aSiteInfo["BASICS"]["LowerSiteName"], "", $strItem);
								if(trim($strSubSiteURL) == "")
									$strNew = '/'.$aSiteInfo["BASICS"]["LowerSiteName"].$strExt;
								else
								{
									if(strstr($strItem, "node"))
										$strQuery = "SELECT * FROM www_admin.drupal8_transition_links WHERE OldURL='".addslashes($strSubSiteURL)."' AND OldSiteName='".$aSiteInfo["BASICS"]["LowerSiteName"]."'";
									else
										$strQuery = "SELECT * FROM www_admin.drupal8_transition_links WHERE OldAliasedURL='".addslashes($strSubSiteURL)."' AND OldSiteName='".$aSiteInfo["BASICS"]["LowerSiteName"]."'";
									$aNewHREF_Results = $classFranklinSqlQuery->MySQL_Queries($strQuery);

									if(count($aNewHREF_Results) == 0) 
									{
										$strHREF_Text = GetIt($aRow['BODY_TEXT'], $iLastSpot, $strType, "href_text");
										ReportIt($aSiteInfo, 1, "", $aRow['ID'], $strHREF_Text, $strServer, $strSchema);
										break;
									};

									if($aNewHREF_Results[0]['OldSiteName'] != "Drupal")
										$strNew = '/'.$aNewHREF_Results[0]['OldSiteName'].$aNewHREF_Results[0]['NewAliasedURL'].$strExt;
									else
										$strNew = $aNewHREF_Results[0]['NewAliasedURL'].$strExt;
								}
							}
						}
					}
					else { PrintR("TYPE NOT FOUND!"); die; };


					if($strNew != "" && $strNew != $strOrig)
					{
						$strImgTag = GetIt($aRow['BODY_TEXT'], $iLastSpot, $strType, "tag")['strFullImgTag'];
						$aRow['BODY_TEXT'] = substr_replace($aRow['BODY_TEXT'], $strNew, $iReplaceStart, $iLengthToReplace);

						if(GetIt($aRow['BODY_TEXT'], $iLastSpot, $strType, "reftype") == "img")
							NoteIt($aSiteInfo, $strSchema, $strImgTag, $strNew, $aRow['ID'], $strHREF_Text, $strSchema, $strServer, $strType);
						else
							NoteIt($aSiteInfo, $strSchema, $strOrig, $strNew, $aRow['ID'], $strHREF_Text, $strSchema, $strServer, $strType);
						$bFound = true;
					}
				}
				$iLastSpot = $iLastSpot+strlen($strType);
			}

			if($strServer == "charlie")
				$strQuery = "UPDATE field_data_body SET body_value='".addslashes($aRow['BODY_TEXT'])."' WHERE entity_id=".$aRow['ID'];
			else
				$strQuery = "UPDATE paragraph__field_text_area SET field_text_area_value='".addslashes($aRow['BODY_TEXT'])."' WHERE entity_id=".$aRow['ID'];

			if($bFound && $aSiteInfo["BASICS"]["ConversionType"] == "initial" || $aSiteInfo['BASICS']['RUN_TYPE'] == "Full")
				$classSqlQuery->MySQL_Queries($strQuery);
		}
	}
	PrintWithoutBreak("", "", 'UpdateTags');


	if($aSiteInfo["BASICS"]["ConversionType"] == "initial")
	{
		print"- COPYING RENORTHERN FILES\n";
		ExecCommand("/htdocs/Drupal/vendor/bin/drush rsync @re-northern-local:%files @".$aSiteInfo["BASICS"]["LowerSiteName"]."-local:%files --mode=rlpvz");
		ExecCommand("chgrp -R wwwapache ".$aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"].'/files');
	}
}




############################################################################################################################################################################
########## INITIAL #########################################################################################################################################################
############################################################################################################################################################################

function CopyUsers(&$aSiteInfo)
{
	print"- COPYING CHARLIE USERS\n";
	$classFranklinSqlQuery = new SqlDataQueries();
	$classFranklinSqlQuery->SpecifyDB("", $aSiteInfo["BASICS"]["OldSiteName"], "", "");

	$classCharlieSqlQuery = new SqlDataQueries();
	$classCharlieSqlQuery->SpecifyDB(Const_connCharlieHost, $aSiteInfo["BASICS"]["OldSiteName"], "", "");

	$strQuery = "SELECT * FROM users_roles";
	$aUserRolesResults = $classCharlieSqlQuery->MySQL_Queries($strQuery);
	foreach($aUserRolesResults as $aUserRole)
	{
		$strQuery = "SELECT * FROM Drupal.users WHERE uid=".$aUserRole['uid'];
		$aUserResults = $classCharlieSqlQuery->MySQL_Queries($strQuery);

		if($aUserResults[0]['name'] != "commark")
		{
			PrintWithoutBreak("--- copying", $aUserResults[0]['name'], 'CopyUsers');
			$strQuery = "SELECT uid FROM users_field_data WHERE name='".addslashes($aUserResults[0]['name'])."'";
			$aResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);
			if(count($aResults) == 0)
			{
				$strQuery = "SELECT * FROM Drupal.role WHERE rid=".$aUserRole['rid'];
				$aRoleResults = $classCharlieSqlQuery->MySQL_Queries($strQuery);

				$strQuery = "SELECT uid FROM users ORDER BY uid desc limit 1";
				$aUIDResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);
				$iUID = $aUIDResults[0]['uid']+1;

				$strQuery = "INSERT INTO users SET 
							 uid=".$iUID.",
							 uuid='".addslashes(GetUUID($aSiteInfo, $aSiteInfo["BASICS"]["OldSiteName"], "users"))."',
							 langcode='en'";
				$classFranklinSqlQuery->MySQL_Queries($strQuery);

				if($aUserResults[0]['pass'] == "A Secret")
					$strAutType = "simplesamlphp_auth_".$aUserResults[0]['name'];
				else
					$strAutType = $aUserResults[0]['mail'];

				$strQuery = "INSERT INTO users_field_data SET 
							uid=".$iUID.",
							langcode='en',
							preferred_langcode='en',
							preferred_admin_langcode='en',
							name='".addslashes($aUserResults[0]['name'])."',
							pass='".addslashes($aUserResults[0]['pass'])."',
							mail='".addslashes($aUserResults[0]['mail'])."',
							timezone='".addslashes("America/Detroit")."',
							status=1,
							created=".time().",
							access=".time().",
							init='".$strAutType."',
							default_langcode=1";
				$classFranklinSqlQuery->MySQL_Queries($strQuery);

				if($aRoleResults[0]['name'] == "editor")
					$aRoleResults[0]['name'] = "migrated_user";

				$strQuery = "INSERT INTO user__roles SET 
							bundle='user',
							deleted=0,
							entity_id=".$iUID.",
							revision_id=".$iUID.",
							langcode='en',
							delta=0,
							roles_target_id='".$aRoleResults[0]['name']."'";
				$classFranklinSqlQuery->MySQL_Queries($strQuery);
			}
		}
	}

	PrintWithoutBreak("", "", 'CopyUsers');
}


function CreatePages(&$aSiteInfo)
{
	$classFranklinSqlQuery = new SqlDataQueries();
	$classFranklinSqlQuery->SpecifyDB("", $aSiteInfo["BASICS"]["OldSiteName"], "", "");

	print"- COPYING NODES\n";
	$classCharlieSqlQuery = new SqlDataQueries();
	$classCharlieSqlQuery->SpecifyDB(Const_connCharlieHost, $aSiteInfo["BASICS"]["OldSiteName"], "", "");
	$strQuery = "SELECT nid, vid, title FROM node WHERE type!='fearless_homepage' AND status=1";
	$aResults = $classCharlieSqlQuery->MySQL_Queries($strQuery);

	$iTime = time();
	foreach($aResults as $aRow)
	{
		$iNID = $aRow['nid'];
		$strTitle = trim($aRow['title']);

		if($strTitle == '')
		{
			$strQuery = "SELECT title FROM node_revision WHERE nid=".$iNID." AND vid=".$aRow['vid'];
			$aResults = $classCharlieSqlQuery->MySQL_Queries($strQuery);
			if(count($aResults) > 0 && $aResults[0]['title'] != "")
				$strTitle = $aResults[0]['title'];
			else
				ReportIt($aSiteInfo, 2, "", $aRow['ID'], "", 'charlie', $aSiteInfo["BASICS"]["OldSiteName"]);
		}


		PrintWithoutBreak("--- Making page", $strTitle, 'CreatePages');

		### GET BODY
		$strQuery = "SELECT body_value FROM field_data_body WHERE entity_id=".$iNID." AND entity_type='node'";
		$aTempResults = $classCharlieSqlQuery->MySQL_Queries($strQuery);
		$strBodyValue = "";
		if(count($aTempResults) > 0)
			$strBodyValue = $aTempResults[0]['body_value'];

		if(strstr($strBodyValue, "<?php"))
			$strNodeType = 'block_page';
		else
			$strNodeType = 'internal_page';


		### GET ALIAS
		$strAlias = '';
		$strQuery = "SELECT alias url_alias FROM url_alias WHERE source='node/".$iNID."'";
		$aNewAliasResults = $classCharlieSqlQuery->MySQL_Queries($strQuery);
		if(count($aNewAliasResults) > 0)
			$strAlias = $aNewAliasResults[0]['url_alias'];
		else
		{
			if($strTitle != "")
			{
				$strAlias = str_replace(" ", "-", $strTitle);
				$strAlias = preg_replace('/[^A-Za-z0-9\-]/', '', $strAlias);
			}
			else
				$strAlias = 'Northern Michigan University';
		}


		### NODE
		$strQuery = "INSERT INTO node SET 
					 type='".addslashes($strNodeType)."',
					 uuid='".addslashes(GetUUID($aSiteInfo, $aSiteInfo["BASICS"]["OldSiteName"], "node"))."',
					 langcode='en'";
		$aNodeResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);

		$strQuery = "INSERT INTO www_admin.drupal8_transition_links SET
					 LinkType='page', 
					 OldSiteName='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."',
					 OldURL='/node/".$iNID."',
					 OldAliasedURL='/".addslashes($strAlias)."',
					 NewURL='/node/".$aNodeResults['ID']."',
					 NewAliasedURL='/".addslashes($strAlias)."',
					 DateLastUsed=0";
		$classFranklinSqlQuery->MySQL_Queries($strQuery);

		$strQuery = "INSERT INTO node_revision SET 
					 nid=".$aNodeResults['ID'].",
					 langcode='en',
					 revision_uid=38,
					 revision_timestamp=".$iTime;
		$aNodeRevResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);

		$strQuery = "UPDATE node SET vid=".$aNodeRevResults['ID']." WHERE nid=".$aNodeResults['ID'];
		$classFranklinSqlQuery->MySQL_Queries($strQuery);


		### FIELD DATA
		$strQuery = "INSERT INTO node_field_data SET 
					 nid=".$aNodeResults['ID'].",
					 vid=".$aNodeRevResults['ID'].",
					 type='".addslashes($strNodeType)."',
					 langcode='en',
					 status=1,
					 uid=14,
					 title='".addslashes($strTitle)."',
					 created=".$iTime.",
					 changed=".$iTime.",
					 promote=0,
					 sticky=0,
					 default_langcode=1";
		$classFranklinSqlQuery->MySQL_Queries($strQuery);
		$classFranklinSqlQuery->MySQL_Queries(str_replace("node_field_data", "node_field_revision", str_replace("type='".addslashes($strNodeType)."',", "", $strQuery)));


		### PARAGRAPH
		$strQuery = "INSERT INTO paragraphs_item SET 
					 type='text_area',
					 langcode='en',
					 uuid='".addslashes(GetUUID($aSiteInfo, $aSiteInfo["BASICS"]["OldSiteName"], "paragraphs_item"))."'";
		$aParagraphResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);

		$strQuery = "INSERT INTO paragraphs_item_revision SET 
					 id=".$aParagraphResults['ID'].",
					 langcode='en',
					 revision_default=1";
		$aParagraphRevResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);

		$strQuery = "UPDATE paragraphs_item SET revision_id=".$aParagraphRevResults['ID']." WHERE id=".$aParagraphResults['ID'];
		$classFranklinSqlQuery->MySQL_Queries($strQuery);


		### PARAGRAPH FIELD DATA
		$strQuery = "INSERT INTO paragraphs_item_field_data SET 
					 id=".$aParagraphResults['ID'].",
					 revision_id=".$aParagraphRevResults['ID'].",
					 type='text_area',
					 langcode='en',
					 status=1,
					 created=".time().",
					 parent_id=".$aNodeResults['ID'].",
					 parent_type='node',
					 parent_field_name='field_ct_cards',
					 behavior_settings='".addslashes(serialize(array()))."',
					 default_langcode=1,
					 revision_translation_affected=1";
		$classFranklinSqlQuery->MySQL_Queries($strQuery);
		$classFranklinSqlQuery->MySQL_Queries(str_replace("type='text_area',", "", str_replace("paragraphs_item_field_data", "paragraphs_item_revision_field_data", $strQuery)));


		### LINK NODE AND PARAGRAPH
		$strQuery = "INSERT INTO node__field_ct_cards SET
					 bundle='".addslashes($strNodeType)."',
					 deleted=0,
					 entity_id='".$aNodeResults['ID']."',
					 revision_id='".$aNodeRevResults['ID']."',
					 langcode='en',
					 delta=0,
					 field_ct_cards_target_id=".$aParagraphResults['ID'].",
					 field_ct_cards_target_revision_id=".$aParagraphRevResults['ID'];
		$classFranklinSqlQuery->MySQL_Queries($strQuery);
		$classFranklinSqlQuery->MySQL_Queries(str_replace("node__field_ct_cards", "node_revision__field_ct_cards", $strQuery));

		### CREATE CARD
		if(strstr($strBodyValue, "<?php"))
			PrepPPHPInclude($strBodyValue, $strBodyValue, 1);

		$strQuery = "SELECT field_scripts_value FROM field_data_field_scripts WHERE entity_id=".$iNID;
		$aTempResults = $classCharlieSqlQuery->MySQL_Queries($strQuery);
		if(count($aTempResults) > 0)
			foreach($aTempResults as $aTempRow)
				PrepPPHPInclude($strBodyValue, $aTempRow['field_scripts_value'], 2);

		$strBodyValue = str_replace('class="rtecenter"', '', $strBodyValue);
		$strBodyValue = str_replace('class="rteright"', '', $strBodyValue);

		$strQuery = "INSERT INTO paragraph__field_text_area SET
					 bundle='".addslashes($strNodeType)."',
					 deleted=0,
					 entity_id=".$aParagraphResults['ID'].",
					 revision_id=".$aParagraphRevResults['ID'].",
					 langcode='en',
					 delta=0,
					 field_text_area_value='".addslashes($strBodyValue)."',
					 field_text_area_format='basic_html'";
		$classFranklinSqlQuery->MySQL_Queries($strQuery);
		$classFranklinSqlQuery->MySQL_Queries(str_replace("paragraph__field_text_area", "paragraph_revision__field_text_area", $strQuery));
 

		### ADD ALIAS
		$strQuery = "INSERT INTO path_alias SET 
					 uuid='".addslashes(GetUUID($aSiteInfo, $aSiteInfo["BASICS"]["OldSiteName"], "path_alias"))."',
					 langcode='en',
					 alias='/".addslashes($strAlias)."',
					 path='/node/".$aNodeResults['ID']."',
					 status=1";
		$aAliasResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);

		$strQuery = "INSERT INTO path_alias_revision SET 
					 id=".$aAliasResults['ID'].",
					 langcode='en',
					 path='/node/".$aNodeResults['ID']."',
					 alias='/".addslashes($strAlias)."',
					 status=1,
					 revision_default=1";
		$aAliasRevResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);

		$strQuery = "UPDATE path_alias SET revision_id=".$aAliasRevResults['ID']." WHERE id=".$aAliasResults['ID'];
		$classFranklinSqlQuery->MySQL_Queries($strQuery);
	}

	ExecCommand("/htdocs/Drupal/vendor/bin/drush -l ".$aSiteInfo["BASICS"]["LowerSiteName"]." cr");
	PrintWithoutBreak("", "", 'CreatePages');
}


function CreateSiteDB(&$aSiteInfo)
{
	print"- CREATING SITE DB\n";

	$classSqlQuery = new SqlDataQueries();
	$classSqlQuery->SpecifyDB("", "Drupal", "", "");

	$classSqlQuery = new SqlDataQueries();
	$classSqlQuery->SpecifyDB("", "Drupal", "", "");
	$classSqlQuery->MySQL_Queries("DROP DATABASE IF EXISTS ".$aSiteInfo["BASICS"]["DBName"]);
	$classSqlQuery->MySQL_Queries("CREATE DATABASE ".$aSiteInfo["BASICS"]["DBName"]." DEFAULT CHARACTER SET utf8;");

	$strNewEntry = file_get_contents("/htdocs/Drupal/drush/sites/example.site.yml");
	$strNewEntry = str_replace("admissions", $aSiteInfo["BASICS"]["LowerSiteName"], $strNewEntry);
	$strNewEntry = str_replace("Admissions", ucfirst($aSiteInfo["BASICS"]["LowerSiteName"]), $strNewEntry);
	$strNewEntry .= $aSiteInfo["BASICS"]["Marker"];

	$strMainFile = file_get_contents("/htdocs/Drupal/drush/sites/self.site.yml");
	$strMainFile = str_replace($aSiteInfo["BASICS"]["Marker"], $strNewEntry, $strMainFile);
	file_put_contents("/htdocs/Drupal/drush/sites/self.site.yml", $strMainFile);

	$strQuery = "GRANT ALL PRIVILEGES ON ".$aSiteInfo["BASICS"]["DBName"].".* TO Drupal@localhost";
	$classSqlQuery->MySQL_Queries($strQuery);

	ExecCommand("echo yes | /htdocs/Drupal/vendor/bin/drush sql-sync @re-northern-local @".$aSiteInfo["BASICS"]["LowerSiteName"]."-local");

	PrintWithoutBreak("", "", 'CreateSiteDB1');

	print"- UPDATING DB                                              \n";
	ExecCommand("/htdocs/Drupal/vendor/bin/drush -l ".$aSiteInfo["BASICS"]["LowerSiteName"]." updb -y ");

	$classSqlQuery->MySQL_Queries("DELETE FROM ".$aSiteInfo["BASICS"]["DBName"].".watchdog");
	$aResults = $classSqlQuery->MySQL_Queries("SELECT data FROM ".$aSiteInfo["BASICS"]["DBName"].".config WHERE name='system.site'");
	$aValues = unserialize($aResults[0]["data"]);
	$aValues["name"] = "NMU ".ucfirst($aSiteInfo["BASICS"]["SiteName"]);
	$classSqlQuery->MySQL_Queries("UPDATE ".$aSiteInfo["BASICS"]["DBName"].".config SET data='".serialize($aValues)."' WHERE name='system.site'");
	$classSqlQuery->MySQL_Queries("UPDATE ".$aSiteInfo["BASICS"]["DBName"].".config_import SET data='".serialize($aValues)."' WHERE name='system.site'");
	$classSqlQuery->MySQL_Queries("UPDATE ".$aSiteInfo["BASICS"]["DBName"].".config_snapshot SET data='".serialize($aValues)."' WHERE name='system.site'");
}


function SetupSiteConfigurationFiles(&$aSiteInfo)
{
	print"- SETTING UP SITE AND APACHE CONFIG FILES\n";

	if(!strstr(file_get_contents($aSiteInfo["BASICS"]["PathDrupal"].".env"), "MYSQL_SUBSITE_DB_".$aSiteInfo["BASICS"]["UpperSiteName"]))
	{
		MakeDir("", $aSiteInfo["BASICS"]["PathDrupal"].".env-bks", "wwwmgmt", "775");
		ExecCommand("cp ".$aSiteInfo["BASICS"]["PathDrupal"].".env"." ".$aSiteInfo["BASICS"]["PathDrupal"].".env-bks/env.bk-".date("Y-m-d--G:i:s"));
		file_put_contents($aSiteInfo["BASICS"]["PathDrupal"].".env", str_replace($aSiteInfo["BASICS"]["Marker"], $aSiteInfo["BASICS"]["NewContent_ENV"].$aSiteInfo["BASICS"]["Marker"], file_get_contents($aSiteInfo["BASICS"]["PathDrupal"].".env")));
	}

	if(!strstr(file_get_contents($aSiteInfo["BASICS"]["PathWeb"].".htaccess"), "#### ".$aSiteInfo["BASICS"]["LowerSiteName"]." ####"))
		file_put_contents($aSiteInfo["BASICS"]["PathWeb"].".htaccess", str_replace($aSiteInfo["BASICS"]["Marker"], $aSiteInfo["BASICS"]["NewContent_htaccess"].$aSiteInfo["BASICS"]["Marker"], file_get_contents($aSiteInfo["BASICS"]["PathWeb"].".htaccess")));

	if(!strstr(file_get_contents($aSiteInfo["BASICS"]["PathSites"]."sites.php"), "sites['".$aSiteInfo["BASICS"]["LowerSiteName"]."']"))
		file_put_contents($aSiteInfo["BASICS"]["PathSites"]."sites.php", str_replace($aSiteInfo["BASICS"]["Marker"], $aSiteInfo["BASICS"]["NewContent_SitePHP"].$aSiteInfo["BASICS"]["Marker"], file_get_contents($aSiteInfo["BASICS"]["PathSites"]."sites.php")));

	file_put_contents($aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"]."/settings.php", str_replace("MYSQL_SUBSITE_DB_NEWSITE", "MYSQL_SUBSITE_DB_".$aSiteInfo["BASICS"]["UpperSiteName"], file_get_contents($aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"]."/settings.php")));

	if(!strstr(file_get_contents($aSiteInfo["BASICS"]["PathConfSites"]), "Alias /".$aSiteInfo["BASICS"]["LowerSiteName"]))
	{
		ExecCommand("chmod 777 /usr/local/apache2/conf/nmu/shared_config/www.sites.conf");
		file_put_contents($aSiteInfo["BASICS"]["PathConfSites"], str_replace($aSiteInfo["BASICS"]["Marker"], $aSiteInfo["BASICS"]["NewContent_Conf"].$aSiteInfo["BASICS"]["Marker"], file_get_contents($aSiteInfo["BASICS"]["PathConfSites"])));
		ExecCommand("chmod 755 /usr/local/apache2/conf/nmu/shared_config/www.sites.conf");
	}

	ExecCommand("git commit apache/www.sites.conf drush/sites/self.site.yml web/.htaccess web/sites/sites.php -m 'new sites created'");
}


function ConfigureDirectoriesAndFiles(&$aSiteInfo)
{
	$aOutput = [];
	$classFranklinSqlQuery = new SqlDataQueries();

	print"- CONFIGURE SITE DIRECTORIES ON FRANKLIN\n";

	MakeDir("", $aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"], "wwwmgmt", "775");

	MakeDir("", $aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"]."/config", "wwwapache", "775");
	MakeDir("", $aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"]."/private", "wwwapache", "730");
	MakeDir("", $aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"]."/files", "wwwapache", "775");
	MakeDir("", $aSiteInfo["BASICS"]["PathD7Files"], "wwwapache", "777");

	MakeDir($aSiteInfo["BASICS"]["PathCommonConf"]."private-htaccess", $aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"]."/private/.htaccess", "wwwmgmt", "664");
	MakeDir($aSiteInfo["BASICS"]["PathCommonConf"]."config-htaccess", $aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"]."/config/.htaccess", "wwwmgmt", "664");
	MakeDir($aSiteInfo["BASICS"]["PathCommonConf"]."settings.php-subsite", $aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"]."/settings.php", "wwwmgmt", "664");

	print"- PULLING SITE FILES FROM CHARLIE\n";
	ExecCommand("ssh -o StrictHostKeyChecking=no ".$aSiteInfo["BASICS"]["Username"]."@charlie.nmu.edu echo");
	PrintWithoutBreak("", "--- pulling files...", 'OldSite_PullFiles');
	ExecCommand("scp -r -o StrictHostKeyChecking=no ".$aSiteInfo["BASICS"]["Username"]."@charlie.nmu.edu:/htdocs/Drupal/sites/".$aSiteInfo["BASICS"]["DBName"]."/files/UserFiles/* ".$aSiteInfo["BASICS"]["PathD7Files"].'/');
	ExecCommand("chgrp -R wwwapache ".$aSiteInfo["BASICS"]["PathD7Files"].'/..');
	PrintWithoutBreak("", "", 'OldSite_PullFiles');


	print"- ADDING FILES TO TRANSLATION TABLE\n";
	$aFiles = MyScanDir($aSiteInfo, $aSiteInfo["BASICS"]["PathD7Files"], $aOutput, true);
	foreach($aFiles as $strFile)
	{
		PrintWithoutBreak("--- adding", str_replace($aSiteInfo["BASICS"]["PathD7Files"], "", $strFile), 'AddToTranslationTable');

		$strOldURL = str_replace($aSiteInfo["BASICS"]["PathD7Files"], '/'.$aSiteInfo["BASICS"]["LowerSiteName"].'/sites/'.$aSiteInfo["BASICS"]['OldSiteName'].'/files/UserFiles', $strFile);
		$strNewURL = str_replace("/htdocs", "",  $strFile);

		$strQuery = "SELECT ID FROM www_admin.drupal8_transition_links WHERE OldURL='".addslashes($strOldURL)."' AND OldSiteName='".$aSiteInfo["BASICS"]["LowerSiteName"]."'";
		$aTempResult = $classFranklinSqlQuery->MySQL_Queries($strQuery);
		if(count($aTempResult) == 0)
		{
			$strQuery = "INSERT INTO www_admin.drupal8_transition_links SET
						 LinkType='file', 
						 OldSiteName='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."',
						 OldURL='".addslashes($strOldURL)."',
						 OldAliasedURL='',
						 NewURL='".addslashes($strNewURL)."',
						 NewAliasedURL='',
						 DateLastUsed=0";
			$classFranklinSqlQuery->MySQL_Queries($strQuery);
		}
	}

	PrintWithoutBreak("", "", 'AddToTranslationTable');
}


function DeleteSite(&$aSiteInfo)
{
	print"- DELETING SITE FROM FRANKLIN\n";

	$strCMD = "rm -rf ".$aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"];
	if(file_exists($aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"]))
		ExecCommand($strCMD);

	$strCMD = "rm -rf ".$aSiteInfo["BASICS"]["PathD7Files"];
	if(file_exists($aSiteInfo["BASICS"]["PathD7Files"]))
		ExecCommand($strCMD);

	$strEntry = file_get_contents("/htdocs/Drupal/drush/sites/example.site.yml");
	$strEntry = str_replace("admissions", $aSiteInfo["BASICS"]["LowerSiteName"], $strEntry);
	$strEntry = str_replace("Admissions", ucfirst($aSiteInfo["BASICS"]["LowerSiteName"]), $strEntry);

	$strMainFile = file_get_contents("/htdocs/Drupal/drush/sites/self.site.yml");
	$strMainFile = str_replace($strEntry, "", $strMainFile);
	file_put_contents("/htdocs/Drupal/drush/sites/self.site.yml", $strMainFile);

	file_put_contents($aSiteInfo["BASICS"]["PathDrupal"].".env", str_replace($aSiteInfo["BASICS"]["NewContent_ENV"], "", file_get_contents($aSiteInfo["BASICS"]["PathDrupal"].".env")));
	file_put_contents($aSiteInfo["BASICS"]["PathWeb"].".htaccess", str_replace($aSiteInfo["BASICS"]["NewContent_htaccess"], "", file_get_contents($aSiteInfo["BASICS"]["PathWeb"].".htaccess")));
	file_put_contents($aSiteInfo["BASICS"]["PathSites"]."sites.php", str_replace($aSiteInfo["BASICS"]["NewContent_SitePHP"], "", file_get_contents($aSiteInfo["BASICS"]["PathSites"]."sites.php")));
	file_put_contents($aSiteInfo["BASICS"]["PathConfSites"], str_replace($aSiteInfo["BASICS"]["NewContent_Conf"], "", file_get_contents($aSiteInfo["BASICS"]["PathConfSites"])));

	$classFranklinSqlQuery = new SqlDataQueries();
	$strQuery = "DELETE FROM www_admin.drupal8_transition_links WHERE OldSiteName='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."'";
	$classFranklinSqlQuery->MySQL_Queries($strQuery);

	$strQuery = "SELECT * FROM www_DrupalConversionTables.aaTemp_Menu WHERE Site='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."'";
	$aTemp = $classFranklinSqlQuery->MySQL_Queries($strQuery);
	foreach($aTemp as $aRow)
	{
		$strQuery = "SELECT * FROM www_DrupalConversionTables.aaTemp_Menu_SubMenu WHERE MenuID='".$aRow['ID']."'";
		$aSubTemp = $classFranklinSqlQuery->MySQL_Queries($strQuery);

		foreach($aSubTemp as $aSubRow)
		{
			$strQuery = "DELETE FROM www_DrupalConversionTables.aaTemp_Menu_SubMenu_Items WHERE SubMenuID='".$aSubRow['ID']."'";
			$classFranklinSqlQuery->MySQL_Queries($strQuery);

			$strQuery = "DELETE FROM www_DrupalConversionTables.aaTemp_Menu_SubMenu WHERE ID='".$aSubRow['ID']."'";
			$classFranklinSqlQuery->MySQL_Queries($strQuery);
		}
	}
	$strQuery = "DELETE FROM www_DrupalConversionTables.aaTemp_Menu WHERE Site='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."'";
	$classFranklinSqlQuery->MySQL_Queries($strQuery);


	$strQuery = "SELECT * FROM www_DrupalConversionTables.aaTemp_File_Usage WHERE Site='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."'";
	$aTemp = $classFranklinSqlQuery->MySQL_Queries($strQuery);
	foreach($aTemp as $aRow)
	{
		$strQuery = "DELETE FROM www_DrupalConversionTables.aaTemp_File_Usage_NIDs WHERE FileID='".$aRow['ID']."'";
		$classFranklinSqlQuery->MySQL_Queries($strQuery);
	}
	$strQuery = "DELETE FROM www_DrupalConversionTables.aaTemp_File_Usage WHERE Site='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."'";
	$classFranklinSqlQuery->MySQL_Queries($strQuery);


	$strQuery = "SELECT * FROM www_DrupalConversionTables.aaTemp_SubMenu WHERE Site='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."'";
	$aTemp = $classFranklinSqlQuery->MySQL_Queries($strQuery);
	foreach($aTemp as $aRow)
	{
		$strQuery = "DELETE FROM www_DrupalConversionTables.aaTemp_SubMenu_Items WHERE SubMenuID='".$aRow['ID']."'";
		$classFranklinSqlQuery->MySQL_Queries($strQuery);
	}
	$strQuery = "DELETE FROM www_DrupalConversionTables.aaTemp_SubMenu WHERE Site='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."'";
	$classFranklinSqlQuery->MySQL_Queries($strQuery);


	$strQuery = "DELETE FROM www_DrupalConversionTables.aaTemp_Nodes WHERE Site='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."'";
	$classFranklinSqlQuery->MySQL_Queries($strQuery);

	$strQuery = "DELETE FROM www_DrupalConversionTables.aaTemp_NodeTypes WHERE Site='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."'";
	$classFranklinSqlQuery->MySQL_Queries($strQuery);

	$strQuery = "DELETE FROM www_DrupalConversionTables.aaTemp_Basic WHERE Site='".addslashes($aSiteInfo["BASICS"]["LowerSiteName"])."'";
	$classFranklinSqlQuery->MySQL_Queries($strQuery);

	$strQuery = "UPDATE www_admin.drupal8_uuids SET Used=0 WHERE Used=2";
	$classFranklinSqlQuery->MySQL_Queries($strQuery);


	$strCMD = "/usr/bin/mysql --max_allowed_packet=999M --user=".Const_connUser." --password=".Const_connPSW." --host=localhost -N -B -e  'DROP DATABASE IF EXISTS ".$aSiteInfo["BASICS"]["DBName"]."'";
	ExecCommand($strCMD);
}


function PrepSite($strOldSiteName, $strNewSiteName, $strNewSiteNameDB, $strConversionType, $strScriptsDir)
{
	$aSiteInfo = [];
	$aSiteInfo["BASICS"]["SiteName"] = $strNewSiteName;
	$aSiteInfo["BASICS"]["OldSiteName"] = $strOldSiteName;
	$aSiteInfo["BASICS"]["NewSiteName"] = $strNewSiteName;
	$aSiteInfo["BASICS"]["ConversionType"] = $strConversionType;
	$aSiteInfo["BASICS"]["Username"] = "aquinn";

	$aSiteInfo["BASICS"]["BaseSiteName"] = "re-northern";
	$aSiteInfo["BASICS"]["BaseSiteDatabaseName"] = "DrupalReNorthern";
	$aSiteInfo["BASICS"]["BaseSiteDatabaseConversionTableName"] = "www_DrupalConversionTables";
	$aSiteInfo["BASICS"]["PathToScripts"] = $strScriptsDir;

	$aSiteInfo["BASICS"]["DBName"] = $strNewSiteNameDB;
	$aSiteInfo["BASICS"]["DBNameFile"] = $aSiteInfo["BASICS"]["DBName"].".sql";
	$aSiteInfo["BASICS"]["LowerSiteName"] = strtolower($aSiteInfo["BASICS"]["SiteName"]);
	$aSiteInfo["BASICS"]["UpperSiteName"] = strtoupper($aSiteInfo["BASICS"]["SiteName"]);

	$aSiteInfo["BASICS"]["PathConfSites"] = "/usr/local/apache2/conf/nmu/shared_config/www.sites.conf";
	$aSiteInfo["BASICS"]["PathDrupal"] = "/htdocs/Drupal/";
	$aSiteInfo["BASICS"]["PathWeb"] = $aSiteInfo["BASICS"]["PathDrupal"]."web/";
	$aSiteInfo["BASICS"]["PathSites"] = $aSiteInfo["BASICS"]["PathWeb"]."sites/";
	$aSiteInfo["BASICS"]["PathCommonConf"] = $aSiteInfo["BASICS"]["PathSites"]."common-conf/";
	$aSiteInfo["BASICS"]["PathDefaultFiles"] = $aSiteInfo["BASICS"]["PathSites"]."default/files";
	$aSiteInfo["BASICS"]["PathD7Files"] = "/htdocs/d7files/".$aSiteInfo["BASICS"]["LowerSiteName"];
	$aSiteInfo["BASICS"]["HttpPathD7Files"] = "/d7files/".$aSiteInfo["BASICS"]["LowerSiteName"];
	$aSiteInfo["BASICS"]["PathModules"] = $aSiteInfo["BASICS"]["PathDrupal"]."web/modules/custom/";

	$aSiteInfo["BASICS"]["NewContent_ENV"] = "MYSQL_SUBSITE_DB_".$aSiteInfo["BASICS"]["UpperSiteName"]."='".$aSiteInfo["BASICS"]["DBName"]."'\n";
	$aSiteInfo["BASICS"]["NewContent_htaccess"] = "  #### ".$aSiteInfo["BASICS"]["SiteName"]." #### \n  RewriteCond %{REQUEST_FILENAME} !-f \n  RewriteCond %{REQUEST_FILENAME} !-d \n  RewriteCond %{REQUEST_URI} ^/".$aSiteInfo["BASICS"]["LowerSiteName"]."/(.*)$ \n  RewriteRule ^(.*)$ /".$aSiteInfo["BASICS"]["LowerSiteName"]."/index.php?q=$1 [L,QSA] \n  #####################\n\n";
	$aSiteInfo["BASICS"]["NewContent_SitePHP"] = "\$sites['nmu.edu.".$aSiteInfo["BASICS"]["LowerSiteName"]."'] = '".$aSiteInfo["BASICS"]["LowerSiteName"]."';\n";
	$aSiteInfo["BASICS"]["NewContent_Conf"] = "Alias /".$aSiteInfo["BASICS"]["LowerSiteName"]." /htdocs/Drupal/web\n";
	$aSiteInfo["BASICS"]["Marker"] = "  #### ADD NEW SITES HERE ####";

	$aSiteInfo['REPORTS']['ConversionIssues'] = [];
	$aSiteInfo['REPORTS']['FinalLinks'] = [];

	$aSiteInfo['BASICS']['RUN_TYPE'] = "";

	$aSiteInfo["BASICS"]["Linode"] = "~/Linode/";
	$GLOBALS["LOG"] = $aSiteInfo["BASICS"]["Linode"]."temp/log.txt";

	return $aSiteInfo;
}




############################################################################################################################################################################
########## FUNCTIONS #######################################################################################################################################################
############################################################################################################################################################################

function MakeDir($strSrcDir, $strDestDir, $strOwner, $strPerms)
{
	if($strSrcDir != "")
	{
		if(is_dir($strSrcDir) && !file_exists($strDestDir))
			ExecCommand("mkdir -p ".$strDestDir);

		ExecCommand("cp -r ".$strSrcDir." ".$strDestDir);
	}
	elseif(!file_exists($strDestDir))
		ExecCommand("mkdir -p ".$strDestDir);

	if($strOwner != "")
		ExecCommand("chgrp -R ".$strOwner." ".$strDestDir);
	if($strPerms != "")
		ExecCommand("chmod -R ".$strPerms." ".$strDestDir);
}


function GetURIFromSites($aSiteInfo)
{
	$strURI = "";
	if(!isset($GLOBALS['sites']))
	{
		require_once($aSiteInfo["BASICS"]["PathSites"].'/sites.php');
		$GLOBALS['sites'] = $sites;
	}

	foreach($GLOBALS['sites'] as $strName=>$strValue)
		if($strValue == $aSiteInfo["BASICS"]["NewSiteName"])
			$strURI = $strName;

	if($strURI == "")
	{
		print"\n\nCould not determine URI.\n";
		die;
	}

	return $strURI;
}


function ExecCommand($strCommand)
{
	$strSourceDir = dirname(__FILE__); 

	if(!isset($GLOBALS['ShouldPrint']) || $GLOBALS['ShouldPrint'] == "")
		$GLOBALS['ShouldPrint'] = false;

	if($GLOBALS['ShouldPrint'] == true || strstr($GLOBALS['ShouldPrint'], "only") || strstr($GLOBALS['ShouldPrint'], "next"))
		PrintR($strCommand);

	if(strstr($GLOBALS['ShouldPrint'], "next") && !strstr($GLOBALS['ShouldPrint'], "nextonly"))
		$GLOBALS['ShouldPrint'] = false;

	if($GLOBALS['ShouldPrint'] != "only" && $GLOBALS['ShouldPrint'] != "nextonly")
	{
		if($GLOBALS['ShouldPrint'] == true)
			system($strCommand);
		else
			system('{ '.$strCommand.'; } &> '.$GLOBALS["LOG"]);

		$strCmd = '
			if [ -f '.$GLOBALS["LOG"].' ]; then
				Beef=$(grep error '.$GLOBALS["LOG"].')
				if [ "$Beef" != "" ]; then cat '.$GLOBALS["LOG"].'; fi
			fi';
		system($strCmd);

		system("if [ -f ".$GLOBALS["LOG"]." ]; then rm -rf ".$GLOBALS["LOG"]."; fi");
	}

	if(strstr($GLOBALS['ShouldPrint'], "die"))
		die;

	if(strstr($GLOBALS['ShouldPrint'], "nextonly"))
		$GLOBALS['ShouldPrint'] = false;
}


function MyScanDir(&$aSiteInfo, $strTarget, $aOutput, $bIsFirst)
{
	#$aNotAllowed = ["jpg","jpeg","tif","tiff","gif","png","gz","js","css"];
	$aNotAllowed = ["gz","js","css","swf"];

	$aContents = scandir($strTarget);
	foreach($aContents as $strContent)
	{
		PrintWithoutBreak("--- sorting file", $strContent, 'file2');

		$strExt = "";
		if(!is_dir($strTarget."/".$strContent) && strlen($strContent) >= 3 && strpos($strContent, ".", 1) !== false)
			$strExt = strtolower(explode(".", $strContent)[count(explode(".", $strContent))-1]);

		if(!is_dir($strTarget."/".$strContent) && !in_array($strExt, $aNotAllowed) && substr($strContent, 0, 1) != ".")
			$aOutput[] = $strTarget."/".$strContent;
		elseif(!is_dir($strTarget."/".$strContent) && in_array($strExt, $aNotAllowed))
			unlink($strTarget."/".$strContent);
		elseif(is_dir($strTarget."/".$strContent) && substr($strContent, 0, 1) != ".")
			$aOutput = MyScanDir($aSiteInfo, $strTarget."/".$strContent, $aOutput, false);
	}

	if($bIsFirst)
		MyCleanDir($aSiteInfo, $strTarget);

	PrintWithoutBreak("", "", 'file2');
	return $aOutput;
}


function MyCleanDir(&$aSiteInfo, $strTarget)
{
	$aContents = scandir($strTarget);
	foreach($aContents as $strContent)
		if(is_dir($strTarget."/".$strContent) && $strContent != "." && $strContent != "..")
			MyCleanDir($aSiteInfo, $strTarget."/".$strContent);

	$aContents = scandir($strTarget);
	if(count($aContents) == 2 && $strTarget != "/htdocs/Drupal/web/sites/".$aSiteInfo["BASICS"]['LowerSiteName']."/files/d7files")
		ExecCommand("rm -rf ".$strTarget);
}


function ErrorMessage($strMessage)
{
	system('printf "\e[48;5;196m\e[38;5;255m'.$strMessage.'\033[0m \n"');
}


function PrintWithoutBreak($strPre, $strString, $strGroupName)
{
	$strExt = " \r";
	$iCurrentLen = (strlen($strString)+strlen($strPre)+5);
	$strErase = '';
	if(isset($GLOBALS['PrintWithoutBreak-'.$strGroupName]) && $iCurrentLen < $GLOBALS['PrintWithoutBreak-'.$strGroupName])
		for($I=0; $I<=($GLOBALS['PrintWithoutBreak-'.$strGroupName]-$iCurrentLen); $I++)
			$strErase .= '\ ';

	$GLOBALS['PrintWithoutBreak-'.$strGroupName] = $iCurrentLen;

	$strDivider = '';
	if($strPre != "" && $strString != "")
		$strDivider = ": ";

	$strString = preg_replace('/[^A-Za-z0-9\-_ ]/', '', $strString);
	system("echo -n ".$strPre.$strDivider.$strString.$strErase.$strExt);
}


function CheckCode($iCode)
{
	if(($iCode >= 200 && $iCode <= 299) || $iCode == 301 || $iCode == 302)
		return true;
	else
		return false; 
}


function FindNumberOfOccurances($strStr, $strSubstr)
{
	$iLastPos = 0;
	$aResults = [];

	while (($iLastPos = strpos($strStr, $strSubstr, $iLastPos)) !== false)
	{
		$aResults[] = $iLastPos;
		$iLastPos = $iLastPos+strlen($strSubstr);
	}

	return count($aResults);
}


function AddReportRow(&$strMsg, $aRow)
{
	$strMsg .= 'Server: '.$aRow['Server'].'<br>';
	$strMsg .= 'Database: '.$aRow['Database'].'<br>';
	$strMsg .= 'Table: '.$aRow['Table'].', ID: '.$aRow['ID'].'<br>';

	$strMsg .= 'Page: <a href="'.$aRow['Page'].'">'.$aRow['Page'].'</a><br>';
	$strMsg .= 'Original Item: '.$aRow['Original'].'<br>';
	$strMsg .= 'New Item: '.$aRow['New'].'<br><br>';
}


function GetTag($strType)
{
	if($strType == "")
		return '<p data-conversion>';
	elseif($strType == "notice")
		return '<p data-conversion="notice">';
	elseif($strType == "warning")
		return '<p data-conversion="warning">';
	else
		return "";
}


function TrimForOldSearch($strSite, $strLink, $strType)
{
	$iAdditional = 2;
	if($strSite == "")
		$iAdditional = 1;
	if($strType == 2)
		$iAdditional--;

	$strOutput = substr($strLink, strlen($strSite)+$iAdditional);
	return $strOutput;
}


function AddDefect(&$aReport, $strLink, $iCode, $strCodeDesc, $iParagraphID, $strAlias)
{
	$aTemp = [];
	$aTemp['link'] = $strLink;
	$aTemp['code'] = $iCode;
	$aTemp['desc'] = $strCodeDesc;
	$aTemp['paragraph__field_text_area id'] = $iParagraphID;
	$aTemp['page_alias'] = $strAlias;

	$aReport[] = $aTemp;
}


function AddAttempt(&$aLink, $strLink, $iCode, $strCodeDesc)
{
	$aTry = [];
	$aTry['link'] = $strLink;
	$aTry['code'] = $iCode;
	$aTry['desc'] = $strCodeDesc;

	$aLink[] = $aTry;
}


function PrepPPHPInclude(&$strBody, $strContent, $strType)
{
	if($strType == "1")
	{
		$strContent = str_replace("<?php", GetTag('warning'), $strContent);
		$strContent = str_replace("?>", '</p>', $strContent);
		$strBody = $strContent;
	}
	else
	{
		$strContent = str_replace("\n", "<br>", $strContent);
		$strContent = GetTag('warning').$strContent.'</p>';
		$strBody = $strContent.'<br>'.$strBody;
	}
}


function MoveAndRegisterFile(&$aSiteInfo, $strFile, $iParagraphID, $strServer, $strSchema)
{
	if($strSchema != "")
		$strSite = strtolower(str_replace('Drupal', '', $strSchema));
	else
		$strSite = $aSiteInfo["BASICS"]["LowerSiteName"];

	$classSqlQuery = new SqlDataQueries();
	if($strSchema == "")
		$strSchema = $aSiteInfo["BASICS"]["DBName"];
	$classSqlQuery->SpecifyDB("", $strSchema, "", "");


	$strFileStagingDir = $aSiteInfo["BASICS"]["PathD7Files"].'/';
	$strSiteFilesDir = $aSiteInfo["BASICS"]["PathSites"].$strSite.'/files/d7files/';
	$strUUID = "";

	MakeDir("", $strSiteFilesDir, "wwwapache", "775");

	if(strstr($strFile, '/'))
	{
		$strDummySite = "";
		$aParts = explode("/", $strFile);
		for($X=0; $X<count($aParts)-1; $X++)
			$strDummySite .= $aParts[$X].'/';

		$strFileOnly = str_replace($strDummySite, "", $strFile);
		$strDummySite = $strSiteFilesDir.$strDummySite;

		if(!file_exists($strDummySite))
			MakeDir("", $strDummySite, "wwwapache", "775");
	}
	else
		$strFileOnly = $strFile;

	if(file_exists($strFileStagingDir.$strFile) && !file_exists($strSiteFilesDir.$strFile))
	{
		$strUUID = GetUUID($aSiteInfo, $strSchema, "file_managed");
		$strQuery = "INSERT INTO file_managed SET 
					 uuid='".addslashes($strUUID)."',
					 langcode='en',
					 uid='38',
					 filename='".addslashes($strFileOnly)."',
					 uri='public://".addslashes($strFile)."',
					 filemime='".addslashes(mime_content_type($strFileStagingDir.$strFile))."',
					 filesize='".filesize($strFileStagingDir.$strFile)."',
					 status=1,
					 created=".time().",
					 changed=".time();
		$aFileResults = $classSqlQuery->MySQL_Queries($strQuery);

		$strQuery = "INSERT INTO file_usage SET 
					 fid=".$aFileResults['ID'].",
					 module='editor',
					 type='paragraph',
					 id=".$iParagraphID.",
					 count=1";
		$classSqlQuery->MySQL_Queries($strQuery);
		if(rename($strFileStagingDir.$strFile, $strSiteFilesDir.$strFile) === false) { PrintR('Moving '.$strFileStagingDir.$strFile.' to '.$strSiteFilesDir.$strFile.' failed.'); die; }

		$strQuery = "UPDATE www_admin.drupal8_transition_links SET NewURL='".addslashes('/d7file/'.$strSite.'/'.$strFile)."'
					 WHERE OldSiteName='".addslashes($strSite)."'
					 AND OldURL='".addslashes('/'.$strSite.'/sites/'.$aSiteInfo["BASICS"]['OldSiteName'].'/files/UserFiles/'.$strFile)."'";
		$aTempResult = $classSqlQuery->MySQL_Queries($strQuery);
		if($aTempResult['rows'] != "1") { PrintR("Found ".$aTempResult['rows']." rows. Should have found 1. ".$strQuery); die;}
	}
	elseif(!file_exists($strFileStagingDir.$strFile) && file_exists($strSiteFilesDir.$strFile))
	{
		$strQuery = "SELECT * FROM file_managed WHERE filename='".addslashes($strFileOnly)."'";
		$aFileResults = $classSqlQuery->MySQL_Queries($strQuery);
		if(count($aFileResults) == 0) { PrintR("Couldn't find entry for file in file_managed: ".$strQuery); die; }

		$strQuery = "SELECT * FROM file_usage WHERE fid=".$aFileResults[0]['fid']." AND module='editor' AND type='paragraph' AND id=".$iParagraphID;
		$aFileUsageResults = $classSqlQuery->MySQL_Queries($strQuery);
		if(count($aFileUsageResults) == 0) 
		{
			$strQuery = "INSERT INTO file_usage SET 
						 fid=".$aFileResults[0]['fid'].",
						 module='editor',
						 type='paragraph',
						 id=".$iParagraphID.",
						 count=1";
			$classSqlQuery->MySQL_Queries($strQuery);
		}
		else
		{
			$strQuery = "UPDATE file_usage SET count=".($aFileUsageResults[0]['count']+1)." WHERE fid=".$aFileResults[0]['fid']." AND module='editor' AND type='paragraph' AND id=".$iParagraphID;
			$classSqlQuery->MySQL_Queries($strQuery);
		}

		$strUUID = $aFileResults[0]['uuid'];
	}
	elseif(!file_exists($strFileStagingDir.$strFile) && !file_exists($strSiteFilesDir.$strFile))
	{
		ReportIt($aSiteInfo, 3, "", $iParagraphID, $strFileStagingDir.$strFile, $strServer, $strSchema);
		$strUUID = "File not found. See report";
	}
	elseif(file_exists($strFileStagingDir.$strFile) && file_exists($strSiteFilesDir.$strFile))  { PrintR("File exists in both places: ".$strFileStagingDir.$strFile." AND ".$strSiteFilesDir.$strFile); die; }

	return $strUUID;
}


function NewSite_ModInstall(&$aSiteInfo)
{
	print"- INSTALL MIGRATION MODULE\n";
	$strCmd = "cd /htdocs/Drupal; /htdocs/Drupal/vendor/drupal/console/bin/drupal --uri=".GetURIFromSites($aSiteInfo)." module:install nmu_migration_assistant";
	ExecCommand($strCmd);
}


function TranslateTag($strTag, $strNewSrc, $strUUID)
{
	if(!strstr($strTag, 'alt=')) { PrintR("No alt tag found for: ".$strTag); die; }
	if(!strstr($strTag, 'src=')) { PrintR("No src tag found for: ".$strTag); die; }
	if(!strstr($strTag, 'src=')) { PrintR("No uuid sent with image: ".$strTag); die; }
	$aAttribsToIgnore = ["border-width", "border-style", "margin-top", "margin-bottom", "border", "margin-left", "margin-right", "text-align", "line-height", "margin", "font-size", "padding", "vertical-align", "max-width", "font-family", "font-size", "box-sizing", "cursor", "color", "background-color", "display", "padding-top"];
	$strOrigTag = $strTag;

	$aAddAttrs = []; $aStyleParts = [];
	$strAlt = explode('"', explode('alt="', $strTag)[1])[0];
	$strTag = str_replace(' alt="'.$strAlt.'"', '', $strTag);						

	$strSrc = explode('"', explode('src="', $strTag)[1])[0];
	$strTag = str_replace(' src="'.$strSrc.'"', '', $strTag);						

	$strStyle = "";
	if(strstr($strTag, 'style="'))
	{
		$strStyle = explode('"', explode('style="', $strTag)[1])[0];
		$strTag = str_replace(' style="'.$strStyle.'"', '', $strTag);						
	}

	while(strstr($strTag, '="'))
	{
		$strName = explode(' ', explode('="', $strTag)[0])[1];
		$strValue = explode('"', explode('="', $strTag)[1])[0];
		$aAddAttrs[$strName] = $strValue;
		$strTag = str_replace(' '.$strName.'="'.$strValue.'"', '', $strTag);						
	}


	$strNew = '<img';
	$strNew .= ' alt="'.$strAlt.'"';
	$strNew .= ' src="'.$strNewSrc.'"';


	if($strStyle != "")
	{
		$aStyleParts = [];

		$aParts = explode(";", $strStyle);
		foreach($aParts as $strPart)
		{
			if(trim($strPart) != "")
			{
				$aMoreParts = explode(":", $strPart);
				$aStyleParts[trim($aMoreParts[0])] = trim($aMoreParts[1]);
			}
		}
	}

	foreach($aStyleParts as $strName=>$strValue)
	{
		if(strstr($strNew, $strName.'=')) { PrintR("1. Attrib already exists. "); die; }

		$strValue = str_replace("px", "", $strValue);
		$strValue = str_replace(";", "", $strValue);

		if(strstr($strValue, "%"))
			break;

		if($strName == "float")
			$strNew .= ' data-align="'.$strValue.'"';
		elseif($strName == "width" || $strName == "height")
			$strNew .= ' '.$strName.'="'.$strValue.'"';
		elseif(in_array($strName, $aAttribsToIgnore))
			$strNew .= '';
		else { PrintR("Could not translate style for image: ".$strOrigTag." name: ".$strName); die; }
	}

	foreach($aAddAttrs as $strName=>$strValue)
	{
		if(strstr($strNew, $strName.'=')) { PrintR("1. Attrib already exists. "); die; }
		$strNew .= ' '.$strName.'="'.$strValue.'"';
	}

	$strNew .= ' data-entity-type="nmu-file" data-entity-uuid="'.$strUUID.'" />'; 

	return $strNew;
}


function MakeReplacement(&$aSiteInfo, $strItem)
{
	$strReplacement1 = '/'.$aSiteInfo["BASICS"]["LowerSiteName"].'/sites/'.$aSiteInfo["BASICS"]["OldSiteName"].'/files/UserFiles/';
	$strReplacement2 = '/sites/'.$aSiteInfo["BASICS"]["OldSiteName"].'/files/UserFiles/';

	$strFile = $strItem;
	if(strstr($strItem, $strReplacement1))
		$strFile = str_replace($strReplacement1, '', $strItem);
	elseif(strstr($strItem, $strReplacement2))
		$strFile = str_replace($strReplacement2, '', $strItem);

	if($strFile == $strItem) 
	{
		PrintR($strReplacement1, 'strReplacement1');
		PrintR($strReplacement2, 'strReplacement2');
		PrintR($strFile, 'strFile');
		PrintR($strItem, 'strItem');
		PrintR("No replacement made in (1)"); 
		die; 
	}
	if(substr($strFile, 0, 1) == '/')
		$strFile = substr($strFile, 1);

	return $strFile;
}


function GetStrong($strString)
{
	return '<strong>'.$strString.'</strong>';
}


function GetIt($strBody, $iLastSpot, $strType, $strToGet)
{
	if($strToGet == "item")
	{
		if(strstr($strType, 'src='))
			$iReplaceStart = $iLastSpot+strlen('src=')+1;
		else
			$iReplaceStart = $iLastSpot+strlen($strType)+1;
		$strFirstHalf = substr($strBody, $iReplaceStart);

		$iLengthToReplace = 0;
		while(substr($strFirstHalf, $iLengthToReplace, 1) != '"' && substr($strFirstHalf, $iLengthToReplace, 1) != "'")
			$iLengthToReplace++;
		$strItem = substr($strFirstHalf, 0, $iLengthToReplace);

		$aTemp = [];
		$aTemp['iReplaceStart'] = $iReplaceStart;
		$aTemp['iLengthToReplace'] = $iLengthToReplace;
		$aTemp['strItem'] = $strItem;

		return $aTemp;
	}

	if($strToGet == "reftype")
	{
		$iSpotOpenBracket = $iLastSpot;
		while(substr($strBody, $iSpotOpenBracket, 1) != '<')
			$iSpotOpenBracket--;
		$iSpotOpenBracket++;

		$iSpotTemp = $iSpotOpenBracket;
		while(substr($strBody, $iSpotTemp, 1) != ' ')
			$iSpotTemp++;
		$strRefType = substr($strBody, $iSpotOpenBracket, $iSpotTemp-$iSpotOpenBracket);

		return $strRefType;
	}

	if($strToGet == "tag")
	{
		$iSpotOpenBracket = $iLastSpot;
		while(substr($strBody, $iSpotOpenBracket, 1) != '<')
			$iSpotOpenBracket--;
		$iSpotOpenBracket++;

		$iReplaceStart = $iSpotOpenBracket-1;

		while(substr($strBody, $iSpotOpenBracket, 1) != '>')
			$iSpotOpenBracket++;
		$iLengthToReplace = $iSpotOpenBracket-$iReplaceStart+1;
		$strFullImgTag = substr($strBody, $iReplaceStart, $iLengthToReplace);

		$aTemp = [];
		$aTemp['iReplaceStart'] = $iReplaceStart;
		$aTemp['iLengthToReplace'] = $iLengthToReplace;
		$aTemp['strFullImgTag'] = $strFullImgTag;

		return $aTemp;
	}

	if($strToGet == "href_text")
	{
		$iTextStart = $iLastSpot;
		while(substr($strBody, $iTextStart, 1) != '>')
			$iTextStart++;
		$iTextStart++;

		$iTextEnd = $iTextStart;
		while(substr($strBody, $iTextEnd, 1) != '<')
			$iTextEnd++;

		$strHREF_Text = substr($strBody, $iTextStart, $iTextEnd-$iTextStart);

		return $strHREF_Text;
	}
}


function StripURL(&$strItem)
{
	if(substr($strItem, 0, 4) == "http")
	{
		if(substr($strItem, 0, 5) == "https")
			$strItem = str_replace("https://", "", $strItem);
		else
			$strItem = str_replace("http://", "", $strItem);
	}

	if(substr($strItem, 0, 3) == "www")
		$strItem = str_replace("www.", "", $strItem);

	if(substr($strItem, 0, 2) == "d7")
		$strItem = str_replace("d7.", "", $strItem);

	if(substr($strItem, 0, 7) == "nmu.edu")
		$strItem = str_replace("nmu.edu", "", $strItem);
}


function ReportIt(&$aSiteInfo, $iIssue, $iNID, $iPID, $strAdditional, $strServer, $strSchema)
{
	$strFranklin_URL = ""; $strCharlie_URL = ""; 
	$classFranklinSqlQuery = new SqlDataQueries();
	$classFranklinSqlQuery->SpecifyDB("", $strSchema, "", "");

	if($iNID == "" && $iPID != "")
	{
		if($strServer == "charlie")
			$iNID = $iPID;
		else
		{
			$strQuery = "SELECT entity_id FROM node__field_ct_cards WHERE field_ct_cards_target_id=".$iPID;
			$aTempResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);
			if(count($aTempResults) == 0) { PrintR("This should not be: ".$strQuery." returned nothing"); die; }
			$iNID = $aTempResults[0]['entity_id'];
		}
	}
	$strFranklin_URL = 'https://dev.nmu.edu/'.$aSiteInfo["BASICS"]["LowerSiteName"].'/node/'.$iNID;

	if($iNID != "")
	{
		if($strServer == "charlie")
			$strCharlie_URL = 'https://www.nmu.edu/'.$aSiteInfo["BASICS"]["LowerSiteName"].'/node/'.$iNID;
		else
		{
			$strQuery = "SELECT OldURL FROM www_admin.drupal8_transition_links WHERE NewURL='/node/".$iNID."' AND OldSiteName='".$aSiteInfo["BASICS"]["LowerSiteName"]."'";
			$aTempResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);
			if(count($aTempResults) > 0)
				$strCharlie_URL = 'https://www.nmu.edu/'.$aSiteInfo["BASICS"]["LowerSiteName"].$aTempResults[0]['OldURL'];
			else
				$strCharlie_URL = 'unavailable';
		}
	}


	$aTemp = []; 
	if($iIssue == 1)
	{
		$aTemp['Issue'] = GetStrong("Could not find translation");
		$aTemp['Note'] = "There is broken link on the following page.";
		$aTemp['Link Text'] = $strAdditional;
		$aTemp['Link'] = $strCharlie_URL;
	}

	if($iIssue == 2)
	{
		$aTemp['Issue'] = GetStrong("Title not found");
		$aTemp['Note'] = "The magnificent one is unable to find a title for the following page.";
		$aTemp['Franklin_URL'] = "n/a";
		$aTemp['Charlie_URL'] = $strCharlie_URL;
	}

	if($iIssue == 3)
	{
		$aTemp['Issue'] = GetStrong("File not found");
		$aTemp['Note'] = "The following page references a file that cannot be found. The file may be an image, pdf, etc. ";
		$aTemp['POO'] = $strAdditional;
		$aTemp['Franklin_URL'] = $strFranklin_URL;
		$aTemp['Charlie_URL'] = $strCharlie_URL;
	}


	$aSiteInfo['REPORTS']['ConversionIssues']['report_group'.$iIssue][] = $aTemp;
}


function NoteIt(&$aSiteInfo, $strSite, $strOldURL, $strNewURL, $iID, $strHrefText, $strSchema, $strServer, $strType)
{
	$aTemp = [];
	$aTemp['Site'] = $strSite;

	$strSiteLower = strtolower(str_replace('Drupal', '', $strSite));
	if($strSite != "Drupal")
		$strSiteLower .= '/';

	if($strServer == "charlie")
	{
		$aTemp['NodeID'] = $iID;
		$aTemp['PageLink'] = 'https://d7.nmu.edu/'.$strSiteLower.'node/'.$iID;
	}
	else
	{
		$classSqlQuery = new SqlDataQueries();
		$classSqlQuery->SpecifyDB("", $strSchema, "", "");
		$strQuery = "SELECT entity_id FROM node__field_ct_cards WHERE field_ct_cards_target_id=".$iID;
		$aResults = $classSqlQuery->MySQL_Queries($strQuery);
		if(count($aResults) > 0)
		{
			$aTemp['NodeID'] = $aResults[0]['entity_id'].', pid: '.$iID;
			$aTemp['PageLink'] = 'https://nmu.edu/'.$strSiteLower.'node/'.$aResults[0]['entity_id'];
		}
		else
		{
			$aTemp['NodeID'] = 'unavailable. pid: '.$iID;
			$aTemp['PageLink'] = 'unavailable';
		}
	}

	if($strHrefText != "")
		$aTemp['LinkText'] = $strLinkText;
	$aTemp['Host'] = $strServer;
	$aTemp['Type'] = $strType;

	if(!strstr($strType, 'src="'))
	{
		if(!strstr($strOldURL, 'nmu.edu'))
			$strOldHREF = 'https://d7.nmu.edu'.$strOldURL;
		else
			$strOldHREF = $strOldURL;
		$strNewHREF = 'https://www.nmu.edu'.$strNewURL;

		$aTemp['OldURL'] = '<a href="'.$strOldHREF.'">'.$strOldURL.'</a>';
		$aTemp['NewURL'] = '<a href="'.$strNewHREF.'">'.$strNewURL.'</a>';
	}
	else
	{
		$aTemp['OldURL'] = str_replace('<','', str_replace('/>', '', substr($strOldURL, 1)));
		$aTemp['NewURL'] = str_replace('<','', str_replace('/>', '', substr($strNewURL, 1)));
	}


	$aSiteInfo['REPORTS']['FinalLinks'][] = $aTemp;
}


function HandleReport($aSiteInfo)
{
	$strTestRun = '';
	if($aSiteInfo['BASICS']['RUN_TYPE'] != "Full")
		$strTestRun = ' (test run)';
	$strMsg = '<h2>'.ucfirst($aSiteInfo["BASICS"]["LowerSiteName"]).' - '.ucfirst($aSiteInfo["BASICS"]["ConversionType"]).' Site Report '.$strTestRun.'</h2>';

	if($aSiteInfo["BASICS"]["ConversionType"] == "initial")
	{
		foreach($aSiteInfo['REPORTS']['ConversionIssues'] as $aGroup)
		{
			if(count($aGroup) > 0)
			{
				$iFirst = true;
				foreach($aGroup as $aItem)
				{
					foreach($aItem as $strName=>$strValue)
					{
						if($iFirst && $strName == "Issue")
							$strMsg .= $strName.": ".$strValue.'<br>';

						if($iFirst && $strName == "Note")
							$strMsg .= $strName.": ".$strValue.'<br>';

						if($strName != "Issue" && $strName != "Note")
							$strMsg .= $strName.": ".$strValue.'<br>';
					}
					$strMsg .= '<br>';

					$iFirst = false;
				}
			}
		}
	}
	

	if($aSiteInfo["BASICS"]["ConversionType"] == "final")
	{
		foreach($aSiteInfo['REPORTS']['FinalLinks'] as $aChange)
		{
			$strMsg .= "Site: ".$aChange['Site'].'<br>';
			$strMsg .= "NodeID: ".$aChange['NodeID'].', Link: <a href="'.$aChange['PageLink'].'">'.$aChange['PageLink'].'</a><br>';
			$strMsg .= "Host: ".$aChange['Host'].'<br>';
			$strMsg .= "Old: ".$aChange['OldURL'].'<br>';
			$strMsg .= "New: ".$aChange['NewURL'].'<br><br>';
		}
	}


	$classMail = new SendMail(0);
	$classMail->SendMail_CommandLineSend("Conversion Results for ".$aSiteInfo["BASICS"]["LowerSiteName"], $strMsg, $aSiteInfo['BASICS']['REPORT_REC'], "aquinn@nmu.edu", "Burt Reynolds");

	print"- SCAN COMPLETE, REPORT EMAILED\n";
}


function GetAliasTrans(&$aSiteInfo, $strNewEntry, $strType)
{
	$classSqlQuery = new SqlDataQueries();
	if($strType == "old")
		$classSqlQuery->SpecifyDB(Const_connCharlieHost, $aSiteInfo["BASICS"]["DBName"], "", "");
	else
		$classSqlQuery->SpecifyDB("", $aSiteInfo["BASICS"]["DBName"], "", "");

	$strURL = "";
	$strAliased = "";
	if(substr($strNewEntry, 0, strlen($aSiteInfo["BASICS"]["LowerSiteName"])+5) == $aSiteInfo["BASICS"]["LowerSiteName"]."/node")
	{
		$strURL = $strNewEntry;
		if($strType == "old")
			$strQuery = "SELECT alias FROM url_alias WHERE source='".addslashes(TrimForOldSearch($aSiteInfo["BASICS"]["LowerSiteName"], $strURL, 1))."'";
		else
			$strQuery = "SELECT alias FROM path_alias WHERE path='".addslashes(TrimForOldSearch($aSiteInfo["BASICS"]["LowerSiteName"], $strURL, 2))."'";
		$aResultsTemp = $classSqlQuery->MySQL_Queries($strQuery);
		if(count($aResultsTemp) > 0)
			$strAliased = $aResultsTemp[0]['alias'];
		else { PrintR("Make alias for ".$strURL."! (914)"); die; }
	}
	elseif($strNewEntry == "/".$aSiteInfo["BASICS"]["LowerSiteName"])
	{
		$strAliased = $strNewEntry;
		$strURL = $strNewEntry;
	}
	elseif(substr($strNewEntry, 1, strlen($aSiteInfo["BASICS"]["LowerSiteName"])) == $aSiteInfo["BASICS"]["LowerSiteName"])
	{
		$strAliased = $strNewEntry;
		if($strType == "old")
			$strQuery = "SELECT source as TURD FROM ".$aSiteInfo["BASICS"]["DBName"].".url_alias WHERE alias='".addslashes(TrimForOldSearch($aSiteInfo["BASICS"]["LowerSiteName"], $strAliased, 1))."'";
		else
			$strQuery = "SELECT path as TURD FROM ".$aSiteInfo["BASICS"]["DBName"].".path_alias WHERE alias='".addslashes(TrimForOldSearch($aSiteInfo["BASICS"]["LowerSiteName"], $strAliased, 2))."'";
		$aResultsTemp = $classSqlQuery->MySQL_Queries($strQuery);
		if(count($aResultsTemp) > 0)
			$strURL = $aResultsTemp[0]['TURD'];
		else
		{
			$strURL = '/'.$aSiteInfo["BASICS"]["LowerSiteName"];
			$strAliased = '/'.$aSiteInfo["BASICS"]["LowerSiteName"];
		}
	}
	else ## offsite OR ALTERNATIVE NMU SITE
	{
		$strURL = $strNewEntry;
		$strAliased = $strNewEntry;
	}

	if(substr($strURL, 0, 1) != '/')
		$strURL = '/'.$strURL;

	$aTemp = [];
	$aTemp['URL'] = $strURL;
	$aTemp['AliasedURL'] = str_replace('/'.$aSiteInfo["BASICS"]["LowerSiteName"], '', $strAliased);

	return $aTemp;
}


function DetermineOutsideSiteLocationForFile(&$aSiteInfo, $strFile, $strServer, $iID, $strSchema)
{
	$strNewPath = ""; $strUUID = "";
	$strSite = strtolower(str_replace('Drupal', '', $strSchema));

	$classSqlQuery = new SqlDataQueries();
	if($strServer == "charlie")
		$strSiteFilesDir = '/htdocs/Drupal/sites/'.$strSchema.'/files/UserFiles/';
	else
	{
		$strSiteFilesDir = $aSiteInfo["BASICS"]["PathSites"].$strSite.'/files/d7files/';
		MakeDir("", $strSiteFilesDir, "wwwapache", "775");
	}


	if(strstr($strFile, '/'))
	{
		$strDummySite = "";
		$aParts = explode("/", $strFile);
		for($X=0; $X<count($aParts)-1; $X++)
			$strDummySite .= $aParts[$X].'/';

		$strFileOnly = str_replace($strDummySite, "", $strFile);
		$strDummySite = $strSiteFilesDir.$strDummySite;

		if(!file_exists($strDummySite))
			MakeDir("", $strDummySite, "wwwapache", "775");
	}
	else
		$strFileOnly = $strFile;


	### EXISTS IN NEW SITE
	if(file_exists($aSiteInfo["BASICS"]["PathSites"].$aSiteInfo["BASICS"]["LowerSiteName"].'/files/'.$strFile))
	{
		$strNewPath = '/'.$aSiteInfo["BASICS"]["LowerSiteName"].'/sites/'.$aSiteInfo["BASICS"]["LowerSiteName"].'/files/'.$strFile;
		if($strServer == "charlie")
			$strNewPath = 'https://dev.nmu.edu/'.$strNewPath;

PrintR("1111");
PrintR($strNewPath);
die;

		if($strServer == "frank")
		{
			$strQuery = "SELECT uuid FROM ".$aSiteInfo["BASICS"]["DBName"].".file_managed WHERE filename='".addslashes($strFileOnly)."'";
			$aResults = $classSqlQuery->MySQL_Queries($strQuery);
			if(count($aResults) == 0) { PrintR("Hammer 27, Veto Lifespan"); die; }
			$strUUID = $aResults[0]['uuid'];
PrintR("2222");
PrintR($strQuery);
PrintR($aResults);
PrintR($strUUID);
die;
		}
	}


	### EXISTS IN D7
	if($strNewPath == "" && file_exists($aSiteInfo["BASICS"]["PathD7Files"].$strFile))
	{
		if($strServer == "frank")
		{
PrintR("FLATUS 3");
			$strUUID = MoveAndRegisterFile($aSiteInfo, $strFile, $iID, $strServer, $strSchema);
			$strNewPath = '/'.$strSite.'/sites/'.$strSite.'/files/'.$strFile;
PrintR("3333");
PrintR($strUUID);
PrintR($strNewPath);
die;
		}
		else
		{
			$strNewPath = 'https://dev.nmu.edu/'.$aSiteInfo["BASICS"]["HttpPathD7Files"].$strFile;
PrintR("4444");
PrintR($strNewPath);
die;
		}
	}


	### LOOK FOR IT ON CHARLIE
	if($strNewPath == "")
	{
		$strPhysicalPath = '/htdocs/Drupal/sites/'.$aSiteInfo["BASICS"]["DBName"].'/files/UserFiles/'.$strFile;

		$strCMD = 'ssh '.$aSiteInfo["BASICS"]["Username"].'@charlie.nmu.edu "if [ -f '.$strPhysicalPath.'  ]; then echo \"exists\"; else echo \"\"; fi"';
		$strOutcome = shell_exec($strCMD);
		if(trim($strOutcome) == "exists")
		{
			if($strServer == "charlie")
			{
				$strNewSub = 'migration_merge';
				$strCMD = 'ssh '.$aSiteInfo["BASICS"]["Username"].'@charlie.nmu.edu "mkdir -p /htdocs/Drupal/sites/'.$strSchema.'/files/UserFiles/'.$strNewSub.'/; chgrp wwwapache /htdocs/Drupal/sites/'.$strSchema.'/files/UserFiles/'.$strNewSub.'/;"';
				ExecCommand($strCMD);

				$strCMD = 'ssh '.$aSiteInfo["BASICS"]["Username"].'@charlie.nmu.edu cp "'.$strPhysicalPath.' /htdocs/Drupal/sites/'.$strSchema.'/files/UserFiles/'.$strNewSub.'/; chgrp wwwapache /htdocs/Drupal/sites/'.$strSchema.'/files/UserFiles/'.$strNewSub.'/'.$strFileOnly.';"';
				shell_exec($strCMD);
				$strNewPath = '/'.$strSite.'/sites/'.$strSchema.'/files/UserFiles/'.$strNewSub.'/'.$strFileOnly;
			}
			else
			{
				$strCMD = 'scp -o StrictHostKeyChecking=no '.$aSiteInfo["BASICS"]["Username"].'@charlie.nmu.edu:'.$strPhysicalPath.' '.$aSiteInfo["BASICS"]["PathSites"].'/'.$strSite.'/files/';
				ExecCommand($strCMD);
				$strUUID = MoveAndRegisterFile($aSiteInfo, $strFile, $iID, $strServer, $strSchema);
				$strNewPath = '/'.$strSite.'/sites/'.$strSite.'/files/'.$strFile;
			}
		}
	}

	$aResponse = [];
	$aResponse['uuid'] = $strUUID;
	$aResponse['path'] = $strNewPath;

	return $aResponse;
}


function GetUUID(&$aSiteInfo, $strDB, $strTable)
{
	$classSqlQuery = new SqlDataQueries();
	$strUUID = $classSqlQuery->MySQL_Queries("SELECT * FROM www_admin.drupal8_uuids WHERE Used=0 LIMIT 1")[0]['uuid'];

	if($aSiteInfo['BASICS']['RUN_TYPE'] == 'Full')
		$classSqlQuery->MySQL_Queries("UPDATE www_admin.drupal8_uuids SET Used=1 WHERE uuid='".$strUUID."'");
	else
		$classSqlQuery->MySQL_Queries("UPDATE www_admin.drupal8_uuids SET Used=2 WHERE uuid='".$strUUID."'");

	### CONFIRM UNIQUE
	if($strDB == "")
		$strDB = $aSiteInfo["BASICS"]["DBName"];
	if(count($classSqlQuery->MySQL_Queries("SELECT * FROM ".$strDB.".".$strTable." WHERE uuid='".$strUUID."'")) > 0) { PrintR("UUID NOT UNIQUE!"); die; }

	return $strUUID;
}


function GenerateUUIDs(&$aSiteInfo, $strDummySite, $iGroupsOf5KToGen)
{
	$strDummySiteDir = strtolower(str_replace("Drupal", "", $strDummySite));
	$iTime = time()-2;

	$classSqlQuery = new SqlDataQueries();
	$classSqlQuery->SpecifyDB("", $strDummySite, "", "");

	for($I=1; $I<=$iGroupsOf5KToGen; $I++)
	{
		$iStart = time();
		$strCMD = 'cd /htdocs/Drupal/web/sites/'.$strDummySiteDir.'; echo "no/n" | /htdocs/Drupal/vendor/drupal/console/bin/drupal --uri='.$strDummySiteDir.' create:nodes internal_page --limit="10000" --title-words=1 --time-range=1 --language="en"';
		ExecCommand($strCMD);

		try 
		{
			$aNewUUIDResults = $classSqlQuery->MySQL_Queries("SELECT uuid FROM node, node_field_data WHERE node.nid=node_field_data.nid AND node_field_data.changed>".$iTime." AND node.type='internal_page'");
			foreach($aNewUUIDResults as $aRow)
				$classSqlQuery->MySQL_Queries("INSERT INTO www_admin.drupal8_uuids SET uuid='".addslashes($aRow['uuid'])."', Used=0");
		}
		catch (Exception $ex)
		{
			print "\WARNING: ".$ex->getMessage()."\n\n";
		}

		$classSqlQuery->MySQL_Queries("DELETE FROM path_alias");
		$classSqlQuery->MySQL_Queries("DELETE FROM path_alias_revision");
		$classSqlQuery->MySQL_Queries("DELETE FROM node");
		$classSqlQuery->MySQL_Queries("DELETE FROM node_field_data");
		$classSqlQuery->MySQL_Queries("DELETE FROM node_revision");
		$classSqlQuery->MySQL_Queries("DELETE FROM node_field_revision");

		$classSqlQuery->MySQL_Queries("ALTER TABLE path_alias AUTO_INCREMENT=1");
		$classSqlQuery->MySQL_Queries("ALTER TABLE path_alias_revision AUTO_INCREMENT=1");
		$classSqlQuery->MySQL_Queries("ALTER TABLE node AUTO_INCREMENT=1");
		$classSqlQuery->MySQL_Queries("ALTER TABLE node_revision AUTO_INCREMENT=1");

		PrintR("Completed batch in: ".(time()-$iStart));
	}

	PrintR("DONE");
}


function WrapItUp(&$aSiteInfo)
{
	$classFranklinSqlQuery = new SqlDataQueries();
	$strQuery = "UPDATE www_admin.drupal8_uuids SET Used=0 WHERE Used=2";
	$classFranklinSqlQuery->MySQL_Queries($strQuery);
}


function SetupRunType(&$aSiteInfo, $bIncludeEric, $bIncludeKelsey, $bRunType)
{
	$aSiteInfo['BASICS']['RUN_TYPE'] = $bRunType;
	
	$aSiteInfo['BASICS']['REPORT_REC'] = 'apquinn@gmail.com';

	if($bIncludeEric)
		$aSiteInfo['BASICS']['REPORT_REC'] .= ',ericjohn@nmu.edu';

	if($bIncludeKelsey)
		$aSiteInfo['BASICS']['REPORT_REC'] .= ',kpotes@nmu.edu';
}


function Fix(&$aSiteInfo)
{
	$classFranklinSqlQuery = new SqlDataQueries();
	$classFranklinSqlQuery->SpecifyDB("", "www_admin", "", "");

	/*
	$strQuery = "SELECT * FROM drupal8_transition_links";
	$aResults = $classFranklinSqlQuery->MySQL_Queries($strQuery);
	foreach($aResults as $aRow)
	{
		if(substr($aRow['NewURL'], 0, strlen('/'.$aRow['OldSiteName'])) == '/'.$aRow['OldSiteName'] || substr($aRow['NewAliasedURL'], 0, strlen('/'.$aRow['OldSiteName'])) == '/'.$aRow['OldSiteName'])
		{
			$strQuery = "UPDATE drupal8_transition_links SET
						NewURL='".str_replace('/'.$aRow['OldSiteName'], '', $aRow['NewURL'])."', 
						NewAliasedURL='".str_replace('/'.$aRow['OldSiteName'], '', $aRow['NewAliasedURL'])."'
						WHERE ID=".$aRow['ID'];
			$classFranklinSqlQuery->MySQL_Queries($strQuery);
		}	
	}
	*/


	/*
	$classFranklinSqlQuery = new SqlDataQueries();
	$classFranklinSqlQuery->SpecifyDB("", "Drupal", "", "");
	$strQuery = "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'Drupa%' AND SCHEMA_NAME!='DrupalDefaultBackkup' AND SCHEMA_NAME!='DrupalFoundation' ORDER BY SCHEMA_NAME";
	$aFranklinDrupalDBs = $classFranklinSqlQuery->MySQL_Queries($strQuery);
	foreach($aFranklinDrupalDBs as $aDBRow)
	{
		# image_gallery_grid
		# block_embed
		$strQuery = "SELECT id FROM ".$aDBRow["SCHEMA_NAME"].".paragraphs_item WHERE type='block_embed'";
		$aFranklinDrupalPIDs = $classFranklinSqlQuery->MySQL_Queries($strQuery);
		foreach($aFranklinDrupalPIDs AS $aPID)
		{
			$strQuery = "SELECT entity_id FROM ".$aDBRow["SCHEMA_NAME"].".node__field_ct_cards WHERE field_ct_cards_target_id='".$aPID['id']."'";
			$aFranklinDrupalNIDs = $classFranklinSqlQuery->MySQL_Queries($strQuery);
			foreach($aFranklinDrupalNIDs as $aNID)
			{
				print 'https://nmu.edu/'.strtolower(str_replace("Drupal", "", $aDBRow["SCHEMA_NAME"]))."/node/".$aNID['entity_id']."\n";
			}

			$strQuery = "SELECT entity_id FROM ".$aDBRow["SCHEMA_NAME"].".node__field_ct_block_cards WHERE field_ct_block_cards_target_id='".$aPID['id']."'";
			$aFranklinDrupalNIDs = $classFranklinSqlQuery->MySQL_Queries($strQuery);
			foreach($aFranklinDrupalNIDs as $aNID)
			{
				print 'https://nmu.edu/'.strtolower(str_replace("Drupal", "", $aDBRow["SCHEMA_NAME"]))."/node/".$aNID['entity_id']."\n";
			}

			$strQuery = "SELECT entity_id FROM ".$aDBRow["SCHEMA_NAME"].".node__field_ct_webform_cards WHERE field_ct_webform_cards_target_id='".$aPID['id']."'";
			$aFranklinDrupalNIDs = $classFranklinSqlQuery->MySQL_Queries($strQuery);
			foreach($aFranklinDrupalNIDs as $aNID)
			{
				print 'https://nmu.edu/'.strtolower(str_replace("Drupal", "", $aDBRow["SCHEMA_NAME"]))."/node/".$aNID['entity_id']."\n";
			}
		}
	}
	*/

	PrintR("boogar");
	die;
}





MainSiteMgmt($argv[1], $argv[2], $argv[3], $argv[4], $strSourceDir);

