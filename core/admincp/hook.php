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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 63804 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase, $vbulletin;
$phrasegroups = array('hooks');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_product.php');
require_once(DIR . '/includes/adminfunctions_template.php');

$assertor = vB::getDbAssertor();
$hook_api = vB_Api::instanceInternal('Hook');

// ######################## CHECK ADMIN PERMISSIONS #######################
// don't allow demo version or admin with no permission to administer hooks
if (is_demo_mode() OR !can_administer('canadminproducts'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
	'hookid' => vB_Cleaner::TYPE_UINT)
);

// ############################# LOG ACTION ###############################
log_admin_action(iif($vbulletin->GPC['hookid'] != 0, 'Hook id = ' . $vbulletin->GPC['hookid']));

// #############################################################################
// ########################### START MAIN SCRIPT ###############################
// #############################################################################

print_cp_header($vbphrase['hook_products_system']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

if (in_array($_REQUEST['do'], array('modify', 'edit', 'add', 'updateactive')))
{
	if (!$vbulletin->options['enablehooks'] OR defined('DISABLE_HOOKS'))
	{
		if (!$vbulletin->options['enablehooks'])
		{
			print_warning_table($vbphrase['hooks_disabled_options']);
		}
		else
		{
			print_warning_table($vbphrase['hooks_disable_config']);
		}
	}
}

// #############################################################################

if ($_POST['do'] == 'updateactive')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'order' => vB_Cleaner::TYPE_ARRAY_UINT,
		'active' => vB_Cleaner::TYPE_ARRAY_UINT,
	));

	$params = array();
	$hooks = $hook_api->getHookList();

	foreach ($hooks AS $hook)
	{
		$params[$hook['hookid']]['hookorder'] = $vbulletin->GPC['order'][$hook['hookid']];
		$params[$hook['hookid']]['active'] = (isset($vbulletin->GPC['active'][$hook['hookid']]) ? 1 : 0);
	}

	$hook_api->updateHookStatus($params);

	$_REQUEST['do'] = 'modify';
}

// #############################################################################

if ($_POST['do'] == 'kill')
{
	$hook_api->deleteHook($vbulletin->GPC['hookid']);

	print_stop_message2('deleted_hook_successfully', 'hook');
}

// #############################################################################

if ($_REQUEST['do'] == 'delete')
{
	print_delete_confirmation('hook', $vbulletin->GPC['hookid'], 'hook', 'kill');
}

// #############################################################################

if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'hookname'       => vB_Cleaner::TYPE_STR,
		'title'          => vB_Cleaner::TYPE_STR,
		'arguments'      => vB_Cleaner::TYPE_STR,
		'active'         => vB_Cleaner::TYPE_BOOL,
		'template'       => vB_Cleaner::TYPE_STR,
		'product'        => vB_Cleaner::TYPE_STR,
		'hookorder'      => vB_Cleaner::TYPE_UINT,
		'return'         => vB_Cleaner::TYPE_STR
	));

	if (substr($vbulletin->GPC['hookname'],0,1) == '#')
	{
		print_stop_message2('invalid_hook');
	}

	if (!$vbulletin->GPC['hookname'] OR !$vbulletin->GPC['title'] OR !$vbulletin->GPC['template'])
	{
		print_stop_message2('please_complete_required_fields');
	}

	$hookdata = array(
		'hookid' => $vbulletin->GPC['hookid'],
		'hookname' => $vbulletin->GPC['hookname'],
		'title' => $vbulletin->GPC['title'],
		'arguments' => $hook_api->encodeArguments($vbulletin->GPC['arguments']),
		'product' => $vbulletin->GPC['product'],
		'active' => $vbulletin->GPC['active'],
		'template' => $vbulletin->GPC['template'],
		'hookorder' => $vbulletin->GPC['hookorder'],
	);

	$hookid = $hook_api->saveHook($vbulletin->GPC['hookid'], $hookdata);

	// stuff to handle the redirect
	$args = array();
	if ($vbulletin->GPC['return'])
	{
		$args = array(
			'do' => 'edit',
			'hookid' => $hookid
		);
	}

	if ($vbulletin->GPC['hookid'])
	{
		print_stop_message2('updated_hook_successfully', 'hook', $args);
	}
	else
	{
		print_stop_message2('added_hook_successfully', 'hook', $args);
	}
}

// #############################################################################

if ($_REQUEST['do'] == 'edit' OR $_REQUEST['do'] == 'add')
{
	$products = fetch_product_list();
	$hooklocations = $hook_api->getXmlHooks();
	$hook = $hook_api->getHookInfo($vbulletin->GPC['hookid']);

	if (!$hook)
	{
		$hook = array(
			'active' => 1,
			'arguments' => '',
			'hookorder' => 10,
		);
	}

	$hook['arguments'] = $hook_api->decodeArguments($hook['arguments']);

	print_form_header('hook', 'update');
	construct_hidden_code('hookid', $hook['hookid']);

	if ($_REQUEST['do'] == 'add')
	{
		$heading = $vbphrase['add_new_hook'];
	}
	else
	{
		$heading = construct_phrase($vbphrase['edit_hook_x'], htmlspecialchars_uni($hook['title']));
	}

	print_table_header($heading);

	print_select_row($vbphrase['product'], 'product', fetch_product_list(), $hook['product'] ? $hook['product'] : 'vbulletin');
	print_yes_no_row($vbphrase['hook_is_active'].'<dfn>'.$vbphrase['hook_active_desc'].'</dfn>', 'active', $hook['active']);
	print_select_row($vbphrase['hook_location'].'<dfn>'.$vbphrase['hook_location_desc'].'</dfn>',
		'hookname',
		array_merge(array('' => $vbphrase['hook_select']), $hooklocations),
		$hook['hookname']
	);
	print_input_row($vbphrase['title'].'<dfn>'.$vbphrase['hook_title_desc'].'</dfn>', 'title', $hook['title'], 1, 60);
	print_input_row($vbphrase['hook_execution_order'].'<dfn>'.$vbphrase['hook_order_desc'].'</dfn>', 'hookorder', $hook['hookorder'], 1, 5);
	print_input_row($vbphrase['template_name'].'<dfn>'.$vbphrase['template_name_desc'].'</dfn>', 'template', $hook['template'], 1, 40);
	print_textarea_row(
		$vbphrase['hook_arguments'].'<dfn>'.$vbphrase['hook_arguments_desc'].'</dfn>',
		'arguments',
		htmlspecialchars($hook['arguments']),
		6, '45" style="width:80%',
		false,
		false,
		'ltr',
		false
	);

	if ($hook['foundproduct'] AND !$hook['productactive'])
	{
		print_description_row(construct_phrase($vbphrase['hook_inactive_due_to_product_disabled'], $products[$hook['product']]));
	}

	print_submit_row($vbphrase['save'], '_default_', 2, '', '<input type="submit" class="button" tabindex="1" name="return" value="'.$vbphrase['save_and_reload'].'" accesskey="e" />');
}

// #############################################################################

if ($_REQUEST['do'] == 'modify')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'sort' => vB_Cleaner::TYPE_NOHTML
	));

	$group_by = $vbulletin->GPC['sort'];
	$products = fetch_product_list(true);

	print_form_header('hook', 'updateactive');
	print_table_header($vbphrase['hooks_system'], 7);

	construct_hidden_code('sort', $group_by);

	switch ($group_by)
	{
		case 'hook':
		{
			$hooks = $hook_api->getHookList(array('hookname', 'hookorder', 'title'));

			print_cells_row(
				array(
					$vbphrase['title'],
					vB_Library_Admin::buildElementCell('', $vbphrase['product'], 0, false, 'hook.php', 'modify&amp;sort=product', vB::getCurrentSession()->get('sessionurl')),
					$vbphrase['hook_execution_order'],
					$vbphrase['template_name'],
					$vbphrase['arguments'],
					$vbphrase['active_gcpglobal'],
					$vbphrase['controls']
				),
				1
			);
		}
		break;

		case 'product':
		default:
		{
			$hooks = $hook_api->getHookProductList();

			print_cells_row(
				array(
					$vbphrase['title'],
					vB_Library_Admin::buildElementCell('', $vbphrase['hook_location'], 0, false, 'hook.php', 'modify&amp;sort=hook', vB::getCurrentSession()->get('sessionurl')),
					$vbphrase['hook_execution_order'],
					$vbphrase['template_name'],
					$vbphrase['arguments'],
					$vbphrase['active_gcpglobal'],
					$vbphrase['controls']
				),
				1
			);
		}
	}

	$prevgroup = '';

	foreach ($hooks AS $hook)
	{
		$product = $products[$hook['product']];
		if (!$product)
		{
			$product = array('title' => '<em>'.$hook['product'].'</em>', 'active' => 1);
		}
		else
		{
			$product['title'] = htmlspecialchars_uni($product['title']);
		}

		if ($group_by == 'hook')
		{
			if ($hook['hookname'] != $prevgroup)
			{
				$prevgroup = $hook['hookname'];
				print_description_row($vbphrase['hook_location'].' : ' . $hook['hookname'], 0, 7, 'tfoot', '" style="text-align: left;');
			}
		}
		else //if ($group_by == 'product')
		{
			if ($product['title'] != $prevgroup)
			{
				$prevgroup = $product['title'];
				print_description_row($vbphrase['product'].' : ' . $product['title'], 0, 7, 'tfoot', '" style="text-align: left;');
			}
		}

		if (!$product['active'])
		{
			$product['title'] = '<strike>'.$product['title'].'</strike>';
		}

		$title = htmlspecialchars_uni($hook['title']);
		$title = ($hook['active'] AND $product['active']) ? $title : "<strike>$title</strike>";

		print_cells_row(array(
			vB_Library_Admin::buildElementCell('hook'.$hook['hookid'], $title, 0, false, 'hook.php', 'edit&amp;hookid='.$hook['hookid'], vB::getCurrentSession()->get('sessionurl')),
			vB_Library_Admin::buildDisplayCell($group_by == 'hook' ? $product['title'] : $hook['hookname']),
			vB_Library_Admin::buildTextInputCell('order['.$hook['hookid'].']', $hook['hookorder'], 1, $title = 'Execution Order'),
			vB_Library_Admin::buildDisplayCell($hook['template']),
			vB_Library_Admin::buildDisplayCell($hook['arguments'] ? 'Yes' : 'No'),
			vB_Library_Admin::buildCheckboxCell('active['.$hook['hookid'].']', 1, 'hook'.$hook['hookid'], $hook['active'], false, false, false),
			vB_Library_Admin::buildElementCell('edit'.$hook['hookid'], '['.$vbphrase['edit'].']', 0, false, 'hook.php', 'edit&amp;hookid='.$hook['hookid'], vB::getCurrentSession()->get('sessionurl')).
			vB_Library_Admin::buildElementCell('delete'.$hook['hookid'], '['.$vbphrase['delete'].']', 0, false, 'hook.php', 'delete&amp;hookid='.$hook['hookid'], vB::getCurrentSession()->get('sessionurl')),
		));
	}

	print_submit_row($vbphrase['save_status'], false, 7);
	echo '<p align="center">' . vB_Library_Admin::buildElementCell('', '['.$vbphrase['add_new_hook'].']', 0, false, 'hook.php', 'add', vB::getCurrentSession()->get('sessionurl')) . '</p>';
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 63804 $
|| ####################################################################
\*======================================================================*/
