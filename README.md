# Expect
Use of 'proc_open' to simulate expect behavior.
That's a mix between 'expect' command line tool and the PHP library: http://php.net/manual/fr/book.expect.php

# Sample code

````
<?php

use Smalot\Expect\Expect;

$expect = new Expect();
$expect->open('telnet 192.168.59.103 4002');

while (1) {
	switch ($expect->expect(
	  array(
		'escape'       => array('/.*Escape character.*\n/mis', Expect::EXP_REGEXP),
		'command line' => array('/.+#/', Expect::EXP_REGEXP),
	  ),
	  $match
	)) {
		case 'escape':
			var_dump('escape', $match);
			$expect->write('');
			break;

		case 'command line':
			var_dump('command line', $match);
			$expect->write('show cdp');
			break 2;

		case Expect::EXP_TIMEOUT:
			die('timeout');

		case Expect::EXP_EOL:
			die('eol');
	}
}

while (1) {
	switch ($expect->expect(
	  array(
		'command line' => array('/(.*)[\r\n]+([^\n]+#)/mis', Expect::EXP_REGEXP),
	  ),
	  $match
	)) {
		case 'command line':
			var_dump('result', $match);
			break 2;

		case Expect::EXP_TIMEOUT:
			die('timeout');

		case Expect::EXP_EOL:
			die('eol');
	}
}



$expect->close();


?>
````
