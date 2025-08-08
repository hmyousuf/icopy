<?php
require_once(dirname(__FILE__) . '/lib/classes/Imap.php');
require_once(dirname(__FILE__) . '/lib/utils.php');
date_default_timezone_set('Asia/Dhaka');

if (version_compare(phpversion(), '5.3.2', '<')) {
    printf("ERROR: requires at least PHP version 5.3.2 (this is version %s)\n", phpversion());
    die(15);
}



// To connect to an SSL IMAP or POP3 server, add /ssl after the protocol
// specification:
// $mbox = imap_open ("{mail.gazi.com:993/imap/ssl/novalidate-cert}INBOX", "yousuf@gazi.com", "Lalmia@mail2020");



// $srv = "{mail.gazi.com:993/imap/ssl/novalidate-cert}";
// $srv = "{mail.lalmia.com:993/imap/ssl/novalidate-cert}";
// $user = "yousuf@lalmia.com";
// $pass = "lalmia@mail";

// $mbox = imap_open($srv, $user, $pass, OP_HALFOPEN)
      // or die("can't connect: " . imap_last_error());

// $list = imap_list($mbox, $srv, "*");
// if (is_array($list)) {
    // foreach ($list as $val) {
        // echo imap_utf7_decode($val) . "\n";
    // }
// } else {
    // echo "imap_list failed: " . imap_last_error() . "\n";
// }

// imap_close($mbox);






/*---------------------------------------*/
die(PHP_EOL .'END-OF-SCRIPT'. PHP_EOL);
/*---------------------------------------*/

$longOpt = array('cfg:','build::','run','show::','comp::','log','verb'); 
$shortOpt = 'c:';
$opts = getopt($shortOpt,$longOpt);
$params_ok = $argc >= 3 ? true : false;
$opt_conf_ok = $params_ok && !empty($opts['cfg']) || !empty($opts['c']) ? true : false;
$opt_build_ok = $opt_conf_ok && isset($opts['build']) ? true : false;
$opt_mkbd_ok = $opt_build_ok && isset($opts['bdate']) ? true : false;
$opt_show_ok = $opt_conf_ok && isset($opts['show']) ? true : false;
$opt_run_ok = $opt_conf_ok && isset($opts['run']) ? true : false;
$opt_mkms_ok = $opt_run_ok && isset($opts['max-size']) && is_numeric($opts['max-size']) ? true : false;
$opt_comp_ok = $opt_conf_ok && isset($opts['comp']) ? true : false;
$opt_log_ok = $opt_conf_ok && isset($opts['log']) ? true : false;
$opt_verb_ok = $opt_conf_ok && isset($opts['verb']) ? true : false;

if ($opt_conf_ok && $opt_build_ok || $opt_show_ok || $opt_run_ok || $opt_comp_ok) {
	// Config file checking and loading
	$confFile = $opts['c'];
    if (file_exists($confFile)) {
        $conf = json_decode(file_get_contents($confFile), true);
        if (!is_array($conf) || !isset($conf['src']) || !isset($conf['dst'])) {
            printf("ERROR: invalid/incomplete configuration in '%s'\n", $confFile);
            die(15);
        }
    }
    else {
        printf("ERROR: configuration file not found: %s\n", $confFile);
        die(15);
    }
	// trck file path variable
	$trackFile = dirname(__FILE__).'/'.basename($confFile,'.json').'.track';
	$trackFileBkp = $trackFile.'.bkp';
	// sdate paring and setting variable
	if (isset($opts['bdate'])) { $bDate = date('D, d M Y H:i:s O',  strtotime($opts['bdate'])); }
	// show parameter processing
	
	//comp var setting
	
	//log file name
	if (isset($opts['log'])) { $logFile = dirname(__FILE__).'/'.basename($confFile,'.json').'.log'; }
	
} else {
	// show help
	echo '	Usage: php '.basename($argv[0]).' -c <confFile> <option[s]>'.PHP_EOL . PHP_EOL;
	echo '	-c <ConfFile>		[Path To Config File]'.PHP_EOL;
	echo '	--build/--build=full	Show Folder List (full Flag will build and store the message list'.PHP_EOL;
	echo '	--show=<Options>		[Show Tracker File Summery (Default). Valid options "size, pend"]'.PHP_EOL;
	echo '	--run				Start Processing The Tracker File.]'.PHP_EOL;
	echo '	--comp/--comp=reload		Compare On Stored Message List. (Reload Flag will regenerate Message List)'.PHP_EOL;
	echo '	--log				[Inactive Feature.]'.PHP_EOL;
	echo PHP_EOL;
	die(15);
}

// die(100);
$conQuota = isset($conf['global']['conQuota']) ? $conf['global']['conQuota'] : 50000000;
$maxMsgSize = isset($conf['global']['maxMsgSize']) ? $conf['global']['maxMsgSize'] : 15000000 ;


$trackFile = dirname(__FILE__).'/'.basename($confFile,'.json').'.SyncList.TRACK';
$trackFileBkp = $trackFile.'.bkp';
$trackCompSrc = dirname(__FILE__).'/'.basename($confFile,'.json').'.CompSrc.TRACK';
$trackCompDst = dirname(__FILE__).'/'.basename($confFile,'.json').'.CompDst.TRACK';

$OK = "\t\e[32m OK \e[39m";
$ERROR = "\t\e[31m ERROR \e[39m";

/* BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_BUILD_*/
if ($opt_build_ok) {
	// echo 'BUILD'.PHP_EOL; die(15);
	$buildFull = $opts['build'] == 'full' ? true : false;
	$bDate = isset($opts['bdate']) ? date('U',strtotime($opts['bdate'])) : 0;
	
	imap_errors();
	// CONNECTING SRC SERVER
	$src = new Imap($conf['src']);
	echo "[SRC] Connecting to: ".$src->getMailbox();
	$src->connect();
	if ($src->isConnected()) { echo $OK; } else { print imap_last_error().PHP_EOL .$ERROR; die(15); }
	echo PHP_EOL;
	
	$sFolderCount = 0;
	$sTotalMsgCount = 0;
	$sFolderList = $src->getSubFolders('', '*');
	$sFolderCount = count($sFolderList);
	$folderData;

	if ( $sFolderCount == 0 ) { echo 'NO FOLDER SELECTED!'.$ERROR.PHP_EOL; die(15); }
	else {
		$folderData[0] = array('config_file'=>$confFile,'sFolderCount'=>$sFolderCount,'sTotalMsgCount'=>0);
		$sl = 0;

		echo 'NUMBER OF FOLDER SELECTED: '.$sFolderCount.$OK.PHP_EOL;
		echo PHP_EOL . ' ==| LIST OF SELECTED FOLDERS |==' .PHP_EOL;

		foreach ($sFolderList as $sFolder) {
				$sc = 0;
				$sl ++;
				if (!$src->openFolder($sFolder)) { echo 'FAILED TO OPEN FOLDER: '.$sFolder.$ERROR; die(31); }
				else {
					$sFolderMsgCount = $src->getFolderMessagesCount();
					$sMsgHdrList = array();


					if ($buildFull) {
						for ( $i=1; $i <= $sFolderMsgCount; $i++ ) {
							$sMsgHdrInfo = (array) $src->getMessageHeaderInfo($i);

							$sMsgSubject = !empty($sMsgHdrInfo['subject']) ? mb_decode_mimeheader($sMsgHdrInfo['subject']) : '';
							
							// $sMsgUID = $src->getMessageUID($i);
							// Processing Subject 
							// if (!empty($sMsgHdrInfo->subject)) {
								// echo $i.' - '.addslashes($sMsgHdrInfo['subject']).' len: '.strlen($sMsgHdrInfo['subject']).PHP_EOL;
								// $sMsgSubject = urlencode(mb_decode_mimeheader($sMsgHdrInfo['subject']));
							// } else { $sMsgSubject = ''; }
							// $sMsgID = isset($sMsgHdrInfo['message_id']) ? mb_decode_mimeheader($sMsgHdrInfo['message_id']) : '';
							
							
							$sMsgHdrList[$i] = array('SUB'=>$sMsgSubject, 'DATE'=>$sMsgHdrInfo['date'],'SIZE'=>$sMsgHdrInfo['Size'],'MAX-SIZE'=>0,'DONE'=>0);
							if ( $sMsgHdrInfo['Size'] > $maxMsgSize ) { $sMsgHdrList[$i]['MAX-SIZE'] = 1; $sc ++; }
						}

					$folderData[0]['sTotalMsgCount'] += $sFolderMsgCount;
					$folderData[$sl] = array('folder_name'=>$sFolder,'folder_msg_count'=>$sFolderMsgCount,'done_counter'=>0,'size_counter'=>$sc, 'sMsgList' => $sMsgHdrList );
					} // buildfull -> generate messages array
					print '=> ["'.$sFolder.'"] Msg Count: '.$sFolderMsgCount.PHP_EOL;
				} //Loading Message Data Per Folder
		} //Looping Folder Data
		// if (!$buildFull) { }
		// else { print PHP_EOL; }
		// CLOSING  Connections
		$src->disconnect();
	
		if ( $buildFull ) {
			$sFolderJSON =  json_encode($folderData, JSON_PRETTY_PRINT);
			print PHP_EOL;
			if (file_exists ($trackFile)) {
				echo ' Track File Exist. Creating Backup:';
				if (rename($trackFile, $trackFileBkp)) 
				{ echo $OK.PHP_EOL; } else { echo $ERROR.PHP_EOL; die(31);}
		
				echo ' Saving Tracker:';
				if ( file_put_contents($trackFile,$sFolderJSON) > 0 ) 
				{ echo $OK.PHP_EOL; } else { echo $ERROR.PHP_EOL; die(31); }
			} else {
				echo ' Saving Tracker:';
				if ( file_put_contents($trackFile,$sFolderJSON) > 0 ) 
				{ echo $OK.PHP_EOL; } else { echo $ERROR.PHP_EOL; die(31); }
			}
		} else { print PHP_EOL . ' Please Run Again with "full" option to Build the Tracker File' . PHP_EOL;  }
	}
} // END OF BUILD TRACK

/* SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_SHOW_*/
if ($opt_show_ok) {
	$showSize = strlen($opts['show']) > 0 && $opts['show'] == 'size' ? true : false;
	$showPend = strlen($opts['show']) > 0 && $opts['show'] == 'pend' ? true : false;
	$showFolder = strlen($opts['show']) > 0 && $opts['show'] == 'folder' ? true : false;
	$showDone = strlen($opts['show']) > 0 && $opts['show'] == 'done' ? true : false;
	// $showDone = strlen($opts['show']) == 0 ? true : false;
	
	if (is_file($trackFile)) {
		$sFolderReadIn = file_get_contents($trackFile);
		echo 'Loading Track File:';
		if (strlen($sFolderReadIn) > 0) { echo $OK.PHP_EOL; $sFolderARR = json_decode($sFolderReadIn,TRUE); } else { echo $ERROR.PHP_EOL; die(31);}
	} else { echo 'Could Not Load Track File:'.$ERROR.PHP_EOL; die(31); }

	print 'confFile: ["'.$sFolderARR[0]['config_file'].'"]'. PHP_EOL .'Total Folder Count: ["'.$sFolderARR[0]['sFolderCount'].'"]'. PHP_EOL .'Total Message Count: ["'.$sFolderARR[0]['sTotalMsgCount'].'"]'.PHP_EOL . PHP_EOL;
		
		$done_count = 0;
		$pend_count = 0;
		$size_count = 0;
	for ($fi = 1; $fi <= count($sFolderARR) - 1; $fi++ ) {
		
		print 'Folder:"'.$sFolderARR[$fi]['folder_name'].'" Msg_Count:"'.$sFolderARR[$fi]['folder_msg_count'].'" Done_Count:"'.$sFolderARR[$fi]['done_counter'].'" Skip_Size_Count:"'.$sFolderARR[$fi]['size_counter'].'"'.PHP_EOL . PHP_EOL;
		
		
		if (!$showFolder) {
			// $last_error_capture = imap_last_error();
			$sFolderMsgCount = $sFolderARR[$fi]['folder_msg_count'];
			$sFolder = $sFolderARR[$fi]['folder_name'];
			for ( $mi=1; $mi <= count($sFolderARR[$fi]['sMsgList']); $mi++) {
				$sMsgSubject = addslashes($sFolderARR[$fi]['sMsgList'][$mi]['SUB']);

				if ( $showSize && $sFolderARR[$fi]['sMsgList'][$mi]['MAX-SIZE'] == 1) {
					$size_count ++;
					print $size_count.' M-SIZE->Folder:"'.$sFolder.'" Date:"'.bd_date($sFolderARR[$fi]['sMsgList'][$mi]['DATE']).'" Subject:"'.$sFolderARR[$fi]['sMsgList'][$mi]['SUB'].'" Size:"'.pretty_byte($sFolderARR[$fi]['sMsgList'][$mi]['SIZE']).'"'.PHP_EOL;
				}

				if ( $showPend && $sFolderARR[$fi]['sMsgList'][$mi]['DONE'] == 0 && $sFolderARR[$fi]['sMsgList'][$mi]['MAX-SIZE'] == 0) {
					$pend_count ++;
					print $pend_count.' PEND->Folder:"'.$sFolder.'" Date:"'.bd_date($sFolderARR[$fi]['sMsgList'][$mi]['DATE']).'" Subject:"'.$sMsgSubject.'" Size:"'.pretty_byte($sFolderARR[$fi]['sMsgList'][$mi]['SIZE']).'"'.PHP_EOL;
				} 
				
				if ( $showDone && $sFolderARR[$fi]['sMsgList'][$mi]['DONE'] == 1) {
					$done_count ++;
					print $done_count.' DONE->Folder:"'.$sFolder.'" Date:"'.bd_date($sFolderARR[$fi]['sMsgList'][$mi]['DATE']).'" Subject:"'.$sFolderARR[$fi]['sMsgList'][$mi]['SUB'].'" Size:"'.pretty_byte($sFolderARR[$fi]['sMsgList'][$mi]['SIZE']).'"'.PHP_EOL;
		
				}
			}
		} // Folder Loop END

	} //Folder Loop
}// END OF SHOWTRACK

/* RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_RUN_*/
if ($opt_run_ok) {
	// echo 'RUN'.PHP_EOL; die(15);
	if (is_file($trackFile)) {
		$sFolderReadIn = file_get_contents($trackFile);
		echo 'Loading Track File:';
		if (strlen($sFolderReadIn) > 0) { echo $OK.PHP_EOL; $sFolderARR = json_decode($sFolderReadIn,TRUE); } else { echo $ERROR.PHP_EOL; die(31);}
	} else { echo 'Could Not Load Track File:'.$ERROR.PHP_EOL; die(31); }
	
	$day_tracker_key = date('\D\T\:Y.m.d');
	// CONNECTING SRC AND DST SERVER
	$src = new Imap($conf['src']);
	echo "[SRC] Connecting to: ".$src->getMailbox();
	$src->connect();
	if ($src->isConnected()) { echo $OK; } else { echo imap_last_error().$ERROR; die(15); }
	echo PHP_EOL;
	
	$dst = new Imap($conf['dst']);
	echo "[DST] Connecting to: ".$dst->getMailbox();
	$dst->connect();
	if ($dst->isConnected()) { echo $OK; } else { echo imap_last_error().$ERROR; die(15); }
	echo PHP_EOL;



	// Printing folder summery headder @ array member 0
	print 'confFile: ["'.$sFolderARR[0]['config_file'].'"]'. PHP_EOL .'Total Folder Count: ["'.$sFolderARR[0]['sFolderCount'].'"]'. PHP_EOL .'Total Message Count: ["'.$sFolderARR[0]['sTotalMsgCount'].'"]'.PHP_EOL . PHP_EOL;

	for ($fi = 1; $fi <= count($sFolderARR) - 1; $fi++ ) {

		print 'Folder:"'.$sFolderARR[$fi]['folder_name'].'" Msg_Count:"'.$sFolderARR[$fi]['folder_msg_count'].'" Done_Count:"'.$sFolderARR[$fi]['done_counter'].'" Skip_Size_Count:"'.$sFolderARR[$fi]['size_counter'].'"]'.PHP_EOL;

		$sFolder = $sFolderARR[$fi]['folder_name'];
		
// CREATING DST FOLDER PATH AND FOLDER
	$sFolderPath = $src->splitFolderPath($sFolder);
	$dFolder = $dst->joinFolderPath($sFolderPath, true);
	$dFolder = $dst->getMappedFolder($dFolder);
	$dFolder = $dst->popFolder($dFolder);
	$dFolder = $dst->pushFolder($dFolder);
			// Create folder at DST
	$dst->createFolder($dFolder);
			//open dst folder
	$dst->openFolder($dFolder);
			//Open src folder
		$src->openFolder($sFolder);
			
		for ( $mi=1; $mi <= count($sFolderARR[$fi]['sMsgList']); $mi++) {
			
			//Select message
			if ( $sFolderARR[$fi]['sMsgList'][$mi]['DONE'] != 1 && $sFolderARR[$fi]['sMsgList'][$mi]['MAX-SIZE'] != 1) {
				if ($conQuota >= 0) {
					//load message from src
					/* 
					*/
					$sMsg = $src->loadMessage($mi);
					
					$sMsgHdrInfo = $src->getMessageHeaderInfo($mi);
					$sMsgHdrInfo->subject =  mb_decode_mimeheader($sMsgHdrInfo->subject);
					$sMsgHdrInfo->Subject =  mb_decode_mimeheader($sMsgHdrInfo->Subject);


					print $mi.' PROCESSING>Folder:"'.$sFolder.'" Date:"'.bd_date($sFolderARR[$fi]['sMsgList'][$mi]['DATE']).'" Subject:"'.$sFolderARR[$fi]['sMsgList'][$mi]['SUB'].'" Size:"'.pretty_byte($sFolderARR[$fi]['sMsgList'][$mi]['SIZE']).'"';
					//Storing to dst
					/* */
					// Flashing Imap Error Buffer
					imap_errors();
					// if (true) {
					if (!$dst->storeMessage($dFolder, $sMsg, $sMsgHdrInfo)) {
						echo $ERROR.'#'.imap_last_error().PHP_EOL;
						
					} else {
						echo $OK.PHP_EOL;
						$sFolderARR[$fi]['sMsgList'][$mi]['DONE'] = 1;
						$sFolderARR[$fi]['done_counter'] ++;
						$sFolderARR[0][$day_tracker_key] += $sFolderARR[$fi]['sMsgList'][$mi]['SIZE'] ;
						
						// SAVING PROGRESS TO TRACKER
						$sFolderJSON =  json_encode($sFolderARR,JSON_PRETTY_PRINT);
						if (file_exists ($trackFile)) {
							// echo 'Track File Exist. Creating Backup:';
							if (!rename($trackFile, $trackFileBkp)) 
							// { echo $OK.PHP_EOL; } else { echo $ERROR.PHP_EOL; die(31);}
							{ echo 'Backup Tracker Failed! '.$ERROR.PHP_EOL; die(31);}
					
							// echo 'Saving Tracker:';
							if ( file_put_contents($trackFile,$sFolderJSON) == 0 )
							// { echo $OK.PHP_EOL; } else { echo $ERROR.PHP_EOL; die(31); }
							{ echo 'Saving Tracker Failed! '.$ERROR.PHP_EOL; die(31); }
						}
		
						// SAVING PROGRESS TO TRACKER DONE
						$conQuota = $conQuota - $sFolderARR[$fi]['sMsgList'][$mi]['SIZE'];
						sleep(1);
					} // message store routine
				} else { // Conn Quota
					echo 'CQ: '.$conQuota." Connection Quota Exceeded!, RUN AGAIN!".$ERROR.PHP_EOL; die(63);
				}  // Conn Quota
			} else {
				if ($opt_verb_ok && $sFolderARR[$fi]['sMsgList'][$mi]['DONE'] == 1) {	
					print 'DONE>Folder:"'.$sFolder.'" Date:"'.bd_date($sFolderARR[$fi]['sMsgList'][$mi]['DATE']).'" Subject:"'.$sFolderARR[$fi]['sMsgList'][$mi]['SUB'].'" Size:"'.pretty_byte($sFolderARR[$fi]['sMsgList'][$mi]['SIZE']).'"'.PHP_EOL;
				}
				if ($opt_verb_ok && $sFolderARR[$fi]['sMsgList'][$mi]['MAX-SIZE'] == 1) {
					print 'SIZE>Folder:"'.$sFolder.'" Date:"'.bd_date($sFolderARR[$fi]['sMsgList'][$mi]['DATE']).'" Subject:"'.$sFolderARR[$fi]['sMsgList'][$mi]['SUB'].'" Size:"'.pretty_byte($sFolderARR[$fi]['sMsgList'][$mi]['SIZE']).'"'.PHP_EOL;
				}
			} //done 
			
			
		} // msg loop
	} //folder loop
	// CLOSING  Connections
	$src->disconnect();
// $dst->disconnect();
	die(127);
}// END OF RUNTRACK


/* COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_COMP_*/
if ($opt_comp_ok) {
	$comp_reload = $opts['comp'] == 'reload' ? true : false;

	if ($comp_reload) {
		// CONNECTING SRC AND DST SERVER
		$src = new Imap($conf['src']);
		echo "[SRC] Connecting to: ".$src->getMailbox();
		$src->connect();
		if ($src->isConnected()) { echo $OK; } else { echo imap_last_error().$ERROR; die(15); }
		echo PHP_EOL;
		
		$dst = new Imap($conf['dst']);
		echo "[DST] Connecting to: ".$dst->getMailbox();
		$dst->connect();
		if ($dst->isConnected()) { echo $OK; } else { echo imap_last_error().$ERROR; die(15); }
		echo PHP_EOL;
		
		$sFolderCount = 0;
		$sTotalMsgCount = 0;
		$dTotalMsgCount = 0;
		$sFolderList = $src->getSubFolders('', '*');
		$sFolderCount = count($sFolderList);
		
		$src_msg_list = array();
		$dst_msg_list = array();

		if ( $sFolderCount == 0 ) { echo 'NO FOLDER SELECTED!'.$ERROR.PHP_EOL; die(15); }
		else {
			// echo 'COMPARING FOLDER: '.$sFolderCount.$OK.PHP_EOL;
			
			$sl = 0;
			
			//Looping Through each Folder
			foreach ($sFolderList as $sFolder) {
				$sl ++;
				$sFolderPath = $src->splitFolderPath($sFolder);
				$dFolder = $dst->joinFolderPath($sFolderPath, true);
				$dFolder = $dst->getMappedFolder($dFolder);
				$dFolder = $dst->popFolder($dFolder);
				$dFolder = $dst->pushFolder($dFolder);
				
				if (!$src->openFolder($sFolder)) { echo 'FAILED TO OPEN FOLDER: '.$sFolder.$ERROR; die(31); }
				else {
					// IMAP SRC FOLDER MSG COUNT
					$sFolderMsgCount = $src->getFolderMessagesCount();
					// LOOPING THROUGH Messeges
					for ( $smi=1; $smi <= $sFolderMsgCount; $smi++ ) {
							
						$sMsgHdr = (array) $src->getMessageHeaderInfo($smi);
						
						
						$smsg_sub = !empty($sMsgHdr['subject']) ?  mb_decode_mimeheader($sMsgHdr['subject']) : '';
						

						// $smsg_date = mb_decode_mimeheader($sMsgHdr['date']);
		//
						if ( $sMsgHdr['Size'] <= $maxMsgSize ) {
							$src_msg_list[$sFolder][$smi] = array('date'=>$sMsgHdr['date'],'udate'=>$sMsgHdr['udate'],'subject'=>$smsg_sub,'size'=>$sMsgHdr['Size'],'mflag'=>0,'s-skip'=>0);
						} else {
							$src_msg_list[$sFolder][$smi] = array('date'=>$sMsgHdr['date'],'udate'=>$sMsgHdr['udate'],'subject'=>$smsg_sub,'size'=>$sMsgHdr['Size'],'mflag'=>0,'s-skip'=>1);
						}
					} // SRC MSG HEADER LOOP
				} // SRC Folder Loop

				// print PHP_EOL .'DST_FOLDER: '. $dFolder . PHP_EOL;
				if (!$dst->openFolder($dFolder)) { echo 'FAILED TO OPEN FOLDER: '.$dFolder.$ERROR; die(31); }
				else {
					// IMAP DSC FOLDER MSG COUNT
					$dFolderMsgCount = $dst->getFolderMessagesCount();
					// LOOPING THROUGH Messeges
					for ( $dmi=1; $dmi <= $dFolderMsgCount; $dmi++ ) {
						
						$dMsgHdr = (array) $dst->getMessageHeaderInfo($dmi);
	
						$dmsg_sub = !empty($dMsgHdr['subject']) ? mb_decode_mimeheader($dMsgHdr['subject']) : '';
						
						// $dmsg_date = mb_decode_mimeheader($dMsgHdr['date']);
						
						if ( $dMsgHdr['Size'] <= $maxMsgSize ) {
							$dst_msg_list[$dFolder][$dmi] = array('date'=>$dMsgHdr['date'],'udate'=>$dMsgHdr['udate'],'subject'=>$dmsg_sub,'size'=>$dMsgHdr['Size']);
						}
						
					} // DST MSG HEADER LOOP
				} // DST FOLDER LOOP
			} //Looping Through each Folder
			// CLOSING  Connections
			$src->disconnect();
			$dst->disconnect();
		} // CHECKING FOLDER SELECTION
		
		$comp_src_json =  json_encode($src_msg_list, JSON_PRETTY_PRINT);
		$comp_dst_json =  json_encode($dst_msg_list, JSON_PRETTY_PRINT);
		
		if ( file_put_contents($trackCompSrc,$comp_src_json) > 0 ) { echo 'Saving SRC Tracker'.$OK.PHP_EOL;} else { echo $ERROR.PHP_EOL; die(31); }
		if ( file_put_contents($trackCompDst,$comp_dst_json) > 0 ) { echo 'Saving DST Tracker'.$OK.PHP_EOL;} else { echo $ERROR.PHP_EOL; die(31); }
	} else { // not reload
		
		// READING AND LOADING TRACK FILE
		$src_read_in = file_get_contents($trackCompSrc);
		$dst_read_in = file_get_contents($trackCompDst);
		if (strlen($src_read_in) > 0) { echo 'SRC Track Loading'.$OK.PHP_EOL; $src_data_list = json_decode($src_read_in,TRUE); } else { echo $ERROR.PHP_EOL; die(31);}
		if (strlen($dst_read_in) > 0) { echo 'DST Track Loading'.$OK.PHP_EOL; $dst_data_list = json_decode($dst_read_in,TRUE); } else { echo $ERROR.PHP_EOL; die(31);}
	
		// print_r($src_data_list);
		// print_r($dst_data_list);
		
		print PHP_EOL;
		// LOOPING EACH FOLDER
		foreach ( array_keys($src_data_list) as $srcFolder) {
			$dstFolder = $srcFolder;
			// $dstFolderKey = str_replace($conf['dst']['folderSeparator'],$conf['src']['folderSeparator'],$dstFolder);
			$dstFolderKey = str_replace($conf['src']['folderSeparator'],$conf['dst']['folderSeparator'],$srcFolder);
			
			// echo $srcFolder.PHP_EOL;
			// print $dstFolder.PHP_EOL;
			// die(100);
			// print_r($conf['dst']);
			// print $conf['src']['folderSeparator'].PHP_EOL;
			if ($srcFolder == $dstFolder) {
				$src_folder_count = count($src_data_list[$srcFolder]);
				$dst_folder_count = count($dst_data_list[$dstFolderKey]);
				$src_size_count = array_sum(array_column($src_data_list[$srcFolder],'s-skip'));
				$src_dst_diff = $src_folder_count - $dst_folder_count;
				$src_dst_diff -= $src_size_count;
				
				echo PHP_EOL . PHP_EOL .'COMPARING FOLDER(S): '.$srcFolder.'('.$src_folder_count.') WITH FOLDER(D): '.$dstFolder.'('.$dst_folder_count.') PENDING: '. $src_dst_diff .' Skipped: ('.$src_size_count.')'.PHP_EOL;
				echo '----------------------------------------------------------------------------------------------------'.PHP_EOL;

				// --- OPTIMIZATION: Build a lookup map from destination messages for O(1) lookups ---
				$dstMessageMap = [];
				if (isset($dst_data_list[$dstFolderKey]) && is_array($dst_data_list[$dstFolderKey])) {
					foreach ($dst_data_list[$dstFolderKey] as $dstMessage) {
						// Create a unique key from date and subject. Using a separator to avoid collisions.
						$key = $dstMessage['date'] . '||' . $dstMessage['subject'];
						$dstMessageMap[$key] = true;
					}
				}

				// --- Now, iterate through source messages ONCE and check against the map ---
				$missing_counter = 0;
				$size_counter = 0;
				foreach ($src_data_list[$srcFolder] as $srcMessage) {
					if ($srcMessage['s-skip'] == 1) {
						$size_counter++;
						continue; // Skip oversized messages
					}

					// Create the same unique key for the source message
					$key = $srcMessage['date'] . '||' . $srcMessage['subject'];

					// If the key does not exist in the destination map, the message is missing.
					if (!isset($dstMessageMap[$key])) {
						$missing_counter++;
						print 'MISSING> DA:"'.$srcMessage['date'].'" SU:"'.$srcMessage['subject'].'" SI:"'.pretty_byte($srcMessage['size']).'"';
						print PHP_EOL;
					}
				}
			} // FOLDER MATCH CONDITION
/*	*/			
		} // FOLDER LOOP
	}	// END OF RELOAD
} // showcomp







/* ################################################################################################ */
// die(PHP_EOL .'END-OF-SCRIPT'. PHP_EOL);
