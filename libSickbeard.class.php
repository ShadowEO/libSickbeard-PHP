<?php

	if(!file_exists("etc/configuration.php"))
		{
			define('SICKBEARD_URL',"http://localhost:8081/api");
		} else {
			include("etc/configuration.php");
		}


	class SickbeardAutomation
	{

		const SICKBEARD_STATUS_WANTED = "wanted";
		const SICKBEARD_STATUS_SKIPPED = "skipped";
		const SICKBEARD_STATUS_IGNORED = "ignored";
		const SICKBEARD_STATUS_ARCHIVED = "archived";
		const SICKBEARD_FUTURE_SOON = "soon";
		const SICKBEARD_FUTURE_TODAY = "today";
		const SICKBEARD_FUTURE = "today|missed";
		const SICKBEARD_FUTURE_MISSED = "missed";
		const SICKBEARD_FUTURE_LATER = "later";
		const LIBSICKBEARD_VERSION = "0.0.2a";
		const SICKBEARD_TRUE = 1;
		const SICKBEARD_FALSE = 0;
		
		var $ApiKey;
		var $URL;
		
		function SickbeardAutomation($ApiKey = null, $URL = null, $UseConfiguration = true)
		{
			// Initialize Library and add it to loaded library classnames
						
			if($UseConfiguration == true)
			{
				
				$this->ApiKey = SICKBEARD_APIKEY;
				$this->URL = SICKBEARD_URL;
				$this->ssh_username = SICKBEARD_SSH_USERNAME;
				$this->ssh_password = SICKBEARD_SSH_PASSWORD;
								
			} else {
				if($ApiKey == null)
				{
					$this->ApiKey = null;	
				} else {
					$this->ApiKey = $ApiKey;
				}
				if($URL == null)
				{
					$this->URL = "http://localhost:8081";
				} else {
					$this->URL = $URL;
				}
				
				
			}
			
		}
		
		function IsRunning()
		{
			$ret = $this->Ping();
			if(!$ret)
			{
				return Self::SICKBEARD_FALSE;
			} else {
				return Self::SICKBEARD_TRUE;
			}
		}
		
		function StartService()
		{
			if(parse_url($this->URL, PHP_URL_HOST) != "localhost")
			{
				$connection = ssh2_connect(parse_url($this->URL, PHP_URL_HOST));
				if(ssh2_auth_password($connection, $this->ssh_username, $this->ssh_password))
				{
					$stream = ssh2_exec("cat /var/run/sickbeard/sickbeard.pid");
					stream_set_blocking($stream, true);
					$pid = stream_get_contents($stream);
					fclose($stream);
					$stream = ssh2_exec("sudo ps -p $pid | grep python");
					stream_set_blocking($stream, true);
					$status = stream_get_contents($stream);
					if(!empty($status))
					{
						return Self::SICKBEARD_TRUE;
					}
					fclose($stream);
					unset($pid);
					unset($status);
					ssh2_exec("sudo /etc/init.d/sickbeard start");
					$stream = ssh2_exec("cat /var/run/sickbeard/sickbeard.pid");
					stream_set_blocking($stream, true);
					$pid = stream_get_contents($stream);
					fclose($stream);
					if(!empty($pid))
					{
						$stream = ssh2_exec("sudo ps -p $pid | grep python");
						stream_set_blocking($stream, true);
						$status = stream_get_contents($stream);
						fclose($stream);
						if(empty($status))
						{
							return Self::SICKBEARD_FALSE;
						} else {
							return Self::SICKBEARD_TRUE;
						}
					} else {
						return Self::SICKBEARD_FALSE;
					}
				}
			} else {
				exec("sudo /etc/init.d/sickbeard start");
				$pid = file_get_contents("/var/run/sickbeard/sickbeard.pid");
				if(shell_exec("sudo ps -p $pid | grep python"))
				{
					return Self::SICKBEARD_TRUE;
				} else {
					return Self::SICKBEARD_FALSE;
				}
			}
		}
		
		function BuildAPIRequest($requestCmd)
		{
			
			$URL=$this->URL."/".$this->ApiKey."/?cmd=".$requestCmd;
			return $URL;
			
		}
		
		function Restart()
		{
			$URL = $this->BuildAPIRequest("sb.restart");
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE)
			{
				return false;	
			}
			$response = $this->DecodeResponse($response);
			return $response;
		}
		
		function SearchTVShows($ShowName, $Lang = "en")
		{
			$URL = $this->BuildAPIRequest("sb.searchtvdb");
			$URL = $URL."&name=".$ShowName."&lang=".$Lang;
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE)
			{
				return false;	
			}
			$Response = $this->DecodeResponse($response);
			return($Response);
			
		}
		
		function SendRequest($URL)
		{
			
			$Ping = $this->BuildAPIRequest("sb.ping");
			$Pong = file_get_contents($Ping);
			if(empty($Pong))
			{
				return Self::SICKBEARD_FALSE;
			}
			
			$Request = file_get_contents($URL);
			$Response = $this->DecodeResponse($Request);
			return $Response;
			
		}
		
		
		function SetDefaults($Status = Self::SICKBEARD_STATUS_SKIPPED, $flattenFolders = Self::SICKBEARD_FALSE, $Initial = null, $archived = null)
		{
			
			$URL = $this->BuildAPIRequest("sb.setdefaults");
			$URL = $URL."&status=".$Status."&flatten_folders=".$flattenFolders;
			if(isset($Initial))
			{
				$URL = $URL."&initial=".$Initial;
			}
			if(isset($archived))
			{
				$URL = $URL."&archived=".$archived;
			}
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE)
			{
				return false;
			}
			$Response = $this->DecodeResponse($response);
			return $Response;
			
		}
		
		function Shutdown()
		{
			
			$URL = $this->BuildAPIRequest("sb.shutdown");
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE)
			{
				return false;	
			}
			$Response = $this->DecodeResponse($response);
			return $Response;
			
		}
		
		function PauseBacklog()
		{
			
			$URL = $this->BuildAPIRequest("sb.pausebacklog");
			$URL = $URL."&pause=1";
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE)
			{
				return false;
			}
			$Response = $this->DecodeResponse($response);
			return $Response;
			
		}
		
		function UnpauseBacklog()
		{
			
			$URL = $this->BuildAPIRequest("sb.pausebacklog");
			$URL = $URL."&pause=0";
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE)
			{
				return false;
			}
			$Response = $this->DecodeResponse($response);
			return $Response;
			
		}
		
		function ForceEpisodeSearch()
		{
			$URL = $this->BuildAPIRequest("sb.forcesearch");
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE)
			{
				return false;
			}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function UpdateShow($ShowID)
		{
			$URL = $this->BuildAPIRequest("show.update");
			$URL = $URL . "&tvdbid=".$ShowID;
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE)
			{
				return false;
			}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function SetShowQuality($ShowID, $Initial = null, $Archived = null)
		{
			$URL = $this->BuildAPIRequest("show.setquality");
			$URL = $URL . "&tvdbid=".$ShowID."&initial=".$Initial."&archived=".$Archived;
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE)
			{
				return false;
			}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function RetrieveShowList($Sort = "name", $IncludePaused = Self::SICKBEARD_FALSE)
		{
			$URL = $this->BuildAPIRequest("shows");
			$URL = $URL."&sort=".$Sort."&paused=".$IncludePaused;
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE)
			{
				return false;
			}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function RetrieveSeasonList($ShowID)
		{
			$URL = $this->BuildAPIRequest("show.seasonlist");
			$URL = $URL."&tvdbid=".$ShowID;
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function RescanLocalFiles($ShowID)
		{
			$URL = $this->BuildAPIRequest("show.refresh");
			$URL = $URL."&tvdbid=".$ShowID;
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function PauseShow($ShowID)
		{
			$URL = $this->BuildAPIRequest("show.pause");
			$URL = $URL."&tvdbid=".$ShowID."&pause=1";
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function UnpauseShow($ShowID)
		{
			$URL = $this->BuildAPIRequest("show.pause");
			$URL = $URL."&tvdbid=".$ShowID."&pause=0";
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function GetShowQuality($ShowID)
		{
			$URL = $this->BuildAPIRequest("show.getquality");
			$URL = $URL."&tvdbid=".$ShowID;
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function RetrieveFutureSoon()
		{
			$Response = $this->RetrieveFutureInfo("date", SICKBEARD_FUTURE_SOON);
			return $Response;
		}
		
		function SetStatus($ShowID, $Season, $Episode = null, $Status = SICKBEARD_STATUS_WANTED, $Force = SICKBEARD_FALSE)
		{
			$URL = $this->BuildAPIRequest("episode.setstatus")."&tvdbid=".$ShowID."&season=".$Season."&force=".$Force;
			if(isset($Episode))
			{
				$URL = $URL."&episode=".$Episode;
			}
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function RetrieveEpisodeInfo($ShowID, $Season, $Episode, $FullPath = SICKBEARD_TRUE)
		{
			$URL = $this->BuildAPIRequest("episode")."&tvdbid=".$ShowID."&season=".$Season."&episode=".$Episode."&full_path=".$FullPath;
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function EpisodeSearch($ShowID, $Season, $Episode)
		{
			$URL = $this->BuildAPIRequest("episode.search")."&tvdbid=".$ShowID."&season=".$Season."&episode=".$Episode;
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$response = $this->DecodeResponse($response);
			return $response;
		}
		
		function RetrieveFutureLater()
		{
			$Response = $this->RetrieveFutureInfo("date", Self::SICKBEARD_FUTURE_LATER);
		}
		
		function RetrieveFutureToday()
		{
			$Response = $this->RetrieveFutureInfo("date", Self::SICKBEARD_FUTURE_TODAY);
			return $Response;
		}
		
		function RetrieveFutureMissed()
		{ 
			$Response = $this->RetrieveFutureInfo("date", Self::SICKBEARD_FUTURE_MISSED);
			return $Response;
		}
		
		function RetrieveFutureInfo($Sort = "date", $type = Self::SICKBEARD_FUTURE, $Paused = null)
		{
			$URL = $this->BuildAPIRequest("future")."&sort=".$Sort."&type=".$type;
			if($Paused)
			{
				$URL = $URL."&paused=".$Paused;
			}
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function RetrieveShowInfo($ShowID)
		{
			$URL = $this->BuildAPIRequest("show")."&tvdbid=".$ShowID;
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function DeleteShow($ShowID)
		{
			$URL = $this->BuildAPIRequest("show.delete");
			$URL = $URL."&tvdbid=".$ShowID;
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function AddExistingShow($ShowID, $ShowLocation = null, $FlattenFolders = null, $Initial = null, $Archived = null)
		{
			$URL = $this->BuildAPIRequest("show.addexisting");
			$URL = $URL."&tvdbid=".$ShowID."&location=".$ShowLocation;
			if(isset($FlattenFolders))
			{
				$URL = $URL."&flatten_folders=".$FlattenFolders;	
			}
			if(isset($Initial))
			{
				$URL = $URL."&initial=".$Initial;
			}
			if(isset($Archived))
			{
				$URL = $URL."&archive=".$Archived;
			}
			$Response = $this->SendRequest($URL);
			if($Response = Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($Response);
			return $Response;
		}
		
		function AddNewShow($ShowID, $ShowLocation = null, $Lang = "en", $FlattenFolders = null, $Status = null, $Initial = null, $Archived = null)
		{
			$URL = $this->BuildAPIRequest("show.addnew");
			$URL = $URL."&tvdbid=".$ShowID."&lang=".$Lang;
			if(isset($ShowLocation))
			{
				$URL = $URL."&location=".$ShowLocation;
			}
			if(isset($FlattenFolders))
			{
				$URL = $URL."&flatten_folders=".$FlattenFolders;
			}
			if(isset($Status))
			{
				$URL = $URL."&status=".$Status;
			}
			if(isset($Initial))
			{
				$URL = $URL."&initial=".$Initial;
			}
			if(isset($Archived))
			{
				$URL = $URL."&archive=".$Archived;
			}
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($Response);
			return $Response;
		}
		
		function GetBannerURL($ShowID)
		{
			$URL = $this->BuildAPIRequest("show.getbanner");
			$URL = $URL."&tvdbid=".$ShowID;
			return $URL;
		}
		
		function GetPosterURL($ShowID)
		{
			$URL = $this->BuildAPIRequest("show.getposter");
			$URL = $URL."&tvdbid=".$ShowID;
			return $URL;
		}
		
		function RetrieveEpisodeListForSeason($ShowID, $SeasonNumber)
		{
			$URL = $this->BuildAPIRequest("show.seasons");
			$URL = $URL."&tvdbid=".$ShowID."&season=".$SeasonNumber;
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE) {return false;}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function ShowStats()
		{
			$URL = $this->BuildAPIRequest("shows.stats");
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE)
			{
				return false;
			}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function CheckScheduler()
		{
			$URL = $this->BuildAPIRequest("sb.checkscheduler");
			$Response = $this->SendRequest($URL);
			if($Response == Self::SICKBEARD_FALSE)
			{
				return false;
			}
			$Response = $this->DecodeResponse($response);
			return $Response;
		}
		
		function DecodeResponse($response)
		{
			
			$response = json_decode($response, true);
			return $response;
		
		}
		
	}
?>
