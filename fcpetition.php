<?php
/*
Plugin Name: FreeCharity.org.uk WordPress Petition
Plugin URI: http://www.freecharity.org.uk/wordpress-petition-plugin/
Description: Simple petitions with e-mail based confirmation to your WordPress installation.
Version: 2.0.8
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
	"petition_confirmation"	=> __("Thank you for signing the petition.\n\n[[curl]]\n\nRegards,\n\nJames","fcpetition"),
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
						petition INT,
                  		email VARCHAR(100),
				        name VARCHAR(100),
						confirm VARCHAR(100),
						comment VARCHAR(". MAX_COMMENT_SIZE ."),
						time DATETIME,UNIQUE KEY email (email,petition)
					);
";

$petitions_table = $table_prefix . "petitions";
$petitions_table_sql = "CREATE TABLE $petitions_table (
						petition INT AUTO_INCREMENT,
						petition_title VARCHAR(100),
						petition_text TEXT,
						petition_confirmation TEXT,
						petition_confirmurl VARCHAR(100),
						petition_from VARCHAR(100),
						petition_maximum INT,
						petition_enabled TINYINT(1),
						petition_comments TINYINT(1),
						PRIMARY KEY (petition)
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

function fcpetition_install(){
	global $wpdb;
	global $options_defaults;
	global $signature_table;
	global $signature_table_sql;
	global $petitions_table;
	global $petitions_table_sql;

	if($wpdb->get_var("SHOW TABLES LIKE '$signature_table'") != $signature_table) {
		require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
		dbDelta($signature_table_sql);
	}
    if($wpdb->get_var("SHOW TABLES LIKE '$petitions_table'") != $petitions_table) {
	    require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	    dbDelta($petitions_table_sql);
    }
}

function fcpetition_import_version1($target) {
	global $wpdb;
	global $old_table;
	global $signature_table;
	$old_rows = $wpdb->get_results("select email,name,confirm,comment,name,time from $old_table");
	$c = 0;
	foreach($old_rows as $row) {
		$q = "INSERT INTO $signature_table (petition,email,name,confirm,comment,time) values ($target,'$row->email','$row->name','$row->confirm','$row->comment','$row->time')";
		$wpdb->query($q);
		$c++;
	}
	$wpdb->query("DROP TABLE $old_table");
	return $c;
}

function fcpetition_count(){
	global $wpdb;
	global $signature_table;
	
	$results = $wpdb->get_results("SELECT count(confirm) as c FROM $signature_table WHERE confirm = ''");
        $count = $results[0]->c;
	return $count;
}

function fcpetition_countu(){
	global $wpdb;
	global $signature_table;
	
	$results = $wpdb->get_results("SELECT count(confirm) as c FROM $signature_table");
        $count = $results[0]->c;
	return $count;
}

function fcpetition_first(){
	global $wpdb;
	global $petitions_table;
	$results = $wpdb->get_results("SELECT petition FROM $petitions_table ORDER by petition limit 0,1");
	if (count($results)==0) return false;
	return $results[0]->petition;
}

function fcpetition_filter_pages($content) {
	/* Filter the_content on appropriate pages. This function contains the
	 * user facing portion of the code. 
	 */
	
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

		#Make sure that no one is cheekily sending a comment when they shouldn't be
		$rs = $wpdb->get_results("select petition_comments from $petitions_table");
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
		} elseif ($wpdb->query("INSERT INTO $signature_table (petition,email,name,confirm,comment,time) VALUES ('$petition','$email','$name','$confirm','$comment',NOW())")===FALSE){
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

function fcpetition_mail($email,$po){
	global $wpdb;
	global $signature_table;
	global $petitions_table;

	$rs = $wpdb->get_results("select petition_confirmation,petition_from,petition_title,confirm from $signature_table,$petitions_table where $petitions_table.petition = $signature_table.petition and email = '$email' and $petitions_table.petition = '$po';");
	$petition_confirmation = $rs[0]->petition_confirmation;
	$petition_from = stripslashes($rs[0]->petition_from);
	$petition_title = stripslashes($rs[0]->petition_title);
	$confirm = $rs[0]->confirm;

	$confirm_url = get_bloginfo('home') . "/?petition-confirm=$confirm";
	$petition_confirmation = str_replace('[[curl]]',$confirm_url,$petition_confirmation);
	$subject = sprintf(__("Petition: Confirm your signing of the petition '%s'","fcpetition"),$petition_title);
	wp_mail($email,"$subject","$petition_confirmation","From: $petition_from");
}

function fcpetition_form($petition){
	/* Generates the HTML form presented in the_content of the tagged
	 * post/page 
	 */

	global $wpdb;
	global $signature_table;
	global $petitions_table;

	$rs = $wpdb->get_results("SELECT * from $petitions_table where petition = $petition");
	if (count($rs) != 1) return "<strong>". __("This petition does not exist","fcpetition"). "</strong>";
	
	$petition_maximum = $rs[0]->petition_maximum;
	$petition_text = wpautop(stripslashes($rs[0]->petition_text));
	$petition_comments = $rs[0]->petition_comments;
	$petition_enabled = $rs[0]->petition_enabled;
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
	if ($petition_comments == 1) { 
		$form = $form . sprintf(__("Please enter an optional comment (maximum %s characters)","fcpetition"),MAX_COMMENT_SIZE).":<br/><textarea name='petition_comment' cols='50'></textarea><br/>";
	}
	$form = $form . "			<input type='hidden' name='petition' value='$petition'/><input type='submit' name='Submit' value='".__("Sign the petition","fcpetition")."'/>
			</form>
		<h3>
			". sprintf(__("Last %d of %d signatories","fcpetition"),$petition_maximum,fcpetition_count())."</h3>";
	foreach ($wpdb->get_results("SELECT name,comment from $signature_table WHERE confirm='' AND petition = '$petition' ORDER BY time DESC limit 0,$petition_maximum") as $row) {
		if ($petition_comments == 1 && $row->comment<>"") {
			$comment = stripslashes($row->comment);
			$form .= "<p><span class='signature'>$row->name, \"$comment\"</span></p>";
		} else {
			$form .= "<span class='signature'>$row->name </span><br/>";
		}
	}
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

function fcpetition_main_page(){
	global $wpdb;
	global $petitions_table;
	global $signature_table;
	global $old_table;
	global $options_defaults;

	//print $_GET['page'];
	if ($_POST['addpetition'] != ''){
		$petition_title = $wpdb->escape($_POST['addpetition']);
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
	if ($_POST['deletepetition'] != ''){
		$petition = $wpdb->escape($_POST['deletepetition']);
		$wpdb->query("DELETE FROM $petitions_table WHERE petition = '$petition'");
		$wpdb->query("DELETE FROM $signature_table WHERE petition = '$petition'");
	}
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
			foreach ($wpdb->get_results("SELECT petition,petition_title from $petitions_table ORDER BY petition") as $row) {
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
	       <?php $plist = $wpdb->get_results("SELECT petition,petition_title from $petitions_table ORDER BY petition");
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

function fcpetition_export(){
	global $wpdb;
	global $signature_table;
	#we ought to check for admin access too
	if ($_GET['petition_export'] && current_user_can('manage_options')){
		$po = $wpdb->escape($_GET['petition_export']);
		header('Content-Type: text/plain');
		foreach ($wpdb->get_results("SELECT name,email,comment,time from $signature_table WHERE confirm='' and petition = '$po' ORDER BY time DESC") as $row) {
		                print '"' . stripslashes($row->name) .'","'. stripslashes($row->email) .'","'.stripslashes($row->comment).'","'. $row->time ."\"\n";
		}
		exit;
	} else {
		return;
	}
}

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

	$n = $_GET['n']?$_GET['n']:0;

	$i = ($n-10>0)?$n-10:0;
	$j = $n+10;

	$base_url = $_SERVER['REQUEST_URI'];
	$base_url = preg_replace("/\&.*/","",$base_url);

	if( $_POST['clear'] == 'Y' ) {
	        $wpdb->query("DELETE from $signature_table WHERE petition='$po'");
			echo '<div id="message" class="updated fade"><p><strong>';
			_e("Signatures cleared","fcpetition");
			echo "</p></strong></div>";

	}
	if($_POST['delete'] != ''){
		$email = $_POST['delete'];
		$wpdb->query("DELETE FROM $signature_table WHERE email = '$email' AND petition='$po'");
		echo '<div id="message" class="updated fade"><p><strong>';
		_e("Signature Deleted.","fcpetition");
		echo "</p></strong></div>";
	}
	if($_POST['resend'] != ''){
	       $email = $_POST['resend'];
	       fcpetition_mail($email,$po); 
	       echo '<div id="message" class="updated fade"><p><strong>';
               _e("Confirmation e-mail resent.","fcpetition");
               echo "</p></strong></div>";
        }

	?>
		
		<div class='wrap'>
		<?php $plist = $wpdb->get_results("SELECT petition,petition_title from $petitions_table ORDER BY petition");
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

	<?php $results = $wpdb->get_results("SELECT * FROM $signature_table WHERE petition='$po' ORDER BY time LIMIT $n,10"); 
		if (count($results) < 1) {
			_e("There are no signatures to manage yet","fcpetition");
			return;
		}
	?>

	<a href="<?php echo get_bloginfo('url') ;?>?petition_export=<?php echo $po;?>"><?php _e("Export petition results as a CSV file","fcpetition");?></a>
	
	<?php
		foreach ($wpdb->get_results("SELECT * FROM $petitions_table WHERE petition='$po'") as $row) {
			foreach ($options_defaults as $option => $default){
				$$option = $row->$option;
			}
		}
	?>

	<?php
		printf(__("<p> Showing %d to %d of %d (%d confirmed)</p>","fcpetition"),$n +1,$j,fcpetition_countu(),fcpetition_count());
		if ($n>0) { $pager .= "<a href='$base_url&n=$i'>" . __("Previous 10","fcpetition") ."</a> ... ";}
		if (count($results)==10) { $pager .= "... <a href='$base_url&n=$j'>". __("Next 10","fcpetition") ."</a>";}
		if ($pager != '') { echo "<p>".$pager."</p>";}
	?>
		<table class="widefat">
		<tr><thead><th><?php _e("Name","fcpetition"); ?></th><th><?php _e("E-mail address","fcpetition"); ?></th>
	<?php
		if ($petition_comments) {
			echo "<th>".__("Comments","fcpetition")."</th>";
		} 
	?>
		<th><?php _e('Time',"fcpetition"); ?></th><th> <?php _e('Confirmation code',"fcpetition"); ?></th><th></th></thead></tr>
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
		if ($petition_comments) { echo "<td class=\"comment\">". stripslashes($row->comment)."</td>";}
	?>
				<td class="time"><?php echo $row->time; ?></td>
				<td><?php echo $confirm; ?></td>
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
	foreach ($wpdb->get_results("SELECT * FROM $petitions_table WHERE petition='$po'") as $row) {
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
			$wpdb->query("update $petitions_table set $option = '$foo' where petition='$po'");
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
	    ?>
	    <div class='wrap'>
		<?php $plist = $wpdb->get_results("SELECT petition,petition_title from $petitions_table ORDER BY petition");
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
