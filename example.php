<?php

require_once('classes/apnClass.php');

$apns 	=	new APNS();
$token	=	"553f09c82a449466bd5cd278aaf34a8cxxxxxxxx";


// APPLE APNS EXAMPLE 1
$apns->newMessage();
$apns->addMessageAlert('Message received from Bob');
$apns->addMessageCustom('acme2', array('bang', 'whiz'));
$apns->_pushMessage($token, "sandbox");

exit;


// APPLE APNS EXAMPLE 2
$apns->newMessage();
$apns->addMessageAlert('Bob wants to play poker', 'PLAY');
$apns->addMessageBadge(5);
$apns->addMessageCustom('acme1', 'bar');
$apns->addMessageCustom('acme2', array('bang', 'whiz'));
$apns->_pushMessage($token, "sandbox");

// APPLE APNS EXAMPLE 3
$apns->newMessage();
$apns->addMessageAlert('You got your emails.');
$apns->addMessageBadge(9);
$apns->addMessageSound('bingbong.aiff');
$apns->addMessageCustom('acme1', 'bar');
$apns->addMessageCustom('acme2', 42);
$apns->_pushMessage($token, "production");

// APPLE APNS EXAMPLE 4
$apns->newMessage();
$apns->addMessageAlert(NULL, NULL, 'GAME_PLAY_REQUEST_FORMAT', array('Jenna', 'Frank'));
$apns->addMessageSound('chime');
$apns->addMessageCustom('acme', 'foo');
$apns->_pushMessage($token, "sandbox");

// APPLE APNS EXAMPLE 5
$apns->newMessage();
$apns->addMessageCustom('acme2', array(5, 8));
$apns->_pushMessage($token, "sandbox");

// SEND MESSAGE TO MORE THAN ONE USER
$apns->newMessage();
$apns->addMessageAlert('Greetings Everyone!');
$apns->_pushMessage($token, "sandbox");
?>
