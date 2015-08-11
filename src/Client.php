<?php

namespace TextMarketer;

/**
 * API client to access Text Marketer's RESTful API, allows you to send SMS,
 * check credits, create accounts, and more.
 * 
 * This class has been designed to be sub-classed, so that you can create a
 * subclass with your API username/password pre-set in the variables $defaultUsername
 * and $defaultPassword and thus use new versions of this class as they are
 * released, without the need for modifying your defaults.
 * 
 * CHANGE LOG
 *
 * v1.4 Added delete scheduled SMS 
 * v1.3 Added schedule parameter to SMS send.
 * v1.2 Misc refactoring and bug fixes. Improved suitability for subclassing. Added sub-account creation function. Updated to REST DTDs v1.6.
 * 
 * @copyright Copyright (c) 2012, Text Marketer
 * @link http://www.textmarketer.co.uk/developers/restful-api.htm
 * @version 1.3
 */
class Client {
	
    static protected $PROD_URL = 'https://api.textmarketer.co.uk/services/rest/';
    static protected $SAND_URL = 'http://sandbox.api.textmarketer.co.uk/services/rest/';
	
	/**
	 * The client class version - internal use only, DO NOT change this
	 * @var float
	 */
	static public $VERSION = '1.4';
	
	/**
	 * Default API username - you can set this if you want, but it is strongly recommended to pass it in to the constructor instead.
	 * 
	 * @var string 
	 */
	protected $defaultUsername = '';
	
	/**
	 * Default API password - you can set this if you want, but it is strongly recommended to pass it in to the constructor instead.
	 * 
	 * @var string
	 */
	protected $defaultPassword = ''; 
    
	// for internal use only
    protected $username, $password;
    protected $lastErrors, $apiUrl;
	protected $responseBody;
	protected $forceSandbox=false;

    /**
     * Constructor for the TMRestClient class
     * 
     * @param string $username your API Gateway Username
     * @param string $password your API Gateway Password
     * @param string $env possible values 'sandbox' or 'production'
     *
     * @return TMRestClient TMRestClient
     * 
     * <code>
     * <?php
     *     $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     * ?>
     * </code>
     */
    public function __construct($username=null, $password=null, $env = 'sandbox') {
		
        $this->lastErrors = array();
        $this->username = (!empty($username)) ? $username : $this->defaultUsername;
        $this->password = (!empty($password)) ? $password : $this->defaultPassword;
		
		if (empty($this->username) OR empty($this->password))
			throw new Exception('The API username and password must be passed into the constructor or set in the class variables.');
		
        if($env == 'production')
		{
			$this->apiUrl = self::$PROD_URL;
		}
        else
		{
			$this->apiUrl = self::$SAND_URL;
		}
    }
    
    /**
     * Make a call to TM Rest API Gateway to test if the username and password are correct
	 * 
	 * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *          if($tmClient->isLoginValid())
     *              echo 'Login is valid.';
     *      } catch (Exception $ex) {
     *			$errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
     * 
     * @return boolean TRUE if login valid, FALSE if username or password not correct
     * @throws Exception on error
     * 
     * 
     */
    public function isLoginValid() {
		try {
			$result = $this->restGatewayCall('credits', 'GET');
			return true;
		} catch (Exception $e) {
			// if the login is incorrect we expect a 'Forbidden' Exception to be thrown
			if ($e->getCode() == 403)
				return false;
			
			throw $e;
		}
		
    }
    
    /**
     * Send a text message to the specified recipient.
     * 
	 * @link http://www.textmarketer.co.uk/blog/2009/07/bulk-sms/supported-and-unsupported-characters-in-text-messages-gsm-character-set/
	 * 
     * @param string $message The textual content of the message to be sent. 
	 * Up to 612 characters from the GSM alphabet. 
     * The SMS characters we can support is documented at @link http://www.textmarketer.co.uk/blog/2009/07/bulk-sms/supported-and-unsupported-characters-in-text-messages-gsm-character-set/. 
	 * Please ensure that data is encoded in UTF-8.
     * 
     * @param string $mobile_number The mobile number of the intended recipient, in international format, e.g. 447777123123.
     * Only one number is allowed. To send a message to multiple recipients, you must call the API for each number.
     * 
     * @param string $originator  A string (up to 11 alpha-numeric characters) or the international mobile 
     * number (up to 16 digits) of the sender, to be displayed to the recipient, e.g. 447777123123 for a UK number.
     * 
     * @param integer $validity Optional. An integer from 1 to 72, indicating the number of hours during which 
     * the message is valid for delivery. Messages which cannot be delivered within
     * the specified time will fail.
     * 
     * @param string $email Optional. Available to txtUs Plus customers only. Specifies the email address 
     * for incoming responses.
     * If you specify an email address, you must specify an originator that is a txtUs
     * Plus number that is on your account, or you will get an error response.
     * 
     * @param string $custom  Optional. An alpha-numeric string, 1-20 characters long, which will be used to
     * 'tag' your outgoing message and will appear in delivery reports, thus facilitating 
     * filtering of reports
	 *
     * @param string $schedule Optional parameter to schedule the message to send at a given time, should be in ISO 8601 format or UNIX Timestamp (Europe/London time)
     * 
     * @return array array with 4 keys: 'message_id', 'scheduled_id', 'credits_used', 'status' e.g. $result['message_id']
     * @throws Exception on error
     * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
	 *			$result = $tmClient->sendSMS('Hello SMS World!', '447777123123', 'Hello World');
	 *			echo "Used {$result['credits_used']} Credits, message ID {$result['message_id']}, Scheduled ID {$result['scheduled_id']}";
	 *      } catch (Exception $ex) {
	 *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
	 *      }
     *  ?>
     * </code>
     */
    public function sendSMS($message, $mobile_number, $originator, $validity = 72, $email = '', $custom = '', $schedule=null) {
		if($schedule != null && is_numeric($schedule)) 
			$schedule = date('c', $schedule);
			
		$params = array(
			'message' => $message,
			'mobile_number' => $mobile_number,
			'originator' => $originator,
			'validity' => $validity,
			'email' => $email,
			'custom' => $custom,
			'schedule' => $schedule
		);
		$xml = $this->restGatewayCall('sms', 'POST', $params);
		$retparams = array(
			'message_id' => (integer) $xml->message_id,
			'scheduled_id' => (integer) $xml->scheduled_id,
			'credits_used' => (integer) $xml->credits_used,
			'status' => (string) $xml->status
		);
		return $retparams;
    }
    
    /**
     * Delete a scheduled text message.
     *
     * @param string $scheduled_id The id of the scheduled text message, as returned by the sendSMS method.
     *
     * @return array array with 2 keys: 'scheduled_id', 'status', e.g. $result['status']
     * @throws Exception on error
     *
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *			$result = $tmClient->deleteSMS('101');
     *			echo "Scheduled ID {$result['scheduled_id']}, Status: {$result['status']}";
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     	*              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
     */
    public function deleteSMS($scheduled_id) {
    	$xml = $this->restGatewayCall('sms/'.urlencode($scheduled_id), 'DELETE', null);
    	$retparams = array(
    			'scheduled_id' => (integer) $xml->scheduled_id,
    			'status' => (string) $xml->status
    	);
    	return $retparams;
    }
    
    /**
     * Get the number of credits currently available on your account.
     * 
     * @return int number of credits currently available on your account.
     * @throws Exception on error
     * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *          echo 'I have '.$tmClient->getCredits().'credits.';
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
     */
    public function getCredits() {
		$xml = $this->restGatewayCall('credits', 'GET');
		if(isset($xml->credits))
			return (integer) $xml->credits;
		return 0;
    }
    
    /**
     * Transfer credits from one account to another account, using the account number for the target.
     * 
     * @param integer $quantity The number of credits to transfer from the source account to the target account.
     * 
     * @param string $targetAccountNumber The account number of the account to transfer the credits to (available in the web-based UI)
     * 
     * @return array array with four keys: 'source_credits_before', 'source_credits_after', 'target_credits_before'
     * and 'target_credits_after' e.g. $result['source_credits_after']
     * @throws Exception on error
     * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *          $result = $tmClient->transferCreditsToAccount(3, '1234');
     *          echo "Transfered 3 Credits (have {$result['source_credits_after']} now), to account 1234, now with {$result['target_credits_after']} Credits";
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
     */
    public function transferCreditsToAccount($quantity, $targetAccountNumber) {
		$params = array(
			'quantity' => $quantity,
			'target' => $targetAccountNumber
		);
		$xml = $this->restGatewayCall('credits', 'POST', $params);
		$retparams = array(
			'source_credits_before' => (integer) $xml->source_credits_before,
			'source_credits_after' => (integer) $xml->source_credits_after,
			'target_credits_before' => (integer) $xml->target_credits_before,
			'target_credits_after' => (integer) $xml->target_credits_after
		);
		return $retparams;
    }
    
    /**
     * Transfer credits from one account to another account, using the username for the target.
     * 
     * @param integer $quantity The number of credits to transfer from the source account to the target account.
     * 
     * @param string $target_username The username of the account to transfer the credits to.
     * 
     * @param string $target_password The password of the account to transfer the credits to.
     * 
     * @return array array with four keys: 'source_credits_before', 'source_credits_after', 'target_credits_before'
     * and 'target_credits_after' e.g. $result['source_credits_after']
     * @throws Exception on error
     * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *          $result = $tmClient->transferCreditsToUser(3, 'targetusername', 'targetpassword');
     *          echo "Transfered 3 Credits (have {$result['source_credits_after']} now), to targetusername, now with {$result['target_credits_after']} Credits";
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
     */
    public function transferCreditsToUser($quantity, $target_username, $target_password) {
		$params = array(
			'quantity' => $quantity,
			'target_username' => $target_username,
			'target_password' => $target_password
		);
		$xml = $this->restGatewayCall('credits', 'POST', $params);
		$retparams = array(
			'source_credits_before' => (integer) $xml->source_credits_before,
			'source_credits_after' => (integer) $xml->source_credits_after,
			'target_credits_before' => (integer) $xml->target_credits_before,
			'target_credits_after' => (integer) $xml->target_credits_after
		);
		return $retparams;
    }
    
    /**
     * DEPRECATED 
	 * @deprecated Replaced by TMRestClient=>getKeywordAvailability()
     */
    public function getKeyword($keyword) {
		$xml = $this->restGatewayCall('keywords/'.$keyword, 'GET');
		$retparams = array(
			'available' => $xml->available,
			'recycle' => $xml->recycle
		);
		return $retparams;
    }
	
	/**
	 * Get the availability of a given reply keyword.
     * A reply keyword allows you receive incoming text messages to your account by providing people
     * with a keyword, which they text to the short code 88802, e.g. text 'INFO' to 88802 to see this
     * in action.
     * 
     * @param string $keyword the keyword to check is availability
     * 
     * @return array array with two keys: 'available' and 'recycle' e.g. $result['available']
     * @throws Exception on error
     * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *          $result = $tmClient->getKeyword('gold');
     *          echo "The 'gold' keyword is available ({$result['available']}), recycled ({$result['recycle']})";
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
	 */
	public function getKeywordAvailability($keyword) {
		$xml = $this->restGatewayCall('keywords/'.$keyword, 'GET');
		$retparams = array(
			'available' => $xml->available=='true',
			'recycle' => $xml->recycle=='true'
		);
		return $retparams;
    }
    
    /**
     * Get a list of available 'send groups' - pre-defined groups containing a list of mobile numbers to send a message to. 
	 * Also lists 'stop groups' - numbers in these groups will never be sent messages.
     * Every account has at least one stop group, so that your recipients can always opt out of
     * receiving messages from you. This is a legal requirement.
     * 
     * @return array array with all groups, one per array row, each group row have 4 keys: 'is_stop', 'id', 'numbers' and 'name'
     * e.g. name of the first returned group $groups[0]['name']
     * 
     * @throws Exception on error
     * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *          $groups = $tmClient->getGroups();
     *          foreach($groups as $group)
     *              foreach($group as $key => $value)
     *                  echo "$key => $value <BR/>";
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
     */
    public function getGroups() {
		$xml = $this->restGatewayCall('groups', 'GET');
		$retparams = array();
		$i = 0;
		foreach($xml->groups[0] as $key => $value) {
			$retparams[$i]['id'] = (int)$value['id'];
			$retparams[$i]['numbers'] = (int)$value['numbers'];
			$retparams[$i]['name'] = (string)$value['name'];
			$retparams[$i]['is_stop'] = $value['is_stop']=='true';
			$i++;
		}
		return $retparams;
    }
    
	/**
     * Add a number/numbers to a 'send group' (excluding 'merge' groups).
     * 
     * @param array $numbersArray The MSISDN (mobile number(s)) you wish to add
     * @param string $groupName Group name or group ID to add the numbers to
     * 
     * @return array array with 3 keys: 'added', 'stopped' and 'duplicates', each one with an array of numbers
     * @throws Exception on error
     * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *          $result = $tmClient->addNumbersToGroup('My Group', array('447777000001','447777000002','447777000003'));
     *          echo 'Numbers added: '.count($result['added']).'<BR/>.;
     *          foreach ($result['added'] as $number)
     *              echo "$number <BR/>";
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
     */
	public function addNumbersToGroupWithName(array $numbersArray, $groupName)
	{
		$params = array(
			'numbers' => implode(',', $numbersArray)
		);
		$xml = $this->restGatewayCall('group/'.urlencode($groupName), 'POST', $params);
		$retparams = array('added'=>array(), 'stopped'=>array(), 'duplicates'=>array());
		foreach($xml->added[0]->number as $key => $value) {
			$retparams['added'][] = (string)$value;
		}
		foreach($xml->stopped[0]->number as $key => $value) {
			$retparams['stopped'][] = (string)$value;
		}
		foreach($xml->duplicates[0]->number as $key => $value) {
			$retparams['duplicates'][] = (string)$value;
		}
		return $retparams;
	}
	
    /**
	 * DEPRECATED - use addNumbersToGroupWithName() instead
	 * @deprecated Replaced by TMRestClient->addNumbersArrayToGroup()
	 */
    public function addNumbersToGroup($groupName, $numbers) {
		return $this->addNumbersToGroupWithName(explode(',', $numbers), $groupName);
    }
    
    /**
     * Shows the details of a group. 
	 * Shows only one group, and includes all the numbers in the group, if there are any.
     * 
     * @param string $group Group name or group ID to get the details of
     * 
     * @return array array with 5 keys: 'name', 'numbers', 'id', 'is_stop' and 'number' 
     * @throws Exception on error
     * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *          $result = $tmClient->getGroup('My Group');
     *          echo "Numbers in group: {$result['numbers']}<BR/>";
     *          foreach ($result['number'] as $number)
     *              echo "$number <BR/>";
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
     */
    public function getGroup($group) {
		$xml = $this->restGatewayCall('group/'.urlencode($group), 'GET');
		$retparams = array(
			'name' => (string)$xml->group['name'],
			'numbers' => (int)$xml->group['numbers'],
			'id' => (int)$xml->group['id'],
			'is_stop' => $xml->group['is_stop']=='true'
		);
		foreach($xml->group[0]->number as $key => $value) {
			$retparams['number'][] = (string)$value;
		}
		return $retparams;
    }

    /**
     * Create a new group.
     * 
     * @param string $group the new group name
     * 
     * @return array array with 4 keys: 'name', 'numbers', 'id', 'is_stop'
     * @throws Exception on error
     * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *          $result = $tmClient->addGroup('New Group');
     *          echo "The ID of {$result['name']} is {$result['id']}";
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
     */
    public function addGroup($group) {
		$xml = $this->restGatewayCall('group/'.urlencode($group), 'PUT');
		$retparams = array(
			'name' => (string)$xml->group['name'],
			'numbers' => (int)$xml->group['numbers'],
			'id' => (int)$xml->group['id'],
			'is_stop' => $xml->group['is_stop']=='true'
		);
		foreach($xml->group[0]->number as $key => $value) {
			$retparams['number'][] = (string)$value;
		}
		return $retparams;
    }
    
    /**
     * Retrieve a list of available delivery report names.
     * 
     * @return array array with 2 keys: 'userdirectory' and 'reports'; reports is an array where each row is a report array with keys 'name', 'last_updated', 'extension'  
     * @throws Exception on error
     * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *          $result = $tmClient->getDeliveryReports();
     *          echo "User Directory: {$result['userdirectory']} <BR/>";
     *          foreach($result['reports'] as $key => $report)
     *              echo "Report number $key: Name->{$report['name']}, Last Updated->{$report['last_updated']}, Extension->{$report['extension']} <BR/>";
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
     */
    public function getDeliveryReports() {
		$xml = $this->restGatewayCall('deliveryReports', 'GET');
		$retparams = array(
			'userdirectory' => (string)$xml->userdirectory
		);
		$i = 0;
		foreach($xml->reports[0] as $key => $report) {
			$retparams['reports'][$i]['name'] = (string)$report['name'];
			$retparams['reports'][$i]['last_updated'] = (string)$report['last_updated'];
			$retparams['reports'][$i]['extension'] = (string)$report['extension'];
			$i++;
		}
		return $retparams;
    }
    
    /**
     * Retrieve individual delivery report shows the current known status of all messages sent on a given day, or for a particular campaign. 
	 * Whereas the function getDeliveryReports() gets a list of available delivery
     * report names, including delivery reports for campaigns.
	 * NOTE that an exception is thrown if no reports are found
     * 
     * e.g. for a delivery report with the name 'mycampaign-020411'
	 * <code>
     * $result = $tmClient->getDeliveryReport('mycampaign-020411');
	 * </code>
     * 
     * e.g. to get the delivery report details for 'mycampaign-020411' between 01:00 and 02:00 on 1st Jan 2011
	 * <code>
     * $result = $tmClient->getDeliveryReport('mycampaign-020411', '2011-01-01T01:00:00+00:00', '2011-01-01T02:00:00+00:00');
     * $result = $tmClient->getDeliveryReport('mycampaign-020411', '2011-01-01T01:00:00+00:00', time());
	 * </code>
	 * 
	 * e.g. to get delivery report details for all campaigns and API sends between the same dates as above
	 * <code>
     * $result = $tmClient->getDeliveryReport('all', '2011-01-01T01:00:00+00:00', '2011-01-01T02:00:00+00:00');
     * $result = $tmClient->getDeliveryReport('all', '2011-01-01T01:00:00+00:00', time());
	 * </code>
	 * 
     * e.g. for a delivery report with the name 'mycampaign-020411' and custom tag 'test' or all
	 * <code>
     * $result = $tmClient->getDeliveryReport('mycampaign-020411', null, null, 'test');
     * $result = $tmClient->getDeliveryReport('all', null, null, 'test');
	 * </code>
     * 
     * * e.g. same as previous but between 2 dates
	 * <code>
     * $result = $tmClient->getDeliveryReport('mycampaign-020411', '2011-01-01T01:00:00+00:00', '2011-01-01T02:00:00+00:00', 'test');
     * $result = $tmClient->getDeliveryReport('all', '2011-01-01T01:00:00+00:00', time(), 'test');
	 * </code>
     * 
     * @param string $name Name of the delivery report to retrieve or 'all' to retrieve all campaign/API report data
     * @param string $date_start Optional parameter get delivery report from $date_start, should be in ISO 8601 or UNIX Timestamp (Europe/London time)
     * @param string $date_end Optional parameter get delivery report to $date_end, should be in ISO 8601 or UNIX Timestamp (Europe/London time)
     * @param string $custom Optional Can specify a custom 'tag', which will restrict the search to those messages 
     * you sent with that tag (see 'custom' parameter of sendSms method).
     * 
     * @return array array with 4 keys: 'name', 'last_updated', 'extension' and 'reportrow', reportrow is an array with keys 
     * 'message_id', 'mobile_number', 'status' and 'last_updated'
     * @throws Exception on error OR NO REPORTS FOUND (404)
     * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *          $result = $tmClient->getDeliveryReport('all');
     *          foreach ($result as $key => $report) {
     *              echo "Report number $key: Name->{$report['name']}, Last Updated->{$report['last_updated']}, Extension->{$report['extension']} <BR/>";
     *              foreach ($report['reportrow'] as $key => $reportrow) {
     *                  echo "Message ID: {$reportrow['message_id']}, number: {$reportrow['mobile_number']}, Status:{$reportrow['status']}, Updated: {$reportrow['last_updated']}<BR/>";
     *              }
     *          }
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
     */
    public function getDeliveryReport($name, $date_start = null, $date_end = null, $custom = null) {
		if($date_start == null || $date_end == null)
		{
			if($custom == '')
				$xml = $this->restGatewayCall('deliveryReport/'.urlencode($name), 'GET');
			else
				$xml = $this->restGatewayCall('deliveryReport/'.urlencode($name).'/custom/'.urlencode($custom), 'GET');
		}
		else {
			if($date_start != null && is_numeric($date_start)) 
				$date_start = date('c', $date_start);
			if($date_end != null && is_numeric($date_end)) 
				$date_end = date('c', $date_end);
			if($custom == '')
				$xml = $this->restGatewayCall('deliveryReport/'.urlencode($name).'/'.urlencode($date_start).'/'.urlencode($date_end), 'GET');
			else
				$xml = $this->restGatewayCall('deliveryReport/'.urlencode($name).'/custom/'.urlencode($custom).'/'.urlencode($date_start).'/'.urlencode($date_end), 'GET');
		}
		$retparams = array();
		$i = 0;
		foreach($xml->report as $key => $report) {
			$retparams[$i]['name'] = (string)$report['name'];
			$retparams[$i]['last_updated'] = (string)$report['last_updated'];
			$retparams[$i]['extension'] = (string)$report['extension'];
			$z = 0;
			foreach($report->reportrow as $row => $reportrow) {
				$retparams[$i]['reportrow'][$z]['last_updated'] = (string)$reportrow['last_updated'];
				$retparams[$i]['reportrow'][$z]['mobile_number'] = (string)$reportrow['mobile_number'];
				$retparams[$i]['reportrow'][$z]['message_id'] = (string)$reportrow['message_id'];
				$retparams[$i]['reportrow'][$z]['status'] = (string)$reportrow['status'];
				$retparams[$i]['reportrow'][$z]['custom'] = (string)$reportrow['custom'];
				$z++;
			}
			$i++;
		}
		return $retparams;
    }
    
	
    /**
	 * Create a new account (requires additional permissions on your account, please contact Text Marketer to apply)
	 * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('createAccount', 'mypass', 'sandbox');
     *      try {
     *          $result = $tmClient->createAccount('New Company');
     *          
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
	 * 
	 * @param string $companyName the company name for the new account owner
	 * @param type $notificationMobile (optional*) the mobile number of the account (*required if $notificationEmail is not set)
	 * @param type $notificationEmail (optional*) the email address of the account (*required if $notificationMobile is not set)
	 * @param string $username (optional) the username you wish to set on the new account - the API username will be the same
	 * @param string $password (optional) the password you wish to set on the new account - the API password will be the same
	 * @param string $promoCode (optional) a promotional code entitling the account to extra credits
	 * @param boolean $overrideRates If set to true, use the credits rates set on your main account (the account used to access the API), rather than the Text Marketer defaults.
	 * @return array array with 10 keys: 'api_password', 'api_username', 'company_name', 'create_date', 'credits', 'password' and 'username', 'notification_email', 'notification_mobile', 'account_id'
	 * @throws Exception on error
	 */
	public function createSubAccount($companyName, $notificationMobile=null, 
			$notificationEmail=null, $username=null, $password=null, $promoCode=null, 
			$overrideRates=false) 
	{
        return $this->doCreateAccount($companyName, $notificationMobile, 
				$notificationEmail, $username, $password, $promoCode, $overrideRates);
		
    }
	
    /**
     * Get the all errors encountered after the last call to the API
     * @return array associative array with 'error_code'=>'error message' format. Could have more then 1 error code.
     * 
     * <code>
     *  <?php
     *      $tmClient = new TMRestClient('myuser', 'mypass', 'sandbox');
     *      try {
     *          if($tmClient->isLoginValid())
     *              echo 'Login is valid.';
     *      } catch (Exception $ex) {
     *          $errors = $tmClient->getLastErrors();
     *          foreach($errors as $errorcode => $errormsg)
     *              echo "Code $errorcode: $errormsg";
     *      }
     *  ?>
     * </code>
     */
    public function getLastErrors() {
        return $this->lastErrors;
    }
    
	
	
	
	
    /******************************************************************
     * PROTECTED Methods - used internally
     *****************************************************************/
    
    /**
     * Add one error to $lastErrors, use getLastErrors() to retrieve them
     * 
     * @param string $code  Error code
     * @param string $msg   Error message
     * @param boolean $new  TRUE is a new error,   code
     */
    protected function addError($code, $msg, $new = true) {
        if($new)
            $this->lastErrors = array();
        $this->lastErrors[$code] = $msg;
    } 
	
    /**
     * Make the HTTP call to the REST API
     * 
     * @param string $service, e.g. 'credits', 'sms', 'group', etc...
     * @param string $method, 'GET' or 'POST'
     * @param array $params, extra parameters needed for specific service
	 * @return string $xml XML response from the REST API
     * 
     * @throws Exception on error
     */
    protected function restGatewayCall($service = 'credits', $method = 'GET', $params = NULL) {
        $url = $this->apiUrl;
        $url .= $service;
        $fields = array(
            'username' => $this->username,
            'password' => $this->password,
			'apiClient' => 'tm-php-' . self::$VERSION
        );
		if ($this->forceSandbox)
			$fields['sandbox'] = 'true';
		
        if(is_array($params) AND $method != 'PUT')
            $fields = array_merge($fields, $params);

		$body = '';
        $options = array(CURLOPT_RETURNTRANSFER => TRUE);
        if($method == 'POST') {
            $options[CURLOPT_URL] = $url;
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $fields;
        } elseif($method == 'PUT') {
			// PUT uses the request body to take the parameters
            $options[CURLOPT_URL] = $url .'?'.http_build_query($fields, '', '&');
            $options[CURLOPT_PUT] = 1;
			if (!empty($params))
			{
				$body = http_build_query($params, '', '&');
				$fh = fopen('php://memory', 'rw');  
				fwrite($fh, $body);  
				rewind($fh);  
				$options[CURLOPT_INFILE] = $fh;  
				$options[CURLOPT_INFILESIZE] = strlen($body);  
			}
			
        } elseif($method == 'DELETE') {
        	$options[CURLOPT_URL] = $url .'?'.http_build_query($fields, '', '&');
        	$options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        	
        } else { 
            $options[CURLOPT_URL] = $url .'?'.http_build_query($fields, '', '&');
        }
		
		if (defined('TM_DEBUG') AND TM_DEBUG==true)
		{
			echo $options[CURLOPT_URL] . " $body <br>\n" . http_build_query($fields, '', '&') . "<br>\n"; 
		}
		
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $responseBody = curl_exec($ch);
		$this->responseBody = $responseBody;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($httpCode == 400) {
            $xml = simplexml_load_string($responseBody);
            $newerror = TRUE;
            foreach($xml->errors[0]->error as $error) {
                $this->addError((int)$error['code'][0], (string)$error[0], $newerror);
                $newerror = FALSE;
            }
            throw new Exception($xml->errors[0]->error[0], (int)$xml->errors[0]->error['code'][0]);
        }
        elseif($httpCode != 200) {
            $this->addError((int)$httpCode, $responseBody, TRUE);
            throw new Exception($responseBody, (int)$httpCode);
        }
        
        curl_close($ch);
        
		$xml = simplexml_load_string($responseBody);  
		if (empty($xml))
		{
			$additional = (defined('TM_DEBUG') AND TM_DEBUG==true) ? $responseBody : '';
			throw new Exception("Unable to parse XML response $additional");
		}
		
		return $xml;
    }
	
	/**
	 * Internal function
	 * @param string $companyName 
	 * @param type $notificationMobile 
	 * @param type $notificationEmail 
	 * @param string $username 
	 * @param string $password 
	 * @param string $promoCode 
	 * @param boolean $overrideRates 
	 * @return array 
	 * @throws Exception on error
	 */
	protected function doCreateAccount($companyName, $notificationMobile=null, 
			$notificationEmail=null, $username=null, $password=null, $promoCode=null, 
			$overrideRates=false, $resource='account/sub') 
	{
		$params = array('company_name'=>$companyName);
		if (!empty($username)) 
			$params['account_username'] = $username;
		if (!empty($password))
			$params['account_password'] = $password;
		if (!empty($promoCode))
			$params['promo_code'] = $promoCode;
		if (!empty($notificationEmail))
			$params['notification_email'] = $notificationEmail;
		if (!empty($notificationMobile))
			$params['notification_mobile'] = $notificationMobile;

		$xml = $this->restGatewayCall($resource, 'PUT', $params);
			
		$retparams = array(
			'api_password' => (string) $xml->account[0]->api_password,
			'api_username' => (string) $xml->account[0]->api_username,
			'company_name' => (string) $xml->account[0]->company_name,
			'create_date' => (string) $xml->account[0]->create_date,
			'credits' => (int) $xml->account[0]->credits,
			'password' => (string) $xml->account[0]->password,
			'username' => (string) $xml->account[0]->username,
			'notification_email' => (string) $xml->account[0]->notification_email,
			'notification_mobile' => (string) $xml->account[0]->notification_mobile,
			'account_id' => (string) $xml->account[0]->account_id
		);

		return $retparams;
	}
}