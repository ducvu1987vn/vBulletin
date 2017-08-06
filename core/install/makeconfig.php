<?php

error_reporting(E_ALL & ~E_NOTICE);

define('VERSION', '5.0.0');
define('THIS_SCRIPT', 'makeconfig.php');
define('VB_AREA', 'tools');
define('VB_ENTRY', 1);

$core = realpath(dirname(__FILE__) . '/../');
if (file_exists($core . '/includes/init.php'))
{ // need to go up a single directory, we must be in includes / admincp / modcp / install
	chdir($core);
}
else
{
	die('Please place this file within the "core/admincp" / "core/install" folder');
}

// define current directory
if (!defined('CWD'))
{
	define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));
}
if (!class_exists('vB')) {
	require_once(CWD . '/vb/vb.php');
}
vB::init();

// #############################################################################

// set the style folder
if (empty($options['cpstylefolder']))
{
	$options['cpstylefolder'] = 'vBulletin_5_Default';
}
// set the version
$options['templateversion'] = VERSION;

$basePath = dirname(__FILE__) . str_repeat(DIRECTORY_SEPARATOR . '..', 2);
$makeConfig = array(
	'frontend' => array(
		'source' => realpath(implode(DIRECTORY_SEPARATOR, array($basePath, 'config.php.bkp'))),
		'dest' => realpath(implode(DIRECTORY_SEPARATOR, array($basePath, 'config.php'))),
		'fields' => array(
			'baseurl' => array('name' => 'Base URL:', 'description' => 'Usually http://www.domain.com or http://www.domain.com/forum'),
			'cookie_prefix' => array('name' => 'Cookie Prefix:', 'description' => 'Default: bb'),
		),
	),
	'backend' => array(
		'source' => realpath(implode(DIRECTORY_SEPARATOR, array($basePath, 'core', 'includes', 'config.php.new'))),
		'dest' => realpath(implode(DIRECTORY_SEPARATOR, array($basePath, 'core', 'includes', 'config.php'))),
		'fields' => array(
			'Database|dbtype' => array('name' => 'Database Type:', 'description' => 'Default: mysql'),
			'Database|dbname' => array('name' => 'Database Name:', 'description' => 'Enter your database name'),
			'Database|tableprefix' => array('name' => 'Table Prefix:', 'description' => 'Optional Table Prefix (OK to leave blank.)'),
			'Database|technicalemail' => array('name' => 'Technical Email:', 'description' => 'Database errors will be emailed to this address'),
			'MasterServer|servername' => array('name' => 'Database Server Name:', 'description' => 'The server name of your database server'),
			'MasterServer|port' => array('name' => 'Database Port #:', 'description' => 'Port of database server'),
			'MasterServer|username' => array('name' => 'Database Username:', 'description' => 'Username to log into database server'),
			'MasterServer|password' => array('type' => 'password', 'name' => 'Database Password:', 'description' => 'Password for database username (no single-quotes allowed)'),
			'Misc|admincpdir' => array('name' => 'Admin CP Directory:', 'description' => 'Default: admincp'),
			'Misc|modcpdir' => array('name' => 'Mod CP Directory:', 'description' => 'Default: modcp'),
			'cookie_prefix' => array('path' => 'Misc|cookieprefix', 'name' => 'Cookie Prefix:', 'description' => 'Default: bb'),
		)
	),
);


if (isset($_REQUEST['submit']) AND $_REQUEST['submit'] == 'Create Files')
{
	$errors = array();
	foreach ($makeConfig AS $component => $componentInfo)
	{
		$configContent = file_get_contents($componentInfo['source']);
		if ($configContent == false)
		{
			die("Error - Could not open $component config file.");
		}

		foreach ($componentInfo['fields'] AS $field => $fieldInfo)
		{
			$fieldPath = isset($fieldInfo['path']) ? $fieldInfo['path'] : $field;
			
			$find = '/^\$config\[\'' . implode("'\]\['", explode('|', $field)) . "'\].*$/m";
			$replace = '$config[\'' . implode("']['", explode('|', $field)) . "'] = '{$_POST[$field]}';" . PHP_EOL;

			$configContent = preg_replace($find, $replace, $configContent);
		}

		if (!file_put_contents($componentInfo['dest'], $configContent))
		{
			$errors[] = 'Was not able to write to the file ' . $componentInfo['dest'] . '. Please check your write permissions and try again.';
		}
	}

	if (empty($errors))
	{
		$selfdelete = '<br /><br />Click this <a href="makeconfig.php?submit=self_delete">link</a> to automatically delete makeconfig.php and begin the install script. Otherwise close this page. <br />';

		die("<br />File Creation Complete.<br /><br />Delete <strong>makeconfig.php</strong> file now, then begin installation.$selfdelete");
	}
	else
	{
		die('<br />There was an error:<ul><li>' . implode('</li><li>', $errors) . '</li></ul><br />Please <a href="makeconfig.php">go back</a> and try again.');
	}
}
elseif (isset($_REQUEST['submit']) AND $_REQUEST['submit'] == 'self_delete')
{
	if (unlink('makeconfig.php'))
	{
		if (file_exists(($makeConfig['frontend']['dest'])))
		{
			require_once($makeConfig['frontend']['dest']);
			header("location: {$config['baseurl_core']}/install/install.php");
		}
		else
		{
			$install_path = (empty($_SERVER['HTTPS']) ? "http://" : "https://") . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
			$install_path = str_replace('makeconfig.php', 'install.php', $install_path);
			header("location: {$install_path}");
		}
		exit;
	}
	else
	{
		die("Self-delete failed. Please delete makeconfig.php manually.");
	}
}

// Prepare to load and display config info
$config = $fields = array();
$caution = '';
foreach ($makeConfig AS $component => $componentInfo)
{
	if (file_exists($componentInfo['source']))
	{
		// load default values
		require_once($componentInfo['source']);
	}

	if (file_exists($componentInfo['dest']))
	{
		$caution .= "Warning $component already exists. <br />";

		// load existing values
		require_once($componentInfo['dest']);
	}

	foreach ($componentInfo['fields'] AS $field => $info)
	{
		$info['class'] = (count($fields) % 2) ? '' : 'alt2';

		if (!isset($info['type']))
		{
			$info['type'] = 'text';
		}

		$info['value'] = '';
		$fields[$field] = isset($fields[$field]) ? array_merge($info, $fields[$field]) : $info;
	}
}

function fetchCurrentConfigValue($fieldName, $config)
{
	if (empty($config))
	{
		return '';
	}
	else
	{
		$tmp = $config;
		$field_path = explode('|', $fieldName);
		foreach ($field_path as $p)
		{
			if (isset($tmp[$p]))
				if (is_array($tmp[$p]))
				{
					$tmp = $tmp[$p];
				}
				else
				{
					return $tmp[$p];
				}
			else
			{
				return '';
			}
		}
	}
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>VB5 - Build Configuration</title>
		<style type="text/css">
			body,td,th {
				color: #000;
			}
			body {
				background-color: #DDD;
			}
			a:link {
				color: #00F;
			}
			a:visited {
				color: #00F;
			}
			a:hover {
				color: #F00;
			}
			a:active {
				color: #F00;
			}
			.maindiv {
				width:800px;
				border-width:1px;
				border-color: white;
				background-color:white;
				margin-left:auto;
				margin-right:auto;
				padding:4px;
				font-family: "Lucida Sans Unicode", "Lucida Grande", sans-serif;
				font-size: 10pt;
			}
			.infobar {
				background-color:rgb(1,55,79);
				color:white;
				font-size:150%;
				margin-top:15px;
				margin-right:-2px;
				margin-left:-2px;
				padding:4px;
			}
			.maincontent {
				padding:4px;
			}
			.alt2 {
				background-color: #DFE6E6;
			}
			.maintable {
				border-collapse:collapse;
				border-spacing: 1px;
				width:100%;
				border: 2px rgb(1,55,79) inset;
			}
			td {
				padding:4px;
			}
			.settingname {
				font-size: 12px;
				font-weight: bold;
			}
			.settingdesc {
				font-size: 11px;
			}
			.filewarning {
				font-weight: bold;
				color: #F00;
			}
		</style>
		<script language="javascript">
			function valForm()
			{
				bstring = new String;
				bstring = document.forms['buildform'].baseurl.value;
				if (!bstring)
				{
					alert ("Base URL must be a valid URL and not end in /");
					return false;
				}
				if (bstring.charAt(bstring.length - 1) == "/") 
				{
					alert ("Base URL must not end in /");
					return false;
				}
				cstring = new String;
				cstring = document.forms['buildform'].coreurl.value;
				cend = new String;
				cend = cstring.substr(cstring.length-5,5);
				if(cend != '/core')
				{
					alert ("Core URL must end in /core");
					return false;
				}
				if (document.forms['buildform'].admincp.value != 'admincp')
					alert("Reminder: You must manually rename the admincp directories to match your custom value.");
				if (document.forms['buildform'].modcp.value != 'modcp')
					alert("Reminder: You must manually rename the modcp directory to match your custom value.");  
				if (!document.forms['buildform'].dbname.value)
				{
					alert ("You must enter a Database Name");
					return false;
				}
				if (!document.forms['buildform'].technicalemail.value)
				{
					var echeck = confirm("If you do not enter an email address you will not be notified of database errors. Support will require a copy of any database error if you run into trouble. Press cancel if you want to enter an email address, otherwise press OK to continue.");
					if (echeck == false)
						return false; 
				}
				if (!document.forms['buildform'].servername.value)
				{
					alert ("You must enter a Database Server Name");
					return false;
				}
				if (!document.forms['buildform'].username.value)
				{
					alert ("You must enter a Database Username");
					return false;
				}
				if (document.forms['buildform'].password.value.indexOf("'") != -1)
				{
					alert ("Passwords cannot contain the single-quote character (')");
					return false;
				}
			}
		</script>
	</head>
	<body onload="updatecore()">

		<div class="maindiv">
			<img src="../../images/misc/vbulletin5_logo.png" width="171" height="42" alt="vBulletin 5" /> <br />
			<div class="infobar">
				vBulletin 5 Configuation Builder
			</div>
			<br />
			<div class="maincontent">
				<form action="makeconfig.php" method="post" name="buildform" onsubmit="return valForm()" >
					<p>Please fill out the following fields to build your configuration data. This utility will auto-create a config.php file in the base directory and in your core/includes/ directory.</p>
					<p>If you require any advanced settings you must manually edit the config.php files yourself. See the install instructions for help.<br />
						<br />
						<span class="filewarning"><?php echo $caution ?></span> </p>
					<table border="1" class="maintable">
						<?php foreach ($fields AS $fieldName => $info): ?>
							<tr class="<?php echo $info['class'] ?>">
								<td width="50%">
									<span class="settingname"><?php echo $info['name'] ?></span><br /> 
									<span class="settingdesc"><?php echo $info['description'] ?></span>
								</td>
								<td><input type="<?php echo $info['type'] ?>" name="<?php echo $fieldName ?>" id="<?php echo $fieldName ?>" size="50" value="<?php echo $info['value'] ?>" /></td>
							</tr>
						<?php endforeach; ?>
					</table>
					<br />
					<div align="center">
						<input name="submit" type="submit" value="Create Files" /> &nbsp; <input name="reset" type="reset" value="Reset" />
					</div>

				</form>
			</div>
		</div>

	</body>
</html>
