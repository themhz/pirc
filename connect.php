<?php

session_start();

require_once('pirc.php');

if (!isset($_SESSION['irc'])) {
   
	// Initialize IRC connection
	$irc = new IRC('irc.freenode.net', 6667, 'themhz2023', '#greece-cafe');

	if (!$irc->connect()) {
		die("Failed to connect to server");
	}
    //$irc->run();
	$_SESSION['irc'] = $irc;
}

// Update chat messages
if($_GET['hasmessage']=='true'){
    $_SESSION['irc']->send_message("#greece-cafe",$_POST('message'));
    echo "ok";
}else{
    while ($data = socket_read($_SESSION['irc']->socket, 1024)) {
        $_SESSION['irc']->handle_messages($data);
        if (strpos($data, "PRIVMSG") !== false) {
            // Example message format: ":nick!user@host PRIVMSG #channel :message"
            $parts = explode(" ", $data);
            $sender = substr($parts[0], 1);  // remove leading colon
            $channel = $parts[2];
            $message = substr($data, strpos($data, ":", 1) + 1);
            $_SESSION['chat_messages'][] = "<strong>$sender:</strong> $message";
        }
    }
    
    // Output chat messages
    if (!isset($_SESSION['chat_messages'])) {
        $_SESSION['chat_messages'] = array();
    }
    foreach ($_SESSION['chat_messages'] as $chat_message) {
        echo "<p>$chat_message</p>";
    }
}

