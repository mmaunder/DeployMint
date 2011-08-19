<?php
/*
	Author: Mark Maunder <mmaunder@gmail.com>
	Author website: http://markmaunder.com/
	License: GPL 3.0
*/

	
define('ERRORLOGFILE', '/var/log/wp_errors');
class deploymint {
	private static $wpTables = array('commentmeta', 'comments', 'links', 'options', 'postmeta', 'posts', 'term_relationships', 'term_taxonomy', 'terms');
	public function installPlugin(){
		$dbuser = DB_USER; $dbpass = DB_PASSWORD; $dbhost = DB_HOST; $dbname = DB_NAME;
		$dbh = mysql_connect( $dbhost, $dbuser, $dbpass, true );
		mysql_select_db($dbname, $dbh);
		mysql_query("create table if not exists dep_options (
			name varchar(100) NOT NULL PRIMARY KEY,
			val varchar(255) default ''
			) default charset=utf8", $dbh);
		if(mysql_error($dbh)){ die("Database error creating table for DeployMint: " . mysql_error($dbh)); }

		mysql_query("create table if not exists dep_projects (
			id int UNSIGNED NOT NULL auto_increment PRIMARY KEY,
			ctime int UNSIGNED NOT NULL,
			name varchar(100) NOT NULL,
			dir varchar(120) NOT NULL,
			deleted tinyint UNSIGNED default 0
		) default charset=utf8", $dbh);
		if(mysql_error($dbh)){ die("Database error creating table for DeployMint: " . mysql_error($dbh)); }
		mysql_query("create table if not exists dep_members (
			blog_id int UNSIGNED NOT NULL,
			project_id int UNSIGNED NOT NULL,
			deleted tinyint UNSIGNED default 0,
			KEY k1(blog_id, project_id)
		) default charset=utf8", $dbh);
		if(mysql_error($dbh)){ die("Database error creating table for DeployMint: " . mysql_error($dbh)); }
		$options = self::getOptions();
		foreach(array('git', 'mysql', 'mysqldump') as $n){
			$options[$n] = $options[$n] ? $options[$n] : trim(self::mexec("which $n") );
		}
		if(! array_key_exists('numBackups', $options)){ $options['numBackups'] = 5; }
		if(! array_key_exists('datadir', $options)){ $options['datadir'] = ""; }
		self::updateOptions($options);
	}
	private function getOptions(){
		global $wpdb;
		$res = $wpdb->get_results($wpdb->prepare("select name, val from dep_options"), ARRAY_A);
		$options = array();
		for($i = 0; $i < sizeof($res); $i++){
			$options[$res[$i]['name']] = $res[$i]['val'];
		}
		return $options;
	}
	private function updateOptions($o){
		global $wpdb;
		foreach($o as $n => $v){
			$wpdb->query($wpdb->prepare("insert into dep_options (name, val) values (%s, %s) ON DUPLICATE KEY UPDATE val=%s", $n, $v, $v));
		}
	}
	private function setOption($name, $val){
		global $wpdb;
		$wpdb->query($wpdb->prepare("insert into dep_options (name, val) values (%s, %s) ON DUPLICATE KEY UPDATE val=%s", $name, $val, $val));
	}
	private function allOptionsSet(){
		global $wpdb;
		$options = self::getOptions();
		foreach(array('git', 'mysql', 'mysqldump', 'datadir') as $v){
			if(! $options[$v]){
				return false;
			}
		}
		if(! preg_match('/^\d+$/', $options['numBackups'])){
			return false;
		}
		return true;
	}
	public function setup(){
		global $wpdb;
		if(is_admin()){
			add_action('admin_menu', 'deploymint::adminMenuHandler');
		}
		add_action('init', 'deploymint::initHandler');
		//wp_deregister_script( 'jquery' );
		//wp_enqueue_script('jquery', plugin_dir_url( __FILE__ ) . 'js/jquery-1.6.2.js', array(  ) );
		wp_register_style('DeployMintCSS', plugin_dir_url( __FILE__ ) . 'css/admin.css');
		wp_enqueue_style('DeployMintCSS');
		add_action('wp_ajax_deploymint_deploy', 'deploymint::ajax_deploy_callback');
		add_action('wp_ajax_deploymint_createProject', 'deploymint::ajax_createProject_callback');
		add_action('wp_ajax_deploymint_reloadProjects', 'deploymint::ajax_reloadProjects_callback');
		add_action('wp_ajax_deploymint_updateCreateSnapshot', 'deploymint::ajax_updateCreateSnapshot_callback');
		add_action('wp_ajax_deploymint_updateDeploySnapshot', 'deploymint::ajax_updateDeploySnapshot_callback');
		add_action('wp_ajax_deploymint_updateSnapDesc', 'deploymint::ajax_updateSnapDesc_callback');
		add_action('wp_ajax_deploymint_createSnapshot', 'deploymint::ajax_createSnapshot_callback');
		add_action('wp_ajax_deploymint_deploySnapshot', 'deploymint::ajax_deploySnapshot_callback');
		add_action('wp_ajax_deploymint_undoDeploy', 'deploymint::ajax_undoDeploy_callback');
		add_action('wp_ajax_deploymint_addBlogToProject', 'deploymint::ajax_addBlogToProject_callback');
		add_action('wp_ajax_deploymint_removeBlogFromProject', 'deploymint::ajax_removeBlogFromProject_callback');
		add_action('wp_ajax_deploymint_deleteProject', 'deploymint::ajax_deleteProject_callback');
		add_action('wp_ajax_deploymint_deleteBackups', 'deploymint::ajax_deleteBackups_callback');
		add_action('wp_ajax_deploymint_updateOptions', 'deploymint::ajax_updateOptions_callback');
		if( !self::allOptionsSet() && is_multisite()){ 
			add_action('admin_notices', 'deploymint::msgDataDir'); 
		}
		if(! is_multisite()){
			add_Action('admin_notices', 'deploymint::msgMultisite');
		}
	}
	public static function __callStatic($name, $args){
		$matches = array();
		if(preg_match('/^projectMenu(\d+)$/', $name, &$matches)){
			self::projectMenu($matches[1]);
		} else {
			die("Method $name doesn't exist!");
		}
	}
	private static function checkPerms(){
		if(! is_user_logged_in()){ die("<h2>You are not logged in.</h2>"); }
		if(! current_user_can('manage_network') ){ die("<h2>You don't have permission to access this page.</h2><p>You need the 'manage_network' Super Admin capability to use DeployMint.</p>"); }
	}
	public static function projectMenu($projectid){
		self::checkPerms();
		global $wpdb;
		if(! self::allOptionsSet()){ echo '<div class="wrap"><h2 class="depmintHead">Please visit the options page and configure all options</h2></div>'; return ; }
		$res = $wpdb->get_results($wpdb->prepare("select * from dep_projects where id=%d and deleted=0", $projectid), ARRAY_A);	
		$proj = $res[0];
		include 'projectPage.php';
	}
	public static function ajax_createProject_callback(){
		self::checkPerms();
		global $wpdb;
		$opt = self::getOptions(); extract($opt, EXTR_OVERWRITE);
		$exists = $wpdb->get_results($wpdb->prepare("select name from dep_projects where name=%s and deleted=0", $_POST['name']), ARRAY_A);
		if(sizeof($exists) > 0){
			die(json_encode(array( 'err' => "A project with that name already exists.")));
		}
		$dir = $_POST['name'];
		$dir = preg_replace('/[^a-zA-Z0-9]+/', '_', $dir);
		$fulldir =  $dir . '-1';
		$counter = 2;
		while(is_dir($datadir . $fulldir)){
			$fulldir = preg_replace('/\-\d+$/', '', $fulldir);
			$fulldir .= '-' . $counter;
			$counter++;
			if($counter > 1000){
				die(json_encode(array( 'err' => "Too many directories already exist starting with \"$dir\"")));
			}
		}

		$finaldir = $datadir . $fulldir;
		if(! @mkdir($finaldir, 0755)){
			die(json_encode(array( 'err' => "Could not create directory $finaldir")));
		}
		$git1 = self::mexec("$git init ; $git add . ", $finaldir);
		$wpdb->query($wpdb->prepare("insert into dep_projects (ctime, name, dir) values (unix_timestamp(), %s, %s)", $_POST['name'], $fulldir));
		die(json_encode(array( 'ok' => 1 )));
	}
	public static function ajax_updateOptions_callback(){
		self::checkPerms();
		$git = trim($_POST['git']);
		$mysql = trim($_POST['mysql']);
		$mysqldump = trim($_POST['mysqldump']);
		$datadir = trim($_POST['datadir']);
		if(! preg_match('/\/$/', $datadir)){
			$datadir .= '/';
		}
		$numBackups = trim($_POST['numBackups']);
		$errs = array();
		if(! ($git && $mysql && $mysqldump && $datadir)){
			$errs[] = "You must specify a value for all options.";
		}
		if(! preg_match('/^\d+$/', $numBackups)){
			$errs[] = "Please specify a number for the number of backups you want to keep.";
		}
		if(sizeof($errs) > 0){
			die(json_encode(array('errs' => $errs)));
		}
		if(! file_exists($mysql)){ $errs[] = "The file '$mysql' specified for mysql doesn't exist."; }
		if(! file_exists($mysqldump)){ $errs[] = "The file '$mysqldump' specified for mysqldump doesn't exist."; }
		if(! file_exists($git)){ $errs[] = "The file '$git' specified for git doesn't exist."; }
		if(! is_dir($datadir)){ 
			$errs[] = "The directory '$datadir' specified as the data directory doesn't exist."; 
		} else {
			$fh = fopen($datadir . '/test.tmp', 'w');
			if(! fwrite($fh, 't')){
				$errs[] = "The directory $datadir is not writeable.";
			}
			fclose($fh);
			unlink($datadir . '/test.tmp');
		}
		if(! preg_match('/^\d+$/', $numBackups)){
			$errs[] = "The number of backups you specify must be a number or 0 to keep all bacukps.";
		}
		if(sizeof($errs) > 0){
			die(json_encode(array('errs' => $errs)));
		} else {
			$options = array(
				'git' => $git,
				'mysql' => $mysql,
				'mysqldump' => $mysqldump,
				'datadir' => $datadir,
				'numBackups' => $numBackups
				);
			self::updateOptions($options);
			die(json_encode(array('ok' => 1)));
		}
	}
	public static function ajax_deleteBackups_callback(){
		self::checkPerms();
		$dbuser = DB_USER; $dbpass = DB_PASSWORD; $dbhost = DB_HOST; $dbname = DB_NAME;
		$dbh = mysql_connect( $dbhost, $dbuser, $dbpass, true );
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		$toDel = $_POST['toDel'];
		for($i = 0; $i < sizeof($toDel); $i++){
			mysql_query("drop database " . $toDel[$i], $dbh);
			if(mysql_error($dbh)){ self::ajaxError("Could not drop database " . $toDel[$i] . ". Error: " . mysql_error($dbh)); }
		}
		die(json_encode(array('ok' => 1)));
	}
		
	public static function ajax_deleteProject_callback(){
		self::checkPerms();
		global $wpdb;
		$wpdb->query($wpdb->prepare("update dep_members set deleted=1 where project_id=%d", $_POST['blogID'], $_POST['projectID']));
		$wpdb->query($wpdb->prepare("update dep_projects set deleted=1 where id=%d", $_POST['projectID']));
		die(json_encode(array('ok' => 1)));
	}
	public static function ajax_removeBlogFromProject_callback(){
		self::checkPerms();
		global $wpdb;
		$wpdb->query($wpdb->prepare("update dep_members set deleted=1 where blog_id=%d and project_id=%d", $_POST['blogID'], $_POST['projectID']));
		die(json_encode(array('ok' => 1)));
	}
	public static function ajax_addBlogToProject_callback(){
		self::checkPerms();
		global $wpdb;
		$det = get_blog_details($_POST['blogID']);
		if(! $det){
			die(json_encode(array('err' => "Please select a valid blog to add.")));
		}
		$wpdb->query($wpdb->prepare("insert into dep_members (blog_id, project_id) values (%d, %d)", $_POST['blogID'], $_POST['projectID']));
		die(json_encode(array('ok' => 1)));
	}
	public static function ajax_createSnapshot_callback(){
		self::checkPerms();
		global $wpdb;
		$opt = self::getOptions(); extract($opt, EXTR_OVERWRITE);

		$pid = $_POST['projectid'];
		$blogid = $_POST['blogid'];
		$name = $_POST['name'];
		$desc = $_POST['desc'];
		if(! preg_match('/\w+/', $name)){
			self::ajaxError("Please enter a name for this snapshot");
		}
		if(strlen($name) > 20){
			self::ajaxError("Your snapshot name must be 20 characters or less.");
		}
		if(preg_match('/[^a-zA-Z0-9\_\-\.]/', $name)){
			self::ajaxError("Your snapshot name can only contain characters a-z A-Z 0-9 and dashes, underscores and dots.");
		}
		if(! $desc){
			self::ajaxError("Please enter a description for this snapshot.");
		}
		$prec = $wpdb->get_results($wpdb->prepare("select * from dep_projects where id=%d and deleted=0", $pid), ARRAY_A);
		if(sizeof($prec) < 1){
			self::ajaxError("That project doesn't exist.");
		}
		$proj = $prec[0];
		$dir = $datadir . $proj['dir'] . '/';
		$mexists = $wpdb->get_results($wpdb->prepare("select blog_id from dep_members where blog_id=%d and project_id=%d and deleted=0", $blogid, $pid), ARRAY_A);
		if(sizeof($mexists) < 1){
			self::ajaxError("That blog doesn't exist or is not a member of this project.");
		}
		if(! is_dir($dir)){
			self::ajaxError("The directory " . $dir . " for this project doesn't exist for some reason. Did you delete it?");
		}
		$branchOut = self::mexec("$git branch 2>&1", $dir);
		if(preg_match('/fatal/', $branchOut)){
			self::ajaxError("The directory $dir is not a valid git repository. The output we received is: $branchOut");
		}
		$branches = preg_split('/[\r\n\s\t\*]+/', $branchOut);
		$bdup = array();
		for($i = 0; $i < sizeof($branches); $i++){
			$bdup[$branches[$i]] = 1;
		}
		if(array_key_exists($name, $bdup)){
			self::ajaxError("A snapshot with the name $name already exists. Please choose another.");
		}
		$cout1 = self::mexec("$git checkout master 2>&1", $dir);
		//Before we do our initial commit we will get an error trying to checkout master because it doesn't exist.
		if( ! preg_match("/(?:Switched to branch|Already on|error: pathspec 'master' did not match)/", $cout1) ){
			self::ajaxError("We could not switch the git repository in $dir to 'master'. The output was: $cout1");
		}
		$prefix = "";
		if($blogid == 1){
			$prefix = $wpdb->base_prefix;
		} else {
			$prefix = $wpdb->base_prefix . $blogid . '_';
		}
		$prefixFile = $dir . 'deployData.txt';
		$fh2 = fopen($prefixFile, 'w');
		if(! fwrite($fh2, $prefix . ':' . microtime(true))){
			self::ajaxError("We could not write to deployData.txt in the directory $dir");
		}
		fclose($fh2);
		$prefixOut = self::mexec("$git add deployData.txt 2>&1", $dir);

		$siteURLRes = $wpdb->get_results($wpdb->prepare("select option_name, option_value from $prefix" . "options where option_name = 'siteurl'"), ARRAY_A);
		$siteURL = $siteURLRes[0]['option_value'];
		$desc = "Snapshot of: $siteURL\n" . $desc;

		$dumpErrs = array();
		foreach(self::$wpTables as $t){
			$tableFile = $t . '.sql';
			$tableName = $prefix . $t;
			$path = $dir . $tableFile;
			$dbuser = DB_USER; $dbpass = DB_PASSWORD; $dbhost = DB_HOST; $dbname = DB_NAME;
			$o1 = self::mexec("$mysqldump --skip-comments --extended-insert --complete-insert --skip-comments -u $dbuser -p$dbpass -h $dbhost $dbname $tableName > $path 2>&1", $dir);
			if(preg_match('/\w+/', $o1)){
				array_push($dumpErrs, $o1);
			} else {

				$grepOut = self::mexec("grep CREATE $path 2>&1");
				if(! preg_match('/CREATE/', $grepOut)){
					array_push($dumpErrs, "We could not create a valid table dump file for $tableName");
				} else {
					$gitAddOut = self::mexec("$git add $tableFile 2>&1", $dir);
					if(preg_match('/\w+/', $gitAddOut)){
						self::ajaxError("We encountered an error running '$git add $tableFile' the error was: $gitAddOut");
					}
				}
			}
		}
		if(sizeof($dumpErrs) > 0){
			$resetOut = self::mexec("$git reset --hard HEAD 2>&1", $dir);
			if(! preg_match('/HEAD is now at/', $resetOut)){
				self::ajaxError("Errors occured during mysqldump and we could not revert the git repository in $dir back to it's original state using '$git reset --hard HEAD'. The output we got was: " . $resetOut);
			}
				
			self::ajaxError("Errors occured during mysqldump: " . implode(', ', $dumpErrs));
		}
		$tmpfile = $datadir . microtime(true) . '.tmp';
		$fh = fopen($tmpfile, 'w');
		fwrite($fh, $desc);
		fclose($fh);
		global $current_user;
		get_currentuserinfo();
		$commitUser = $current_user->user_firstname . ' ' . $current_user->user_lastname . ' <' . $current_user->user_email . '>';
		$commitOut2 = self::mexec("$git commit --author=\"$commitUser\" -a -F \"$tmpfile\" 2>&1", $dir);
		//unlink($tmpfile);
		if(! preg_match('/files changed/', $commitOut2)){
			self::ajaxError("git commit failed. The output we got was: $commitOut2");
		}
		$brOut2 = self::mexec("$git branch $name 2>&1 ", $dir);
		if(preg_match('/\w+/', $brOut2)){
			self::ajaxError("We encountered an error running '$git branch $name' the output was: $brOut2");
		}
		die(json_encode(array('ok' => 1)));
	}
	public static function ajax_undoDeploy_callback(){
		self::checkPerms();
		global $wpdb;
		$sourceDBName = $_POST['dbname'];
		$dbuser = DB_USER; $dbpass = DB_PASSWORD; $dbhost = DB_HOST; $dbname = DB_NAME;
		$dbh = mysql_connect( $dbhost, $dbuser, $dbpass, true );
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		mysql_select_db($sourceDBName, $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		$tmpdbName = 'dep_tmpdb' . preg_replace('/\./', '', microtime(true));
		mysql_query("create database " . $tmpdbName, $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		$res1 = mysql_query("show tables", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		$allTables = array();
		while($row1 = mysql_fetch_array($res1, MYSQL_NUM)){
			if(! preg_match('/^dep_/', $row1[0])){
				array_push($allTables, $row1[0]);
			}
		}
		$renames = array();
		foreach($allTables as $t){
			array_push($renames, "$dbname.$t TO $tmpdbName.$t, $sourceDBName.$t TO $dbname.$t");
		}
		$stime = microtime(true);
		mysql_query("RENAME TABLE " . implode(', ', $renames), $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		$lockTime = sprintf('%.4f', microtime(true) - $stime);
		mysql_query("drop database $tmpdbName", $dbh);
		foreach($allTables as $t){
			mysql_query("create table $sourceDBName.$t like $dbname.$t", $dbh);
			if(mysql_error($dbh)){ self::ajaxError("A database error occured trying to recreate the backup database, but the deployment completed. Error: " . substr(mysql_error($dbh), 0, 200)); }
			mysql_query("insert into $sourceDBName.$t select * from $dbname.$t", $dbh);
			if(mysql_error($dbh)){ self::ajaxError("A database error occured trying to recreate the backup database, but the deployment completed. Error: " . substr(mysql_error($dbh), 0, 200)); }
		}
		if(mysql_error($dbh)){ self::ajaxError("A database error occured (but the revert was completed!): " . substr(mysql_error($dbh), 0, 200)); }
		die(json_encode(array('ok' => 1, 'lockTime' => $lockTime)));
	}
	public static function ajax_deploySnapshot_callback(){
		self::checkPerms();
		global $wpdb;
		$opt = self::getOptions(); extract($opt, EXTR_OVERWRITE);
		$pid = $_POST['projectid'];
		$blogid = $_POST['blogid'];
		$name = $_POST['name'];
		$leaveComments = true; //$_POST['leaveComments'];

		if(! preg_match('/\w+/', $name)){
			self::ajaxError("Please select a snapshot to deploy.");
		}
		$prec = $wpdb->get_results($wpdb->prepare("select * from dep_projects where id=%d and deleted=0", $pid), ARRAY_A);
		if(sizeof($prec) < 1){
			self::ajaxError("That project doesn't exist.");
		}
		$proj = $prec[0];
		$dir = $datadir . $proj['dir'] . '/';
		$mexists = $wpdb->get_results($wpdb->prepare("select blog_id from dep_members where blog_id=%d and project_id=%d and deleted=0", $blogid, $pid), ARRAY_A);
		if(sizeof($mexists) < 1){
			self::ajaxError("That blog doesn't exist or is not a member of this project. Please select a valid blog to deploy to.");
		}
		if(! is_dir($dir)){
			self::ajaxError("The directory " . $dir . " for this project doesn't exist for some reason. Did you delete it?");
		}
		$co1 = self::mexec("$git checkout $name 2>&1", $dir);
		if(! preg_match('/(?:Switched|Already)/', $co1)){
			self::ajaxError("We could not find snapshot $name in the git repository. The error was: $co1");
		}
		$destTablePrefix = "";
		if($blogid == 1){
			$destTablePrefix = $wpdb->base_prefix;
		} else {
			$destTablePrefix = $wpdb->base_prefix . $blogid . '_';
		}
		$res3 = $wpdb->get_results($wpdb->prepare("select option_name, option_value from $destTablePrefix" . "options where option_name IN ('siteurl', 'home')"), ARRAY_A);
		if(sizeof($res3) < 1){
			self::ajaxError("We could not find the data we need for the blog you're trying to deploy to.");
		}
		$options = array();
		for($i = 0; $i < sizeof($res3); $i++){
			$options[$res3[$i]['option_name']] = $res3[$i]['option_value'];
		}

		$fh = fopen($dir . 'deployData.txt', 'r');
		$deployData = fread($fh, 100);
		$depDat = explode(':', $deployData);
		$sourceTablePrefix = $depDat[0];
		if(! $sourceTablePrefix){
			self::ajaxError("We could not read the table prefix from $dir/deployData.txt");
		}
		$tmpDBName = "";
		for($i = 1; $i < 10; $i++){
			$tmpDBName = 'deptmp__' . microtime(true);
			$tmpDBName = preg_replace('/\./', '', $tmpDBName);
			$res = $wpdb->get_results($wpdb->prepare("show tables from $tmpDBName"), ARRAY_A);
			if(sizeof($res) < 1){
				break;
			}
			if($i > 4){
				self::ajaxError("We could not create a temporary database name after 5 tries. You may not have the create DB privelege.");
			}
		}
		$wpdb->query($wpdb->prepare("create database $tmpDBName"));
		$dbuser = DB_USER; $dbpass = DB_PASSWORD; $dbhost = DB_HOST; $dbname = DB_NAME;
		$slurp1 = self::mexec("cat *.sql | $mysql -u $dbuser -p$dbpass -h $dbhost $tmpDBName ", $dir);
		if(preg_match('/\w+/', $slurp1)){
			self::ajaxError("We encountered an error importing the data files from snapshot $name. The error was: " . substr($slurp1, 0, 100));
		}
		$dbh = mysql_connect( $dbhost, $dbuser, $dbpass, true );
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		if(! mysql_select_db($tmpDBName, $dbh)){ self::ajaxError("Could not select database $tmpDBName : " . mysql_error($dbh)); }
		$curdbres = mysql_query("select DATABASE()", $dbh); $curdbrow = mysql_fetch_array($curdbres, MYSQL_NUM); 
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		$destSiteURL = $options['siteurl'];
		$res4 = mysql_query("select option_value from $sourceTablePrefix" . "options where option_name='siteurl'", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		if(! $res4){ self::ajaxError("We could not get the siteurl from the database we're about to deploy. That could mean that we could not create the DB or the import failed."); }
		$row = mysql_fetch_array($res4, MYSQL_ASSOC);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		if(! $row){ self::ajaxError("We could not get the siteurl from the database we're about to deploy. That could mean that we could not create the DB or the import failed. (2)"); }
		$sourceSiteURL = $row['option_value'];
		if(! $sourceSiteURL){ self::ajaxError("We could not get the siteurl from the database we're about to deploy. That could mean that we could not create the DB or the import failed. (3)"); }
		$destHost = preg_replace('/^https?:\/\/([^\/]+).*$/i', '$1', $destSiteURL);
		$sourceHost = preg_replace('/^https?:\/\/([^\/]+).*$/i', '$1', $sourceSiteURL);
		foreach($options as $oname => $val){
			mysql_query("update $sourceTablePrefix" . "options set option_value='" . mysql_real_escape_string($val) . "' where option_name='" . mysql_real_escape_string($oname) . "'", $dbh);
			if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		}
		$res5 = mysql_query("select ID, post_content, guid from $sourceTablePrefix" . "posts", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		while($row = mysql_fetch_array($res5, MYSQL_ASSOC)){
			$content = preg_replace('/(https?:\/\/)' . $sourceHost . '/i', '$1' . $destHost, $row['post_content']);
			$guid = preg_replace('/(https?:\/\/)' . $sourceHost . '/i', '$1' . $destHost, $row['guid']);
			mysql_query("update $sourceTablePrefix" . "posts set post_content='" . mysql_real_escape_string($content) . "', guid='" . mysql_real_escape_string($guid) . "' where ID=" . $row['ID'], $dbh);
			if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		}

		if($leaveComments){
			//Delete comments from DB we're deploying
			mysql_query("delete from $sourceTablePrefix" . "comments", $dbh);
			if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
			mysql_query("delete from $sourceTablePrefix" . "commentmeta", $dbh);
			if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
			//Bring comments across from live (destination) DB
			mysql_query("insert into $tmpDBName.$sourceTablePrefix" . "comments select * from $dbname.$destTablePrefix" . "comments", $dbh);
			if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
			mysql_query("insert into $tmpDBName.$sourceTablePrefix" . "commentmeta select * from $dbname.$destTablePrefix" . "commentmeta", $dbh);
			if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }

			//Then remap comments to posts based on the "slug" which is the post_name
			$res6 = mysql_query("select dp.post_name as destPostName, dp.ID as destID, sp.post_name as sourcePostName, sp.ID as sourceID from $dbname.$destTablePrefix" . "posts as dp, $tmpDBName.$sourceTablePrefix" . "posts as sp where dp.post_name = sp.post_name", $dbh);
			if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
			if(! $res6){
				self::ajaxError("DB error creating maps betweeb post slugs: " . mysql_error($dbh));
			}
			$pNameMap = array();
			while($row = mysql_fetch_array($res6, MYSQL_ASSOC)){
				$pNameMap[$row['destID']] = $row['sourceID'];
			}
				
			$res10 = mysql_query("select comment_ID, comment_post_ID from $sourceTablePrefix" . "comments", $dbh);
			if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
			while($row = mysql_fetch_array($res10, MYSQL_ASSOC)){
				//If a post exists in the source with the same slug as the destination, then associate the destination's comments with that post.
				if(array_key_exists($row['comment_post_ID'], $pNameMap)){ 
					mysql_query("update $sourceTablePrefix" . "comments set comment_post_ID=" . $pNameMap[$row['comment_post_ID']] . " where comment_ID=" . $row['comment_ID'], $dbh);
					if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
				} else { //Otherwise delete the comment because it is associated with a post on the destination which does not exist in the source we're about to deploy
					mysql_query("delete from $sourceTablePrefix" . "comments where comment_ID=" . $row['comment_ID'], $dbh);
					if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
				}
			}
			$res11 = mysql_query("select ID from $sourceTablePrefix" . "posts", $dbh);
			if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
			while($row = mysql_fetch_array($res11, MYSQL_ASSOC)){
				$res12 = mysql_query("select count(*) as cnt from $sourceTablePrefix" . "comments where comment_post_ID=" . $row['ID'], $dbh);
				if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
				$row5 = mysql_fetch_array($res12, MYSQL_ASSOC);
				mysql_query("update $sourceTablePrefix" . "posts set comment_count=" . $row5['cnt'] . " where ID=" . $row['ID'], $dbh);
				if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
			}
		}
		if(! mysql_select_db($dbname, $dbh)){ self::ajaxError("Could not select database $dbname : " . mysql_error($dbh)); }
		$curdbres = mysql_query("select DATABASE()", $dbh); $curdbrow = mysql_fetch_array($curdbres, MYSQL_NUM); 
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		$res14 = mysql_query("show tables", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		$allTables = array();
		while($row = mysql_fetch_array($res14, MYSQL_NUM)){
			array_push($allTables, $row[0]);
		}
		$backupDBName = "depbak__" . preg_replace('/\./', '', microtime(true));
		mysql_query("create database $backupDBName", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		if(! mysql_select_db($backupDBName, $dbh)){ self::ajaxError("Could not select database $backupDBName : " . mysql_error($dbh)); }
		error_log("BACKUPDB: $backupDBName");
		$curdbres = mysql_query("select DATABASE()", $dbh); $curdbrow = mysql_fetch_array($curdbres, MYSQL_NUM); ;

		foreach($allTables as $t){
			#We're taking across all tables including dep_ tables just so we have a backup. We won't deploy dep_ tables though
			mysql_query("create table $backupDBName.$t like $dbname.$t", $dbh);
			if(mysql_error($dbh)){
				self::ajaxError("Could not create table $t in backup DB: " . mysql_error($dbh));
			}
			mysql_query("insert into $t select * from $dbname.$t", $dbh);
			if(mysql_error($dbh)){
				self::ajaxError("Could not copy table $t from $dbname database: " . mysql_error($dbh));
			}
		}
		mysql_query("create table dep_backupdata (name varchar(20) NOT NULL, val varchar(255) default '')", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		mysql_query("insert into dep_backupdata values ('blogid', '" . $blogid . "')", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		mysql_query("insert into dep_backupdata values ('prefix', '" . $destTablePrefix . "')", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		mysql_query("insert into dep_backupdata values ('deployTime', '" . microtime(true) . "')", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		mysql_query("insert into dep_backupdata values ('deployFrom', '" . $sourceHost . "')", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		mysql_query("insert into dep_backupdata values ('deployTo', '" . $destHost . "')", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		mysql_query("insert into dep_backupdata values ('snapshotName', '" . $name . "')", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		mysql_query("insert into dep_backupdata values ('projectID', '" . $pid . "')", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		mysql_query("insert into dep_backupdata values ('projectName', '" . $proj['name'] . "')", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }

		if(! mysql_select_db($tmpDBName, $dbh)){ self::ajaxError("Could not select database $tmpDBName : " . mysql_error($dbh)); }
		$curdbres = mysql_query("select DATABASE()", $dbh); $curdbrow = mysql_fetch_array($curdbres, MYSQL_NUM); 

		$renames = array();
		foreach(self::$wpTables as $t){
			array_push($renames, "$dbname.$destTablePrefix" . "$t TO $tmpDBName.old_$t, $tmpDBName.$sourceTablePrefix" . "$t TO $dbname.$destTablePrefix" . $t);
		}
		$stime = microtime(true);
		mysql_query("RENAME TABLE " . implode(", ", $renames), $dbh);
		$lockTime = sprintf('%.4f', microtime(true) - $stime);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		mysql_query("drop database $tmpDBName", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured trying to drop an old temporary database, but the deployment completed. Error was: " . substr(mysql_error($dbh), 0, 200)); }
		self::deleteOldBackupDatabases();	
		die(json_encode(array('ok' => 1, 'lockTime' => $lockTime)));
	}
	public static function ajax_updateSnapDesc_callback(){
		self::checkPerms();
		global $wpdb;
		$opt = self::getOptions(); extract($opt, EXTR_OVERWRITE);
		$pid = $_POST['projectid'];
		$snapname = $_POST['snapname'];
		$res = $wpdb->get_results($wpdb->prepare("select dir from dep_projects where id=%d", $pid), ARRAY_A);
		$dir = $res[0]['dir'];
		$fulldir = $datadir . $dir;
		$logOut = self::mexec("$git checkout $snapname >/dev/null 2>&1 ; $git log -n 1 2>&1 ; $git checkout master >/dev/null 2>&1", $fulldir);
		$logOut = preg_replace('/^commit [0-9a-fA-F]+[\r\n]+/', '', $logOut);
		if(preg_match('/fatal: bad default revision/', $logOut)){
			die(json_encode(array('desc' => '' )));
		}
		die(json_encode(array('desc' => $logOut )));
	}
	public static function ajax_updateDeploySnapshot_callback(){
		self::checkPerms();
		global $wpdb;
		$opt = self::getOptions(); extract($opt, EXTR_OVERWRITE);
		$pid = $_POST['projectid'];
		$blogsTable = $wpdb->base_prefix . 'blogs';
		$blogs = $wpdb->get_results($wpdb->prepare("select $blogsTable.blog_id as blog_id, $blogsTable.domain as domain from dep_members, $blogsTable where dep_members.blog_id = $blogsTable.blog_id and dep_members.project_id = %d and dep_members.deleted=0", $pid), ARRAY_A);
		$res1 = $wpdb->get_results($wpdb->prepare("select dir from dep_projects where id=%d", $pid), ARRAY_A);
		$dir = $datadir . $res1[0]['dir'];
		if(! is_dir($dir)){
			self::ajaxError("The directory $dir for this project does not exist.");
		}
		$bOut = self::mexec("$git branch 2>&1", $dir);
		$branches = preg_split('/[\r\n\s\t\*]+/', $bOut);
		$snapshots = array();
		for($i = 0; $i < sizeof($branches); $i++){
			if(preg_match('/\w+/', $branches[$i])){
				$bname = $branches[$i];
				if($bname == 'master'){
					continue;
				}
				$dateOut = self::mexec("$git checkout $bname 2>&1; $git log -n 1 | grep Date 2>&1", $dir);
				$m = '';
				if(preg_match('/Date:\s+(.+)$/', $dateOut, &$m)){
					$ctime = strtotime($m[1]);
					$date = $m[1];
					array_push($snapshots, array( 'name' => $branches[$i], 'created' => $date, 'ctime' => $ctime ));
				}
			} else {
				unset($branches[$i]);
			}
		}
		if(sizeof($snapshots) > 0){
			function ctimeSort($b, $a){ if($a['ctime'] == $b['ctime']){ return 0; } return ($a['ctime'] < $b['ctime']) ? -1 : 1; }
			usort($snapshots, 'ctimeSort');
		}
		die(json_encode(array(
			'blogs' => $blogs,
			'snapshots' => $snapshots
			)));
	}

	public static function ajax_updateCreateSnapshot_callback(){
		self::checkPerms();
		global $wpdb;
		$pid = $_POST['projectid'];
		$blogsTable = $wpdb->base_prefix . 'blogs';
		$blogs = $wpdb->get_results($wpdb->prepare("select $blogsTable.blog_id as blog_id, $blogsTable.domain as domain from dep_members, $blogsTable where dep_members.blog_id = $blogsTable.blog_id and dep_members.project_id = %d and dep_members.deleted=0", $pid), ARRAY_A);
		die(json_encode(array('blogs' => $blogs)));
	}
	public static function ajax_reloadProjects_callback(){
		self::checkPerms();
		global $wpdb;
		$blogsTable = $wpdb->base_prefix . 'blogs';
		$projects = $wpdb->get_results($wpdb->prepare("select id, name from dep_projects where deleted=0"), ARRAY_A);
		$allBlogs = $wpdb->get_results($wpdb->prepare("select blog_id, domain from $blogsTable order by domain asc"), ARRAY_A);
		for($i = 0; $i < sizeof($projects); $i++){
			$mem = $wpdb->get_results($wpdb->prepare("select $blogsTable.blog_id as blog_id, $blogsTable.domain as domain from dep_members, $blogsTable where dep_members.deleted=0 and dep_members.project_id=%d and dep_members.blog_id = $blogsTable.blog_id", $projects[$i]['id']), ARRAY_A);
			$projects[$i]['memberBlogs'] = $mem;
			$memids = array();
			$notSQL = "";
			if(sizeof($mem) > 0){
				for($j = 0; $j < sizeof($mem); $j++){
					array_push($memids, $mem[$j]['blog_id']);
				}
				$notSQL = "where blog_id NOT IN (" . implode(",", $memids) . ")";
			}
			$nonmem = $wpdb->get_results($wpdb->prepare("select blog_id, domain from $blogsTable $notSQL order by domain asc"), ARRAY_A);
			$projects[$i]['nonmemberBlogs'] = $nonmem;
			$projects[$i]['numNonmembers'] = sizeof($nonmem);

				
		}
		die(json_encode(array( 
			'projects' => $projects
			)));
	}
	public static function ajax_deploy_callback(){
		self::checkPerms();
		global $wpdb;
		$fromid = $_POST['deployFrom'];
		$toid = $_POST['deployTo'];
		$msgs = array();
		$fromBlog = $wpdb->get_results($wpdb->prepare("select blog_id, domain from wp_blogs where blog_id=%d", $fromid), ARRAY_A);
		$toBlog = $wpdb->get_results($wpdb->prepare("select blog_id, domain from wp_blogs where blog_id=%d", $toid), ARRAY_A);
		if(sizeof($fromBlog) != 1){ die("We could not find the blog you're deploying from."); }
		if(sizeof($toBlog) != 1){ die("We could not find the blog you're deploying to."); }
		$fromPrefix = '';
		$toPrefix = '';

		if($fromid == 1){ $fromPrefix = 'wp_'; } else { $fromPrefix = 'wp_' . $fromid . '_'; }
		if($toid == 1){ $toPrefix = 'wp_'; } else { $toPrefix = 'wp_' . $toid . '_'; }
		$t_fromPosts = $fromPrefix . 'posts';
		$t_toPosts = $toPrefix . 'posts';
		$fromPostTotal = $wpdb->get_results($wpdb->prepare("select count(*) as cnt from $t_fromPosts where post_status='publish'", $fromid), ARRAY_A);
		$toPostTotal = $wpdb->get_results($wpdb->prepare("select count(*) as cnt from $t_toPosts where post_status='publish'", $toid), ARRAY_A);
		$fromNewestPost = $wpdb->get_results($wpdb->prepare("select post_title from $t_fromPosts where post_status='publish' order by post_modified desc limit 1", $fromid), ARRAY_A);
		$toNewestPost = $wpdb->get_results($wpdb->prepare("select post_title from $t_toPosts where post_status='publish' order by post_modified desc limit 1", $toid), ARRAY_A);
		die(json_encode(array(
			'fromid' => $fromid,
			'toid' => $toid,
			'fromDomain' => $fromBlog[0]['domain'],
			'fromPostTotal' => $fromPostTotal[0]['cnt'],
			'fromNewestPostTitle' => $fromNewestPost[0]['post_title'],
			'toDomain' => $toBlog[0]['domain'],
			'toPostTotal' => $toPostTotal[0]['cnt'],
			'toNewestPostTitle' => $toNewestPost[0]['post_title']
			)));
	}
	public static function initHandler(){
		if(is_admin()){
			wp_enqueue_script('jquery-templates', plugin_dir_url( __FILE__ ) . 'js/jquery.tmpl.min.js', array( 'jquery' ) );
			wp_enqueue_script('deploymint-js', plugin_dir_url(__FILE__) . 'js/deploymint.js', array('jquery'));
			wp_localize_script('deploymint-js', 'DeployMintVars', array(
				'ajaxURL' => admin_url('admin-ajax.php')
				));
		}
	}
	public static function adminMenuHandler(){
		global $wpdb;
		add_submenu_page("DeployMint", "Manage Projects", "Manage Projects", "manage_network", "DeployMint", 'deploymint::deploymintMenu');
		add_menu_page( "DeployMint", "DeployMint", 'manage_network', 'DeployMint', 'deploymint::deploymintMenu', WP_PLUGIN_URL . '/DeployMint/images/deployMintIcon.png');
		$projects = $wpdb->get_results($wpdb->prepare("select id, name from dep_projects where deleted=0"), ARRAY_A);
		for($i = 0; $i < sizeof($projects); $i++){
			add_submenu_page("DeployMint", "Proj: " . $projects[$i]['name'], "Proj: " . $projects[$i]['name'], "manage_network", "DeployMintProj" . $projects[$i]['id'], 'deploymint::projectMenu' . $projects[$i]['id']);
		}
		add_submenu_page("DeployMint", "Emergency Revert", "Emergency Revert", "manage_network", "DeployMintBackout", 'deploymint::undoLog');
		add_submenu_page("DeployMint", "Options", "Options", "manage_network", "DeployMintOptions", 'deploymint::myOptions');
		add_submenu_page("DeployMint", "Help", "Help", "manage_network", "DeployMintHelp", 'deploymint::help');
	}
	public static function deploymintMenu(){
		if(! self::allOptionsSet()){ echo '<div class="wrap"><h2 class="depmintHead">Please visit the options page and configure all options</h2></div>'; return ; }
		include 'deploymintHome.php';
	}
	public static function help(){
		include 'help.php';
	}
	public static function myOptions(){
		$opt = self::getOptions();
		include 'myOptions.php';
	}
	private static function deleteOldBackupDatabases(){
		self::checkPerms();
		$opt = self::getOptions(); extract($opt, EXTR_OVERWRITE);
		if($numBackups < 1){ return; }
		$dbuser = DB_USER; $dbpass = DB_PASSWORD; $dbhost = DB_HOST; $dbname = DB_NAME;
		$dbh = mysql_connect( $dbhost, $dbuser, $dbpass, true );
		mysql_select_db($dbname, $dbh);
		$res1 = mysql_query("show databases", $dbh);
		if(mysql_error($dbh)){ error_log("A database error occured: " . mysql_error($dbh)); return ; }
		$dbs = array();
		while($row1 = mysql_fetch_array($res1, MYSQL_NUM)){
			if(preg_match('/^depbak__/', $row1[0])){
				$dbname = $row1[0];
				$res2 = mysql_query("select val from $dbname.dep_backupdata where name='deployTime'", $dbh);
				if(mysql_error($dbh)){ error_log("Could not get deployment time for $dbname database"); return; }
				$row2 = mysql_fetch_array($res2, MYSQL_ASSOC);
				if($row2 && $row2['val']){
					array_push($dbs, array( 'dbname' => $dbname, 'deployTime' => $row2['val'] ));
				} else {
					error_log("Could not get deployment time for backup database $dbname");
					return;
				}
			}
		}
		if(sizeof($dbs) > $numBackups){
			function deployTimeSort($a, $b){ if($a['deployTime'] == $b['deployTime']){ return 0; } return ($a['deployTime'] < $b['deployTime']) ? -1 : 1; }
			usort($snapshots, 'deployTimeSort');
			for($i = 0; $i < sizeof($dbs) - $numBackups; $i++){
				$db = $dbs[$i];
				$dbToDrop = $db['dbname'];
				mysql_query("drop database $dbToDrop", $dbh);
				if(mysql_error($dbh)){ error_log("Could not drop backup database $dbToDrop when deleting old backup databases:" . mysql_error($dbh)); return; }
			}
		}




	}
	public static function undoLog(){
		self::checkPerms();
		if(! self::allOptionsSet()){ echo '<div class="wrap"><h2 class="depmintHead">Please visit the options page and configure all options</h2></div>'; return ; }
		$dbuser = DB_USER; $dbpass = DB_PASSWORD; $dbhost = DB_HOST; $dbname = DB_NAME;
		$dbh = mysql_connect( $dbhost, $dbuser, $dbpass, true );
		mysql_select_db($dbname, $dbh);
		$res1 = mysql_query("show databases", $dbh);
		if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
		$dbs = array();
		while($row1 = mysql_fetch_array($res1, MYSQL_NUM)){
			if(preg_match('/^depbak__/', $row1[0])){
				$dbname = $row1[0];
				$res2 = mysql_query("select * from $dbname.dep_backupdata", $dbh);
				if(mysql_error($dbh)){ self::ajaxError("A database error occured: " . substr(mysql_error($dbh), 0, 200)); }
				$dbData = array();
				while($row2 = mysql_fetch_array($res2, MYSQL_ASSOC)){
					$dbData[$row2['name']] = $row2['val'];
				}
				$dbData['dbname'] = $dbname;
				$dbData['deployTimeH'] = date('l jS \of F Y h:i:s A', sprintf('%d', $dbData['deployTime'] ));
				array_push($dbs, $dbData);
			}
		}
		function deployTimeSort($b, $a){ if($a['deployTime'] == $b['deployTime']){ return 0; } return ($a['deployTime'] < $b['deployTime']) ? -1 : 1; }
		usort($dbs, 'deployTimeSort');


		include 'undoLog.php';
	}
	public static function ajaxError($msg){
		die(json_encode(array('err' => $msg)));
	}
	private static function showMessage($message, $errormsg = false){
		if($errormsg) {
			echo '<div id="message" class="error">';
		} else {
			echo '<div id="message" class="updated fade">';
		}

		echo "<p><strong>$message</strong></p></div>";
	}   
	public static function msgDataDir(){ deploymint::showMessage("You need to visit the options page for \"DeployMint\" and configure all options including a data directory that is writable by your web server.", true); }
	public static function msgMultisite(){ deploymint::showMessage("The DeployMint plugin is designed to be used with WordPress MU. You are running an ordinary WordPress installation and need to convert your blog to WordPress MU to use DeployMint. You can learn how to <a href=\"http://codex.wordpress.org/Create_A_Network\" target=\"_blank\">convert this blog to WordPress MU on this page (opens a new window)</a>.", true); }
	public static function mexec($cmd, $cwd = './', $env = NULL){
		$dspec = array(
				0 => array("pipe", "r"),  //stdin
				1 => array("pipe", "w"),  //stdout
				2 => array("pipe", "w") //stderr
				);
		$proc = proc_open($cmd, $dspec, $pipes, $cwd);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$ret = proc_close($proc);
		return $stdout . $stderr;
	}
}
?>
