#!/usr/bin/env php
<?php

function updateFromAddresses($addressObject, &$fromAddresses)
{
	foreach ($addressObject->from as $fromObject) {
		if (isset($fromObject->mailbox) && isset($fromObject->host)) {
			$fromAddresses[] = $fromObject->mailbox . "@" . $fromObject->host;
		}
	}
}

function getMailboxList($mailbox, $user, $password)
{
	if (!$mbox = imap_open($mailbox, $user, $password))  {
		echo "Cannot connect/check imap mail: " . imap_last_error() . "\n";
		exit(1);
	}

	if (($result = imap_list($mbox, $mailbox, "*")) === false) {
		echo "Cannot get list of mailboxes: " . imap_last_error() . "\n";
		exit(1);
	}
	imap_close($mbox);
	return $result;
}

function getFromAddresses($mailbox, $user, $password)
{
	if (!$mbox = imap_open($mailbox, $user, $password))  {
		echo "Cannot connect/check imap mail: " . imap_last_error() . "\n";
		exit(1);
	}

	if (($msgCount = imap_num_msg($mbox)) === false) {
		echo "Cannot get number of messages in mailbox: " . imap_last_error() . "\n";
		exit(1);
    }

    $fromAddresses = [];
    for ($i =1; $i <= $msgCount; $i++) {
    	updateFromAddresses(imap_rfc822_parse_headers(imap_fetchheader($mbox, $i)), $fromAddresses);
    }

    $sieveText = "[";
    foreach (array_unique($fromAddresses) as $address) {
    	$sieveText .= '"' . $address . '", ';
    }

    imap_close($mbox);

    return rtrim($sieveText, ', ') . "]";
}


$options = getopt('aghlm:u:p:');

if (isset($options['h']) or 
	!(isset($options['m']) and isset($options['u']) and isset($options['p']) and 
		(isset($options['g']) xor isset($options['l']) xor isset($options['a'])))) {
	echo "Usage: " . basename($argv[0]) . " [-h] (-g | -l | -a) -m <mbox> -u <username> -p <password>\n";
	exit(1);
}


if (isset($options['l'])) {
	foreach (getMailboxList($options['m'], $options['u'], $options['p']) as $mailboxName) {
		echo "$mailboxName\n";
	}
} elseif (isset($options['g'])) {
	echo getFromAddresses($options['m'], $options['u'], $options['p']) . "\n";

} elseif (isset($options['a'])) {
	foreach (getMailboxList($options['m'], $options['u'], $options['p']) as $mailboxName) {
		echo "Getting unique sender addresses from " . $mailboxName . " ... ";
		$fromAddresses = getFromAddresses($mailboxName, $options['u'], $options['p']);
		if (strlen($fromAddresses) < 3) {
			echo "no addresses found.\n";
		} else {
			preg_match('/[^}]+$/', $mailboxName, $matches);
			$sieveMailboxName = str_replace('/', '.', $matches[0]);

			$rules[$sieveMailboxName] = getFromAddresses($mailboxName, $options['u'], $options['p']);
			echo "OK.\n";
		}
	}

	foreach ($rules as $key => $value) {
		echo "\n";
		echo 'if address :is "From" ' . $value . " {\n" .
			'  fileinto "' . $key . "\";\n" .
			"  stop;\n" .
			"}\n\n";
	}
}

?>