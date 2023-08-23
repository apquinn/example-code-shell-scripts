#!/usr/bin/php
<?php

require_once "/Linode/scripts/ShellScripts/Core_Common.php";

function NMU_AutoLoad($strClassName)
{
	if(preg_match("/^[A-Za-z_]+$/", $strClassName)) {
		require_once "/htdocs/cmsphp/Includes/Classes/NMU_$strClassName.class.php";
	}
}

spl_autoload_register('NMU_AutoLoad');


function Main($strType)
{
	try
	{
		$strPad = "                                                        ";
		$aSites = yaml_parse_file("/htdocs/Drupal/drush/sites/self.site.yml");

		foreach($aSites as $strName=>$aSite)
		{
			if(strstr($strName, "-dev") || $strName == "dev")
			{
				if(strstr($strName, "-"))
					$strProdName = str_replace("-dev", "-prod", $strName);
				else
					$strProdName = str_replace("dev", "prod", $strName);

				if($strType == "all" || $strType == "db")
				{
#					echo "--- Syncing sites:		$strName - syncing db ".$strPad."\r";

                    exec('/htdocs/Drupal/vendor/bin/drush -y @'.$strName.' sql-create 2>/dev/null');
#                   exec('/htdocs/Drupal/vendor/bin/drush -y sql-sync @'.$strProdName.' @'.$strName.' 2>/dev/null');
                    exec('/htdocs/Drupal/vendor/bin/drush -y sql-sync @'.$strProdName.' @'.$strName);
				}

				if($strType == "all" || $strType == "files")
				{
					echo "--- Syncing sites:		$strName - copying files dir ".$strPad."\r";

					$strFiles = "/htdocs/Drupal/web/sites/";
					if($strName == "dev")
						$strFiles .= "default/";
					else
						$strFiles .= $aSite["uri"]."/";
					$strFiles .= "files";

					if(!file_exists($strFiles))
						exec("sudo mkdir -p ".$strFiles."; sudo chmod 770 ".$strFiles."; sudo chown aquinn ".$strFiles."; sudo chgrp wwwapache ".$strFiles);
					exec('/htdocs/Drupal/vendor/bin/drush -y rsync @'.$strProdName.':%files @'.$strName.':%files --mode=rlpvz 2>/dev/null');
				}


				if($strType == "all" || $strType == "db")
				{
					echo "--- Syncing sites:		$strName - clearing cache ".$strPad."\r";
					exec('/htdocs/Drupal/vendor/bin/drush -y @'.$strName.' cr 2>/dev/null');
				}
			}
		}
		echo "--- Syncing sites:".$strPad."\r";
		echo "--- Syncing sites:		done ".$strPad."\n";
	}
	catch (Exception $ex)
	{
		HandleError($ex->getMessage());
	}
}


Main($argv[1]);

?>