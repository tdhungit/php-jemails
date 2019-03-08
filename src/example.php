<?php
/**
 * Created by AVOCA.IO
 * Website: http://avoca.io
 * User: Jacky
 * Email: hungtran@up5.vn | jacky@youaddon.com
 * Person: tdhungit@gmail.com
 * Skype: tdhungit
 * Git: https://github.com/tdhungit
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';

$emailReader = new PHPJEmails\Libraries\Emails\Email_reader();
$result = $emailReader->byDate('2019-03-06', '2019-03-08');
$emailReader->close();

header("Content-Type: text/plain");
print_r($result);