<?php

/*
 * Class ArrayFilterUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class ArrayFilterUtility extends FilterUtility
{/*{{{*/

  static function get_selector_regex() {
    $selector_regex = '@({([^}]*)}|\[([^]]*)\]|(([-_0-9a-z=#]*)*)[,]*)@';
    return $selector_regex;
  }

  static function component_condition_input_check($sa, $attparts) {
    // return 'is_array($a'.$attparts.') && array_key_exists("'.$sa.'", $a'.$attparts.')';
    if ( strlen($attparts) > 0 ) {
      return function($a) use( $sa, $attrparts ) { 
        $attrnames = [];
        $res = preg_match_all("@\[['\"]*([^'\"]{1,})['\"]*\]@i", $attparts, $attrnames);
        // Only handles case $res == 1 and count($attrnames) == 2
        return is_array($a[ $attrnames[1][0] ]) && array_key_exists("{$sa}", $a[ $attrnames[1][0] ]);
      };
    }
    return function($a) use ($sa) {
      return is_array($a) && array_key_exists($sa, $a);
    };
  }

  static function component_attribute_parts($sa) {
    return "['{$sa}']";
  }

  static function get_returnable_element($attr) {
    // return '$a["'.$attr.'"]';
    return function($a) use ($attr) {
      return $a[$attr];
    };
  }

  static function get_condition_exact_match($attr,$val) {
    // return 'is_array($a) && ($a["'.$attr.'"] == "'.$val.'")';
    return function($a) use ($attr, $val) {
      return is_array($a) && ($a[$attr] == $val);
    };
  }

  static function get_condition_regex_match($val, $regex_modifier, $attparts) {
    // return '1 == preg_match("@('.$val.')@'.$regex_modifier.'",$a'.$attparts.')'; 
    if ( 0 < strlen($attparts) ) {
      return function($a) use ($val, $regex_modifier, $attparts) {
        $attrnames = [];
        $res = preg_match_all("@\[['\"]*([^'\"]{1,})['\"]*\]@i", $attparts, $attrnames);
        // Only handles case $res == 1 and count($attrnames) == 2
        return 1 == preg_match("@({$val})@{$regex_modifier}",$a[ $attrnames[1][0] ]); 
      };
    }
    return function($a) use ($val, $regex_modifier) {
      return 1 == preg_match("@({$val})@{$regex_modifier}",$a); 
    };
  }

  static function get_condition_regex_matched_returnable($attr) {
    // return '$a["'.$attr.'"]';
    return function($a) use( $attr ) { return $a[$attr]; };
  }

  static function get_returnable_value_array($returnable, $d) {
    if (0) {
      $returnable_map = function($a) { return "\"{$a}\" => \$a[\"{$a}\"]"; };
      $intermediate_result = array_map($returnable_map, $returnable);
      $returnable_match = 'array(' . join(',',$intermediate_result) .')';
      self::syslog(__METHOD__,__LINE__,"Returning: {$returnable_match}"); 
      self::recursive_dump( $returnable, __METHOD__ . ":Source" );
      self::recursive_dump( [ $returnable_map ], __METHOD__ . ":Map" );
      self::recursive_dump( $intermediate_result, __METHOD__ . ":Result" );
    }

    // return $returnable_match; 
    return function($a) use( $returnable ) {
      // Return array containing pairs [ $k => $a[ $k ] ]
      $result = [];
      foreach ( $returnable as $key ) { $result[ $key ] = $a[ $key ]; }
      return $result; 
    };
  }

  static function get_returnable_value_array_singleentry($returnable, $d) {
    if (0) {
      $returnable_map = function($a) { $m = array(); return !(1 == preg_match("@^([#])(.*)@i", $a, $m)) ? "\$a[\"{$a}\"]" : ( 0 < strlen($m[2]) ? "\$a[\"$m[2]\"]" : "\$a" ) ; };
      $intermediate_result = array_map($returnable_map, $returnable);
      $returnable_match = join(',',$intermediate_result);
      self::syslog(__METHOD__,__LINE__,"Returning: {$returnable_match} parameter " . print_r( $returnable, TRUE ) ); 
      // return $returnable_match;  
    }
    return function($a) use( $returnable ) {
      return $a[ $returnable[0] ];
    };
  }

	static function get_returnable_value_element($returnable) {
		// return '$a';
    return function($a) {
      return $a;
    };
	}

  static function return_map_condition($conditions, $returnable_match, $d) {
    // return 'return ' . join(' && ', $conditions) . ' ? ' . $returnable_match . ' : NULL;';
    return function($a) use( $conditions, $returnable_match ) {
      $b = TRUE;
      foreach( $conditions as $condition ) {
        $b &= $condition($a);
        if ( !$b ) break;
      }
      return $b ? $returnable_match($a) : NULL;
    };
  }

}/*}}}*/
