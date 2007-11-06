<?php
/*
Plugin Name: FreeCharity.org.uk WordPress Petition
Plugin URI: http://www.freecharity.org.uk/wordpress-petition-plugin/
Description: Adds a single, simple petition with e-mail based confirmation to your WordPress installation.
Version: 1.0
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

add_action('admin_menu', 'fcpetition_add_pages');
add_action('the_content','fcpetition_filter_pages');
register_activation_hook(__FILE__, fcpetition_install()); 
load_plugin_textdomain("fcpetition", 'wp-content/plugins/'.plugin_basename(dirname(__FILE__)));
add_action('get_header','fcpetition_export');


function fcpetition_upgrade(){
	global $wpdb;
	$table_name = $wpdb->prefix . "petition";

	$code_version = 1;
	$current_version = get_option("petition_version");
	if(!isset($current_version)) { $current_version = 0;}
	if($code_version==1 && $current_version==0){
		$sql = "ALTER TABLE $table_name ADD COLUMN comment VARCHAR(300) AFTER confirm";
		$wpdb->query($sql);
		update_option("petition_version",1);
	}
}

function fcpetition_install(){
	/* Basic setup for new users */

	global $wpdb;

	# Setup Database table
	$table_name = $wpdb->prefix . "petition";

	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$sql = "CREATE TABLE $table_name (
			  	  email VARCHAR(100),
				  name VARCHAR(100),
				  confirm VARCHAR(100),
				  comment VARCHAR(300),
				  time DATETIME,
				  UNIQUE KEY email (email)
		);";
		require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
		dbDelta($sql);
	}
	
	# Setup options, only if not already defined from a previous installation
	if (get_option("petition_title")=="") {update_option("petition_title", __("My Petition","fcpetition"));}
        if (get_option("petition_text")=="") {update_option("petition_text", __("We the undersigned ask you to sign our petition.","fcpetition"));}
	if (get_option("petition_confirmation")=="") {update_option("petition_confirmation", __("Thank you for signing the petition.\n\n[[curl]]\n\nRegards,\n\nJames","fcpetition"));}
	if (get_option("petition_confirmurl")=="")  {update_option("petition_confirmurl",__("<PLEASE ENTER THE CORRECT URL>","fcpetition"));}
	if (get_option("petition_from")=="") {update_option("petition_from", __("My Petition <mypetition@foo.co.m>","fcpetition"));}
	if (get_option("petition_maximum")=="") {update_option("petition_maximum", 10);}
	if (get_option("petition_enabled")=="") {update_option("petition_enabled", "N" );}
	if (get_option("petition_comments")=="") {update_option("petition_comments", "N" );}
	fcpetition_upgrade();
}

function fcpetition_filter_pages($content) {
	/* Filter the_content on appropriate pages. This function contains the
	 * user facing portion of the code. 
	 */

	global $wpdb;

        $table_name = $wpdb->prefix . "petition";

	if($_GET['petition_confirm'] != '' && substr_count($content,"[[petition]]")>0){
		$confirm = $wpdb->escape($_GET['petition_confirm']);
		if ($wpdb->query("UPDATE $table_name SET confirm = '' WHERE confirm = '$confirm'")==1) {
			return __("Your signature has now been added to the petition. Thank you.","fcpetition");
		} else {
			return __("The confirmation code you supplied was invalid. Either it was incorrect or it has already been used.","fcpetition");
		}
	}

	if( $_POST['petition_posted'] == 'Y' && substr_count($content,"[[petition]]")>0) {
		#If the petition has been posted

		#Clean some of the input, make SQL safe and remove HTML from name and comment which may be displayed later.
		$name = $wpdb->escape($_POST['petition_name']);
		$name = wp_kses($name,array());
		$email = $wpdb->escape($_POST['petition_email']);
		$email =  wp_kses($email,array());
		$comment = $wpdb->escape($_POST['petition_comment']);
		$comment = wp_kses($comment,array());

		#Make sure that no one is cheekily sending a comment when they shouldn't be
		if(get_option("petition_comment")!='Y') { $comment = "";}

		#Pretty much lifted from lost password code
		$confirm = substr( md5( uniqid( microtime() ) ), 0, 16);

		$wpdb->hide_errors();
		if ($name == ""){
			return __("Sorry, you must enter a name to sign the petition.","fcpetition");
		} elseif (!is_email($email)){
			return __("Sorry, \"$email\" does not appear to be a valid e-mail address.","fcpetition");
		} elseif ($wpdb->query("INSERT INTO $table_name (email,name,confirm,comment,time) VALUES ('$email','$name','$confirm','$comment',NOW())")===FALSE){
			# This has almost certainly occured due to a duplicate email key
                        $wpdb->show_errors();
                        return __("Sorry, someone has already attempted to sign the petition using this e-mail address.","fcpetition");
		} else {
			$wpdb->show_errors();
                        # Successful signature, send an e-mail asking the user to confirm
                        $petition_confirmation = str_replace('[[curl]]',$confirm_url,$petition_confirmation);
			fcpetition_mail($email);
                        return __("Thank you for signing the petition. An e-mail has been sent to you so that you may confirm your signature.","fcpetition");
		}
	} else {
		#If not, decide whether to display the petition
		if(get_option("petition_enabled")=='Y'){
			return str_replace('[[petition]]',fcpetition_form(),$content);
		} else {
			return str_replace('[[petition]]','<strong>[[This petition has been disabled]]</strong>',$content);
		}
	}
}

function fcpetition_mail($email){
	global $wpdb;
	$table_name = $wpdb->prefix . "petition";

	$petition_confirmation = get_option("petition_confirmation");
	$petition_confirmurl = get_option("petition_confirmurl");
        $petition_from = get_option("petition_from");
        $petition_title = get_option("petition_title");
	$results = $wpdb->get_results("SELECT confirm FROM $table_name WHERE email = '$email'");
	$confirm = $results[0]->confirm;

        #Construct a confirmation URL, appending an extra parameter to the URL as necessary
        if(substr_count($petition_confirmurl,"?") > 0) {
	        #There are already arguments, add one
	        $confirm_url =  $petition_confirmurl."&petition_confirm=$confirm";
	} else {
                #There are no arguments, start one
		$confirm_url =  $petition_confirmurl."?petition_confirm=$confirm";
	}
	$petition_confirmation = str_replace('[[curl]]',$confirm_url,$petition_confirmation);
	wp_mail($email,"Petition: Confirm your signing of the '$petition_title'","$petition_confirmation","From: $petition_from");
}

function fcpetition_form(){
	/* Generates the HTML form presented in the_content of the tagged
	 * post/page 
	 */

	global $wpdb;
	$table_name = $wpdb->prefix . "petition";
	$petition_maximum = get_option("petition_maximum");
	$petition_text = get_option("petition_text");
	$petition_comments = get_option("petition_comments");
	$form_action = str_replace( '%7E', '~', $_SERVER['REQUEST_URI']);
	$form  = "</p>
		<div class='petition'>
		$petition_text<br/><br/>
			<em>". __("After you have added your name to this petition an e-mail will be sent to the given address to confirm your signature. Please make sure that your e-mail address is correct or you will not receive this e-mail and your name will not be counted.","fcpetition") ."
			</em>
		<br/><br/>
			<form name='petition' method='post' action='$form_action' class='petition'>
				<input type='hidden' name='petition_posted' value='Y'/>".
				__("Name","fcpetition").":<br/><input type='text' name='petition_name' value=''/><br/>".
				__("E-mail address","fcpetition").":<br/><input type='text' name='petition_email' value=''/><br/>";
	if ($petition_comments == 'Y') { 
		$form = $form . __("Please enter an optional comment","fcpetition").":<br/><textarea name='petition_comment' cols='50'></textarea><br/>";
	}
	$form = $form . "			<input type='submit' name='Submit' value='".__("Sign the petition","fcpetiton")."'/>
			</form>
		<h3>
			".__("Last ","fcpetition"). $petition_maximum . __(" signatories","fcpetition").
		"</h3>";
	foreach ($wpdb->get_results("SELECT name,comment from $table_name WHERE confirm='' ORDER BY time DESC limit 0,$petition_maximum") as $row) {
		if ($petition_comments == 'Y' && $row->comment<>"") {
			$form .= "<span class='signature'>$row->name, \"$row->comment\"</span><br/>";
		} else {
			$form .= "<span class='signature'>$row->name </span><br/>";
		}
	}
	return $form."</div><p>";
}

function fcpetition_add_pages() {
	/* Add pages to the admin interface
	 */

	add_options_page(__("Petition Options","fcpetiton"), 'Petition', 8,basename(__FILE__), 'fcpetition_options_page');
	add_management_page(__("Manage Petition","fcpetiton"), 'Petition', 8,basename(__FILE__), 'fcpetition_manage_page');
}

function fcpetition_export(){
	global $wpdb;
	$table_name = $wpdb->prefix . "petition";
	#we ought to check for admin access too
	if ('Y' == $_GET['petition_export'] && current_user_can('manage_options')){
		header('Content-Type: text/plain');
		foreach ($wpdb->get_results("SELECT name,email,time from $table_name WHERE confirm='' ORDER BY time DESC") as $row) {
		                print $row->name .",". $row->email .",". $row->time ."\n";
		}
		exit;
	} else {
		return;
	}
}

function fcpetition_manage_page() {
	fcpetition_upgrade();
	global $wpdb;
	$comments = get_option("petition_comments");

        $table_name = $wpdb->prefix . "petition";
	$n = $_GET['n']?$_GET['n']:0;

	$i = ($n-10>0)?$n-10:0;
	$j = $n+10;

	$base_url = $_SERVER['REQUEST_URI'];
	$base_url = preg_replace("/\&.*/","",$base_url);

	if( $_POST['clear'] == 'Y' ) {
	                $wpdb->query("TRUNCATE $table_name");
			echo '<div id="message" class="updated fade"><p><strong>';
			_e("Signatures cleared","fcpetition");
			echo "</p></strong></div>";

	}
	if($_POST['delete'] != ''){
		$email = $_POST['delete'];
		$wpdb->query("DELETE FROM $table_name WHERE email = '$email'");
		echo '<div id="message" class="updated fade"><p><strong>';
		_e("Signature Deleted.","fcpetition");
		echo "</p></strong></div>";
	}
	if($_POST['resend'] != ''){
	       $email = $_POST['resend'];
	       fcpetition_mail($email); 
	       echo '<div id="message" class="updated fade"><p><strong>';
               _e("Confirmation e-mail resent.","fcpetition");
               echo "</p></strong></div>";
        }

	echo "<div class='wrap'><h2>".__("Petition Management","fcpetition")."</h2>";

	echo '<a href="'.get_bloginfo('url').'?petition_export=Y">'.__("Export petition results as a CSV file","fcpetition").'</a>';

	$results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time LIMIT $n,10");

	echo "<p> Showing ".($n +1). " to $j </p>";
	if ($n>0) { $pager .= "<a href='$base_url&n=$i'>Previous 10</a> ... ";}
	if (count($results)==10) { $pager .= "... <a href='$base_url&n=$j'>Next 10</a>";}
	if ($pager != '') { echo "<p>".$pager."</p>";}
	echo '<table class="widefat">';
	echo '<tr><thead><th>'.__("Name").'</th><th>'.__("E-mail").'</th>';
	if ($comments=='Y') {echo '<th>'.__("Comments").'</th>';}
	echo '<th>'.__('Time').'</th><th>'.__('Confirmation code').'</th></thead></tr>';
	foreach ($results as $row) {
		if ($row->confirm=='') { 
			$confirm = "<em>".__("Confirmed")."</em>";
		} else { 
			$confirm = $row->confirm; 
			$confirm = $confirm . "<form name='resendform' method='post' action='".str_replace( '%7E', '~', $_SERVER['REQUEST_URI'])."'>
	                                               <input type='hidden' name='resend' value='$row->email'/>
		                                       <input type='submit' name='Submit' value='".__("Resend Confirmation e-mail")."'/>
					</form>";
		}
                echo "
			<tr>
				<td>$row->name</td>
				<td>$row->email</td>
		";
		if ($comments=='Y') { echo "<td>$row->comment</td>";}
		echo "
				<td>$row->time</td>
				<td>$confirm</td>
				<td>
					<form name='deleteform' method='post' action='".str_replace( '%7E', '~', $_SERVER['REQUEST_URI'])."'>
						<input type='hidden' name='delete' value='$row->email'/>
						<input type='submit' name='Submit' value='".__("Delete")."'/>
					</form>
				</td>
			</tr>";
	}
	echo "</table>";

	?>
                <form name="clearform" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
	        	<p class="submit">
	        		<input type="hidden" name="clear" value="Y">
				<input type="submit" name="Submit" value="<?php _e("Clear all signatures","fcpetition")?>" />
		        </p>
	        </form>
	</div>
	<?php
}

function fcpetition_options_page() {
	/* Handles the petition settings
	 */
    fcpetition_upgrade();

    global $wpdb;
    $table_name = $wpdb->prefix . "petition";

    $petition_title = get_option("petition_title");
    $petition_text = get_option("petition_text");
    $petition_confirmation = get_option("petition_confirmation");
    $petition_confirmurl = get_option("petition_confirmurl");
    $petition_from = get_option("petition_from");
    $petition_maximum = get_option("petition_maximum");
    $petition_enabled = get_option("petition_enabled");
    $petition_comments = get_option("petition_comments");
    // Test for submitted data
    if( $_POST['submitted'] == 'Y' ) {
            // Read their posted values
	    $petition_title = $_POST['petition_title'];
	    $petition_text = $_POST['petition_text'];
	    $petition_confirmation = $_POST['petition_confirmation'];
	    $petition_confirmurl = $_POST['petition_confirmurl'];
	    $petition_from = $_POST['petition_from'];
	    $petition_maximum = $_POST['petition_maximum'];
	    $petition_enabled = ($_POST['petition_enabled']=='Y')?'Y':'N';
	    $petition_comments = ($_POST['petition_comments']=='Y')?'Y':'N';
            // Save the posted value in the database
	    update_option("petition_title", $petition_title );
            update_option("petition_text", $petition_text );
	    update_option("petition_confirmation", $petition_confirmation );
	    update_option("petition_confirmurl", $petition_confirmurl );
	    update_option("petition_from", $petition_from );
	    update_option("petition_maximum", $petition_maximum );
	    update_option("petition_enabled", $petition_enabled );
	    update_option("petition_comments", $petition_comments );
            // Put an options updated message on the sc
	    ?>
	    <div id="message" class="updated fade"><p><strong>
		    <?php _e("Options Updated.","fcpetition") ?>
	    </p></strong></div>
	    <?php
    }
	    ?>
	    <div class='wrap'>
	    	<h2><?php _e("Petition Options","fcpetition")?></h2>
		<form name="optionsform" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<input type="hidden" name="submitted" value="Y">
		<p>
			<?php _e("Please enter the petition title","fcpetition")?><br/>
			<input type="text" name="petition_title" value="<?php echo $petition_title; ?>" size="72"/>
		</p>
		<p>
			<?php _e("Please enter the petition text","fcpetition")?><br/>
			<textarea name="petition_text" rows="10" cols="72"><?php echo $petition_text; ?></textarea>
		</p>
		<p>
			<?php _e("Please enter the confirmation email text. Insert [[curl]] where the confirmation URL is to appear. [[curl]] <strong>must</strong> appear in the text or the confirmation e-mails will not work.","fcpetition")?><br/>
        	        <textarea name="petition_confirmation" rows="10" cols="72"><?php echo $petition_confirmation; ?></textarea>
		</p>
		<p>
		        <?php _e("Please enter the confirmation URL. This is the page or post on which the petition appears. This option <strong>must</strong> be correctly set or the confirmation e-mails will not work.","fcpetition")?><br/>
		        <input type="text" name="petition_confirmurl" size="72" value="<?php echo $petition_confirmurl; ?>"/>
		</p>

		<p>
			<?php _e("Please enter the address which the confirmation e-mail will appear to be sent from. Any replies to the confirmation e-mail will be directed to this address. This <strong>must</strong> follow the same format as the example address.","fcpetition")?><br/>
			<input type="text" name="petition_from" value="<?php echo $petition_from; ?>" size="72"/>
		</p>
		<p>
			<?php _e("Please enter the maximum number of signatures to be displayed","fcpetition")?><br/>
			<input type="text" name="petition_maximum" value="<?php echo $petition_maximum; ?>"/>
		</p>
		<p>
			<?php _e("Allow signatories to leave a comment","fcpetition")?>
			<input type="checkbox" name="petition_comments" value="Y" <?php echo ($petition_comments=='Y')?'checked':'';?>>
		</p>
		<p>
			<?php _e("Enable Petition","fcpetition")?>
			<input type="checkbox" name="petition_enabled" value="Y" <?php echo ($petition_enabled=='Y')?'checked':'';?>>
		</p>
			<p class="submit">
			<input type="submit" name="Submit" value="<?php _e("Update Options","fcpetition")?>" />
		</p>
		</form>
			<p>Written by James Davis and licensed under the GNU GPL. For assistance please visit this plugin's <a href="http://www.freecharity.org.uk/wordpress-petition-plugin/">web page</a>.

		</p>
	    <?php
    echo "</div>";

}
?>
