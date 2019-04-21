<HTML>
<HEAD><TITLE>PushOver Callback Interface</TITLE></HEAD>
<BODY>

<?php 

#<!--
#  receipt = (your receipt ID)
#  acknowledged = 1
#  acknowledged_at = 1360019238 (a Unix timestamp of when the notification was acknowledged)
#  acknowledged_by = (user key) (the user key of the user that first acknowledged the notification)
#-->

# DB:
# MariaDB [pushover]> describe alert;
# +-------------+-------------+------+-----+---------+-------+
# | Field       | Type        | Null | Key | Default | Extra |
# +-------------+-------------+------+-----+---------+-------+
# | timestamp   | int(11)     | YES  |     | NULL    |       |
# | priority    | tinyint(4)  | YES  |     | NULL    |       |
# | alerttype   | tinyint(4)  | YES  |     | NULL    |       |
# | receipt     | char(32)    | YES  |     | NULL    |       |
# | hostname    | varchar(64) | YES  |     | NULL    |       |
# | servicename | varchar(64) | YES  |     | NULL    |       |
# +-------------+-------------+------+-----+---------+-------+


# Settings:
$sticky = 0;		# Set to 2 to remain untill recovery
$notify = 0;		# Set to 1 to enable notifications
$persist = 0;		# Set to 1 to Persist comment after recovery
$comment = "Acknowledged via PushOver Callback";
$cmdsocket = "/var/lib/icinga/rw/icinga.cmd";
$logfile = "/tmp/pushover.log";

# Arguments
$receipt = $_POST['receipt'];
$timestamp = $_POST['acknowledged_at'];
$user = $_POST['acknowledged_by'];
$dateprint = date( DATE_RFC2822, $timestamp);
$found = 0;

file_put_contents($logfile, "Received callback for receipt: $receipt from user $user on timestamp: $dateprint ($timestamp)\n", FILE_APPEND );

if( $receipt != "" )
{
	$db = new PDO('mysql:host=localhost;dbname=pushover;charset=utf8mb4', 'USERNAME', 'PASSWORD');

	$stmt = $db->prepare("SELECT * FROM alert WHERE receipt=? limit 1");
	$stmt->execute(array($receipt));

	foreach($results = $stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
	{
   		#var_dump($row);
		$hostname = $row['hostname'];
		$servicename = $row['servicename'];
		$alerttype = $row['alerttype'];
		$timestamp = $row['timestamp'];
		$found++;

		print "Found matching alert: $hostname / $servicename / $alerttype / $timestamp\n";

		print "Sending notitication to nagios/icinga\n";
		if ( $alerttype == 0 )
		{
			$command = "[$timestamp] ACKNOWLEDGE_HOST_PROBLEM;$hostname;$sticky;$notify;$persist;Callback;$comment";
			file_put_contents($cmdsocket, $command, FILE_APPEND );
			file_put_contents($logfile, "[$timestamp] ACKNOWLEDGE_HOST_PROBLEM;$hostname;$sticky;$notify;$persist;Callback;$comment", FILE_APPEND );
		}
		elseif ( $alerttype == 1 )
		{
			$command = "[$timestamp] ACKNOWLEDGE_SVC_PROBLEM;$hostname;$servicename>;$sticky;$notify;$persist;Callback;$comment";
			file_put_contents($cmdsocket, $command, FILE_APPEND );
			file_put_contents($logfile, "[$timestamp] ACKNOWLEDGE_SVC_PROBLEM;$hostname;$servicename>;$sticky;$notify;$persist;Callback;$comment", FILE_APPEND );
		}
	}

	if ( $found == 1 )
	{
		print "Removing callback entry from database\n";
		$stmt = $db->prepare("delete from alert where receipt=? limit 1");
		$results = $stmt->execute(array($receipt));
		print "Result = $results\n";
	}

}

?>
</BODY>
</HTML>
