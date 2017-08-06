<?php
require_once(dirname(__FILE__) . "/../include/viglink_utils.class.php");

class Viglink_Api_Site extends vB_Api_Extensions {
  protected $product = 'viglink';
  protected $developer = 'VigLink';
  protected $title = 'Global';

  protected $minver = '5.0.0 Alpha';
  protected $maxver = '5.0.99';

  protected $AutoInstall = 1;
  protected $extensionOrder = 10;

  const VIGLINK_FEATURE_ALL = 1;
  const VIGLINK_FEATURE_LII = 2;

  public function isViglinkEnabled($prev, $feature = self::VIGLINK_FEATURE_ALL) {
    $utils = new Viglink_Utils;

    $is_enabled = (bool) vB::getDatastore()->getOption('viglink_enabled');
    $has_key = (bool) vB_Api::instance('site')->getViglinkKey();

    $args = func_get_args();

    $enabled = $is_enabled && $has_key;

    switch ($feature) {
      case self::VIGLINK_FEATURE_ALL:
        return $enabled;
      case self::VIGLINK_FEATURE_LII:
        // disabled for one of this user's groups?
        $disabled_group_ids = json_decode($utils->getOption('lii_excluded_usergroups', '[]'));
        $user_disabled_group_ids = array_intersect($disabled_group_ids, vB::getUserContext()->fetchUserGroups());
        $lii_enabled_for_groups = empty($user_disabled_group_ids);

        $lii_enabled = $lii_enabled_for_groups;

        return $enabled && $lii_enabled;
    }
  }

  public function getViglinkKey() {
    return vB::getDatastore()->getOption('viglink_key');
  }
}

