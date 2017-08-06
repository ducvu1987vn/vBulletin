<?php

/**
 * vB_Api_Editor
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Editor extends vB_Api
{

	/*
	 * Smiley Locations
	 */
	protected $smilieImages = array();

	/*
	 * Smiley Titles
	 */
	protected $smilieDescriptions = array();

	/*
	 * Smiley Categories
	 */
	protected $smilieCategories = array();

	protected $smilieData = null;

	protected function __construct()
	{
		parent::__construct();
	}

	/** Returns the array of custom bbcode info
	*
	 *
	 *
	 */

	public function fetchCustomBbcode()
	{
		$bbcodeCache = vB::getDatastore()->get_value('bbcodecache');
		$data = array();
		if ($bbcodeCache)
		{
			foreach ($bbcodeCache AS $bbcode)
			{
				if ($bbcode['buttonimage'] != '')
				{
					$data[] = array(
						'title'       => $bbcode['title'],
						'bbcodetag'   => $bbcode['bbcodetag'],
						'buttonimage' => $bbcode['buttonimage'],
						'twoparams'   => $bbcode['twoparams'],
					);
				}
			}
		}

		return $data;
	}

	/**
	 * Returns the array of smilie info
	 *
	 * @return 	array	The icons
	 */
	public function fetchSmilies()
	{
		// if smilieData is set then addSmilie has also been called
		if ($this->smilieData !== null)
		{
			return $this->smilieData;
		}

		$this->smilieData = array();

		$options = vB::getDatastore()->get_value('options');

		if (!$options['wysiwyg_smtotal'])
		{
			return $this->smilieData;
		}

		$smilies = vB::get_db_assertor()->assertQuery('vBForum:fetchImagesSortedLimited',
			array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'table'                       => 'smilie',
				vB_dB_Query::PARAM_LIMITSTART => 0,
				vB_dB_Query::PARAM_LIMIT      => $options['wysiwyg_smtotal'],
			)
		);
		if ($smilies AND $smilies->valid())
		{
			foreach ($smilies AS $smilie)
			{
				$this->addSmilie($smilie);
			}

			$this->smilieData = array(
				'images'        => $this->smilieImages,
				'descriptions'  => $this->smilieDescriptions,
				'categories'    => $this->smilieCategories,
			);
		}

		return $this->smilieData;
	}

	/**
	 * Add smilie to array and build categories in the process
	 *
	 * @param	mixed	Output to write
	 */
	protected function addSmilie($smilie)
	{
		static $prevcat = '';

		$this->smilieImages[$smilie['smilieid']] = $smilie['smiliepath'];
		$this->smilieDescriptions[$smilie['smilieid']] = vB_String::htmlSpecialCharsUni($smilie['smilietext']);

		if ($prevcat != $smilie['category'])
		{
			$prevcat = $this->smilieCategories[$smilie['smilieid']] = $smilie['category'];
		}
		else
		{
			$this->smilieCategories[$smilie['smilieid']] = '';
		}
	}
}
