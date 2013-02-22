<?php 

require_once('Codebird.php');

class Twitter{
	
	//oAuth API
	private $codebird = null;
	//local file cache for user timeline
	private $user_timeline_cache = '/user_timeline_cache.txt';
	
	/**
	*
	* @param String $consumerKey    - consumerKey - (generate on Twitter)
	* @param String $consumerSecret - app secret - (generate on Twitter)
	* @param String $accessToken    - oAuth token (generate on Twitter)
	* @param String $accessSecret   - oAuth Secret (generate on Twitter)
	*
	*/
	public function __construct($consumerKey,$consumerSecret,$accessToken,$accessSecret)
	{
		//setup cache
		$this->user_timeline_cache = __DIR__ . $this->user_timeline_cache;
		
		//setup Twitter API
		Codebird::setConsumerKey($consumerKey,$consumerSecret);
		$this->codebird = Codebird::getInstance();
		$this->codebird->setToken($accessToken,$accessSecret);
	}
	
	
	/**
	*
	* @param int $number - number of tweets to retrive
	* @param Boolean $json - return as JSON array, defaults to PHP array
	* 
	* @return mixed $reply - PHP or JSON array of timeline tweets
	*	
	*/
	public function getUserTimeline($screenname = 'dusteddesign', $number = 5, $json = false)
	{
		$reply = array();
		
		if( (file_exists($this->user_timeline_cache)) && (filemtime($this->user_timeline_cache) > (time() - 60 * 60))){
			$reply = $this->readCache($this->user_timeline_cache);
			$tweets = array();
			foreach($reply as $tweet)
			{
				$tweet->text = $this->render($tweet->text);
				$tweets[] = (array)$tweet;
			}
			
		}
		else
		{
			
			//check the rate just in case - we dont want to get blacklisted
			$status = $this->getApplicationRatelimit();
			$status = (array)$status['resources']->statuses;
			$limit = $status["/statuses/user_timeline"]->remaining;
			
			if($limit > 0)
			{
				$reply = (array)$this->codebird->statuses_userTimeline(array(
					'count' => $number,
					'screen_name' => $screenname
					));
				if($reply['httpstatus'] == 200)
				{
					//write to file
					file_put_contents($this->user_timeline_cache,serialize($reply));
				}
				else
				{
					//This is an error but we'll return the cache file if there is one as we might of hit a limit
					if($cache = $this->readCache($this->user_timeline_cache))
					{
						$reply = $cache;
					}
					else{
						//otherwise show and error
						echo 'The Twitter API call failed with code ' . $reply['httpstatus'] . '. No cache file to fall back on';
					}
				}
			}
			else
			{
				//This is an error but we'll return the cache file if there is one as we might of hit a limit
				if($cache = $this->readCache($this->user_timeline_cache))
				{
					$reply = $cache;
				}
				else{
					//otherwise show and error
					echo "Error: API limit hit";
				}
			}
				
		}
		if($json)
		{
			return json_encode($reply);
		}
		else
		{
			return $reply;
		}
	
	}
	
	/*
	*
	* Read and unserialise a cache file
	* 
	* @param String $cachefile - file to read
    * @return Boolean - return false if cache file doesnt exist
	*
	**/
	private function readCache($cachefile)
	{
		if(file_exists($cachefile))
		{
			return unserialize(file_get_contents($cachefile));

		}
		else
			return false;
	}
	
	
	/*
	*
	* Get the hourly rate limits and status
	*
	*/
	private function getApplicationRateLimit()
	{		
	
		$status = (array)$this->codebird->application_rateLimitStatus();
		return $status;
	}
	
	private function render($tweet)
	{
		$tweet = preg_replace('/(https?:\/\/[^\s"<>]+)/','<a href="$1">$1</a>',$tweet);
		$tweet = preg_replace('/(^|[\n\s])@([^\s\"\t\n\r<:]*)/is', '$1<a href="http://twitter.com/$2">@$2</a>', $tweet);
		$tweet = preg_replace('/(^|[\n\s])#([^\s"\t\n\r<:]*)/is', '$1<a href="http://twitter.com/search?q=%23$2">#$2</a>', $tweet);
		return $tweet;
	}
	
}

