<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Deletes all the associated data for a specific from the database.
* Only known master (volatile) data is removed. For example, customized versions
* of the templates are left.
*
* @param	string	Product ID to delete
* @param	string	Whether the deletion needs to work on a 3.5 DB (for vB upgrade scripts)
* @param	string	True if you are calling this for a product upgrade. Old master templates will be moved instead of removed.
*/
function delete_product($productid, $compliant_35 = false, $for_product_upgrade = false)
{
	$assertor = vB::getDbAssertor();
	$options = vB::getDatastore()->getValue('options');

	$assertor->delete('product', array('productid' => $productid));
	$assertor->delete('productcode', array('productid' => $productid));
	$assertor->delete('vBForum:phrase', array('product' => $productid, 'languageid' => -1));
	$assertor->delete('phrasetype', array('product' => $productid));
	$assertor->delete('template', array('product' => $productid, 'styleid' => -10));
	$assertor->delete('setting', array('product' => $productid, 'volatile' => 1));
	$assertor->delete('settinggroup', array('product' => $productid, 'volatile' => 1));
	$assertor->delete('vBForum:adminhelp', array('product' => $productid, 'volatile' => 1));
	$assertor->delete('moderatorlog', array('product' => $productid));

	if (!$compliant_35)
	{
		$assertor->delete('productdependency', array('productid' => $productid));
		$assertor->delete('cron', array('product' => $productid, 'volatile' => 1));
		$assertor->delete('vBForum:faq', array('product' => $productid, 'volatile' => 1));
	}

	// Stuff to do only if version is vBulletin 5+
	require_once(DIR . '/includes/adminfunctions_template.php');
	if (is_newer_version($options['templateversion'], '5.0.0 Alpha 1', true))
	{
		$assertor->delete('hook', array('product' => $productid));

		// Dont zap these on an upgrade.
		if (!$for_product_upgrade)
		{
			$assertor->delete('widget', array('product' => $productid));
			$assertor->delete('widgetdefinition', array('product' => $productid));
		}
	}

	if ($for_product_upgrade)
	{
		$assertor->assertQuery('removePackageTemplate', array('productid' => $productid));
		$assertor->update('template', array('styleid' => -10), array('product' => $productid, 'styleid' => -1));
	}
	else
	{
		$assertor->delete('template', array('product' => $productid, 'styleid' => -1));

		$ids = array();
		if (!$compliant_35)
		{
			$types = $assertor->getRows('removePackageTypesFetch', array('productid' => $productid));

			foreach ($types AS $type)
			{
				$ids[] = $type['contenttypeid'];
			}

			if (!empty($ids))
			{
				foreach ($ids AS $TypeId)
				{
					vB_Library::instance('Content_Attach')->zapAttachmentType($TypeId);
				}
			}
		}
	}

	return true;
}

function get_product_export_xml($productid)
{
	$assertor = vB::getDbAssertor();

	//	Set up the parent tag
	$product_details = $assertor->getRow('product', array('productid' => $productid));

	if (!$product_details)
	{
		throw new vB_Exception_AdminStopMessage('invalid_product_specified');
	}

	$xml = new vB_XML_Builder();

	// ############## main product info
	$xml->add_group(
		'product', array(
		'productid' => strtolower($product_details['productid']),
		'active' => $product_details['active']
	)); // Parent for product

	$xml->add_tag('title', $product_details['title']);
	$xml->add_tag('description', $product_details['description']);
	$xml->add_tag('version', $product_details['version']);
	$xml->add_tag('url', $product_details['url']);
	$xml->add_tag('versioncheckurl', $product_details['versioncheckurl']);

	// ############## dependencies
	$product_dependencies = $assertor->assertQuery('productdependency',
		array('productid' => $productid),
		array('field' => array('dependencytype', 'parentproductid', 'minversion'),
			'direction' => array(vB_dB_Query::SORT_ASC, vB_dB_Query::SORT_ASC, vB_dB_Query::SORT_ASC)
		)
	);

	$xml->add_group('dependencies');
	while ($product_dependencies AND $product_dependencies->valid())
	{
		$product_dependency = $product_dependencies->current();
		$deps = array('dependencytype' => $product_dependency['dependencytype']);
		if ($product_dependency['dependencytype'] == 'product')
		{
			$deps['parentproductid'] = $product_dependency['parentproductid'];
		}
		$deps['minversion'] = $product_dependency['minversion'];
		$deps['maxversion'] = $product_dependency['maxversion'];

		$xml->add_tag('dependency', '', $deps);
		$product_dependencies->next();
	}
	unset($product_dependency);

	$xml->close_group();

	// ############## install / uninstall codes
	$productcodes = $assertor->getRows('productcode', array('productid' => $productid));
	$xml->add_group('codes');

	$productcodes_grouped = array();
	$productcodes_versions = array();
	foreach ($productcodes AS $productcode)
	{
		// have to be careful here, as version numbers are not necessarily unique
		$productcodes_versions["$productcode[version]"] = 1;
		$productcodes_grouped["$productcode[version]"][] = $productcode;
	}

	$productcodes_versions = array_keys($productcodes_versions);
	usort($productcodes_versions, 'version_sort');

	foreach ($productcodes_versions AS $version)
	{
		foreach ($productcodes_grouped["$version"] AS $productcode)
		{
			$xml->add_group('code', array('version' => $productcode['version']));
				$xml->add_tag('installcode', $productcode['installcode']);
				$xml->add_tag('uninstallcode', $productcode['uninstallcode']);
			$xml->close_group();
		}
	}

	$xml->close_group();

	// ############## templates
	$gettemplates = $assertor->assertQuery('template',
		array('product' => $productid, 'styleid' => -1),
		'title'
	);

	$xml->add_group('templates');
	while ($gettemplates AND $gettemplates->valid())
	{
		$template = $gettemplates->current();
		if (is_newer_version($template['version'], $product_details['version']))
		{
			// version in the template is newer than the version of the product,
			// which probably means it's using the vB version
			$template['version'] = $product_details['version'];
		}

		$xml->add_tag('template', ($template['templatetype'] == 'template') ? $template['template_un'] : $template['template'], array(
			'name' => htmlspecialchars($template['title']),
			'templatetype' => $template['templatetype'],
			'date' => $template['dateline'],
			'username' => $template['username'],
			'version' => htmlspecialchars_uni($template['version'])
		), true);

		$gettemplates->next();
	}

	$xml->close_group();

	// ############## Stylevars
	$stylevarinfo = get_stylevars_for_export($productid, -1);
	$stylevar_cache = $stylevarinfo['stylevars'];
	$stylevar_dfn_cache = $stylevarinfo['stylevardfns'];


	$xml->add_group('stylevardfns');
	foreach ($stylevar_dfn_cache AS $stylevargroupname => $stylevargroup)
	{
		$xml->add_group('stylevargroup', array('name' => $stylevargroupname));
		foreach($stylevargroup AS $stylevar)
		{
			$xml->add_tag('stylevar', '',
				array(
					'name' => htmlspecialchars($stylevar['stylevarid']),
					'datatype' => $stylevar['datatype'],
					'validation' => base64_encode($stylevar['validation']),
					'failsafe' => base64_encode($stylevar['failsafe'])
				)
			);
		}
		$xml->close_group();
	}
	$xml->close_group();

	$xml->add_group('stylevars');
	foreach ($stylevar_cache AS $stylevarid => $stylevar)
	{
		$xml->add_tag('stylevar', '',
			array(
				'name' => htmlspecialchars($stylevar['stylevarid']),
				'value' => base64_encode($stylevar['value'])
			)
		);
	}
	$xml->close_group();

	// ############## hooks
	$xml->add_group('hooks');

	$hooks = vB_Api::instanceInternal("Hook")->getHookList(array('hookname'), array('product' => $productid));

	foreach ($hooks AS $hook)
	{
		$xml->add_group('hook');
			$xml->add_tag('hookname', $hook['hookname']);
			$xml->add_tag('title', $hook['title']);
			$xml->add_tag('active', $hook['active']);
			$xml->add_tag('hookorder', $hook['hookorder']);
			$xml->add_tag('template', $hook['template']);
			$xml->add_tag('arguments', $hook['arguments']);
		$xml->close_group();
	}

	$xml->close_group();

	// ############## phrases
	$phrasetypes = vB_Api::instanceInternal('phrase')->fetch_phrasetypes(false);
	$phrases = array();
	$getphrases = $assertor->getRows('vBForum:phrase',
		array('languageid' => array(-1, 0), 'product' => $productid),
		array('languageid', 'fieldname', 'varname')
	);

	foreach ($getphrases AS $getphrase)
	{
		$phrases["$getphrase[fieldname]"]["$getphrase[varname]"] = $getphrase;
	}

	$xml->add_group('phrases');

	// make sure the phrasegroups are in a reliable order
	ksort($phrases);

	foreach ($phrases AS $_fieldname => $typephrases)
	{
		// create a group for each phrase type that we have phrases for
		// then insert the phrases

		$xml->add_group('phrasetype', array('name' => $phrasetypes["$_fieldname"]['title'], 'fieldname' => $_fieldname));

		// make sure the phrases are in a reliable order
		ksort($typephrases);

		foreach ($typephrases AS $phrase)
		{
			$xml->add_tag('phrase', $phrase['text'], array(
				'name' => $phrase['varname'],
				'date' => $phrase['dateline'],
				'username' => $phrase['username'],
				'version' => htmlspecialchars_uni($phrase['version'])
			), true);
		}

		$xml->close_group();
	}

	$xml->close_group();

	// ############## options
	$setting = array();
	$settinggroup = array();
	$groups = $assertor->getRows('settinggroup', array('volatile' => 1), array('displayorder', 'grouptitle'));
	foreach ($groups AS $group)
	{
		$settinggroup["$group[grouptitle]"] = $group;
	}

	ksort($settinggroup);

	$options = $assertor->getRows('setting', array('product' => $productid, 'volatile' => 1), array('displayorder', 'varname'));
	foreach ($options AS $row)
	{
		$setting["$row[grouptitle]"][] = $row;
	}

	$xml->add_group('options');

	foreach ($settinggroup AS $grouptitle => $group)
	{
		if (empty($setting["$grouptitle"]))
		{
			continue;
		}

		// add a group for each setting group we have settings for
		$xml->add_group('settinggroup', array('name' => htmlspecialchars($group['grouptitle']), 'displayorder' => $group['displayorder']));

		ksort($setting["$grouptitle"]);

		foreach($setting["$grouptitle"] AS $set)
		{
			$arr = array('varname' => $set['varname'], 'displayorder' => $set['displayorder']);
			if ($set['advanced'])
			{
				$arr['advanced'] = 1;
			}
			$xml->add_group('setting', $arr);

			if ($set['datatype'])
			{
				$xml->add_tag('datatype', $set['datatype']);
			}
			if ($set['optioncode'] != '')
			{
				$xml->add_tag('optioncode', $set['optioncode']);
			}
			if ($set['validationcode'])
			{
				$xml->add_tag('validationcode', $set['validationcode']);
			}
			if ($set['defaultvalue'] !== '')
			{
				$xml->add_tag('defaultvalue', $set['defaultvalue']);
			}
			if ($set['blacklist'])
			{
				$xml->add_tag('blacklist', 1);
			}
			if ($set['ispublic'])
			{
				$xml->add_tag('public', 1);
			}

			$xml->close_group();
		}

		$xml->close_group();
	}

	$xml->close_group();

	// ############## admin help
	$help_topics_results = $assertor->getRows('vBForum:adminhelp', array('product' => $productid, 'volatile' => 1), array('script', 'action', 'displayorder', 'optionname'));
	$help_topics = array();
	foreach ($help_topics_results AS $help_topic)
	{
		$help_topics["$help_topic[script]"][] = $help_topic;
	}

	ksort($help_topics);

	$xml->add_group('helptopics');

	foreach ($help_topics AS $script => $script_topics)
	{
		$xml->add_group('helpscript', array('name' => $script));
		foreach ($script_topics AS $topic)
		{
			$attr = array('disp' => $topic['displayorder']);
			if ($topic['action'])
			{
				$attr['act'] = $topic['action'];
			}
			if ($topic['optionname'])
			{
				$attr['opt'] = $topic['optionname'];
			}
			$xml->add_tag('helptopic', '', $attr);
		}
		$xml->close_group();
	}

	$xml->close_group();

	// ############## Cron entries
	$cron_results = $assertor->getRows('cron', array(
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'product', 'value' => $productid, vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_EQ),
				array('field' => 'volatile', 'value' => 1, vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_EQ),
				array('field' => 'varname', 'value' => '', vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_NE)
			)
		)
	);

	$xml->add_group('cronentries');
	foreach ($cron_results AS $cron)
	{
		$minutes = unserialize($cron['minute']);
		if (!is_array($minutes))
		{
			$minutes = array();
		}

		$xml->add_group('cron', array(
			'varname' => $cron['varname'],
			'active' => $cron['active'],
			'loglevel' => $cron['loglevel']
		));
		$xml->add_tag('filename', $cron['filename']);
		$xml->add_tag('scheduling', '', array(
			'weekday' => $cron['weekday'],
			'day' => $cron['day'],
			'hour' => $cron['hour'],
			'minute' => implode(',', $minutes)
		));
		$xml->close_group();
	}

	$xml->close_group();

	$faq_results = $assertor->getRows('vBForum:faq', array('product' => $productid, 'volatile' => 1), 'faqname');
	$xml->add_group('faqentries');
	foreach ($faq_results AS $faq)
	{
		$xml->add_tag('faq', '', array(
			'faqname' => $faq['faqname'],
			'faqparent' => $faq['faqparent'],
			'displayorder' => $faq['displayorder'],
		));
	}

	$xml->close_group();

	// ############## widgets
	$xml->add_group('widgets');

	$widgets = vB_Api::instanceInternal("Widget")->getWidgetList(array('product' => $productid));

	foreach ($widgets AS $widget)
	{
		$xml->add_group('widget');

		$xml->add_tag('title', $widget['title']);
		$xml->add_tag('template', $widget['template']);
		$xml->add_tag('admintemplate', $widget['admintemplate']);
		$xml->add_tag('category', $widget['category']);
		$xml->add_tag('icon', $widget['icon']);
		$xml->add_tag('cloneable', $widget['cloneable']);
		$xml->add_tag('canbemultiple', $widget['canbemultiple']);
		$xml->add_tag('guid', $widget['guid']);

		$definitions = $widget['definitions'];

		$xml->add_group('definitions');

		if (is_array($definitions))
		{
			foreach ($definitions AS $definition)
			{
				$xml->add_group('definition');

				$xml->add_tag('name', $definition['name']);
				$xml->add_tag('label', $definition['label']);
				$xml->add_tag('field', $definition['field']);
				$xml->add_tag('isrequired', $definition['isrequired']);
				$xml->add_tag('isusereditable', $definition['isusereditable']);
				$xml->add_tag('displayorder', $definition['displayorder']);
				$xml->add_tag('validationtype', $definition['validationtype']);
				$xml->add_tag('validationmethod', $definition['validationmethod']);
				$xml->add_tag('defaultvalue', $definition['defaultvalue']);
				$xml->add_tag('data', $definition['data']);

				$xml->close_group();
			}
		}

		$xml->close_group(); // definitions

		$xml->close_group(); // widget
	}

	$xml->close_group();

	// ############## Finish up
	$xml->close_group();
	$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n" . $xml->output();

	unset($xml);
	return $doc;
}

/**
* Installs a product from the xml text
*
* This function depends on the vb class loader, which requires that the
* framework init is called.
*
* @return bool True if the product requires a template merge, false otherwise
*/
function install_product($xml, $allow_overwrite = false, $verbose = true)
{
	global $vbphrase;
	global $vbulletin;
	$assertor = vB::getDbAssertor();

	require_once(DIR . '/includes/class_bitfield_builder.php');
	require_once(DIR . '/includes/class_xml.php');

	//share some code with the main xml style import
	require_once(DIR . '/includes/adminfunctions_template.php');

	if ($verbose)
	{
		print_dots_start('<b>' . $vbphrase['importing_product'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');
	}

	$xmlobj = new vB_XML_Parser($xml);
	if ($xmlobj->error_no() == 1)
	{
		if ($verbose)
		{
			print_dots_stop();
		}
		throw new vB_Exception_AdminStopMessage('no_xml_and_no_path');
	}

	if(!$arr = $xmlobj->parse())
	{
		if ($verbose)
		{
			print_dots_stop();
		}
		throw new vB_Exception_AdminStopMessage(
			array('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line()));
	}

	// ############## general product information
	$info = array(
		'productid'       => substr(preg_replace('#[^a-z0-9_]#', '', strtolower($arr['productid'])), 0, 25),
		'title'           => $arr['title'],
		'description'     => $arr['description'],
		'version'         => $arr['version'],
		'active'          => $arr['active'],
		'url'             => $arr['url'],
		'versioncheckurl' => $arr['versioncheckurl']
	);

	if (!$info['productid'])
	{
		if ($verbose)
		{
			print_dots_stop();
		}
		throw new vB_Exception_AdminStopMessage('invalid_file_specified');
	}

	if (strtolower($info['productid']) == 'vbulletin')
	{
		if ($verbose)
		{
			print_dots_stop();
		}
		throw new vB_Exception_AdminStopMessage(array('product_x_installed_no_overwrite', 'vBulletin'));
	}

	// check for bitfield conflicts on install
	$bitfields = vB_Bitfield_Builder::return_data();
	if (!$bitfields)
	{
		$bfobj =& vB_Bitfield_Builder::init();
		if ($bfobj->errors)
		{
			if ($verbose)
			{
				print_dots_stop();
			}
			throw new vB_Exception_AdminStopMessage(array(
				'bitfield_conflicts_x',
				'<li>' . implode('</li><li>', $bfobj->errors) . '</li>'
			));
		}
	}

	// get system version info
	$system_versions = array(
		'php'       => PHP_VERSION,
		'vbulletin' => $vbulletin->options['templateversion'],
		'products'  => fetch_product_list(true)
	);

	$mysql_version = $assertor->getRow('mysqlVersion');
	$system_versions['mysql'] = $mysql_version['version'];

	// ############## import dependencies
	if (isset($arr['dependencies']['dependency']) AND is_array($arr['dependencies']['dependency']))
	{
		$dependencies =& $arr['dependencies']['dependency'];
		if (!isset($dependencies[0]))
		{
			$dependencies = array($dependencies);
		}

		$dependency_errors = array();
		$ignore_dependency_errors = array();

		// let's check the dependencies
		foreach ($dependencies AS $dependency)
		{
			// if we get an error, we haven't met this dependency
			// if we go through without a problem, we have automatically met
			// all dependencies for this "class" (mysql, php, vb, a specific product, etc)
			$this_dependency_met = true;

			// build a phrase for the version compats -- will look like (minver / maxver)
			if ($dependency['minversion'])
			{
				$compatible_phrase = construct_phrase(
					$vbphrase['compatible_starting_with_x'],
					htmlspecialchars_uni($dependency['minversion'])
				);
			}
			else
			{
				$compatible_phrase = '';
			}

			if ($dependency['maxversion'])
			{
				$incompatible_phrase = construct_phrase(
					$vbphrase['incompatible_with_x_and_greater'],
					htmlspecialchars_uni($dependency['maxversion'])
				);
			}
			else
			{
				$incompatible_phrase = '';
			}

			if ($compatible_phrase OR $incompatible_phrase)
			{
				$required_version_info = "($compatible_phrase";
				if ($compatible_phrase AND $incompatible_phrase)
				{
					$required_version_info .= ' / ';
				}
				$required_version_info .= "$incompatible_phrase)";
			}

			// grab the appropriate installed version string
			if ($dependency['dependencytype'] == 'product')
			{
				// group dependencies into types -- individual products get their own group
				$dependency_type_key = "product-$dependency[parentproductid]";

				// undocumented feature -- you can put a producttitle attribute in a dependency so the id isn't displayed
				$parent_product_title = (!empty($dependency['producttitle']) ? $dependency['producttitle'] : $dependency['parentproductid']);

				$parent_product = $system_versions['products']["$dependency[parentproductid]"];
				if (!$parent_product)
				{
					// required product is not installed
					$dependency_errors["$dependency_type_key"] = construct_phrase(
						$vbphrase['product_x_must_be_installed'],
						htmlspecialchars_uni($parent_product_title),
						$required_version_info
					);
					continue; // can't do version checks if the product isn't installed
				}
				else if ($parent_product['active'] == 0)
				{
					// product is installed, but inactive
					$dependency_errors["{$dependency_type_key}-inactive"] = construct_phrase(
						$vbphrase['product_x_must_be_activated'],
						htmlspecialchars_uni($parent_product_title)
					);
					$this_dependency_met = false;
					// allow version checks to continue
				}

				$sys_version_str = $parent_product['version'];
				$version_incompatible_phrase = 'product_incompatible_version_x_product_y';
			}
			else
			{
				$dependency_type_key = $dependency['dependencytype'];
				$parent_product_title = '';
				$sys_version_str = $system_versions["$dependency[dependencytype]"];
				$version_incompatible_phrase = 'product_incompatible_version_x_' . $dependency['dependencytype'];
			}

			// if no version string, we are trying to do an unsupported dep check
			if ($sys_version_str == '')
			{
				continue;
			}

			$sys_version = fetch_version_array($sys_version_str);


			// error if installed version < minversion
			if ($dependency['minversion'])
			{
				$dep_version = fetch_version_array($dependency['minversion']);

				for ($i = 0; $i <= 5; $i++)
				{
					if ($sys_version["$i"] < $dep_version["$i"])
					{
						// installed version is too old
						$dependency_errors["$dependency_type_key"] = construct_phrase(
							$vbphrase["$version_incompatible_phrase"],
							htmlspecialchars_uni($sys_version_str),
							$required_version_info,
							$parent_product_title
						);
						$this_dependency_met = false;
						break;
					}
					else if ($sys_version["$i"] > $dep_version["$i"])
					{
						break;
					}
				}
			}

			// error if installed version >= maxversion
			if ($dependency['maxversion'])
			{
				$dep_version = fetch_version_array($dependency['maxversion']);

				$all_equal = true;

				for ($i = 0; $i <= 5; $i++)
				{
					if ($sys_version["$i"] > $dep_version["$i"])
					{
						// installed version is newer than the maxversion
						$dependency_errors["$dependency_type_key"] = construct_phrase(
							$vbphrase["$version_incompatible_phrase"],
							htmlspecialchars_uni($sys_version_str),
							$required_version_info,
							$parent_product_title
						);
						$this_dependency_met = false;
						break;
					}
					else if ($sys_version["$i"] < $dep_version["$i"])
					{
						// not every part is the same and since we've got less we can exit
						$all_equal = false;
						break;
					}
					else if ($sys_version["$i"] != $dep_version["$i"])
					{
						// not every part is the same
						$all_equal = false;
					}
				}

				if ($all_equal == true)
				{
					// installed version is same as the max version, which is the first incompat version
					$dependency_errors["$dependency_type_key"] = construct_phrase(
						$vbphrase["$version_incompatible_phrase"],
						htmlspecialchars_uni($sys_version_str),
						$required_version_info,
						$parent_product_title
					);
					$this_dependency_met = false;
				}
			}

			if ($this_dependency_met)
			{
				// we met 1 dependency for this type -- this emulates or'ing together groups
				$ignore_dependency_errors["$dependency_type_key"] = true;
			}
		}

		// for any group we met a dependency for, ignore any errors we might
		// have gotten for the group
		foreach ($ignore_dependency_errors AS $dependency_type_key => $devnull)
		{
			unset($dependency_errors["$dependency_type_key"]);
		}

		if ($dependency_errors)
		{
			$dependency_errors = array_unique($dependency_errors);
			$dependency_errors = '<ol><li>' . implode('</li><li>', $dependency_errors) . '</li></ol>';

			if ($verbose)
			{
				print_dots_stop();
			}
			throw new vB_Exception_AdminStopMessage(
				array('dependencies_not_met_x', $dependency_errors));
		}
	}

	// look to see if we already have this product installed
	if ($existingprod = $assertor->getRow('product', array('productid' => $info['productid'])))
	{
		if (!$allow_overwrite)
		{
			if ($verbose)
			{
				print_dots_stop();
			}
			throw new vB_Exception_AdminStopMessage(
				array('product_x_installed_no_overwrite', $info['title']));
		}

		$active = $existingprod['active'];

		// not sure what we're deleting, so rebuild everything
		$rebuild = array(
			'templates' => true,
			'hooks'     => true,
			'phrases'   => true,
			'options'   => true,
			'cron'      => true
		);

		$installed_version = $existingprod['version'];
	}
	else
	{
		$active = ($info['active'] ? 1 : 0);

		$rebuild = array(
			'templates' => false,
			'hooks'     => false,
			'phrases'   => false,
			'options'   => false,
			'cron'      => false
		);

		$installed_version = null;
	}

	// ############## import install/uninstall code
	if (isset($arr['codes']['code']) AND is_array($arr['codes']['code']))
	{
		$codes =& $arr['codes']['code'];
		if (!isset($codes[0]))
		{
			$codes = array($codes);
		}

		// run each of the codes
		foreach ($codes AS $code)
		{
			// Run if: code version is * (meaning always run), no version
			//		previously installed, or if the code is for a newer version
			//		than is currently installed
			if ($code['version'] == '*' OR $installed_version === null OR is_newer_version($code['version'], $installed_version))
			{
				eval($code['installcode']);
			}
		}

		// Clear routes from datastore
		build_datastore('routes', serialize(array()), 1);

		//assume that the product may have installed content types and purge the content type cache
		vB_Cache::instance()->purge('vb_types.types');
	}

	// dependencies checked, install code run. Now clear out the old product info;
	// settings should be retained in memory already
	delete_product($info['productid'], false, true);

	if (is_array($codes))
	{
		// we've now run all the codes, if execution is still going
		// then it's going to complete fully, so insert the codes
		$productCodes = array();
		foreach ($codes AS $code)
		{
			/* insert query */
			$productCodes[] = array(
				'productid' => $info['productid'],
				'version' => $code['version'],
				'installcode' => $code['installcode'],
				'uninstallcode' => $code['uninstallcode']
			);
		}

		$assertor->insertMultiple('productcode',
			array('productid', 'version', 'installcode', 'uninstallcode'),
			$productCodes
		);
	}

	if (is_array($dependencies))
	{
		// dependencies met, codes run -- now we can insert the dependencies into the DB
		$productDependencies = array();
		foreach ($dependencies AS $dependency)
		{
			/* insert query */
			$productDependencies[] = array(
				'productid' => $info['productid'],
				'dependencytype' => $dependency['dependencytype'],
				'parentproductid' => $dependency['parentproductid'],
				'minversion' => $dependency['minversion'],
				'maxversion' => $dependency['maxversion']
			);
		}

		$assertor->insertMultiple('productdependency',
			array('productid', 'dependencytype', 'parentproductid', 'minversion', 'maxversion'),
			$productDependencies
		);
	}

	/* insert query */
	$assertor->insert('product', array(
		'productid' => $info['productid'],
		'title' => $info['title'],
		'description' => $info['description'],
		'version' => $info['version'],
		'active' => intval($active),
		'url' => $info['url'],
		'versioncheckurl' => $info['versioncheckurl']
	));

	// ############## import templates
	if (isset($arr['templates']['template']) AND is_array($arr['templates']['template']))
	{
		$querybits = array();
		$querytemplates = 0;

		$templates =& $arr['templates']['template'];
		if (!isset($templates[0]))
		{
			$templates = array($templates);
		}

		foreach ($templates AS $template)
		{
			$title = $template['name'];
			$template['template'] = $template['value'];
			$template['username'] = $template['username'];
			$template['templatetype'] = $template['templatetype'];
			$template['date'] = intval($template['date']);

			if ($template['templatetype'] != 'template')
			{
				// template is a special template
				$querybits[] = array(
					'styleid' => -1,
					'templatetype' => $template['templatetype'],
					'title' => $title,
					'template' => $template['template'],
					'template_un' => '',
					'dateline' => $template['date'],
					'username' => $template['username'],
					'version' => $template['version'],
					'product' => $info['productid']
				);
			}
			else
			{
				// template is a standard template
				$querybits[] = array(
					'styleid' => -1,
					'templatetype' => $template['templatetype'],
					'title' => $title,
					'template' => compile_template($template['value']),
					'template_un' => $template['template'],
					'dateline' => $template['date'],
					'username' => $template['username'],
					'version' => $template['version'],
					'product' => $info['productid']
				);
			}

			if (++$querytemplates % 20 == 0)
			{
				/*insert query*/
				$assertor->assertQuery('replaceValues', array('values' => $querybits, 'table' => 'template'));
				$querybits = array();
			}

			// Send some output to the browser inside this loop so certain hosts
			// don't artificially kill the script. See bug #34585
			if (VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
			{
				echo ' ';
				vbflush();
			}
		}

		// insert any remaining templates
		if (!empty($querybits))
		{
			/*insert query*/
			$assertor->assertQuery('replaceValues', array('values' => $querybits, 'table' => 'template'));
		}
		unset($querybits);

		$rebuild['templates'] = true;
	}

	// ############## import stylevars
	if (isset($arr['stylevardfns']['stylevargroup']) AND is_array($arr['stylevardfns']['stylevargroup']))
	{
		xml_import_stylevar_definitions($arr['stylevardfns'], $info['productid']);
	}

	if (!empty($arr['stylevars']) AND is_array($arr['stylevars']) AND is_array($arr['stylevars']['stylevar']))
	{
		xml_import_stylevars($arr['stylevars'], -1);
	}

	// ############## import hooks
	if (isset($arr['hooks']['hook']) AND is_array($arr['hooks']['hook']))
	{
		$hooks =& $arr['hooks']['hook'];

		if (!isset($hooks[0]))
		{
			$hooks = array($hooks);
		}

		foreach ($hooks AS $hook)
		{
			$hook['product'] = $info['productid'];
			$assertor->insert('hook', $hook);
		}

		$rebuild['hooks'] = true;
	}

	// ############## import phrases
	if (isset($arr['phrases']['phrasetype']) AND is_array($arr['phrases']['phrasetype']))
	{
		require_once(DIR . '/includes/adminfunctions_language.php');

		$master_phrasetypes = array();
		$master_phrasefields = array();
		foreach(vB_Api::instanceInternal('phrase')->fetch_phrasetypes(false) as $phrasetype)
		{
			$master_phrasefields["$phrasetype[fieldname]"] = true;
		}

		$phrasetypes =& $arr['phrases']['phrasetype'];
		if (!isset($phrasetypes[0]))
		{
			$phrasetypes = array($phrasetypes);
		}

		foreach ($phrasetypes AS $phrasetype)
		{
			if (empty($phrasetype['phrase']))
			{
				continue;
			}

			if ($phrasetype['fieldname'] == '' OR !preg_match('#^[a-z0-9_]+$#i', $phrasetype['fieldname'])) // match a-z, A-Z, 0-9,_ only
			{
				continue;
			}

			$fieldname = $master_phrasefields["$phrasetype[fieldname]"];

			if (!$fieldname)
			{
				$assertor->assertQuery('installProductPhraseTypeInsert', array(
					'fieldname' => $phrasetype['fieldname'],
					'title' => $phrasetype['name'],
					'editrows' => 3,
					'product' => $info['productid']
				));

				// need to add the column to the language table as well
				$assertor->assertQuery('addLanguageFromPackage', array('fieldname' => $phrasetype['fieldname']));
			}

			$phrases =& $phrasetype['phrase'];
			if (!isset($phrases[0]))
			{
				$phrases = array($phrases);
			}

			$sql = array();

			foreach ($phrases AS $phrase)
			{
				$sql[] = array(
					'languageid' => -1,
					'fieldname' => $phrasetype['fieldname'],
					'varname' => $phrase['name'],
					'text' => $phrase['value'],
					'product' => $info['productid'],
					'username' => $phrase['username'],
					'dateline' => $phrase['date'],
					'version' => $phrase['version']
				);
			}

			/*insert query*/
			$assertor->assertQuery('replaceValues', array('values' => $sql, 'table' => 'phrase'));
		}

		$rebuild['phrases'] = true;
	}

	// ############## import settings
	if (isset($arr['options']['settinggroup']) AND is_array($arr['options']['settinggroup']))
	{
		$settinggroups =& $arr['options']['settinggroup'];
		if (!isset($settinggroups[0]))
		{
			$settinggroups = array($settinggroups);
		}

		foreach ($settinggroups AS $group)
		{
			if (empty($group['setting']))
			{
				continue;
			}

			// create the setting group if it doesn't already exist
			/*insert query*/
			$assertor->assertQuery('installProductSettingGroupInsert', array(
				'grouptitle' => $group['name'],
				'displayorder' => $group['displayorder'],
				'volatile' => 1,
				'product' => $info['productid']
			));

			$settings =& $group['setting'];
			if (!isset($settings[0]))
			{
				$settings = array($settings);
			}

			$setting_bits = array();

			foreach ($settings AS $setting)
			{
				if (isset($vbulletin->options["$setting[varname]"]))
				{
					$newvalue = $vbulletin->options["$setting[varname]"];
				}
				else
				{
					$newvalue = $setting['defaultvalue'];
				}

				$setting_bits[] = array(
					'varname' => $setting['varname'],
					'grouptitle' => $group['name'],
					'value' => trim($newvalue),
					'defaultvalue' => trim($setting['defaultvalue']),
					'datatype' => trim($setting['datatype']),
					'optioncode' => $setting['optioncode'],
					'displayorder' => $setting['displayorder'],
					'advanced' => intval($setting['advanced']),
					'volatile' => 1,
					'validationcode' => $setting['validationcode'],
					'blacklist' => $setting['blacklist'],
					'ispublic' => intval($setting['public']),
					'product' => $info['productid']
				);
			}

			/*insert query*/
			$assertor->assertQuery('replaceValues', array('values' => $setting_bits, 'table' => 'setting'));
		}

		$rebuild['options'] = true;
	}

	// ############## import admin help
	if (isset($arr['helptopics']['helpscript']) AND is_array($arr['helptopics']['helpscript']))
	{
		$help_scripts =& $arr['helptopics']['helpscript'];
		if (!isset($help_scripts[0]))
		{
			$help_scripts = array($help_scripts);
		}

		foreach ($help_scripts AS $help_script)
		{
			// Deal with single entry
			if (!is_array($help_script['helptopic'][0]))
			{
				$help_script['helptopic'] = array($help_script['helptopic']);
			}

			$help_sql = array();
			foreach ($help_script['helptopic'] AS $topic)
			{
				$helpsql[] = array(
					'script' => $help_script['name'],
					'action' => $topic['act'],
					'optionname' => $topic['opt'],
					'displayorder' => intval($topic['disp']),
					'volatile' => 1,
					'product' => $info['productid']
				);
			}

			if (!empty($helpsql))
			{
				/*insert query*/
				$assertor->assertQuery('replaceValues', array('values' => $helpsql, 'table' => 'adminhelp'));
			}
		}
	}

	// ############## import cron
	if (isset($arr['cronentries']['cron']) AND is_array($arr['cronentries']['cron']))
	{
		require_once(DIR . '/includes/functions_cron.php');

		$cron_entries =& $arr['cronentries']['cron'];
		if (!isset($cron_entries[0]))
		{
			$cron_entries = array($cron_entries);
		}

		foreach ($cron_entries AS $cron)
		{
			$cron['varname'] = preg_replace('#[^a-z0-9_]#i', '', $cron['varname']);
			if (!$cron['varname'])
			{
				continue;
			}

			$cron['active'] = ($cron['active'] ? 1 : 0);
			$cron['loglevel'] = ($cron['loglevel'] ? 1 : 0);

			$scheduling = $cron['scheduling'];
			$scheduling['weekday'] = intval($scheduling['weekday']);
			$scheduling['day'] = intval($scheduling['day']);
			$scheduling['hour'] = intval($scheduling['hour']);
			$scheduling['minute'] = explode(',', preg_replace('#[^0-9,-]#i', '', $scheduling['minute']));
			if (count($scheduling['minute']) == 0)
			{
				$scheduling['minute'] = array(0);
			}
			else
			{
				$scheduling['minute'] = array_map('intval', $scheduling['minute']);
			}

			/*insert query*/
			$cronSql[] = array(
				'weekday' => $scheduling['weekday'],
				'day' => $scheduling['day'],
				'hour' => $scheduling['hour'],
				'minute' => serialize($scheduling['minute']),
				'filename' => $cron['filename'],
				'loglevel' => $cron['loglevel'],
				'active' => $cron['active'],
				'varname' => $cron['varname'],
				'volatile' => 1,
				'product' => $info['productid']
			);
			$cronid = $assertor->assertQuery('replaceValues', array('values' => $cronSql, 'table' => 'cron', 'returnId' => true));
			if ($cronid)
			{
				build_cron_item($cronid);
			}

			$rebuild['cron'] = true;
		}
	}

	// ############## import faq
	if (isset($arr['faqentries']['faq']) AND is_array($arr['faqentries']['faq']))
	{
		$faq_entries =& $arr['faqentries']['faq'];
		if (!isset($faq_entries[0]))
		{
			$faq_entries = array($faq_entries);
		}

		$sql = array();
		foreach ($faq_entries AS $faq)
		{
			$sql[] = array(
				'faqname' => $faq['faqname'],
				'faqparent' => $faq['faqparent'],
				'displayorder' => intval($faq['displayorder']),
				'volatile' => 1,
				'product' => $info['productid']
			);
		}

		if ($sql)
		{
			/*insert query*/
			$assertor->assertQuery('replaceValues', array('values' => $sql, 'table' => 'faq'));
		}
	}

	// ############## import widgets
	if (isset($arr['widgets']['widget']) AND is_array($arr['widgets']['widget']))
	{
		$widgets =& $arr['widgets']['widget'];
		if (!isset($widgets[0]))
		{
			$widgets = array($widgets);
		}

		foreach ($widgets AS $widget)
		{
			$existing = $assertor->getRow(
				'widget',
				array(
					'guid' => $widget['guid'],
					'product' => $info['productid']
				)
			);

			if ($existing['widgetid'])
			{
				$data = $widget + $existing;
				unset ($data['definitions']);
				$data['isthirdparty'] = 1;
				$data['product'] = $info['productid'];

				$result = $assertor->update(
					'widget',
					$data,
					array(
						'widgetid' => $existing['widgetid']
					)
				);

				$wdfs_old = $assertor->getRows(
					'widgetdefinition',
					array(
						'widgetid' => $existing['widgetid']
					)
				);

				$assertor->delete(
					'widgetdefinition',
					array(
						'widgetid' => $existing['widgetid']
					)
				);

				$index_old = array();
				foreach ($wdfs_old AS $key => $definition)
				{
					$index_old[$key] = $definition['name'];
				}

				$wdfs_new =& $widget['definitions']['definition'];

				if (!isset($wdfs_new[0]))
				{
					$wdfs_new = array($wdfs_new);
				}

				foreach ($wdfs_new AS &$definition)
				{
					if ($key_old = array_search($definition['name'], $index_old))
					{
						$definition = $definition + $wdfs_old[$key_old];
					}

					$data = $definition;
					$data['product'] = $info['productid'];
					$data['widgetid'] = $existing['widgetid'];

					$assertor->insert('widgetdefinition', $data);
				}
			}
			else
			{
				$data = $widget;
				$data['isthirdparty'] = 1;
				$data['product'] = $info['productid'];

				unset ($data['definitions']);
				$result = $assertor->insert('widget', $data);
				$widgetid = is_array($result) ? array_pop($result) : $result;

				if ($widgetid AND is_array($widget['definitions']['definition']))
				{
					$definitions =& $widget['definitions']['definition'];

					if (!isset($definitions[0]))
					{
						$definitions = array($definitions);
					}

					foreach ($definitions AS $definition)
					{
						$data = $definition;
						$data['widgetid'] = $widgetid;
						$data['product'] = $info['productid'];

						$assertor->insert('widgetdefinition', $data);
					}
				}
			}
		}
	}

	// Check if the hook system is disabled. If it is, enable it.
	if (!$vbulletin->options['enablehooks'])
	{
		$assertor->update('setting', array('value' => 1), array('varname' => 'enablehooks'));

		$rebuild['options'] = true;
	}

	// Now rebuild everything we need...
	if ($rebuild['hooks'])
	{
		vB_Api::instanceInternal("Hook")->buildHookDatastore();
	}

	if ($rebuild['templates'])
	{
		if ($error = build_all_styles(0, 0, '', false, $verbose))
		{
			return $error;
		}
	}

	if ($rebuild['phrases'])
	{
		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();
	}

	if ($rebuild['options'])
	{
		vB::getDatastore()->build_options();
	}

	if ($rebuild['cron'])
	{
		require_once(DIR . '/includes/functions_cron.php');
		build_cron_next_run();
	}

	build_product_datastore();

	// build bitfields to remove/add this products bitfields
	vB_Bitfield_Builder::save();

	if ($verbose)
	{
		print_dots_stop();
	}

	$info['need_merge'] = ($rebuild['templates'] AND $installed_version);
	return $info;
}

/**
* Used to do sorting of version number strings via usort().
* adminfunctions_template.php must be required before you use this!
*
* @param	string	Version number string 1
* @param	string	Version number string 2
*
* @return	integer	0 if the same, -1 if #1 < #2, 1 if #1 > #2
*/
function version_sort($a, $b)
{
	if ($a == $b)
	{
		return 0;
	}
	else if ($a == '*')
	{
		// * < non-*
		return -1;
	}
	else if ($b == '*')
	{
		// any non-* > *
		return 1;
	}

	return (is_newer_version($a, $b) ? 1 : -1);
}

/**
* Fetches an array of products dependent on a specific product, though whether
* this is a parent-child or child-parent relationship is determined based on
* the construction of the dependency list.
* If the parent is the key, this function will recursively find a list of children.
* If the child is the key, the function will recursively find a list of parents.
*
* @param	string	Product to find parents/children for
* @param	array	An array of dependencies to pull from in form [pid][] => pid
*
* @return	array	Array of children/parents
*/
function fetch_product_dependencies($productid, &$dependency_list)
{
	if (!is_array($dependency_list["$productid"]))
	{
		return array();
	}

	$list = array();
	foreach ($dependency_list["$productid"] AS $subproductid)
	{
		// only traverse this branch if we haven't done it before -- prevent infinte recursion
		if (!isset($list["$subproductid"]))
		{
			$list["$subproductid"] = $subproductid;
			$list = array_merge(
				$list,
				fetch_product_dependencies($subproductid, $dependency_list)
			);
		}
	}

	return $list;
}

/**
*	Setup the default permissions for the product.
*
*	This is primarily intended for vbsuite products and should be called from the
* the package install code.  It sets the default admincp permissions for the
* package.
*
*	@permission_field The field in the administrator table that stores the product permissions
* @permissions The permission bit field for the default.
* @userid The id of the logged in user -- if given the user will be granted access in addtion
* 	to administrators with product access.
*
*/
function setup_default_admin_permissions($permission_field, $permissions, $userid = null)
{
	global $vbulletin;

	$user = array();
	if ($userid)
	{
		$user = $vbulletin->db->query_first("
			SELECT administrator.*, IF(administrator.userid IS NULL, 0, 1) AS isadministrator,
				user.userid, user.username
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "administrator AS administrator ON(administrator.userid = user.userid)
			WHERE user.userid = " . intval($userid)
		);
	}

	//if we have a user logged in on the install, then give them admin rights to the product.
	if ($user)
	{
		if (!$user['isadministrator'])
		{
			// should this user have an administrator record??
			$userinfo = fetch_userinfo($user['userid']);
			cache_permissions($userinfo);
			if ($userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
			{
				$admindm =& datamanager_init('Admin', $vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
				$admindm->set('userid', $userinfo['userid']);
				$admindm->save();
				unset($admindm);
			}
		}

		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "administrator SET
				$permission_field = " . intval($permissions) . "
			WHERE userid = $user[userid]
		");
	}

	//otherwise, lets give access to anybody set up so they can install it.
	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "administrator SET
			$permission_field = " . intval($permissions) . "
		WHERE adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['canadminproducts']
	);
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 63715 $
|| ####################################################################
\*======================================================================*/
