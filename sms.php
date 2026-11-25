
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Twilio\Rest\Client;

define('TWILIO_SID',   'ACc98a6740c16ab2b4a472f321a70010fb');
define('TWILIO_TOKEN', '44c2a0129eca733abd9baad85530181b');
define('TWILIO_FROM',  '+12202144924');

function sendSMS($to, $message) {
    try {
        $client = new Client(TWILIO_SID, TWILIO_TOKEN);
        $client->messages->create(
            '+91' . $to,
            [
                'from' => TWILIO_FROM,
                'body' => $message
            ]
        );
        return true;
    } catch (Exception $e) {
        error_log('SMS Error: ' . $e->getMessage());
        return false;
    }
}
?>