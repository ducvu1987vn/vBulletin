<?php
class Viglink_Utils {
  /**
   * Get a setting's label by field name
   */
  public function getLabel($name, $include_desc = true) {
    $label = $this->getText($name . "_label");
    $desc = $this->getText($name . "_desc");
    if($include_desc && ! empty($desc)) {
      $label .= "<dfn>" . $desc . "</dfn>";
    }
    return $label;
  }

  /**
   * Get a setting's value by field name
   */
  public function getOption($name, $default = null) {
    global $vbulletin;
    $val = $vbulletin->options['viglink_' . $name];
    return empty($val) ? $default : $val;
  }

  /**
   * Get a phrase by name
   */
  public function getText($name, $viglink = true) {
    global $vbphrase;
    return $vbphrase[($viglink ? "viglink_" : "") . $name];
  }
}

