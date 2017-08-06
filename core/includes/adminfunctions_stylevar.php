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

// ###################### Validate stylevar values #######################
// local functions for validation handling non simple information
function sanitize_url( $url )
{
	$find =    array('<',    '>',  '"', ';');
	$replace = array('',     '',   '',  ''); // fill in URL encoded equivalent for those characters

	$url = str_replace($find, $replace, $url);
	return $url == '' ? 'none' : $url;
}

function sanitize_font_family( $string )
{
	$find =    array('<', '>', '"', ';');
	$replace = array('',  '',  '',  '');

	return str_replace($find, $replace, $string);
}

function validate_url( $string )
{
	// validate the string
	// return true if okay
	// trigger error if fail
}

function validate_numerics( $numerics )
{
	if ($numerics == floatval($numerics))
	{
		return true;
	}
	else
	{
		// trigger error
	}
}

function validate_repeats( $repeats )
{
	// check if the provided string matches an acceptable repeat format
	// return true if okay
	// trigger error if fail
}

function validate_font_family( $fontfamily )
{
	// check if it is "word comma word comma quote word space word quote comma word" format
	// if so, return sanatize_font_family( $fontfamily ) that removes the nasty stuff we don't want
	// if not, trigger error
}


/**
 * Enter description here...
 *
 * @return unknown
 */
function fetch_stylevar_datatypes_array()
{
	static $stylevar_datatypes = false;

	if (!is_array($stylevar_datatypes))
	{
		// TODO: change this to actually query the stylevar.datatype ENUM schema
		// SHOW COLUMNS FROM stylevar WHERE field =  "datatype";
		// Requires additional permission (SHOW) for MySQL user, not sure if it would be a good idea
		$stylevar_datatypes = array(
			'numeric' => 'numeric',
			'string' => 'string',
			'color' => 'color',
			'url' => 'url',
			'path' => 'path',
			'imagedir' => 'imagedir',
		);
	}

	return $stylevar_datatypes;
}

/**
 * Enter description here...
 *
 * @param 	boolean $addblank
 * @return array	units
 */
function fetch_css_units_array($addblank = false)
{
	static $css_units = false;

	if (!is_array($css_units))
	{
		// TODO: change this to actually query the stylevar.units ENUM schema
		// SHOW COLUMNS FROM stylevar WHERE field =  "units";
		// Requires additional permission (SHOW) for MySQL user, not sure if it would be a good idea
		$css_units = array(
			'%'  => '%',
			'px' => 'px',
			'pt' => 'pt',
			'em' => 'em',
			'ex' => 'ex',
			'pc' => 'pc',
			'in' => 'in',
			'cm' => 'cm',
			'mm' => 'mm',
		);
	}

	if ($addblank)
	{
		return array_merge(array('' => '&nbsp;'), $css_units);
	}
	else
	{
		return $css_units;
	}
}

/**
 * Enter description here...
 *
 * @return unknown
 */
function fetch_stylevars_array()
{
	global $vbulletin;
	static $stylevars = array();

	if (empty($stylevars))
	{
		if ($vbulletin->GPC['dostyleid'] > 0)
		{
			$parentlist = vB_Library::instance('Style')->fetchTemplateParentlist($vbulletin->GPC['dostyleid']);
			$parentlist = explode(',',trim($parentlist));
		}
		else
		{
			$parentlist = array('-1');
		}
		$stylevars_result = vB::getDbAssertor()->assertQuery('fetchStylevarsArray', array('parentlist' => $parentlist));
		foreach ($stylevars_result as $sv)
		{
			$sv['styleid'] = $sv['stylevarstyleid'];
			if (empty($stylevars[$sv['stylevargroup']][$sv['stylevarid']]['currentstyle']))
			{
				// Skip if Stylevar was already found as changed in the current style
				$stylevars[$sv['stylevargroup']][$sv['stylevarid']] = $sv;
				if ($sv['styleid'] == $vbulletin->GPC['dostyleid'])
				{
					// Stylevar was changed in the current style, no need to check for
					// customized stylevars in the parent styles after that.
					$stylevars[$sv['stylevargroup']][$sv['stylevarid']]['currentstyle'] = '1';
				}
			}
		}
	}

	// sort it so its nice and neat
	$to_return = array();
	$groups = array_keys($stylevars);
	natsort($groups);
	foreach($groups AS $group)
	{
		$stylevarids = array_keys($stylevars[$group]);
		natsort($stylevarids);
		foreach ($stylevarids AS $stylevarid)
		{
			// don't need to go any deeper, stylevar.styleid doesn't really matter in display sorting
			$to_return[$group][$stylevarid] = $stylevars[$group][$stylevarid];
		}
	}

	return $to_return;
}


/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/