<?php

/*
 * Class FilterUtility
 * Legiscope - web site reflection framework
 * Implements a simple parser for CSS-style selectors on nested arrays
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

abstract class FilterUtility {

	static $debug_operators = TRUE;
  
	abstract static function get_selector_regex();

  static function get_map_functions($docpath, $d = 0) 
  {/*{{{*/
    $debug = FALSE;
    // A mutable, recursing alternative to XPath
    // Extract content from parse containers
    $map_functions = array();
    // Disallow recursion beyond a depth we actually use. parse_html() returns a shallow nested array having at most depth 3 from a root entry.
    if ( $d > 4 ) return $map_functions;
    // A hash for the tag selector is interpreted to mean "return siblings of an element which match the selector"
    // Pattern yields the selectors in match component #3,
    // and the subject item description in component #2.
    $matches = array();

    preg_match_all(static::get_selector_regex(), $docpath, $matches);

    array_walk($matches,function(& $a, $k) { $a = is_array($a) ? array_filter($a) : NULL; if (empty($a)) $a = "*";});

    $subjects   = $matches[2]; // 
    $selectors  = $matches[3]; // Key-value match pairs (A=B, match exactly; A*=B regex match)
    $returnable = $matches[4]; // Return this key from all containers

    $conditions = array(); // Concatenate elements of this array to form the array_map condition

    if ( $debug ) {
      static::recursive_dump( [ $docpath ], '(marker) Input' );
      static::recursive_dump( [
        'subjects'   => $subjects,
        'returnable' => $returnable,
        'selectors'  => $selectors,
      ], "(marker) Intermediate input" );
    }

		if ( is_array($selectors) ) foreach ( $selectors as $condition ) {

      if ( !(1 == preg_match('@([^*=]*)(\*=|=)*(.*)@', $condition, $p)) )
      {/*{{{*/
        // A condition must take the form of an equality test.
        // The test itself is implemented as either a simple comparison or a regex match.
        static::syslog(__FUNCTION__,__LINE__,"--- WARNING: Unparseable condition. Terminating recursion.");
        return array();
      }/*}}}*/

      $attr = $p[1];
      $conn = $p[2]; // *= for regex match; = for equality
      $val  = $p[3];

      if ( $debug ) {
        static::recursive_dump([
          'attr' => $attr,
          'conn' => $conn,
          'val'  => $val,
        ],"(marker) Selector components" );
      }

      $attparts = '';
      if ( strlen($attr) == 0 ) {
        static::syslog(__FUNCTION__,__LINE__,"Tumifi: Empty attr" );
      }
      else {
        // Specify a match on nested arrays using [kd1:kd2] to match against
        // $a[kd1][kd2] 
        if ( $debug ) static::syslog( __FUNCTION__, __LINE__, "Tumifi: {$attr} - " . print_r( $attr, TRUE ) );
        foreach ( explode(':', $attr) as $i => $sa ) {
          // ???
          if ( $debug ) static::syslog( __FUNCTION__, __LINE__, "----------------------------------" );
          $input_check = static::component_condition_input_check($sa, $attparts);

          if ( $debug ) static::syslog( __FUNCTION__, __LINE__, "Tumifi: input_check[{$i}]: " . (is_callable($input_check) ? dump_callable($input_check) : $input_check) );
          if ( !is_null($input_check) ) $conditions[] = $input_check;

          $attparts .= static::component_attribute_parts($sa);

          if ( $debug ) static::syslog( __FUNCTION__, __LINE__, "Tumifi: attparts[{$i}]: {$attparts}" );
        }
        if ( $debug ) { 
          $n = count( $conditions );
          static::syslog( __FUNCTION__, __LINE__, "- - - - - - - - - - - - - - - - - " );
          static::syslog( __FUNCTION__, __LINE__, "Tumifi [{$n}]: {$attr} - " . print_r( $attparts, TRUE ) );
          static::syslog( __FUNCTION__, __LINE__, "----------------------------------" );
        }
      }

      if ( empty($val) ) {
        // There is only an attribute to check for.  Include source element if the attribute exists 
        if (!is_array($returnable)) $returnable = static::get_returnable_element($attr);
      } else if ( $conn == '=' ) {
        // Allow condition '*' to stand for any matchable value; 
        // if an asterisk is specified, then the match for a specific
        // value is omitted, so only existence of the key is required.
        $condition_fragment = static::get_condition_exact_match($attr,$val); 
        if ( $val != '*' ) $conditions[] = $condition_fragment; 
      } else if ($conn == '*=') {
        $split_val = explode('|', $val);
        $regex_modifier = NULL;
        if ( 1 < count($split_val) ) {
          $regex_modifier = $split_val[count($split_val)-1];
          array_pop($split_val);
          $val = join('|',$split_val);
        }
        $condition_fragment = static::get_condition_regex_match($val, $regex_modifier, $attparts);
        $conditions[] = $condition_fragment;
        if ( $returnable == '*' ) $returnable = static::get_condition_regex_matched_returnable($attr);
      } else {
        static::syslog(__FUNCTION__,__LINE__,"Unrecognized comparison operator '{$conn}'");
      }
    }

    if ( $debug ) static::recursive_dump($conditions,"(marker) Conditions" );

    if ( is_array($returnable) ) {
      if ( 1 == count($returnable) ) {
        // If the returnable is specified as '#', then all siblings of the matching element(s) are returned.
				$returnable_match = static::get_returnable_value_array_singleentry($returnable, $d);
        if ( $debug ) static::syslog(__FUNCTION__,__LINE__,"returnable_match = " . (is_callable($returnable_match) ? dump_callable($returnable_match) : $returnable_match) );
      } else {
        $returnable_match = static::get_returnable_value_array($returnable, $d);
        if ( $debug ) static::syslog(__FUNCTION__,__LINE__,"returnable_match = " . (is_callable($returnable_match) ? dump_callable($returnable_match) : $returnable_match) );
      }
    } else {
      if ( $returnable == '*' ) {
        // If the returnable attribute is given as '*', return the entire array value.
        // self::syslog(__FUNCTION__,__LINE__,"--- WARNING: Map function will be unusable, no return value (currently '{$returnable}') in map function.  Bailing out");
				$returnable_match = static::get_returnable_value_element($returnable);
      } else {
        $returnable_match = $returnable;
      }
    }
    if ( $debug ) static::syslog(__FUNCTION__,__LINE__,"returnable_match = " . (is_callable($returnable_match) ? dump_callable($returnable_match) : $returnable_match) );

    $map_condition = static::return_map_condition($conditions, $returnable_match, $d);

    if ( $debug ) static::syslog( __FUNCTION__, __LINE__, "Tumifi: map_condition: " . (is_callable($map_condition) ? dump_callable($map_condition) : $map_condition) );
    $map_functions[] = $map_condition;
    if ( $debug ) { 
      static::syslog(__FUNCTION__,__LINE__,"- (marker) Extracting from '{$docpath}'");
      static::recursive_dump($matches,"(marker) matches");
      static::recursive_dump($conditions,"(marker) conditions");
    }

    if ( is_array($subjects) && 0 < count($subjects) ) {
      foreach ( $subjects as $subpath ) {
        if ( $debug ) static::syslog(__FUNCTION__,__LINE__,"(marker) - Passing sub-path at depth {$d}: {$subpath}");
        $submap = static::get_map_functions($subpath, $d+1);
        if ( is_array($submap) ) $map_functions = array_merge($map_functions, $submap);
      }
    }

    if ( $debug ) {
      static::syslog( __FUNCTION__, __LINE__, "Tumifi: Final map functions array" );
      recursive_dump( [ $docpath ]  , '<-------------' );
      recursive_dump( $map_functions, '------------->' );
    }

    return $map_functions;
  }/*}}}*/

	/** Duplicate utility methods **/

  static protected function logging_ok($prefix) {/*{{{*/
    if ( 1 == preg_match('@(WARNING|ERROR|MARKER)@i', $prefix) ) return TRUE;
    if ( TRUE === C('DEBUG_ALL') ) return TRUE;
    if ( 1 == preg_match('@([(]SKIP[)])@i', $prefix) ) return FALSE;
    // if ( FALSE === C('DEBUG_'.get_class(self)) ) return FALSE;
		return TRUE;
  }/*}}}*/

  static function syslog($fxn, $line, $message) {/*{{{*/
    if ( self::logging_ok($message) ) { 
      syslog( LOG_INFO, self::syslog_preamble($fxn, $line) . " {$message}" );
      if ( !(FALSE === C('SLOW_DOWN_RECURSIVE_DUMP')) ) usleep(C('SLOW_DOWN_RECURSIVE_DUMP'));
    }
  }/*}}}*/

  static protected function syslog_preamble($fxn, $line) {/*{{{*/
    $line = is_null($line) ? "" : "({$line})";
    return get_class() . ":: {$fxn}{$line}: ";
  }/*}}}*/

  static protected function recursive_dump($a, $prefix = NULL) {/*{{{*/
    if ( !is_array($a) ) return;
    if ( !static::logging_ok($prefix) ) return;
    static::recursive_dump_worker($a, 0, $prefix);
  }/*}}}*/

  static private function recursive_dump_worker($a, $depth = 0, $prefix = NULL) {/*{{{*/
    if ( !(FALSE === C('SLOW_DOWN_RECURSIVE_DUMP')) ) usleep(C('SLOW_DOWN_RECURSIVE_DUMP'));
    foreach ( $a as $key => $val ) {
      $logstring = is_null($prefix) 
        ? basename(__FILE__) . "::" . __LINE__ . "::" . __FUNCTION__ . ": "
        : get_class() . " :{$prefix}: "
        ;
      $logstring .= str_pad(' ', $depth * 3, " ", STR_PAD_LEFT) . '('.gettype($val).')' . " {$key} => " ;
      if ( is_array($val) ) {
        syslog( LOG_INFO, $logstring );
        static::recursive_dump_worker($val, $depth + 1, $prefix);
      }
      else {
        if ( is_null($val) ) 
          $logstring .= 'NULL';
        else if ( is_bool($val) )
          $logstring .= ($val ? 'TRUE' : 'FALSE');
        else if ( empty($val) )
          $logstring .= '[EMPTY]';
        else if ( is_callable($val) )
          $logstring .= dump_callable($val);
        else
          $logstring .= substr("{$val}",0,500) . (strlen("{$val}") > 500 ? '...' : '');
        syslog( LOG_INFO, $logstring );
      }
    }
  }/*}}}*/

}

