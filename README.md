Read Email

## Install
```
composer install
```

## Config
```
edit imap mail in src/Config/Config.php
```

## Use
```
require __DIR__ . '/../vendor/autoload.php';

$emailReader = new PHPJEmails\Libraries\Emails\Email_reader();
$result = $emailReader->byDate('2019-03-06', '2019-03-08');
$emailReader->close();

header("Content-Type: text/plain");
print_r($result);
```