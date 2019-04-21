#!/usr/bin/php
<?php
include "Pushover.php";

# Getopts
$shortopts  = "";
$shortopts .= "u:";		# User token
$shortopts .= "p:";		# state-id
$shortopts .= "t:";		# Notification type: 0 = host, 1 = service
$shortopts .= "h:";		# Hostname
$shortopts .= "s:";		# Servicename (optional for host-notifications)

$longopts  = array(
    "usertoken:",  		# User token
    "stateid:",			# Priority
	"alerttype:",		# Notification type: 0 = host, 1 = service
	"hostname:",		# Hostname
	"servicename:",	# Servicename (optional for host-notifications)
);
$options = getopt($shortopts, $longopts);

$timestamp = time();
$stateid = $options['stateid'];
$alerttype = $options['alerttype'];
$hostname = $options['hostname'];
$servicename = $options['servicename'];
$receipt = NULL;
$message = file_get_contents("php://stdin");
$logfile = "/tmp/notification.log";

# Magios state id's
# host-state-id: 0=UP, 1=DOWN, 2=UNREACHABLE.
# service-state-id: 0=OK, 1=WARNING, 2=CRITICAL, 3=UNKNOWN.
# PushOver Priority:
# 0 = regular
# 1 = high prio
# 2 = high prio until acknowledgement

print "Creating object\n";
$n = new Pushover();

print "Setting up object\n";
$n->setToken( "TOKENTOKENTOKEN" );
$n->setUser( $options['usertoken'] );
$alerttypenr = 0;

if ($alerttype === "host")
{	
	print "Found 'host' type alert\n";
	$alerttypenr = 1;
	$n->setTitle( "$hostname failed" );
	if ( $options['stateid'] != 0 )		# Down or unreachable
	{
		$priority = 2;
	}
	else								# OK
	{
		$priority = 0;
	}
}
elseif( $alerttype === "service" )
{
	print "Found 'service' type alert\n";
	$alerttypenr = 2;
	$n->setTitle( "$servicename failed on $hostname" );
	if ( $options['stateid'] <= 1 )		# OK or WARNING
	{
		$priority = 0;
	}
	elseif ( $options['stateid'] == 3 )	# Unknown
	{
		$priority = 1;
	}
	else								# Failed
	{
		$priority = 2;
	}
}
else
{
	print "Unknown type alert\n";
}
$n->setMessage( "$message" );
$n->setCallback( "https://path-to-your/callbackscript.php" );
$n->setPriority( $priority );
$n->setExpire( 3600 );
$n->setRetry( 90 );
$n->setDebug( 1 );


print "Debugging:\n";
print $n->getPriority() . "\n";
print $n->getUser() . "\n";

print "Calling send()\n";
$retval = $n->send();

$receipt = $n->getReceipt();

print "Done\nReceipt = $receipt\n";

if ( $receipt != "" )
{
	$db = new PDO('mysql:host=localhost;dbname=pushover;charset=utf8mb4', 'USERNAME', 'PASSWORD');

	$stm = $db->prepare("insert into alert (timestamp, priority, alerttype, hostname, servicename, receipt) values ('$timestamp', '$priority', '$alerttypenr', '$hostname', '$servicename', '$receipt' )");

	$result = $stm->execute();

	print "Done\nResult = $result\n";
	file_put_contents($logfile, "Query = insert into alert (timestamp, priority, alerttype, hostname, servicename, receipt) values ('$timestamp', '$priority', '$alerttypenr', '$hostname', '$servicename', '$receipt' )\n", FILE_APPEND );
	file_put_contents($logfile, "Result = $result\n", FILE_APPEND );

}

?>
