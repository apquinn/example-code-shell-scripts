<?php

$strHost					= $argv[1];
$GLOBALS['HostSharePath']	= $argv[2];
$GLOBALS['HtdocsLocation']	= $argv[3];
$strTestAction				= $argv[4];
$GLOBALS['TestKey']			= $argv[5];

require_once $GLOBALS['HostSharePath']."/Scripts/Core_Common.php";
require_once "/".$GLOBALS['HtdocsLocation']."/cmsphp/Includes/2015_Functions_Common.php";


function TestRunner_Main($strHost, $strTestAction)
{
	try
	{
		if($strTestAction == 'SuiteListAll')
			ListAllSuites();
		if($strTestAction == 'SuiteList')
			ListSuite($strSuiteID);
		if($strTestAction == 'SuiteListTests')
			ListSuiteTests($strSuiteID);
		if($strTestAction == 'SuiteExecAll')
			ExecSuiteAllTests($strSuiteID, $strHost);

		if($strTestAction == 'TestsListAll')
			TestsListAll();
		if($strTestAction == 'TestsGet')
			TestsGet($strTestID);
		if($strTestAction == 'TestsExec')
			TestsExec($strHost, $strTestID);


		if($strTestAction == '')
			WalkThrough();
	}
	catch (Exception $ex)
	{
		print Const_emSQLError.": <BR>".$ex->getMessage();
	}
}


function WalkThrough()
{
	try
	{
		$aServers = FindBootstrapVariablesOtherWhere("Address", "CanTest", "yes");
		foreach($aServers as $strServer)
		{
			$aTemp = array();
			$aTemp['Name'] = $strServer;
			$aTemp['ID'] = $strServer;
			
			$strFinalServers[] = $aTemp;
		}

		$aServerResponse = Inquire("Which server would you like to test?", $strFinalServers, false);
		$strHost = $aServerResponse[0]['ID'];
		if($strHost == "charlie.nmu.edu")
			$strHost = "www.nmu.edu";

		$aResults = ListAllSuites();
		$aSuiteResponse = Inquire("Which test suite would you like to run against ".$strHost."?", $aResults, true);

		$strTestResults = array();
		if(count($aSuiteResponse) > 1)
		{
			foreach($aSuiteResponse as $aSuite)
				$strTestResults[$aSuite['ID']] = ExecSuiteAllTests($aSuite['ID'], $strHost);
		}
		else
		{
			$aResults = ListSuiteTests($aSuiteResponse[0]['ID']);
			$aTestResponse = Inquire("Which test from the suite '".$aSuiteResponse[0]['Name']."' would you like to run?", $aResults, true);

			if(count($aTestResponse) > 1)
				$strTestResults[$aSuiteResponse[0]['ID']] = ExecSuiteAllTests($aSuiteResponse[0]['ID'], $strHost);
			else
				$strTestResults = TestsExec($strHost, $aTestResponse[0]['ID']);
		}

		DisplayTestResults($strTestResults, $strHost);
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function Inquire($strText, $aResults, $bAddAll)
{
	try
	{
		$iCount = 1;
		$strResponse = "--";
		while(!is_numeric($strResponse) || $strResponse > $iCount)
		{
			foreach($aResults as $aRow)
				print $iCount++." - ".$aRow['Name']."\n";
			if($bAddAll)
				print $iCount." - run all\n";
			print"\n";

			if($strResponse != "--")
				print "Invalid response. ";
			print $strText." ";

			$fhStandardInHandle = fopen ("php://stdin","r");
			$strResponse = trim(fgets($fhStandardInHandle));

			print"\n\n";
		}

		if($strResponse == count($aResults)+1)
			return $aResults;
		else
		{
			$aTemp = array();
			$aTemp[] = $aResults[$strResponse-1];

			return $aTemp;
		}
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function TestsExec($strHost, $strTestID)
{
	try
	{
		$aNameResults = TestsGet($strTestID);
		print "Executing test '".$aNameResults['Name']."' on host '".$strHost."' \n";

		$curlHandle = curl_init("https://api.ghostinspector.com/v1/tests/".$strTestID."/execute/?apiKey=".$GLOBALS['TestKey']."&startUrl=".$strHost);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
		$aResults = json_decode(curl_exec($curlHandle), true);

		curl_close($curlHandle);

		$aFinalResults = ExtractSingleTestResults($aResults, $strHost);

		return $aFinalResults;
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function TestsGet($strTestID)
{
	try
	{
		$curlHandle = curl_init("https://api.ghostinspector.com/v1/tests/".$strTestID."/?apiKey=".$GLOBALS['TestKey']);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
		$aResults = json_decode(curl_exec($curlHandle), true);

		curl_close($curlHandle);

		if($aResults['code'] == "SUCCESS")
		{
			$aFinalResults = array();
			$aFinalResults['Name'] = $aResults['data']['name'];
			$aFinalResults['ID'] = $aResults['data']['_id'];

			return $aFinalResults;
		}
		else
			return "failure";
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function TestsListAll()
{
	try
	{
		$curlHandle = curl_init("https://api.ghostinspector.com/v1/tests/?apiKey=".$GLOBALS['TestKey']);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
		$aResults = json_decode(curl_exec($curlHandle), true);

		curl_close($curlHandle);

		$aFinalResults = ExtractTestInfo($aResults);

		return $aFinalResults;
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function ExecSuiteAllTests($strSuiteID, $strHost)
{
	try
	{
		$aNameResults = ListSuite($strSuiteID);
		print "Executing tests for suite '".$aNameResults['Name']."' on host '".$strHost."' \n";

		$curlHandle = curl_init("https://api.ghostinspector.com/v1/suites/".$strSuiteID."/execute/?apiKey=".$GLOBALS['TestKey']."&startUrl=".$strHost);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
		$aResults = json_decode(curl_exec($curlHandle), true);

		curl_close($curlHandle);

		$aFinalResults = ExtractTestResults($aResults, $strHost);

		return $aFinalResults;

	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function ListSuiteTests($strSuiteID)
{
	try
	{
		$curlHandle = curl_init("https://api.ghostinspector.com/v1/suites/".$strSuiteID."/tests/?apiKey=".$GLOBALS['TestKey']);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
		$aResults = json_decode(curl_exec($curlHandle), true);

		curl_close($curlHandle);
	
		if($aResults['code'] == "SUCCESS")
		{
			$aFinalResults = array();
			foreach($aResults['data'] as $aResult)
			{
				$aTemp = array();
				$aTemp['Name'] = $aResult['name'];
				$aTemp['ID'] = $aResult['_id'];

				$aFinalResults[] = $aTemp;
			}

			return $aFinalResults;
		}
		else
			return "failure";
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function ListSuite($strSuiteID)
{
	try
	{
		$curlHandle = curl_init("https://api.ghostinspector.com/v1/suites/".$strSuiteID."/?apiKey=".$GLOBALS['TestKey']);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
		$aResults = json_decode(curl_exec($curlHandle), true);

		curl_close($curlHandle);
	
		if($aResults['code'] == "SUCCESS")
		{
			$aFinalResults = array();
			$aFinalResults['Name'] = $aResults['data']['name'];
			$aFinalResults['ID'] = $aResults['data']['_id'];

			return $aFinalResults;
		}
		else
			return "failure";
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function ListAllSuites()
{
	try
	{
		$curlHandle = curl_init("https://api.ghostinspector.com/v1/suites/?apiKey=".$GLOBALS['TestKey']);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
		$aResults = json_decode(curl_exec($curlHandle), true);

		curl_close($curlHandle);
	
		if($aResults['code'] == "SUCCESS")
		{
			$aFinalResults = array();
			foreach($aResults['data'] as $aResult)
			{
				$aTemp = array();
				$aTemp['Name'] = $aResult['name'];
				$aTemp['ID'] = $aResult['_id'];

				$aFinalResults[] = $aTemp;
			}

			return $aFinalResults;
		}
		else
			return "failure";
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}




#######################################
########## PRIVATE FUNCTIONS ##########

function ExtractTestInfo($aResults, $strHost)
{
	try
	{
		foreach($aResults['data'] as $aResult)
		{
			$aTemp = array();

			$aTemp['OrgName'] = $aResult['organization']['name'];
			$aTemp['OrgID'] = $aResult['organization']['_id'];

			$aTemp['SuiteName'] = $aResult['suite']['name'];
			$aTemp['SuiteID'] = $aResult['suite']['_id'];

			$aTemp['TestName'] = $aResult['name'];
			$aTemp['TestID'] = $aResult['_id'];
			
			$aFinalResults[] = $aTemp;
		}

		return $aFinalResults;

	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function ExtractTestResults($aResults, $strHost)
{
	try
	{
		$aFinalResults = array();
		foreach($aResults['data'] as $aResult)
		{
			$aTemp = array();
			$aTemp['TestName'] = $aResult['testName'];
			$aTemp['TestID'] = $aResult['test'];
			$aTemp['ExecutionID'] = $aResult['_id'];

			$aTemp['ExecutionTime'] = FormatResult($aResult['executionTime'], "executionTime");
			$aTemp['DateExecutionStarted'] = FormatResult($aResult['dateExecutionStarted'], "dateExecutionStarted");
			$aTemp['StartUrl'] = $aResult['startUrl'];
			$aTemp['Video'] = $aResult['video']['url'];
			$aTemp['Passing'] = $aResult['passing'];

			$aTemp['Errors'] = array();
			if($aTemp['Passing'] != 1)
				$aTemp['Errors'] = ExtractSteps($aResult['steps'], $strHost, $aResult);

			$aFinalResults[] = $aTemp;
		}

		return $aFinalResults;
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function ExtractSingleTestResults($aResults, $strHost)
{
	try
	{
		$aFinalResults = array();
		$aTemp = array();

		$aSuiteInfo = ListSuite($aResults['data']['test']['suite']);
		$aTemp['SuiteName'] = $aSuiteInfo['Name'];
		$aTemp['SuiteID'] = $aResults['data']['test']['suite'];

		$aTemp['TestName'] = $aResults['data']['test']['name'];
		$aTemp['TestID'] = $aResults['data']['test']['_id'];

		$aTemp['ExecutionTime'] = FormatResult($aResults['data']['executionTime'], "executionTime");
		$aTemp['DateExecutionStarted'] = FormatResult($aResults['data']['dateExecutionStarted'], "dateExecutionStarted");
		$aTemp['StartUrl'] = $aResults['data']['startUrl'];
		$aTemp['Video'] = $aResults['data']['video']['url'];
		$aTemp['Passing'] = $aResults['data']['passing'];

		$aTemp['Errors'] = array();
		if($aTemp['Passing'] != 1)
			$aTemp['Errors'] = ExtractSteps($aResults['data']['steps'], $strHost, $aResults);

		$aFinalResults[$aTemp['SuiteID']][] = $aTemp;

		return $aFinalResults;
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function ExtractSteps($aSteps, $strHost, $aResults)
{
	try
	{
		$aErrors = array();

		foreach($aSteps as $aStep)
		{
			if($aStep['passing'] != "1")
			{
				$aTemp = array();
				$aTemp['Step_ID'] = $aStep['_id'];
				$aTemp['Command'] = $aStep['command'];
				$aTemp['Target'] = $aStep['target'];

				$strErrorMsg = "Error ID:".$aStep['_id']."<br>";
				$strErrorMsg .= "Command:".$aStep['Command']."<br>";
				$strErrorMsg .= "Target:".$aStep['Target'];

				$aErrors[] = $aTemp;

				$classError = new ErrorHandler();
				$classError->ErrorHandler_ManualEventLog("TestRunner", "ExtractSteps", Const_Error, "An error occured while testing ".$aTemp['TestName'].".", $strErrorMsg, serialize($aResults), $strHost);
			}
		}

		return $aErrors;
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function FormatResult($strValue, $strType)
{
	try
	{
		if($strType == "executionTime")
			$strValue = round($strValue/1000, 0);

		if($strType == "dateExecutionStarted")
		{
			$aParts = explode("T", $strValue);
			$aDateParts = explode("-", $aParts[0]);
			$aTimeParts = explode(":", $aParts[1]);

			$strHour = str_replace("T", "", $aTimeParts[0]);
			$strmin	 = $aTimeParts[1];
			$aSecondParts = explode(".", $aTimeParts[2]);
			$strSec	 = $aSecondParts[0];

			$strDate = $aDateParts[2]."-".$aDateParts[1]."-".$aDateParts[0];
			$strTime = $strHour.":".$strmin.":".$strSec;

			$strValue = $strTime." ".$strDate;
		}

		return $strValue;
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


function DisplayTestResults($aResults, $strHost)
{
	try
	{
		print"\n\nTest Results for host '".$strHost."': \n\n";
		foreach($aResults as $strSuiteID=>$aSuite)
		{
			$aNameResults = ListSuite($strSuiteID);
			print "Results for test suite '".$aNameResults['Name']."'  \n";

			foreach($aSuite as $aTest)
			{
				print"\t".$aTest['TestName'];
				if($aTest['Passing'] == 1)
					print" - Passed! \n";
				else
				{
					print" - Errors encountered \n";
					foreach($aTest['Errors'] as $aError)
					{
						foreach($aError as $strErrorName=>$strErrorValue)
							print "\t\t".$strErrorName.": ".$strErrorValue."\n";
						print"\n";
					}
				}
			}

			print"\n";
		}

		print"\n";
	}
	catch (Exception $ex)
	{
		throw new exception($ex->getMessage());
	}
}


TestRunner_Main($strHost, $strTestAction);



?>
