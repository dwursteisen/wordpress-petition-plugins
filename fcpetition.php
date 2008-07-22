<?php
/*
Plugin Name: FreeCharity.org.uk WordPress Petition
Plugin URI: http://www.freecharity.org.uk/wordpress-petition-plugin/
Description: Simple petitions with e-mail based confirmation to your WordPress installation.
Version: 2.1
Author: James Davis
Author URI: http://www.freecharity.org.uk/
*/
?>
<?php
/*  Copyright 2007 James Davis (email: james@freecharity.org.uk)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
?>
<?php

/*
 *  Global variables and constants
 */

load_plugin_textdomain("fcpetition", 'wp-content/plugins/'.plugin_basename(dirname(__FILE__)));

// Define options and their default settings
$options_defaults = array (
	"petition_title" 		=> '',
	"petition_text"  		=> __("We the undersigned ask you to sign our petition.","fcpetition"),
	"petition_confirmation"	=> __("Thank you for signing the petition. You must confirm this by visiting the following address: \n\n[[curl]]\n\nRegards,\n\nJames","fcpetition"),
	"petition_confirmurl" 	=> __("<PLEASE ENTER THE CORRECT URL>","fcpetition"),
	"petition_from" 		=>  sprintf(__("My Petition <%s>","fcpetition"),get_option('admin_email')),
	"petition_maximum" 		=> 10,
	"petition_enabled" 		=> 0,
	"petition_comments" 	=> 0
);

/*  Define the maximum comment size. You can't simply just change this for an existing install
 *  you must modify the database table too
 */
define("MAX_COMMENT_SIZE",255);
/*  Disable e-mail verficiation of petitions.
 *  THIS IS A BAD THING. ENABLING THIS FEAUTRE WILL OPEN YOUR PETITION TO ABUSE AND SPAM.
 *  Set the option to 1 if you really want this. Otherwise, leave well alone.
 *  This option is purposely hidden to ordinary users.
 */ 
define("OVERRIDE_VERIFICATION",0);

// The petition table
$signature_table = $table_prefix . "petition_signatures";
$signature_table_sql = "CREATE TABLE $signature_table (
						`petition` INT,
                  		`email` VARCHAR(100),
				        `name` VARCHAR(100),
						`confirm` VARCHAR(100),
						`comment` VARCHAR(". MAX_COMMENT_SIZE ."),
						`fields`	TEXT,
						`time` DATETIME,UNIQUE KEY email (email,petition)
					);
";

$petitions_table = $table_prefix . "petitions";
$petitions_table_sql = "CREATE TABLE $petitions_table (
						`petition` INT AUTO_INCREMENT,
						`petition_title` VARCHAR(100),
						`petition_text` TEXT,
						`petition_confirmation` TEXT,
						`petition_confirmurl` VARCHAR(100),
						`petition_from` VARCHAR(100),
						`petition_maximum` INT,
						`petition_enabled` TINYINT(1),
						`petition_comments` TINYINT(1),
						PRIMARY KEY (petition)
					);
";

$fields_table = $table_prefix . "petition_fields";
$fields_table_sql = "CREATE TABLE $fields_table (
						`petition` INT,
						`name`	VARCHAR(100),
						`type`	VARCHAR(10),
						`opt`		VARCHAR(10),
						UNIQUE KEY name (petition,name)
					);
";


$old_table = $table_prefix . "petition";

/*
 *  Actions
 */

add_action('admin_menu', 'fcpetition_add_pages');			//Action adds pages
add_action('the_content','fcpetition_filter_pages');		//Action to display the petition to the user
add_action('get_header','fcpetition_export');				//Action for exporting the petition
if ( isset($_REQUEST['petition-confirm']) )
    add_action('template_redirect', 'fcpetition_confirm');

register_activation_hook(__FILE__, fcpetition_install()); 

/*
 *  Functions
 */

/*
 * Displays the confirmation page
 */
function fcpetition_confirm(){
	global $wpdb;
	global $signature_table;

	$confirm = $wpdb->escape($_GET['petition-confirm']);
	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html>
	<head>
		<title><?php printf(__('Confirm Petition Signature - %s', "fcpetition"), get_bloginfo('name')); ?></title>
		<style type="text/css" media="screen">
			@import url( <?php echo get_settings('siteurl'); ?>/wp-admin/wp-admin.css );
		</style>
		<link rel="stylesheet" type="text/css" media="print" href="<?php echo get_settings('siteurl'); ?>/print.css" />
		<meta http-equiv="Content-Type" content="text/html;charset=<?php bloginfo('charset'); ?>" />
	</head>
	<body>
	<div class="wrap">
		<h2><?php printf(__('Confirm Petition Signature - %s', "fcpetition"), get_bloginfo('name')); ?></h2>
		<p>
	<?php
	if ($wpdb->query("UPDATE $signature_table SET confirm = '' WHERE confirm = '$confirm'")==1) {
		print __("Your signature has now been added to the petition. Thank you.","fcpetition");
	} else {
		print __("The confirmation code you supplied was invalid. Either it was incorrect or it has already been used.","fcpetition");
	}
	?>
		</p>
		<p>
		<a href="<?php bloginfo('home')?>"><?php printf(__('Take me back to "%s"', "fcpetition"),get_bloginfo('name')); ?></a>
		</p>
		</div>
		</body>
	</html>
	<?php
	die();
}

/*
 * Installs the plugin, setting up database tables where necessary
 */
function fcpetition_install(){
	global $wpdb;
	global $options_defaults;
	global $signature_table;
	global $signature_table_sql;
	global $petitions_table;
	global $petitions_table_sql;
	global $fields_table;
	global $fields_table_sql;

	// Create the table that holds the signatures
	if($wpdb->get_var("SHOW TABLES LIKE '$signature_table'") != $signature_table) {
		require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
		dbDelta($signature_table_sql);
	}
	// Create the table that holds the individual petition settings
    if($wpdb->get_var("SHOW TABLES LIKE '$petitions_table'") != $petitions_table) {
	    require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	    dbDelta($petitions_table_sql);
    }
	// Create the table which holds the custom fields for individual petitions.
	if($wpdb->get_var("SHOW TABLES LIKE '$fields_table'") != $fields_table) {
	    require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	    dbDelta($fields_table_sql);
    }
	// Upgrade the petitions table if the custom fields column isn't present
	if($wpdb->get_var("SHOW COLUMNS FROM $signature_table LIKE 'fields'") != "fields") {
		$wpdb->get_results("ALTER TABLE $signature_table ADD fields TEXT;");
	}
}

/* 
 * Imports data into a specified petition, from tables created by version 1 of the plugin
 */
function fcpetition_import_version1($target) {
	global $wpdb;
	global $old_table;
	global $signature_table;
	/* 
	 *  The old database tables could only store a single petition per installation. Fetch these rows from the old table
	 */
	$old_rows = $wpdb->get_results("SELECT `email`,`name`,`confirm`,`comment`,`name`,`time` from $old_table");
	$c = 0;
	foreach($old_rows as $row) {
		$q = "INSERT INTO $signature_table (petition,email,name,confirm,comment,time) values ($target,'$row->email','$row->name','$row->confirm','$row->comment','$row->time')";
		$wpdb->query($q);
		$c++;
	}
	// Delete the old table
	$wpdb->query("DROP TABLE $old_table");
	return $c;
}

/* Show the total number of confirmed signatures. 
 * NEEDS fixing
 */
function fcpetition_count(){
	global $wpdb;
	global $signature_table;
	
	$results = $wpdb->get_results("SELECT count(confirm) as c FROM $signature_table WHERE `confirm` = ''");
        $count = $results[0]->c;
	return $count;
}

/* Show the total numbers of unconfirmed signatures 
 * NEEDS fixing
 */
function fcpetition_countu(){
	global $wpdb;
	global $signature_table;
	
	$results = $wpdb->get_results("SELECT count(confirm) as c FROM $signature_table");
        $count = $results[0]->c;
	return $count;
}

/* Return the ID of the first petition.
 * Used so that management and options pages are initialised to display the
 * earliest extant petition.
 */
function fcpetition_first(){
	global $wpdb;
	global $petitions_table;
	$results = $wpdb->get_results("SELECT `petition` FROM $petitions_table ORDER by `petition` limit 0,1");
	if (count($results)==0) return false;
	return $results[0]->petition;
}

/*
 * The user facing section of the code. Inserts the petition into pages/posts.
 */
function fcpetition_filter_pages($content) {
	global $wpdb;
	global $signature_table;
	global $petitions_table;

	if( $_POST['petition_posted'] == 'Y' && preg_match('/\[\[petition-(.*)\]\]/',$content)) {
		#If the petition has been posted

		#Clean some of the input, make SQL safe and remove HTML from name and comment which may be displayed later.
		$name = $wpdb->escape($_POST['petition_name']);
		$name = wp_kses($name,array());
		$email = $wpdb->escape($_POST['petition_email']);
		$email =  wp_kses($email,array());
		$comment = $wpdb->escape($_POST['petition_comment']);
		$comment = wp_kses($comment,array());
		$petition = $wpdb->escape($_POST['petition']);
		$petition = wp_kses($petition,array());
		$fields = base64_encode(serialize(fcpetition_collectfields($petition)));

		#Make sure that no one is cheekily sending a comment when they shouldn't be
		$rs = $wpdb->get_results("SELECT `petition_comments` from $petitions_table");
		if($rs[0]->petition_comments == 0) $comment = "";

		#Pretty much lifted from lost password code
		$confirm = substr( md5( uniqid( microtime() ) ), 0, 16);

		$wpdb->hide_errors();
		if ($name == ""){
			return __("Sorry, you must enter a name to sign the petition.","fcpetition");
		} elseif (!is_email($email)){
			return __("Sorry, \"$email\" does not appear to be a valid e-mail address.","fcpetition");
		} else if (strlen($comment) > MAX_COMMENT_SIZE) {
			return __("Sorry, your comment is longer than ".MAX_COMMENT_SIZE." characters.","fcpetition");
		} elseif ($wpdb->query("INSERT INTO $signature_table (petition,email,name,confirm,comment,time,fields) VALUES ('$petition','$email','$name','$confirm','$comment',NOW(),'$fields')")===FALSE){
			# This has almost certainly occured due to a duplicate email key
                        $wpdb->show_errors();
                        return __("Sorry, someone has already attempted to sign the petition using this e-mail address.","fcpetition");
		} else {
			$wpdb->show_errors();
                        # Successful signature, send an e-mail asking the user to confirm
						if (OVERRIDE_VERIFICATION) { 
							$wpdb->query("UPDATE $signature_table SET confirm = '' WHERE confirm = '$confirm'");
							return __("Your signature has now been added to the petition. Thank you.","fcpetition");						
						} else {
	                        $petition_confirmation = str_replace('[[curl]]',$confirm_url,$petition_confirmation);
							fcpetition_mail($email,$petition);
                        	return __("Thank you for signing the petition. An e-mail has been sent to you so that you may confirm your signature.","fcpetition");
						}
		}
	} else {
		#If not, decide whether to display the petition
		if (preg_match('/\[\[petition-(.*)\]\]/',$content,$m)) {
			return preg_replace('/\[\[petition-(.*)\]\]/',fcpetition_form($m[1]),$content);
		} else {
			return $content;
		}
	}
}

/*
 * Sends the confirmation e-mail for petition $po to $email.
 */
function fcpetition_mail($email,$po){
	global $wpdb;
	global $signature_table;
	global $petitions_table;

	$rs = $wpdb->get_results("SELECT `petition_confirmation`,`petition_from`,`petition_title`,`confirm` from $signature_table,$petitions_table where $petitions_table.petition = $signature_table.petition and `email` = '$email' and $petitions_table.petition = '$po';");
	$petition_confirmation = $rs[0]->petition_confirmation;
	$petition_from = stripslashes($rs[0]->petition_from);
	$petition_title = stripslashes($rs[0]->petition_title);
	$confirm = $rs[0]->confirm;

	$confirm_url = get_bloginfo('home') . "/?petition-confirm=$confirm";
	$petition_confirmation = str_replace('[[curl]]',$confirm_url,$petition_confirmation);
	$subject = sprintf(__("Petition: Confirm your signing of the petition '%s'","fcpetition"),$petition_title);
	wp_mail($email,"$subject","$petition_confirmation","From: $petition_from");
}

/*
 * Returns the HTML form to be presented in a page/post.
 */ 
function fcpetition_form($petition){
	global $wpdb;
	global $signature_table;
	global $petitions_table;

	// Check that the petition exists
	$rs = $wpdb->get_results("SELECT * from $petitions_table where `petition` = $petition");
	if (count($rs) != 1) return "<strong>". __("This petition does not exist","fcpetition"). "</strong>";

	// Fetch the petition's attributes
	$petition_maximum = $rs[0]->petition_maximum;
	$petition_text = wpautop(stripslashes($rs[0]->petition_text));
	$petition_comments = $rs[0]->petition_comments;
	$petition_enabled = $rs[0]->petition_enabled;

	// Check that the petition is enabled
	if(!$petition_enabled) return "<strong>".__("This petition is not enabled","fcpetition")."</strong>";

	$form_action = str_replace( '%7E', '~', $_SERVER['REQUEST_URI']);
	$form  = "
		$petition_text<br/><br/>
			<em>". __("After you have added your name to this petition an e-mail will be sent to the given address to confirm your signature. Please make sure that your e-mail address is correct or you will not receive this e-mail and your name will not be counted.","fcpetition") ."
			</em>
		<br/><br/>
			<form name='petition' method='post' action='$form_action' class='petition'>
				<input type='hidden' name='petition_posted' value='Y'/>".
				__("Name","fcpetition").":<br/><input type='text' name='petition_name' value=''/><br/>".
				__("E-mail address","fcpetition").":<br/><input type='text' name='petition_email' value=''/><br/>";
	// If comments are enabled, display that portion of the form
	if ($petition_comments == 1) { 
		$form = $form . sprintf(__("Please enter an optional comment (maximum %s characters)","fcpetition"),MAX_COMMENT_SIZE).":<br/><textarea name='petition_comment' cols='50'></textarea><br/>";
	}
	// If any custom fields are defined, display that part of the form
	$form = $form . fcpetition_livefields($petition);
	$form = $form . "			<input type='hidden' name='petition' value='$petition'/><input type='submit' name='Submit' value='".__("Sign the petition","fcpetition")."'/>
			</form>
		<h3>
			". sprintf(__("Last %d of %d signatories","fcpetition"),$petition_maximum,fcpetition_count())."</h3>";

	// Print the last $petition_maximum sigantures
	foreach ($wpdb->get_results("SELECT `name`,`comment` from $signature_table WHERE `confirm`='' AND `petition` = '$petition' ORDER BY `time` DESC limit 0,$petition_maximum") as $row) {
		// Are comments enabled and a comment exists?
		if ($petition_comments == 1 && $row->comment<>"") {
			$comment = stripslashes($row->comment);
			$form .= "<p><span class='signature'>$row->name, \"$comment\"</span></p>";
		} else {
			$form .= "<span class='signature'>$row->name </span><br/>";
		}
	}
	//Return the form, wrapping it in a <div> and closing and opening paragraph tags
	return "</p><div class='petition'>".$form."</div><p>";
}

function fcpetition_add_pages() {
	/* Add pages to the admin interface
	 */
	global $petitions_table;
	global $wpdb;

	add_options_page(__("Add/Delete Petitions","fcpetition"), __("Add/Delete Petitions","fcpetition"), 8,basename(__FILE__)."_main", 'fcpetition_main_page');
	add_options_page(__("Petition Options","fcpetition"), __("Petition Options","fcpetition"), 8,basename(__FILE__)."_options", 'fcpetition_options_page');
	add_management_page(__("Petition Management","fcpetition"), __("Petition Management","fcpetition"), 8,basename(__FILE__)."_manage", 'fcpetition_manage_page');
}

/*
 * Page for Adding/Deleting petitions.
 */
function fcpetition_main_page(){
	global $wpdb;
	global $petitions_table;
	global $signature_table;
	global $old_table;
	global $options_defaults;

	//If a petition has been added
	if ($_POST['addpetition'] != ''){
		$petition_title = $wpdb->escape($_POST['addpetition']);
		// Correctly form the SQL query
		$n = "(petition_title";
		$v = "('$petition_title'";
		foreach ($options_defaults as $option => $default) {
			if ($option == "petition_title") continue;
			$n .= ",$option"; 
			$v .= ",'$default'";
		}
		$n .= ")";
		$v .= ")";
		$wpdb->query("INSERT into $petitions_table $n values $v;");

	}
	
	//Delete a petition
	if ($_POST['deletepetition'] != ''){
		$petition = $wpdb->escape($_POST['deletepetition']);
		$wpdb->query("DELETE FROM $petitions_table WHERE petition = '$petition'");
		$wpdb->query("DELETE FROM $signature_table WHERE petition = '$petition'");
	}

	//Import petition data from version 1's database tables into a specified new petition
	if ($_POST['importpetition'] != ''){
		$target = $wpdb->escape($_POST['importpetition']);
		$rows_imp = fcpetition_import_version1($target);
		?>
			<div id="message" class="updated fade"><p><strong>
				<?php printf(__("Imported %s signatures","fcpetition"),$rows_imp); ?>
			</strong></p></div>

		<?php

	}

	?>
		<div class='wrap'><h2><?php _e("Add New Petition","fcpetition") ?> </h2>
		<p><?php _e("Adding or deleting a petition will not immediately update the structure of the administration menus.","fcpetition"); ?></p>
		<form name="petitionmain" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
			<input type="text" name="addpetition">
			<p class="submit">
			<input type='submit' name='Submit' value='<?php _e("Add Petition","fcpetition")?>'/>
			</p>
		</form>
		</div>
		<div class='wrap'><h2><?php _e("Current Petitions","fcpetition") ?> </h2>
			<table class="widefat">
			<tr><thead><th><?php _e("Petition ID","fcpetition")?></th><th><?php _e("Petition Title","fcpetition")?></th><th></th></thead></tr>
			<?php
			foreach ($wpdb->get_results("SELECT `petition`,`petition_title` from $petitions_table ORDER BY `petition`") as $row) {
				?>
				<tr>
					<td><?php print $row->petition;?></td><td><a href="<?php bloginfo('url')?>/wp-admin/options-general.php?page=fcpetition.php_options&petition_select=<?php print $row->petition;?>"><?php print stripslashes($row->petition_title);?></a></td>
					<td>
						<form name="petitionmain" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
							<input type="hidden" name="deletepetition" value="<?php print $row->petition;?>">
							<input type='submit' name='Submit' value='<?php _e("Delete Petition","fcpetition")?>'/>
						</form>
					</td>
				
				</tr>
				<?php
			}
			?>
			</table>
		</div>
		<?php $old_t =  $wpdb->get_results("SHOW TABLES FROM ".DB_NAME." LIKE '$old_table';"); 
			if(count($old_t) > 0) { ?>
		<div class='wrap'><h2>Import data from version 1.</h2>
	       <?php $plist = $wpdb->get_results("SELECT `petition`,`petition_title` from $petitions_table ORDER BY `petition`");
	            if(count($plist) > 0) { ?>
			<form name="petitionmain" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
			 	<?php _e("Import to petition:","fcpetition"); ?>
				<select name="importpetition">
		        <?php foreach ($plist as $row) {?>
					<option value="<?php print $row->petition;?>"><?php print stripslashes($row->petition_title);?></option>
				<?php } ?>
				</select>
				<input type='submit' name='Submit' value='<?php _e("Import Petition","fcpetition")?>'/>
			<?php } else { ?>
				<?php _e("You must first add a petition to import the data to.","fcpetition"); ?>
			<?php } ?>
		</div>
		<?php } ?>
	<?php
}

/*
 * Export the specified petition in CSV format
 */
function fcpetition_export(){
	global $wpdb;
	global $signature_table;
	#we ought to check for admin access too
	if ($_GET['petition_export'] && current_user_can('manage_options')){
		$po = $wpdb->escape($_GET['petition_export']);
		header('Content-Type: text/plain');
		foreach ($wpdb->get_results("SELECT `name`,`email`,`comment`,`time`,`fields` from $signature_table WHERE `confirm`='' and `petition` = '$po' ORDER BY `time` DESC") as $row) {
				?>
"<?php echo stripslashes($row->name); ?>","<?php echo stripslashes($row->email); ?>","<?php echo stripslashes($row->comment); ?>","<?php echo stripslashes($row->time); ?>"<?php fcpetition_csvfields(unserialize(base64_decode($row->fields))); ?>

<?php
		}
		// Important that we stop WordPress here. No further output after the CSV data has been displayed.
		exit;
	} else {
		// Simply do nothing if the user has no rights
		return;
	}
}
/*
 * A page to manage signatures to a particular petition.
 */ 
function fcpetition_manage_page() {
	global $wpdb;
	global $signature_table;
	global $petitions_table;
	global $options_defaults;

    if($_POST['petition_select']) {
		$po =  $wpdb->escape($_POST['petition_select']);
	} else {	
		$po = fcpetition_first();
	}

	/* $count - number of entries to display a time
	 * 			default to ten unless the user asks otherwise
	 */
	if($_POST['count']) {
		$count = $wpdb->escape($_POST['count']);
	} elseif ($_GET['count']) {
		$count = $wpdb->escape($_GET['count']);
	} else {
		$count = 10;
	}

	/* $n - The row number of the first entry to be displayed.
	 *		Defaults to 0, start from the first row
     */
	if($_POST['n']) {
 		$n = $wpdb->escape($_POST['n']);
	} elseif ($_GET['n']) {
 		$n = $wpdb->escape($_GET['n']);
	} else {
		$n = 0;
	}
	
	/* $i - the row number of the first row of the previous page
	 *       0 if there is no previous page
	 */
	$i = ($n-$count>0)?$n-$count:0;

	/* $j - the row number of the first row of the next page
	 */
	$j = $n+$count;

	$base_url = $_SERVER['REQUEST_URI'];
	$base_url = preg_replace("/\&.*/","",$base_url);

	//Clear all signatures from a petition
	if( $_POST['clear'] == 'Y' ) {
	        $wpdb->query("DELETE from $signature_table WHERE petition='$po'");
			echo '<div id="message" class="updated fade"><p><strong>';
			_e("Signatures cleared","fcpetition");
			echo "</p></strong></div>";

	}
	//Delete a specific signature from a petition
	if($_POST['delete'] != ''){
		$email = $_POST['delete'];
		$wpdb->query("DELETE FROM $signature_table WHERE email = '$email' AND petition='$po'");
		echo '<div id="message" class="updated fade"><p><strong>';
		_e("Signature Deleted.","fcpetition");
		echo "</p></strong></div>";
	}
	//Deletes a comment from a specific signature
	if($_POST['erase'] != ''){
		$email = $_POST['erase'];
		$wpdb->query("UPDATE $signature_table SET comment='' where  email = '$email' AND petition='$po'");
		echo '<div id="message" class="updated fade"><p><strong>';
		_e("Comment erased.","fcpetition");
		echo "</p></strong></div>";
	}
	//Resends a specific confirmation e-mail
	if($_POST['resend'] != ''){
	       $email = $_POST['resend'];
	       fcpetition_mail($email,$po); 
	       echo '<div id="message" class="updated fade"><p><strong>';
               _e("Confirmation e-mail resent.","fcpetition");
               echo "</p></strong></div>";
        }
	//User asks to resend confirmation e-mails to all unconfirmed addresses from a specified petition
	if($_GET['resendall'] && !$_POST['resendall']){

		//Fetch the petition name
		$nm = $wpdb->get_results("SELECT `petition_title` from $petitions_table where `petition` = $po");
		$name = $nm[0]->petition_title;

		//Work out how many e-mails would be sent, this is used to warn the user from
		//spamming signatories.
		$ct = $wpdb->get_results("SELECT count(*) as c from $signature_table WHERE `petition`='$po' AND `confirm` != ''");
		$cu = $ct[0]->c;
		?>
			<div class='wrap'>
				<h2><?php echo sprintf(__('Resend all unconfirmed e-mails for "%s"',"fcpetition"),$name);?></h2>
				<p>
				<?php echo sprintf(__('You are about to resend all unconfirmed e-mails for this particular petition. Doing so will
				send %s e-mail(s)',"fcpetition"),$cu); ?>
				</p>
				<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
					<input type='submit' name='submit' value='Yes I want to do this'/>
					<input type='hidden' name='resendall' value='yes'/>
					<input type='hidden' name='po'	value='<?php echo $po;?>'/>
				</form>
			</div>
		<?php
		return;
	}
	//Do the resending of all confirmation e-mails to all unconfirmed addresses from a specified petition.
	if($_POST['resendall']){
		$list = $wpdb->get_results("SELECT `email` from $signature_table WHERE `petition`='$po' AND `confirm` != ''");
		foreach($list as $addr) {
			fcpetition_mail($addr->email,$po);
		}

		echo '<div id="message" class="updated fade"><p><strong>';
			_e("Confirmation e-mails resent.","fcpetition");
	    echo "</p></strong></div>";
	}

	?>
		
		<div class='wrap'>
		<?php $plist = $wpdb->get_results("SELECT `petition`,`petition_title` from $petitions_table ORDER BY `petition`");
		      if (count($plist)>0) {
		?>
		<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<p><?php _e("Petition:","fcpetition"); ?>
		<select name="petition_select" onchange='this.form.submit()'>
		<?php
            foreach ($plist as $row) {
		?>
			<?php if ($row->petition == $po) { ?>
				<option value="<?php print $row->petition;?>" selected="yes"><?php print stripslashes($row->petition_title);?></option>
			<?php } else { ?>
				<option value="<?php print $row->petition;?>"><?php print stripslashes($row->petition_title);?></option>
			<?php } ?>

		<?php } ?>
		</p>
		</select>
		<noscript><input type="submit" name="Submit" value="<?php _e("Select","fcpetition")?>" /></noscript>
		</form>
		<?php } else { ?>
				<div id="message" class="error fade"><p><strong>    
                		<?php _e("Please add a petition.","fcpetition"); ?>
				</p></strong></div>
		<?php } ?>
	<?php

	if ($po==0) { echo "</div>"; return;}
	?>
	<h2><?php _e("Petition Management","fcpetition") ?></h2>

	<?php $results = $wpdb->get_results("SELECT * FROM $signature_table WHERE `petition`='$po' ORDER BY `time` LIMIT $n,$count"); 
		if (count($results) < 1) {
			_e("There are no signatures to manage yet","fcpetition");
			return;
		}
	?>

	<a href="<?php echo get_bloginfo('url') ;?>?petition_export=<?php echo $po;?>"><?php _e("Export petition results as a CSV file","fcpetition");?></a>
	| <a href="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>&resendall=yes&po=<?php echo $po;?>"><?php _e("Resend all e-mails","fcpetition"); ?></a>	
	<?php
		foreach ($wpdb->get_results("SELECT * FROM $petitions_table WHERE `petition`='$po'") as $row) {
			foreach ($options_defaults as $option => $default){
				$$option = $row->$option;
			}
		}
	?>

	<?php
		printf(__("<p> Showing %d to %d of %d (%d confirmed)</p>","fcpetition"),$n +1,$j,fcpetition_countu(),fcpetition_count());
	?>
		<form name="changecount" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
			<?php printf(__("Show %s entries at a time","fcpetition"),"<input type='text' name='count' value='$count' size='3'/>"); ?>
			<input type="hidden" name="n" value="<?php echo $n;?>"/>
			<input type="submit" name="submit" value="<?php _e("Change","fcpetition"); ?>"/>
		</form>
	<?php
		if ($n>0) { $pager .= "<a href='$base_url&n=$i&count=$count'>" . __("Previous $count","fcpetition") ."</a> ... ";}
		if (count($results)==$count) { $pager .= "... <a href='$base_url&n=$j&count=$count'>". __("Next $count","fcpetition") ."</a>";}
		if ($pager != '') { echo "<p>".$pager."</p>";}
	?>
		<table class="widefat">
		<tr><thead><th><?php _e("Name","fcpetition"); ?></th><th><?php _e("E-mail address","fcpetition"); ?></th>
	<?php
		if ($petition_comments) {
			echo "<th>".__("Comments","fcpetition")."</th>";
		} 
	?>
		<th><?php _e('Time',"fcpetition"); ?></th><th> <?php _e('Confirmation code',"fcpetition"); ?></th><th><?php _e('Fields',"fcpetition"); ?></th><th></th></thead></tr>
		<?php
		foreach ($results as $row) {
		if ($row->confirm=='') { 
			$confirm = "<em>".__("Signature confirmed.","fcpetition")."</em>";
		} else { 
			$confirm = $row->confirm; 
			$confirm = $confirm . "<form name='resendform' method='post' action='".str_replace( '%7E', '~', $_SERVER['REQUEST_URI'])."'>
	                               	<input type='hidden' name='resend' value='$row->email'/>
									<input type='hidden' name='petition_select' value='$po'/>
		                            <input type='submit' name='Submit' value='".__("Resend Confirmation e-mail","fcpetition")."'/>
								   </form>";
		}
    ?>
			<tr>
				<td class="name"><?php echo stripslashes($row->name); ?></td>
				<td class="email"><?php echo stripslashes($row->email); ?></td>
	<?php
		if ($petition_comments) { 
		?>
				<td class=\"comment\"><?php echo stripslashes($row->comment);?>
				<?php if ($row->comment != "") { ?>
				<form name='eraseform' method='post' action='<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>'>
					<input type='hidden' name='erase' value='<?php echo $row->email;?>'/>
					<input type='hidden' name='petition_select' value='<?php echo $po;?>'>
					<input type='submit' name='Submit' value='<?php _e("Erase","fcpetition");?>'/>
				</form>
				<?php } ?>
				</td>
		<?php
		}
		
	?>
				<td class="time"><?php echo $row->time; ?></td>
				<td><?php echo $confirm; ?></td>
				<td><?php fcpetition_prettyfields(unserialize(base64_decode($row->fields))); ?></td>
				<td>
					<form name='deleteform' method='post' action='<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>'>
						<input type='hidden' name='delete' value='<?php echo $row->email;?>'/>
						<input type='submit' name='Submit' value='<?php _e("Delete Signature","fcpetition"); ?>'/>
						<input type='hidden' name='petition_select' value='<?php echo $po;?>'>
					</form>
				</td>
			</tr>
	<?php } ?>
	</table>

                <form name="clearform" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
	        	<p class="submit">
	        		<input type="hidden" name="clear" value="Y">
					<input type="hidden" name="petition_select" value="<?php echo $po; ?>">
				<input type="submit" name="Submit" value="<?php _e("Clear all signatures","fcpetition")?>" />
		        </p>
	        </form>
	</div>
	<?php
}

/*
 * Adds a custom field to the database
 */
function fcpetition_addfield($po,$fieldname,$fieldtype){
	global $wpdb;
	global $fields_table;
	$sql = "INSERT into $fields_table (petition,name,type) values ($po,'$fieldname','$fieldtype')";
	$wpdb->get_results($sql);
}

/*
 *  Deletes a custom field from the database
 */
function fcpetition_deletefield($po,$fieldname){
	global $wpdb;
	global $fields_table;
	$sql = "DELETE FROM $fields_table WHERE petition = '$po' and name = '$fieldname'";
	$wpdb->get_results($sql);
}

/*
 * Displays the custom fields in a form suitable for the options page.
 * Also defines the form for deletion of fields 
 */

function fcpetition_displayfields($po) {
	global $wpdb;
	global $fields_table;
	$sql = "SELECT * FROM $fields_table WHERE `petition` = '$po'";
	$res = $wpdb->get_results($sql);
	if (count($res) > 0) {
		?>
		<table>
			<tr><thead><th>Name</th><th>Type</th><th>Options</th><th></th></thead></tr>
		<?php
		foreach($res as $row){
			?>
			<tr>
				<td><?php echo $row->name; ?></td>
				<td><?php echo $row->type; ?></td>
				<td><?php echo $row->opt; ?></td>
				<td>
					<form  method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>"/>
						<input type="hidden" name="fieldname" value="<?php echo $row->name; ?>"/>
						<input type="hidden" name="deletefield" value="yes"/>
						<input type="hidden" name="petition_select" value="<?php echo $po; ?>"/>
						<input type="submit" name="Submit" value="<?php _e("Delete","fcpetition")?>"/>
					</form>
				</td>
			</tr>
		<?php
		}
	}
	?>
	</table>
	<?php
}

/* 
 *  Returns the HTML for the user to input data for defined custom fields
 */
function fcpetition_livefields($po) {
	global $wpdb;
	global $fields_table;
	$sql = "SELECT * FROM $fields_table WHERE `petition` = '$po'";
	$res = $wpdb->get_results($sql);
	$output = "";
	if(count($res)>0) {
		foreach($res as $row){
			$output .= "$row->name:<br/><input type='$row->type' name='$row->name'/><br/>\n";
		}
	}
	return $output;
}

/*
 *  Scans the HTTP headers for submitted data matching defined custom fields.
 *  Places the results in an array/map. This is later stored in serialized form
 *  in the signature's database row.
 */
function fcpetition_collectfields($po) {
	global $wpdb;
	global $fields_table;
	$sql = "SELECT `name` FROM $fields_table WHERE `petition` = '$po'";
	$res = $wpdb->get_results($sql);
	foreach($res as $field) {
		$f = str_replace(" ","_",$field->name);
		if($_POST[$f]){
			$package[$f] = $wpdb->escape($_POST[$f]);
		} else {
			$package[$f] = "";
		}
	}
	return $package;
}

/*
 * The form for adding custom fields on the options page.
 */
function fcpetition_fieldform($po) {
	?>
			<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>"/>
				<input type="hidden" name="addfield" value="yes"/>
				<input type="hidden" name="petition_select" value="<?php echo $po; ?>"/>
				 <input type="hidden" name="fieldtype" value="text"/>
				<input type="text" name="fieldname"/>
				<input type="submit" name="Submit" value="<?php _e("Add","fcpetition")?>"/>
			</form>
	<?php
}

/*
 * A pretty HTML representation of the custom field data
 */
function fcpetition_prettyfields($package) {
	foreach ($package as $field => $value) {
		print "<strong>$field:</strong> $value ";
	}
}

/*
 *  CSV output of the custom field data
 */
function fcpetition_csvfields($package) {
	foreach ($package as $field => $value){
		print ",\"$value\"";
	}
}

function fcpetition_options_page() {
	/* Handles the petition settings
	 */

    global $wpdb;
    global $options_defaults;
	global $signature_table;
	global $petitions_table;

	if($_POST['petition_select']) {
		$po =  $wpdb->escape($_POST['petition_select']);
	} elseif ($_GET['petition_select']) {
		$po =  $wpdb->escape($_GET['petition_select']);
	} else {
		$po = fcpetition_first();
	}
	#Fetch options
	foreach ($wpdb->get_results("SELECT * FROM $petitions_table WHERE `petition`='$po'") as $row) {
		foreach ($options_defaults as $option => $default){
			$$option = stripslashes($row->$option);
		}
	}

    // Test for submitted data
    if( $_POST['submitted'] == 'Y' ) {
		
		foreach ($options_defaults as $option => $default){
			//Perform any checks here, continue over any problem input
			if($option == "petition_confirmation" && !strpos($_POST[$option],"[[curl]]")) {
				$p_error = __("[[curl]] must appear in your confirmation email text.","fcpetition");
				$petition_confirmation =  $$option;
				continue;
			}
			//Update options table
			$$option = $_POST[$option];
			$foo = $wpdb->escape($_POST[$option]);
			$wpdb->query("UPDATE $petitions_table set $option = '$foo' where petition='$po'");
		}

	    if($p_error != "") {
		print "
			<div id=\"message\" class=\"error fade\"><p><strong>
				$p_error
	                </p></strong></div>
		";
	    }

	    ?>
	    <div id="message" class="updated fade"><p><strong>
		    <?php _e("Options Updated.","fcpetition") ?>
	    </p></strong></div>
	    <?php
    }

	if ( $_POST['addfield']) {
		$fieldtype = $wpdb->escape($_POST['fieldtype']);
		$fieldname = $wpdb->escape($_POST['fieldname']);
		fcpetition_addfield($po,$fieldname,$fieldtype);
	}
	if ( $_POST['deletefield']) {
		$fieldname = $wpdb->escape($_POST['fieldname']);
		fcpetition_deletefield($po,$fieldname);
	}

	    ?>
	    <div class='wrap'>
		<?php $plist = $wpdb->get_results("SELECT `petition`,`petition_title` from $petitions_table ORDER BY `petition`");
			if(count($plist) > 0) {
		?>
		<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<p><?php _e("Petition:","fcpetition"); ?>
		<select name="petition_select" onchange='this.form.submit()'>
		<?php
            foreach ($plist as $row) {
		?>
			<?php if ($row->petition == $po) { ?>
				<option value="<?php print $row->petition;?>" selected="yes"><?php print stripslashes($row->petition_title);?></option>
			<?php } else { ?>
				<option value="<?php print $row->petition;?>"><?php print stripslashes($row->petition_title);?></option>
			<?php } ?>

		<?php } ?>
		</p>
		</select>
		<noscript><input type="submit" name="Submit" value="<?php _e("Select","fcpetition")?>" /></noscript>
	 	</form>
		<?php } else { ?>
			<div id="message" class="error fade"><p><strong>	
				<?php _e("Please add a petition.","fcpetition"); ?>
			</p></strong></div>
		<?php } ?>

		<?php if($po != 0) { ?>
	    	<h2><?php _e("Petition Options","fcpetition")?></h2>
			<?php fcpetition_displayfields($po);fcpetition_fieldform($po); ?>
			<!-- <p><a href="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>&editfields=yes&petition_select=<?php echo $po; ?>"><?php _e("Add custom field to this petition...","fcpetition"); ?></a></p>-->
			<p><?php printf(__("Place [[petition-%s]] in the page or post where you wish this petition to appear.","fcpetition"),$po); ?></p>
		<form name="optionsform" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<input type="hidden" name="submitted" value="Y">
		<input type="hidden" name="petition_select" value="<?php echo $po; ?>"/>
		<p>
			<?php _e("Please enter the petition title","fcpetition")?><br/>
			<input type="text" name="petition_title" value="<?php echo stripslashes($petition_title); ?>" size="72"/>
		</p>
		<p>
			<?php _e("Please enter the petition text","fcpetition")?><br/>
			<textarea name="petition_text" rows="10" cols="72"><?php echo stripslashes($petition_text); ?></textarea>
		</p>
		<p>
			<?php _e("Please enter the confirmation email text. Insert [[curl]] where the confirmation URL is to appear. [[curl]] <strong>must</strong> appear in the text or the confirmation e-mails will not work.","fcpetition")?><br/>
        	        <textarea name="petition_confirmation" rows="10" cols="72"><?php echo stripslashes($petition_confirmation); ?></textarea>
		</p>
		<p>
		        <?php _e("Please enter the confirmation URL. This is the page or post on which the petition appears. This option <strong>must</strong> be correctly set or the confirmation e-mails will not work.","fcpetition")?><br/>
		        <input type="text" name="petition_confirmurl" size="72" value="<?php echo stripslashes($petition_confirmurl); ?>"/>
		</p>

		<p>
			<?php _e("Please enter the address which the confirmation e-mail will appear to be sent from. Any replies to the confirmation e-mail will be directed to this address. This <strong>must</strong> follow the same format as the example address.","fcpetition")?><br/>
			<input type="text" name="petition_from" value="<?php echo stripslashes($petition_from); ?>" size="72"/>
		</p>
		<p>
			<?php _e("Please enter the maximum number of signatures to be displayed","fcpetition")?><br/>
			<input type="text" name="petition_maximum" value="<?php echo $petition_maximum; ?>"/>
		</p>
		<p>
			<?php _e("Allow signatories to leave a comment","fcpetition")?>
			<input type="checkbox" name="petition_comments" value="1" <?php echo ($petition_comments)?'checked':'';?>>
		</p>
		<p>
			<?php _e("Enable Petition","fcpetition")?>
			<input type="checkbox" name="petition_enabled" value="1" <?php echo ($petition_enabled)?'checked':'';?>>
		</p>
			<p class="submit">
			<input type="submit" name="Submit" value="<?php _e("Update Options","fcpetition")?>" />
		</p>
		</form>
		<?php } ?>
			<hr/>
			<p>Written by James Davis and licensed under the GNU GPL. For assistance please visit this plugin's <a href="http://www.freecharity.org.uk/wordpress-petition-plugin/">web page</a>.

		</p>
	    <?php
    echo "</div>";

}
?>
