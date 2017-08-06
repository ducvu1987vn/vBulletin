<?php
// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cphome');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . "/include/viglink_utils.class.php");
chdir(dirname(__FILE__) . "/../../admincp");
require_once('./global.php');

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

class Viglink_AdminCP {
  const ACTION_RENDER = 1;
  const ACTION_UPDATE = 2;

  const PAGE_INTRO = 1;
  const PAGE_OPTIONS = 2;
  const PAGE_UNLINK = 3;

  private $page;
  private $action;

  private $utils;

  public function __construct($do) {
    $this->utils = new Viglink_Utils();
    $this->setPageAndAction($do);

    switch ($this->action) {
      case self::ACTION_UPDATE: $this->process(); break;
      case self::ACTION_RENDER: $this->render();  break;
    }
  }

  /**
   * Get the value for the "do" parameter
   */
  private function getDo($action = self::ACTION_RENDER, $page = NULL) {
    return $action . "," . (empty($page) ? $this->page : $page);
  }

  /**
   * Process form input
   */
  private function process() {
    global $vbulletin;

    switch($this->page) {
      case self::PAGE_INTRO:
        $vbulletin->input->clean_gpc('p', 'key', TYPE_NOTHTML);
        $key = $vbulletin->GPC['key'];
        if (!empty($key)) {
          $this->updateKey($key);
          $this->page = self::PAGE_OPTIONS;
        }
        break;

      case self::PAGE_UNLINK:
        $this->updateKey(NULL);
        $this->page = self::PAGE_INTRO;
        break;

      case self::PAGE_OPTIONS:
        $vbulletin->input->clean_array_gpc('p', array(
          'enabled' => TYPE_UINT,
          'usergroup_enabled' => TYPE_ARRAY_UINT
        ) );

        $usergroup_disabled = array();
        foreach ($vbulletin->GPC['usergroup_enabled'] as $id => $enabled) {
          if ((bool) $enabled === false) {
            $usergroup_disabled[] = $id;
          }
        }

        $vbulletin->db->query_write("
          UPDATE " . TABLE_PREFIX . "setting
          SET value = CASE varname
            WHEN 'viglink_enabled'
              THEN '" . $vbulletin->db->escape_string($vbulletin->GPC['enabled']) ."'
            WHEN 'viglink_lii_excluded_usergroups'
              THEN '" . $vbulletin->db->escape_string(json_encode($usergroup_disabled)) . "'
            ELSE value END
          WHERE varname IN
            ('viglink_enabled','viglink_lii_excluded_usergroups')
        ");
    }

    build_options();

    define('CP_REDIRECT', basename(__FILE__) . '?do=' . $this->getDo(self::ACTION_RENDER));
    print_stop_message('saved_settings_successfully');
  }

  /**
   * Render the page
   */
  private function render() {
    $this->renderHeader();
    $this->renderBody();
    $this->renderFooter();
  }

  /**
   * Render the page's body
   */
  private function renderBody() {
    switch($this->page) {
      case self::PAGE_INTRO:
        print_table_header($this->utils->getText('intro_header'));
        print_description_row($this->utils->getText('summary') . $this->utils->getText('description'));
        print_description_row($this->utils->getText('general_settings_header'), false, 2, 'thead');
        print_input_row($this->utils->getLabel('key'), "key", $this->utils->getOption('key'));
        print_submit_row();
        break;
      case self::PAGE_OPTIONS:
        $disabled_group_ids = json_decode($this->utils->getOption('lii_excluded_usergroups', '[]'));

        print_table_header($this->utils->getText('options_header'));
        print_description_row($this->utils->getText('summary'));
        print_description_row($this->utils->getText('general_settings_header'), false, 2, 'thead');
        print_yes_no_row($this->utils->getLabel('enabled'), "enabled", $this->utils->getOption('enabled'));
        print_label_row($this->utils->getLabel('key', false), $this->utils->getOption('key') . $this->utils->getText('unlink'));
        print_description_row($this->utils->getText('group_settings_header'), false, 2, 'thead');
        foreach(vB_Api::instance('usergroup')->fetchUsergroupList() as $usergroupid => $usergroup) {
          print_yes_no_row($usergroup['title'], "usergroup_enabled[${usergroup['usergroupid']}]", !in_array($usergroup['usergroupid'], $disabled_group_ids));
        }
        print_submit_row();
    }
  }

  /**
   * Render the page's header
   */
  private function renderHeader() {
    print_cp_header($this->utils->getText('settings_menu_label'));
    print_form_header(basename(__FILE__, ".php"), $this->getDo(self::ACTION_UPDATE));
  }

  /**
   * Render the page's footer
   */
  private function renderFooter() {
    print_cp_footer();
  }

  /**
   * Parse the "do" parameter into the current page and action
   */
  private function setPageAndAction($do) {
    $defaults = array(
      self::ACTION_RENDER,
      $this->utils->getOption("key") ? self::PAGE_OPTIONS : self::PAGE_INTRO
    );

    $parts = @split(",", $do);

    $this->action = intval($parts[0] ? $parts[0] : $defaults[0], 10);
    $this->page = intval($parts[1] ? $parts[1] : $defaults[1], 10);
  }

  /**
   *
   */
  private function updateKey($key) {
    global $vbulletin;

    $vbulletin->db->query_write("
      UPDATE " . TABLE_PREFIX . "setting
      SET value = '" . $vbulletin->db->escape_string($key) . "'
      WHERE varname = 'viglink_key'
    ");
  }
}

$vbulletin->input->clean_gpc('r', 'do', TYPE_NOTHTML);
new Viglink_AdminCP($vbulletin->GPC['do']);

