textmarketer-sms-php-sdk
========================

TextMarketer's PHP SDK for easy integration between your app and our SMS messaging REST API.

## Example

```php
// REPLACE with your Text Marketer API username and password
$client = new \TextMarketer\Client('myuser', 'mypass', 'production');

try {
	
	$credits = $client->getCredits();
	echo "I have $credits credits.<br />\n";

	if ($credits > 0)
		$result = $client->sendSMS('Hello SMS World!', '447777777777', 'My Name');
	
	echo "Used {$result['credits_used']} credits, message ID {$result['message_id']}, scheduled ID {$result['scheduled_id']}<br /><br />\n"; 
	
} catch (Exception $ex) {
	// handle a possible error
	echo "An error occurred: {$ex->getCode()}, {$ex->getMessage()}";
}
```