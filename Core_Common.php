<?php
date_default_timezone_set('America/New_York');
$GLOBALS['argv'] = $argv;

define("Const_connCharlieHost", "charlie.nmu.edu");
define("Const_connHost", "localhost"); #Franklin
define("Const_connDB", "www_webadmin");
define("Const_connUser", "aquinn");
define("Const_connPSW", "QJeep1a1.");
define("Const_Success", "Const_Success");
define("TempDumpDir", "/tmp/ServerBackups");
define("BaseDir", "~/Linode");


class BaseClass
{
	protected $classSession;
	protected $bIsPersistant;
	protected $strObjName = "";
	protected $strStorageName = "BaseClassNameStorage";
	protected $classSqlQuery;

	protected function __construct($aArgs=[])
	{
		try
		{
			$this->classSession = new SessionMgmt();

			$this->bIsPersistant = $aArgs['bIsPersistant'] ?? false;

			$this->BaseClass_StoreNameAndType($aArgs);

			if($aArgs['bIsPersistant'] === true && (!isset($aArgs['bReset']) || $aArgs['bReset'] === false))
				$this->BaseClass_SelfLoad();

			$this->BaseClass_StoreSelf();
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	protected function BaseClass_StoreNameAndType($aArgs)
	{
		try
		{
			if (($aArgs['strObjName'] == null || $aArgs['strObjName'] == "") && $aArgs['bIsPersistant'] != false)
				throw new Exception('You must provide an object name for a persistant class. It can be anything. It is used so that it can be loaded when you request the object on another page. (failed in BaseClass_StoreName)');

			elseif ($aArgs['strObjName'] != "" && in_array($aArgs['strObjName'], $GLOBALS[$this->strStorageName]) && $aArgs['bIsPersistant'] == false)
				throw new Exception('The object name "'.$this->strObjName.'" has already been used. It must be unique for a given page. On future pages you will need to reference it using it name if you want it to be persistant. (failed in BaseClass_StoreName)');

			elseif ($aArgs['strObjName'] == null || $aArgs['strObjName'] == "")
			{
				$iUniqueName = str_replace(" ", "", str_replace("0.", "", microtime()));
				while(in_array($iUniqueName, $GLOBALS[$this->strStorageName]))
					$iUniqueName .= 1;

				$GLOBALS[$this->strStorageName][] = $iUniqueName;
				$this->strObjName = $iUniqueName;
			}
			elseif ($this->strObjName != null )
			{
				$GLOBALS[$this->strStorageName][] = $aArgs['strObjName'];
				$this->strObjName = $aArgs['strObjName'];
			}
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	function BaseClass_SelfDump()
	{
		try
		{
			PrintR($this);
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	protected function BaseClass_SelfLoad()
	{
		try
		{
			$aSessions = $this->classSession->SessionMgmt_Select($this->strObjName);
			foreach ($aSessions as $strName=>$strValue)
				$this->{$strName} = $strValue;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	protected function BaseClass_StoreSelf()
	{
		try
		{
			$this->classSession->SessionMgmt_Set($this->strObjName, $this);
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	function __destruct()
	{
		#print'<pre>';
		#print_r($this);
		#print'</pre>';
		#		if(!$this->bIsPersistant) {
		#			$classSession->SessionMgmt_DeleteValue($this->strObjName);
		#		}
	}
}


class TimeTrack
{
	private $aMarkPoints;
	private $iStartTime;
	private $iEndTime;
	private $strFilename;
	private $iTimeMark;
	private $strTitle;

	function TimeTrack($strFilename, $strTitle)
	{
		$this->aMarkPoints = array();
		$this->iStartTime = time();
		$this->iTimeMark = time();
		$this->strFilename = $strFilename;
		$this->strTitle = $strTitle;

		$strMessage = $strTitle." started at ".date("H:i:s", $this->iStartTime)."\n";
		$this->OutputMsg($strMessage);
	}

	function MarkTrack($strName)
	{
		$aTemp = array();
		$aTemp['Name'] = $strName;
		$aTemp['Time'] = time();

		$this->aMarkPoints[] = $aTemp;

		$this->PrintTrack($aTemp['Name'], $aTemp['Time']);
	}

	function EndTrack()
	{
		$this->iEndTime = time();

		$iTotalDuration = $this->iEndTime-$this->iStartTime;
		$strMessage = $this->strTitle." ended at ".date("H:i:s", $this->iEndTime).". Total duration: ".$iTotalDuration." seconds";

		if($iTotalDuration > 90)
			$strMessage .= " (".round($iTotalDuration/60, 1)." minutes)";

		 $strMessage .= "\n";

		$this->OutputMsg($strMessage);
	}

	private function PrintTrack($strMarkName, $iCurrentTimeMark)
	{
		$iDuration = ($iCurrentTimeMark - $this->iTimeMark);
		$strMessage = $strMarkName." took ".$iDuration." seconds";
		if($iDuration > 90)
			$strMessage .= " (".round($iDuration/60, 1)." minutes)";

		$strMessage .= ".\n";

		$this->iTimeMark = $iCurrentTimeMark;

		$this->OutputMsg($strMessage);
	}

	private function OutputMsg($strMessage)
	{
		if($this->strFilename != "")
		{
			$strFinalMsg = "";
			if(file_exists($this->strFilename))
				$strFinalMsg = file_get_contents($this->strFilename);

			$strFinalMsg .= $strMessage;

			file_put_contents($this->strFilename, $strFinalMsg);
			chmod($this->strFilename, 0777);
		}
		else
			echo $strMessage;
	}
}


function ReadInBootstrap()
{
	$file_handle = fopen("/htdocs/Drupal/DREWTEMP/scripts/UserConfig/Bootstrap-Common.sh", "r");

	while (!feof($file_handle))
	{
		$strLine = fgets($file_handle);
		if(strstr($strLine, "="))
		{
			$aParts = explode("=", $strLine);
			$GLOBALS[$aParts[0]] = str_replace('"', "", trim($aParts[1]));
		}
	}
	fclose($file_handle);
}


function HandleError($strError)
{
	print "An error has occcured: ".$strError."\n\n";
}


function FormatSize($size)
{
	$mod = 1000;
	$units = explode(' ','B KB MB GB TB PB');

	for ($i = 0; $size > $mod; $i++)
		$size /= $mod;

	return round($size, 2) . ' ' . $units[$i];
}


function PrintR()
{
	$iNumArgsSent = func_num_args();
	$aArgs = func_get_args();

	$objToPrint = $aArgs[0];
	if($iNumArgsSent >= 2)
		$strPreText = $aArgs[1];
	if($iNumArgsSent >= 3)
		$bOverride = $aArgs[2];

	if(!isset($strPreText) || $strPreText == "")
	{
		if(!isset($GLOBALS['PrintR_PRETEXT']) || $GLOBALS['PrintR_PRETEXT'] == "")
			$GLOBALS['PrintR_PRETEXT'] = 1;
		else
			$GLOBALS['PrintR_PRETEXT']++;

		$strPreText = $GLOBALS['PrintR_PRETEXT'];
	}

	if(!isset($strPreText) || $strPreText == "")
		$strPreText = "here: ";

	if(is_array($objToPrint) || is_object($objToPrint))
	{
		print $strPreText.":\n";
		print_r($objToPrint);
		print"\n\n";
	}
	else
		print $strPreText.': '.$objToPrint."\n\n";
}


function CommandLineErrorHandler($strMessage)
{
	$strCurrent = "";

	if(file_exists("/Users/$Username/Desktop/Issue.log"))
		$strCurrent = file_get_contents("/Users/$Username/Desktop/Issue.log");

	file_put_contents("/Users/$Username/Desktop/Issue.log", $strCurrent."\n".$strMessage);
}




class ErrorHandler extends BaseClass
{
	protected $classSqlQuery;
	private $strSessionName = 'ErrorHandler';
	public static $bNotifyAdmin = false;

	function __construct($aArgs=[])
	{
		try
		{
			parent::__construct($aArgs);
			$this->classSqlQuery = new SqlDataQueries();
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	public static function ErrorHandler_MessageDisplay()
	{
		try
		{
			$iPage = 0;
			if (isset($_REQUEST["page"]) && $_REQUEST["page"] != "")
				$iPage = $_REQUEST["page"];
			elseif(CORE_GetQueryStringVar("page") != "")
				$iPage = CORE_GetQueryStringVar("page");

			if($iPage > 0)
			{
				$classSqlQuery = new SqlDataQueries();
				$strQuery = "SELECT  Title FROM cms_admin_comp WHERE ID=".$iPage;
				$aResults = $classSqlQuery->MySQL_Queries($strQuery);

				print'<h2>'.$aResults[0]['Title'].'</h2>';
			}


			if (isset($_SESSION['cmsAdmin_PositiveOutcome']) && $_SESSION['cmsAdmin_PositiveOutcome'] != "")
				print'<div class="row"><div class="col-sm-10" style="color:#cc6633">'.$_SESSION['cmsAdmin_PositiveOutcome'].'</div></div>';
			$_SESSION['cmsAdmin_PositiveOutcome'] = "";

			if (isset($_SESSION['cmsAdmin_PositiveOutcome']) && $_SESSION['cmsAdmin_PositiveOutcome'] != "")
				print'<div class="row"><div class="col-sm-10" style="color:#cc6633">'.$_SESSION['cmsAdmin_PositiveOutcome'].'</div></div>';
			$_SESSION['cmsAdmin_NoticeOutcome'] = "";


			$classSession = new SessionMgmt();
			$aValues = $classSession->SessionMgmt_Select("ErrorHandler-Warning");
			if ($aValues["outcome"] == "Warning")
			{
				print'<div class="row"><div class="col-sm-10" style="color:#EB984E">'.$aValues["outcome-message"].'</div></div>';

				if(isset($aValues['outcome-corrections']) && count($aValues['outcome-corrections']) > 0)
				{
					print'<div class="row" style="padding:0px 0px 15px 0px;"><ul>';
					foreach ($aValues['outcome-corrections'] as $strMessage)
						print'<div class="col-sm-10"><li>'.$strMessage.'</li></div>';
					print'</ul></div>';
				}

				$classSession->SessionMgmt_DeleteValue("ErrorHandler-Warning");
			}
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	public static function ErrorHandler_HandleCorrections($aIssues)
	{
		try
		{
			if (isset($aIssues) && count($aIssues) > 0)
			{
				$aValues = [];
				$classSession = new SessionMgmt();

				$aValues["outcome"] = "Warning";
				$aValues["outcome-message"] = "The following issues need to be corrected:";
				$aValues["outcome-corrections"] = $aIssues;
				$classSession->SessionMgmt_Set("ErrorHandler-Warning", $aValues);

				$_REQUEST[Const_Action] = $_REQUEST[Const_Action] ?? "";
				$_REQUEST[Const_Phase] = $_REQUEST[Const_Phase] ?? "";
				$_REQUEST[Const_ElementID] = $_REQUEST[Const_ElementID] ?? "";
				$_REQUEST[Const_Subaction] = $_REQUEST[Const_Subaction] ?? "";

				$strURL = CORE_GetURL(Const_ParentURL, $_REQUEST[Const_Action], $_REQUEST[Const_Phase], $_REQUEST[Const_ElementID], $_REQUEST[Const_Subaction], Const_Error);
				header("Location: ".$strURL);
				die;
			}
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	public static function ErrorHandler_CatchError($ex, $aAdditional = [])
	{
		try
		{
			$classSqlQuery = new SqlDataQueries();

			if (is_object($ex))
				$strErrorMsg = $ex->getMessage();
			else
				$strErrorMsg = $ex;

			PrintR($strErrorMsg);
			die;
		}
		catch (Exception $ex)
		{
			print'A severe error has occured. Please try again or contact the NMU web team at edesign@nmu.edu';
			die;
		}
	}
}


class MiscFunctions
{
	static function MiscFunctions_StartSession()
	{
		try
		{
			$strResult = session_id();
			if (empty($strResult))
				session_start();

			return true;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}
}


class SessionMgmt
{
	private $strSessionID = "";
	private $classSqlQuery = "";

	function __construct()
	{
		try
		{
			$this->classSqlQuery = new SqlDataQueries();
			$this->classSqlQuery->SpecifyDB(Const_connCharlieHost, "", "", "");

			if(isset($_SESSION['SessionMgmt_SessionID']) && $_SESSION['SessionMgmt_SessionID'] != "")
				$this->strSessionID = $_SESSION['SessionMgmt_SessionID'];
			else
				$this->strSessionID = session_id();

			$this->SessionMgmt_TouchSession();
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function SessionMgmt_SetSessionID($iID)
	{
		try
		{
			if($iID != "")
			{
				$this->strSessionID = $iID;
				$_SESSION['SessionMgmt_SessionID'] = $iID;

				$this->SessionMgmt_TouchSession();
			}

			return $this->strSessionID;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function SessionMgmt_GetSessionID()
	{
		try
		{
			$this->SessionMgmt_TouchSession();

			return $this->strSessionID;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function SessionMgmt_Set($strFieldName, $objObject)
	{
		try
		{
			$this->SessionMgmt_TouchSession();

			$aSessions = $this->SessionMgmt_SelectAll();
			$aSessions[$strFieldName] = $objObject;
			$strQuery = "UPDATE www_admin.sessionmgmt_session_storage SET SessionData='".addslashes(serialize($aSessions))."' WHERE SessionID='".addslashes($this->strSessionID)."'";
			$this->classSqlQuery->MySQL_Queries($strQuery);

			return true;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function SessionMgmt_Select($strFieldName)
	{
		try
		{
			$this->SessionMgmt_TouchSession();

			$aResults = $this->SessionMgmt_SelectAll();

			if(isset($aResults[$strFieldName]))
				return $aResults[$strFieldName];
			else
				return [];
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function SessionMgmt_SelectAll()
	{
		try
		{
			$this->SessionMgmt_TouchSession();

			$strQuery = "SELECT * FROM www_admin.sessionmgmt_session_storage WHERE SessionID='".addslashes($this->strSessionID)."'";
			$aResults = $this->classSqlQuery->MySQL_Queries($strQuery);

			if (count($aResults) > 0)
				return unserialize($aResults[0]['SessionData']);
			else
				return [];
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	function SessionMgmt_DeleteValue($strFieldName)
	{
		try
		{
			$this->SessionMgmt_TouchSession();

			if (!isset($strFieldName) || $strFieldName == "")
				ErrorHandler::ErrorHandler_CatchError("FiledName is required for SessionMgmt_DeleteValue");
			else
			{
				$aSessions = $this->SessionMgmt_SelectAll();

				if (is_array($aSessions) && count($aSessions) > 0 && isset($aSessions[$strFieldName])) {
					unset($aSessions[$strFieldName]);

					$strQuery = "UPDATE www_admin.sessionmgmt_session_storage SET SessionData='".addslashes(serialize($aSessions))."', LastTouchDate='".time()."' WHERE SessionID='".addslashes($this->strSessionID)."'";
					$this->classSqlQuery->MySQL_Queries($strQuery);
				}
			}
			return true;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function SessionMgmt_DeleteAll()
	{
		try
		{
			$this->SessionMgmt_TouchSession();

			$strQuery = "UPDATE FROM www_admin.sessionmgmt_session_storage SET SessionData='' WHERE SessionID='".addslashes($this->strSessionID)."'";
			$this->classSqlQuery->MySQL_Queries($strQuery);
			return true;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	private function SessionMgmt_CreateSession()
	{
		try
		{
			$iTime = time();
			$strTime = date("n-j-Y G:ia");

			$strQuery = "INSERT INTO www_admin.sessionmgmt_session_storage SET SessionID='".addslashes($this->strSessionID)."', SessionData='', LastTouchDate='".$iTime."', LastTouchDateStr='".addslashes($strTime)."'";
			$this->classSqlQuery->MySQL_Queries($strQuery);
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	private function SessionMgmt_TouchSession()
	{
		try
		{
			$strQuery = "DELETE FROM www_admin.sessionmgmt_session_storage WHERE LastTouchDate<".(time() - ini_get("session.gc_maxlifetime"));
			$this->classSqlQuery->MySQL_Queries($strQuery);

			$strQuery = "SELECT ID FROM www_admin.sessionmgmt_session_storage WHERE SessionID='".addslashes($this->strSessionID)."' ORDER BY ID DESC";
			$aResults = $this->classSqlQuery->MySQL_Queries($strQuery);

			if (count($aResults) == 0)
				$this->SessionMgmt_CreateSession();
			elseif(count($aResults) > 1) {
				$this->SessionMgmt_DestroySession();
				$this->SessionMgmt_CreateSession();
			}
			else {
				$iTime = time();
				$strTime = date("n-j-Y G:ia");

				$strQuery = "UPDATE www_admin.sessionmgmt_session_storage SET LastTouchDate='".$iTime."', LastTouchDateStr='".addslashes($strTime)."' WHERE SessionID='".addslashes($this->strSessionID)."'";
				$this->classSqlQuery->MySQL_Queries($strQuery);
			}

			return true;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	private function SessionMgmt_DestroySession()
	{
		try
		{
			$strQuery = "DELETE FROM www_admin.sessionmgmt_session_storage WHERE SessionID='".addslashes($this->strSessionID)."'";
			$this->classSqlQuery->MySQL_Queries($strQuery);
			return true;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}
}


class SqlDataQueries
{
	private $host = Const_connHost;
	private $dbname = Const_connDB;
	private $user = Const_connUser;
	private $password = Const_connPSW;
	private $dbConnection;
	private $dbTransEnabled = false;

	function __construct()
	{
		try
		{
			return Const_Success;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function __destruct()
	{
		try
		{
			$this->Disconnect();
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function GetConnectInfo()
	{
		try
		{
			$aInfo[] = $this->host;
			$aInfo[] = $this->dbname;
			$aInfo[] = $this->user;
			$aInfo[] = $this->password;
			$aInfo[] = $this->dbConnection;
			$aInfo[] = $this->dbTransEnabled;
			return $aInfo;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function SpecifyDB($strHost, $strDB, $strUser, $strPassword)
	{
		try
		{
			if ($strHost != "")
				$this->host = $strHost;
			if ($strDB != "")
				$this->dbname = $strDB;
			if ($strUser != "")
				$this->user = $strUser;
			if ($strPassword != "")
				$this->password = $strPassword;
			return Const_Success;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function Transaction_Start()
	{
		try
		{
			$this->Connect();
			$this->dbConnection->autocommit(FALSE);
			$this->dbTransEnabled = true;
			return Const_Success;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function Transaction_Commit()
	{
		try
		{
			if($this->dbTransEnabled == true)
			{
				$this->dbTransEnabled = false;
				$this->dbConnection->commit();
			}

			return Const_Success;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function Transaction_Rollback()
	{
		try
		{
			if ($this->dbTransEnabled == true)
			{
				$this->dbTransEnabled = false;
				$this->dbConnection->rollback();
			}
			return Const_Success;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function MySQL_Queries($strQuery)
	{
		try
		{
			if (isset($_SESSION['Counter']))
				$_SESSION['Counter'] += 1;
			else
				$_SESSION['Counter'] = 1;

			$aResults = [];

			$this->Connect();
			if (!$objResult = $this->dbConnection->query($strQuery))
			{
				$strErrMessage = $this->dbConnection->error;

				if ($this->dbTransEnabled)
					$this->Transaction_Rollback();
				ErrorHandler::ErrorHandler_CatchError("MySQL Error: ".$strErrMessage, [$strQuery]);
			}

			if (strpos(' SELECT ', $this->FirstWord($strQuery)))
			{
				if ($objResult->num_rows > 0)
				{
					$indx = 0;
					while ($aResults[$indx] = $objResult->fetch_assoc())
						$indx++;

					unset($aResults[$indx]);
				}

				$objResult->close();
			}

			if (strpos(' INSERT UPDATE DELETE', $this->FirstWord($strQuery)))
			{
				$aResults['rows'] = $this->dbConnection->affected_rows;
				if ($this->FirstWord($strQuery) == 'INSERT')
				{
					$aResults['insertid'] = $this->dbConnection->insert_id;
					$aResults['ID'] = $this->dbConnection->insert_id;
				}
			}

			if (!$this->dbTransEnabled)
				$this->Disconnect();

			return $aResults;
		}
		catch (Exception $ex)
		{
			if ($this->dbTransEnabled)
				$this->Transaction_Rollback();

			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function Fetch_Fields($strTable)
	{
		try
		{
			$arrFields = [];

			$this->Connect();
			if (!$objResult = $this->dbConnection->query("SELECT * FROM `".$strTable."` LIMIT 1")) {
				if ($this->dbTransEnabled)
					$this->Transaction_Rollback();
				ErrorHandler::ErrorHandler_CatchError("MySQL Error: ".$this->dbConnection->error);
			}
			$objFields = $objResult->fetch_fields();

			foreach ($objFields as $field)
				$arrFields[$field->name] = $field->name;
			return $arrFields;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	private function Connect()
	{
		try
		{
			if (!($this->dbConnection))
			{
				$this->dbConnection = new mysqli($this->host, $this->user, $this->password, $this->dbname);
				if ($this->dbConnection->connect_errno)
				{
					if ($this->dbTransEnabled)
						$this->Transaction_Rollback();
					ErrorHandler::ErrorHandler_CatchError("Unable to connect to DB: ".$this->dbConnection->connect_error);
				}

				if (!$objResult = $this->dbConnection->query("SET NAMES utf8")) {
					if ($this->dbTransEnabled)
						$this->Transaction_Rollback();
					ErrorHandler::ErrorHandler_CatchError("MySQL Error: ".$this->dbConnection->error);
				}
			}
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	private function Disconnect()
	{
		try
		{
			if ($this->dbConnection)
				$this->dbConnection->close();
			$this->dbConnection = false;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	private function FirstWord($strString)
	{
		try
		{
			$words = preg_split("/[\s,]+/", strtoupper($strString));
			if (count($words) == 0) {
					if ($this->dbTransEnabled)
						$this->Transaction_Rollback();
					ErrorHandler::ErrorHandler_CatchError("Query string appears corrupt or empty. ");
			}

			return $words[0];
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}
}



class SystemAdmin
{
	private $strBackFileLocation = "/htdocs/Webb/HeaderRebuildBackups/";
	protected $classSqlQuery = "";


	function __construct($aArgs=[])
	{
		try
		{
			$this->classSqlQuery = new SqlDataQueries();

		}

		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function SystemAdmin_ServerBan($iIP, $iCurrentTime, $strUserAgent, $strScriptURL, $strProtectedForm, $strWhose)
	{
		try
		{
			$strQuery = 'INSERT INTO cms_form_gateway (`ip_addr`, `submit_date`, `user_agent`, `script_url`, `ban`, `form`, `whose`) VALUES (\''.$iIP.'\', \''.$iCurrentTime.'\', \''.$strUserAgent.'\', \''.$strScriptURL.'\', \'1\', \''.$strProtectedForm.'\', \''.$strWhose.'\');';
			$this->classSqlQuery->MySQL_Queries($strQuery);

			//fail2ban is watching our error logs for this message and will trigger a ban if it is seen
			error_log('ban-this-user', 0);
			echo '<h1>Banned</h1><p>Your IP address has been banned from this server.  Please contact <a href="mailto:edesign@nmu.edu">edesign@nmu.edu</a> if you believe this ban was not warranted.</p>';

			exit;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function SystemAdmin_CheckDBSize($strDBName)
	{
		try
		{
			$strWhere = "";
			if ($strDBName != "")
				$strWhere = " WHERE SCHEMA_NAME='".addslashes($strDBName)."'";

			$strQuery = "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ".$strWhere." ORDER BY SCHEMA_NAME";
			$aResults = $this->classSqlQuery->MySQL_Queries($strQuery);

			$iTotal = 0;
			foreach ($aResults as $aRow)
				$iTotal += $this->SystemAdmin_ListSizes($aRow['SCHEMA_NAME'], "", false);

			if ($iTotal > 0)
				print'<div class="col col-lw col-text-right">Total Size</div><div class="col">'.round($iTotal / 1000, 2).' GB</div>';

			return true;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}

	function SystemAdmin_CheckTableSize($strDBName, $strTableName)
	{
		try
		{
			$this->SystemAdmin_ListSizes($strDBName, $strTableName, true);
			return true;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	private function SystemAdmin_ListSizes($strDBName, $strTableName, $bShowTables)
	{
		try
		{
			if ($strDBName == "" && $strTableName != "")
				throw new Exception("If you send a table name, you must also send a database name.");

			if (!isset($GLOBALS['SystemAdmin_ListSizes']) || $GLOBALS['SystemAdmin_ListSizes'] == "")
			{
				print'<style>
				.col {
					display:inline-block;
					padding-right:10px;
				}

				.col-w {
					min-width:475px;
				}

				.col-text-right {
					text-align:right;
				}

				.col-lw {
					min-width:275px;
				}
				</style>';
			}

			$strWhere = "";
			if ($strDBName != "")
				$strWhere = " WHERE TABLE_SCHEMA='".$strDBName."' ";
			if ($strTableName != "")
				$strWhere .= " AND table_name='".$strTableName."' ";

			$strQuery = "SELECT TABLE_SCHEMA, table_name AS 'Table', round(((data_length + index_length) / 1024 / 1024), 2) 'size' FROM information_schema.TABLES ".$strWhere." ORDER BY size DESC";
			$aResults = $this->classSqlQuery->MySQL_Queries($strQuery);

			$iSize = 0;
			foreach ($aResults as $aRow)
			{
				if ($bShowTables)
				{
					print'<div class="col col-w">'.$aRow['TABLE_SCHEMA'].' - '.$aRow['Table'].'</div><div class="col">'.round($aRow['size'], 2).' mb</div>';
					print'<div style="clear:both;"></div>';
				}

				$iSize += $aRow['size'];
			}

			if (!$bShowTables && $iSize > 0)
				print'<div class="col col-lw">'.$strTableName.'</div><div class="col">'.round($iSize, 2).' mb</div>';
			elseif ($bShowTables && $iSize > 0)
				print'<div class="col col-w col-text-right">Total Size</div><div class="col">'.round($iSize, 2).' mb</div>';
			print'<div style="clear:both; padding-bottom:10px;"></div>';

			return $iSize;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	function SystemAdmin_Print($aArgs, $strType)
	{
		try
		{
			$strPreText = "";

			$objToPrint = $aArgs[0];
			if (count($aArgs) >= 2)
				$strPreText = $aArgs[1];

			if ($strPreText == "")
			{
				if (!isset($GLOBALS['PrintR_PRETEXT']) || $GLOBALS['PrintR_PRETEXT'] == "")
					$GLOBALS['PrintR_PRETEXT'] = 1;
				else
					$GLOBALS['PrintR_PRETEXT']++;

				$strPreText = $GLOBALS['PrintR_PRETEXT'];
			}


			if (SystemAdmin::SystemAdmin_Debug_GetMode() || $strType == "Everyone")
			{
				if ($strType == "Commandline")
					$strBreak = "\n";
				else
					$strBreak = "<br>";

				if ($strType == "Strip" && (is_array($objToPrint) || is_object($objToPrint)))
					$objToPrint = json_decode(str_replace("<", "", json_encode($objToPrint)));
				elseif ($strType == "Strip" && (!is_array($objToPrint) && !is_object($objToPrint)))
					$objToPrint = str_replace("<", "", $objToPrint);

				if (is_array($objToPrint) || is_object($objToPrint))
				{
					print'<div style="color:red; font-size:larger">'.$strPreText.'</div>: '.$strBreak.'<pre>';
					print_r($objToPrint);
					print"</pre>".$strBreak.$strBreak;
				}
				else
					print '<div style="color:red; font-size:larger">'.$strPreText.'</div>: '.$objToPrint.$strBreak.$strBreak;
			}
			return true;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	public static function SystemAdmin_Debug_GetMode()
	{
		try
		{
			if(isset($_COOKIE['umc_debug_flag']) && $_COOKIE['umc_debug_flag'] == "debug")
				return true;

			if(isset($_SERVER['USER']))
			{
				$aInfo = SystemAdmin::SystemAdmin_Debug_GetAdminInfo();

				foreach($aInfo as $aEntry)
					if($_SERVER['USER'] == $aEntry['Username'])
						return true;
			}

			if(isset($_SERVER["SHELL"]) && $_SERVER["SHELL"] != "")
				return true;

			return false;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	public static function SystemAdmin_Debug_SetMode($strAction)
	{
		try
		{
			if ($strAction == "set")
				setcookie("umc_debug_flag", "debug", 0, "/", "nmu.edu", false, false);
			else
				setcookie("umc_debug_flag", "", 0, "/", "nmu.edu", false, false);
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	public static function SystemAdmin_Debug_CheckDomain()
	{
		try
		{
			if(isset($_SERVER['HTTP_HOST']))
			{
				$aInfo = SystemAdmin::SystemAdmin_Debug_GetAdminInfo();

				foreach($aInfo as $aEntry)
					foreach($aEntry['Domains'] as $strEntry)
						if($_SERVER['HTTP_HOST'] == $strEntry)
							return true;
			}

			return false;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	public static function SystemAdmin_Debug_CheckSSOUserLoggedIn()
	{
		try
		{
			if(isset($_SESSION[Const_sLoginName]))
			{
				$aInfo = SystemAdmin::SystemAdmin_Debug_GetAdminInfo();

				foreach($aInfo as $aEntry)

					if($_SESSION[Const_sLoginName] == $aEntry['Username'])
						return true;
			}

			return false;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	private static function SystemAdmin_Debug_GetAdminInfo()
	{
		try
		{
			$aDrewIPs = ["198.110.203.200",  "198.110.203.172",  "198.110.203.173",  "198.110.203.128",  "198.110.203.197",  "204.38.63.79"];
			$aDrewDomains = ["aqvm1.nmu.edu",  "aqvm2.nmu.edu"];

			$aEricIPs = ["198.110.203.106",  "198.110.203.107",  "198.110.203.203",  "198.110.203.73",  "204.38.63.71"];
			$aEricDomains = ["ejvm.nmu.edu",  "ejvm1.nmu.edu",  "ejvm2.nmu.edu"];

			$aMikeIPs = ["198.110.203.215",  "198.110.203.113",  "204.38.63.68"];
			$aMikeDomains = ["mkvm1.nmu.edu",  "mkvm2.nmu.edu"];

			$aCmdIPs = ["::1"];
			$aLocalIPs = ["127.0.0.1"];

			$aAllInfo =[["GroupName" => "Drew", "Username" => "aquinn", "IPGroup" => $aDrewIPs, "Domains" => $aDrewDomains, "DebugPath" => "/htdocs/Webb/DebugDump/Drew.txt"],
						["GroupName" => "Drew", "Username" => "drewquinn", "IPGroup" => $aDrewIPs, "Domains" => $aDrewDomains, "DebugPath" => "/htdocs/Webb/DebugDump/Drew.txt"],
						["GroupName" => "CmdLine", "Username" => "", "IPGroup" => $aCmdIPs, "Domains" => [], "DebugPath" => "/htdocs/Webb/DebugDump/Commandline.txt"],
						["GroupName" => "LocalHost", "Username" => "", "IPGroup" => $aLocalIPs, "Domains" => [], "DebugPath" => "/htdocs/Webb/DebugDump/Localhost.txt"]];

			return $aAllInfo;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}
}


class SendMail
{
	private $objMailer;
	private $strPlainTextMsg = "";

	function __construct($iDebugLevel)
	{
		try
		{
			require_once '/htdocs/cmsphp/Includes/vendor/autoload.php';
			$this->objMailer = new PHPMailer\PHPMailer\PHPMailer();

			//Enable SMTP debugging: 0 = off (for production use), 1 = client messages, 2 = client and server messages, 4 includes all messages
			if ($iDebugLevel == "")
				$iDebugLevel = 0;
			$this->objMailer->SMTPDebug = $iDebugLevel;

			$this->objMailer->isSMTP();
			$this->objMailer->Host = 'mailgateway.nmu.edu';
			$this->objMailer->Port = 587;
			$this->objMailer->SMTPAuth = true;
			$this->objMailer->AuthType = 'LOGIN';
			$this->objMailer->Username = 'edesign';
			$this->objMailer->Password = 'p1ckl35';
			$this->objMailer->SMTPOptions = [
				'ssl' => [
					'verify_peer'       => false,
					'verify_peer_name'  => false,
					'allow_self_signed' => true
				]
			];

			$this->objMailer->SMTPSecure = 'tls';
			$this->objMailer->Debugoutput = 'html';
			$this->objMailer->CharSet = 'UTF-8';

			return true;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}


	function SendMail_CommandLineSend($strSubject, $strMsg, $strRecipients, $strFrom, $strFromName)
	{
		try
		{
			if ($strFrom == "")
				$strFrom = "edesign@nmu.edu";
			if ($strFromName == "")
				$strFromName = "NMU e-design team";

			$this->objMailer->AddReplyTo($strFrom, $strFromName);
			$this->objMailer->SetFrom($strFrom, $strFromName);
			$this->objMailer->Subject = $strSubject;
			$this->objMailer->Body = $strMsg;
			$this->objMailer->AltBody = "To view the message, please use an HTML compatible email viewer.";

			if(strstr($strRecipients, ','))
			{
				$aParts = explode(',', $strRecipients);
				foreach($aParts as $strPerson)
					$this->objMailer->addAddress($strPerson, '');
			}
			else
				$this->objMailer->addAddress($strRecipients, '');

			if (!$this->objMailer->send())
				throw new Exception("PHP Mailer Error: ".$this->objMailer->ErrorInfo);

			return true;
		}
		catch (Exception $ex)
		{
			ErrorHandler::ErrorHandler_CatchError($ex);
		}
	}
}

?>
