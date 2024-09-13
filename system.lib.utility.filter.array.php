<?php


if (!defined('SYSTEM_BASE')) define('SYSTEM_BASE', './system');

/*
 * generate_province_image
 * write_barangay_svg_paths 
 * write_svg_paths 
 * generate_province_image
 * transform_svg
 * LegiscopeBase::transform_svgimage
 *
 *
 */

function dump_callable( & $v )
{/*{{{*/
  $reflection_f = new ReflectionFunction( $v );
  $r_filename = $reflection_f->getFileName();
  $r_startline = $reflection_f->getStartLine();
  $r_endline = $reflection_f->getEndLine();
  $w = "[ Callable {$r_filename}:{$r_startline}:{$r_endline} ]";
  return $w;
}/*}}}*/

function array_element($a, $v, $defaultval = NULL)
{/*{{{*/
  return (is_array($a) && array_key_exists($v, $a)) ? $a[$v] : $defaultval;
}/*}}}*/

function nonempty_array_element($a, $v, $defaultval = NULL)
{/*{{{*/
  return (is_array($a) && array_key_exists($v, $a) && !empty($a[$v])) ? $a[$v] : $defaultval;
}/*}}}*/

function camelcase_to_array($classname)
{/*{{{*/
  $name_components = array(0 => NULL);
  $ucase_cname     = strtoupper($classname);
  $last_matchpos   = 0;
  // Fill array with camelcase name parts
  for ( $nameindex = 0 ; $nameindex < strlen($classname) ; $nameindex++ ) {
    if ( substr($classname,$nameindex,1) == substr($ucase_cname,$nameindex,1) ) {
      // syslog( LOG_INFO, "- {$last_matchpos} - {$name_components[$last_matchpos]}" );
      $last_matchpos++;
      $name_components[$last_matchpos] = '';
    }
    $name_components[$last_matchpos] .= strtolower(substr($classname,$nameindex,1));
  }
  return array_filter($name_components);
}/*}}}*/

function recursive_dump( $a, $prefix = "-->", $depth = 0 )
{/*{{{*/
  global $skipkeys;
  $pad = str_pad("", 2 * $depth, " ", STR_PAD_LEFT);
  if ( !is_array($a) || empty($a) ) {
    ArrayFilterUtility::syslog( "", "", $prefix . "{$pad}Empty" );
    return 0;
  }
  foreach ( $a as $k => $v ) {
    if ( is_array( $v ) ) {
      if ( is_array($skipkeys) && array_key_exists($k, $skipkeys) ) {
        ArrayFilterUtility::syslog( "", "", $prefix . "{$pad}{$k} => [skipped]" );
      }
      else {
        ArrayFilterUtility::syslog( "", "", $prefix . "{$pad}{$k} => ..." );
        recursive_dump( $v, $prefix, $depth + 1 );
      }
    }
    else {
      if ( is_bool( $v ) ) {
        $v = $v ? 'true' : 'false';
      }
      else if ( is_callable( $v ) ) {
        $v = dump_callable( $v ); 
      }
      ArrayFilterUtility::syslog( "", "", $prefix . "{$pad}{$k} => {$v}" );
    }
  }
}/*}}}*/

function C($constant_name, $if_unset = FALSE )
{/*{{{*/
  return defined($constant_name) ? constant($constant_name) : $if_unset;
}/*}}}*/


abstract class FilterUtility
{/*{{{*/

	static $debug_operators = FALSE;
  
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

  static function syslog($fxn, $line, $message) 
  {/*{{{*/
    printf( "%s%s\n", static::syslog_preamble($fxn, $line), $message );
  }/*}}}*/

  static protected function syslog_preamble($fxn, $line) 
  {/*{{{*/
    $line = empty($line) ? "" : "({$line})";
    return get_class() . "::{$fxn}{$line}:";
  }/*}}}*/

  static protected function recursive_dump($a, $prefix = NULL) 
  {/*{{{*/
    if ( !is_array($a) || empty($a) ) {
      static::syslog( "", "", "Empty" );
      return;
    }
    static::recursive_dump_worker($a, 0, $prefix);
  }/*}}}*/

  static private function recursive_dump_worker($a, $depth = 0, $prefix = NULL) 
  {/*{{{*/
    foreach ( $a as $key => $val ) {
      $logstring = "{$prefix}:" . str_pad('', $depth * 2, " ", STR_PAD_LEFT) . '('.gettype($val).')' . " {$key} => " ;
      if ( is_array($val) ) {
        static::syslog( "", "", $logstring );
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
        static::syslog( "", "", $logstring );
      }
    }
  }/*}}}*/

  function & reorder_with_sequence_tags(& $c) 
  {/*{{{*/
    // Reorder containers by stream context sequence number
    // If child tags in a container possess a 'seq' ordinal value key (stream/HTML rendering context sequence number),
    // then these children are reordered using that ordinal value.
    if ( is_array($c) ) {
      if ( array_key_exists('children', $c) ) return $this->reorder_with_sequence_tags($c['children']);
      $sequence_num = function($a) { return is_array($a) ? array_element($a,"seq",array_element(array_element($a,"attrs",array()),"seq")) : NULL; };
      $filter_src   = function($a) { $rv = (is_array($a) && array_key_exists("seq",$a)) ? $a : (is_array($a) && is_array(array_element($a,"attrs")) && array_key_exists("seq",$a["attrs"]) ? $a : NULL); if (!is_null($rv)) { unset($rv["attrs"]["seq"]); unset($rv["seq"]); }; return $rv; };
      $containers   = array_filter(array_map($sequence_num, $c));
      if ( is_array($containers) && (0 < count($containers))) {
        $filtered = array_map($filter_src, $c);
        if ( is_array($filtered) && count($containers) == count($filtered) ) {
          $containers = array_combine(
            $containers,
            $filtered
          );
          if ( is_array($containers) ) {
            $containers = array_filter($containers);
            ksort($containers);
            $c = $containers;
          }
        }
      } else {
        
      }
    }
    return $this;
  }/*}}}*/

  function resequence_children(& $containers) 
  {/*{{{*/
    return array_walk(
      $containers,
      function(& $a, $k, $s) { $s->reorder_with_sequence_tags($a); },
      $this
    );
  }/*}}}*/

  function filter_nested_array(& $a, $docpath, $reduce_to_element = FALSE) 
  {/*{{{*/
    $filter_map = $this->get_map_functions($docpath);
    if ( static::$debug_operators ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"------ (marker) Container to process: " .  count($this->containers) );
      $this->recursive_dump($a,'(Content)');
      $this->recursive_dump($filter_map,'(Filter map)');
    }/*}}}*/
    foreach ( $filter_map as $i => $map ) {
      // $map is intended to be a function that takes a single parameter
      if ( $i == 0 ) {
        if ( static::$debug_operators ) {/*{{{*/
          $n = count($a);
          $this->syslog(__FUNCTION__,__LINE__,"A ------ (marker) N = {$n} Map: " . 
          (is_callable($map) ? dump_callable($map) : $map) );
        }/*}}}*/
        if ( is_array($a) ) {
          $a = array_filter(array_map($map, $a));
          if ( static::$debug_operators ) {/*{{{*/
            $n = count($a);
            $this->syslog(__FUNCTION__,__LINE__,"A <<<<<< (marker) N = {$n} Map:" . (is_callable($map) ? dump_callable($map) : print_r($map,TRUE) ));
          }/*}}}*/
          $this->resequence_children($a);
        }
      } else {
        if ( static::$debug_operators ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"B ------ (marker) N = {$n} Map: {$a}");
        }/*}}}*/
        foreach ( $a as $seq => $m ) {
          $a[$seq] = array_filter(array_map($map, $m));
        }
      }
      if ( static::$debug_operators ) {/*{{{*/
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - Map #{$i} - " . (is_callable($map) ? dump_callable($map) : print_r($map,TRUE) ));
        $this->recursive_dump($a,'(marker)');
      }/*}}}*/
    }
    if ( is_numeric($reduce_to_element) ) {
      $a = is_array($a) ? array_values($a) : array(NULL);
      return array_element($a,intval($reduce_to_element));
    } else if ( FALSE === $reduce_to_element ) { 
      return $a;
    } else if ( is_null($reduce_to_element) ) {
      $a = is_array($a) ? array_values($a) : array(NULL);
      return $a[0];
    }
    return $a;
    
  }/*}}}*/

}/*}}}*/

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

class RawparseUtility extends ArrayFilterUtility
{/*{{{*/

  protected $containers             = array();
  private   $hash_generator_counter = 0;
  private   $tag_counter            = 0;

  protected $headerset                 = array();
  protected $parser                    = NULL;
  protected $current_tag               = NULL;
  protected $tag_stack                 = array();
  protected $links                     = array();
  protected $container_stack           = array();
  protected $filtered_doc              = array();
  protected $custom_parser_needed      = FALSE;
  protected $page_url_parts            = array();
  protected $removable_containers      = array();
  protected $promise_stack             = array();
  protected $enable_filtered_doc_cache = TRUE;
  protected $content_type              = NULL;
  protected $freewheel                 = FALSE; // TRUE to prevent execution of parser callbacks
  protected $terminated                = FALSE; // TRUE to end piecewise parsing
  protected $no_store                  = FALSE; // TRUE to prevent populating stacks

  /*
   * HTML document XML parser class
   */

  function __construct() 
  {/*{{{*/
    $this->initialize();
  }/*}}}*/

  function __destruct() 
  {/*{{{*/
    if ( !is_null($this->parser) ) {
      xml_parser_free($this->parser);
    }
    $this->parser = NULL;
    $this->structure_reinit();
  }/*}}}*/
  
  protected function initialize() 
  {/*{{{*/

    $this->parser = xml_parser_create('UTF-8');
    xml_set_object($this->parser, $this);
    xml_set_element_handler($this->parser, 'ru_tag_open', 'ru_tag_close');
    xml_set_character_data_handler($this->parser, 'ru_cdata');
    xml_set_default_handler($this->parser, 'ru_default');
    /* Diagnostics */
    xml_set_start_namespace_decl_handler($this->parser,'ru_start_namespace');
    xml_set_end_namespace_decl_handler($this->parser, 'ru_end_namespace');
    xml_set_processing_instruction_handler($this->parser, 'ru_processing_instr');
    xml_set_external_entity_ref_handler($this->parser, 'ru_external_entity_ref');

    /* Options. Do not change these XML_OPTION_CASE_FOLDING */
    xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 1 );
    xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1 );
    xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');

  }/*}}}*/

  function structure_reinit() 
  {/*{{{*/
    $this->headerset              = array();
    $this->parser                 = NULL;
    $this->current_tag            = NULL;
    $this->tag_stack              = array();
    $this->links                  = array();
    $this->container_stack        = array();
    $this->containers             = array();
    $this->filtered_doc           = array();
    $this->page_url_parts         = array();
    $this->removable_containers   = array();
    $this->hash_generator_counter = 0;
    $this->tag_counter            = 0;
    $this->promise_stack          = array();
  }/*}}}*/

  function & clear_containers() 
  {/*{{{*/
    $this->containers = NULL;
    $this->filtered_containers = NULL;
    gc_collect_cycles();
    $this->structure_reinit();
    return $this;
  }/*}}}*/

  function & enable_filtered_doc($b) 
  {/*{{{*/
    $this->enable_filtered_doc_cache = $b;
    return $this;
  }/*}}}*/

  function & assign_containers(& $c, $reduce_to_element = NULL) 
  {/*{{{*/
    if ( is_null($reduce_to_element) )
      $this->containers = $c;
    else {
      $this->containers = $c[$reduce_to_element];
    }
    gc_collect_cycles();
    return $this->containers;
  }/*}}}*/

  function attributes_as_string($attrs, $as_array = FALSE, $concat_with = " ") 
  {/*{{{*/
    $attrstr = array();
    foreach ( $attrs as $key => $val ) {
      $key = strtolower($key);
      $attrstr[$key] = <<<EOH
{$key}="{$val}"
EOH;
    }
    if ( !$as_array ) $attrstr = join($concat_with, $attrstr);
    // $this->syslog(__FUNCTION__,__LINE__,"- {$attrstr}");
    return $attrstr;
  }/*}}}*/

  function needs_custom_parser() 
  {/*{{{*/
    return $this->custom_parser_needed;
  }/*}}}*/

  function & set_parent_url($url) 
  {/*{{{*/
    $this->page_url_parts = UrlModel::parse_url($url);
    return $this;
  }/*}}}*/

  function & mark_container_sequence() 
  {/*{{{*/
    $this->reorder_with_sequence_tags($this->containers);
    return $this;
  }/*}}}*/

  function & pop_from_containers(& $container) 
  {/*{{{*/
    $container = NULL;
    $container = array_pop($this->containers);
    reset($this->containers);
    return $this;
  }/*}}}*/

  function & replace_containers($c)
  {/*{{{*/
    $this->containers = $c;
    return $this->containers;
  }/*}}}*/

  function & containers_r() 
  {/*{{{*/
    return $this->containers;
  }/*}}}*/

  function clear_temporaries() 
  {/*{{{*/
    $this->filtered_containers = NULL;
    $this->removable_containers = NULL;
  }/*}}}*/

  function get_containers($docpath = NULL, $reduce_to_element = FALSE)
  {/*{{{*/
    $this->filtered_containers = array();
    if ( is_array($this->removable_containers) )
    foreach ( $this->removable_containers as $remove ) {
      unset($this->containers[$remove]);
    }
    $this->removable_containers = array();

    if ( !is_null($docpath) ) {
      $this->filtered_containers = $this->containers;
      return $this->filter_nested_array($this->filtered_containers, $docpath, $reduce_to_element);
    }
    return $this->containers;
  }/*}}}*/

  function & get_headers() 
  {/*{{{*/
    return $this->headerset;
  }/*}}}*/

  function & get_links() 
  {/*{{{*/
    return $this->links;
  }/*}}}*/

  function & get_filtered_doc() 
  {/*{{{*/
    return $this->filtered_doc;
  }/*}}}*/

  function slice($s) 
  {/*{{{*/
    // Duplicated in DatabaseUtility
    return create_function('$a', 'return $a["'.$s.'"];');
  }/*}}}*/

  function strip_headers($data) 
  {/*{{{*/

    $is_headerline    = 0;
    $header_set_index = 0;
    $fragment         = ""; 
    $rawhtml          = array(); 
    $this->headerset  = array();

    // Skip response headers (storing them in headerset[] for later use)
   
    foreach ( $data as $fragment ) {
      $fragment = str_replace(array('&nbsp;','&'),array('','&amp;'),$fragment);
      $fragment = trim($fragment);
      if ( !is_null($is_headerline) ) {
        if ( 1 == preg_match('@^HTTP/1.1@', $fragment) ) {
          $is_headerline = TRUE;
        } else if ($is_headerline == FALSE) {
          // If we are no longer in a header line block, set is_headerline = NULL
          $is_headerline = NULL;
        }
        if ( !is_null($is_headerline) && $is_headerline ) {
          $this->headerset[$header_set_index][] = $fragment;
        }
        if ( 0 == strlen($fragment) ) {
          // An empty line separates groups response header lines from each other,
          // and from the main body of content.
          $header_set_index++;
          $is_headerline = FALSE;
        }
        if ( !is_null($is_headerline) ) continue;
      }
      $rawhtml[] = $fragment;
    }

    $rawhtml = join(" ", $rawhtml);

    return $rawhtml;
  }/*}}}*/

  function reset($skip_alloc = FALSE, $clear_containers = TRUE) 
  {/*{{{*/
    if ( !is_null($this->parser) ) {
      xml_parser_free($this->parser);
      $this->parser = NULL;
    }
    if ( !$skip_alloc ) $this->initialize();
    $this->current_tag     = NULL;
    $this->tag_stack       = array();
    $this->links           = array();
    $this->container_stack = array();
    if ( $clear_containers )
    $this->containers      = array();
    $this->filtered_doc    = array();
    return $this;
  }/*}}}*/

  function standard_parse(UrlModel & $urlmodel) 
  {/*{{{*/
    return $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );
  }/*}}}*/

  function parse_html(& $raw_html, array $response_headers, $only_scrub = FALSE) 
  {/*{{{*/

    $debug_method = TRUE;

    $this->reset();

    if ( empty($raw_html) ) {
      $this->syslog(__FUNCTION__,__LINE__, "(warning) Nothing to parse. Returning NULL");
      return NULL;
    }

    libxml_use_internal_errors(TRUE);
    libxml_clear_errors();

    $doctype_match = 'content-type';
    if ( !array_key_exists($doctype_match, $response_headers) ) {
      $this->syslog(__FUNCTION__,__LINE__, "(warning) Missing key '{$doctype_match}' for encoding check. Count " . count($response_headers) );
      $this->recursive_dump($response_headers,'(warning) - Must use doctype');
    }

    $doctype_match = array(); 
    $this->content_type = array_key_exists('content-type', $response_headers) &&
      1 == preg_match('@charset=([^;]*)@', $response_headers['content-type'], $doctype_match)
      ? strtolower($doctype_match[1])
      : 'iso-8859-1' // Default assumption
      ;
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__, "(warning) Assuming encoding '{$this->content_type}' <- " . array_element($response_headers,'content-type') );
      $this->recursive_dump($doctype_match,"(marker) - --- - Matches");
      $this->recursive_dump($response_headers,"(marker) - - --- - - Headers");
    }

    $dom                      = new DOMDocument();
    $dom->recover             = TRUE;
    $dom->resolveExternals    = TRUE;
    $dom->preserveWhiteSpace  = FALSE;
    $dom->strictErrorChecking = FALSE;
    $dom->substituteEntities  = TRUE;

    $raw_html = join('',array_filter(explode("\n",str_replace(
      array(
        "\r",
        '><',
      ),
      array(
        "\n",
        ">\n<",
      ), 
      preg_replace(
        array(
          '@(<!doctype(.*)>)@im',
          '/<html([^>]*)>/imU',
          '/>([ ]*)</m',

          '/<!--(.*)-->/imUx',
          '@<noscript>(.*)</noscript>@imU',
          '@<script([^>]*)>(.*)</script>@imU',
        ),
        array(
          '',
          '<html>',
          '><',

          '',
          '',
          '',
        ),
        $this->iconv($raw_html)
      )
    ))));

    $loadresult = $dom->loadHTML($raw_html);

    $dom->normalizeDocument();

    if ( !$loadresult ) $this->syslog( __FUNCTION__, __LINE__, "(critical) -- WARNING: Failed to filtering HTML as XML, load result FAIL" );

    $full_length  = mb_strlen($dom->saveXML());
    $chunk_length = 16384;
    if ($debug_method) $this->syslog(__FUNCTION__,__LINE__, "(marker) --------------------------- mb_strlen " . $full_length );
    for ( $offset = 0 ; $offset < $full_length ; $offset += $chunk_length ) {
      $is_final = ($offset + $chunk_length) >= $full_length;
      xml_parse($this->parser, mb_substr($dom->saveXML(), $offset, $chunk_length), $is_final);
      if ( $this->terminated ) {
        $this->syslog(__FUNCTION__,__LINE__,"(warning) Terminated.");
        break;
      }
    }
    $xml_errno = xml_get_error_code($this->parser);

    if ( $xml_errno == 0 ) {

      $dom->formatOutput = FALSE;
      $raw_html = $dom->saveHTML();
      if ($debug_method) $this->syslog(__FUNCTION__,__LINE__, "(marker) ---------- XML parse OK\n" . substr($raw_html,0,500));

    } else {

      $this->syslog(__FUNCTION__,__LINE__, "(warning) --------------------------- " . __LINE__ );
      $error_offset = xml_get_current_byte_index($this->parser);
      $xml_errstr = xml_error_string($xml_errno);
      $this->syslog( __FUNCTION__, __LINE__, 
        "PARSE ERROR #{$xml_errno}: {$xml_errstr} " . 
        "offset {$error_offset} context \n" . substr($dom->saveXML(), $error_offset - 10, 200)
      );
      $errors = libxml_get_errors();
      $errors_count = 0;
      foreach ($errors as $error) {
        $this->syslog(__FUNCTION__,__LINE__,"(warning) - ".getcwd()." Parse err @ line {$error->line} col {$error->column}: {$error->message}");
        $errors_count++;
      }

      if ( $errors_count > 0 ) {
        $timestamp_sec = time();
        $milliseconds = round(microtime(true) * 10000);
        $timestamp = "{$timestamp_sec}-{$milliseconds}";
        syslog( LOG_INFO, "TS: {$timestamp} (" . strlen($raw_html) . ")" );
        file_put_contents( "/var/www/avahilario.net/cache/{$timestamp}.str", $raw_html, LOCK_EX );

      }
    }

    // Postprocessing

    // Add unprocessed container stack entries to $this->containers
    while ( 0 < count($this->container_stack) ) $this->stack_to_containers(TRUE);

    $only_non_empty = create_function('$a', 'return 0 < count(array_element($a,"children")) ? $a : NULL;');
    $this->containers = array_filter(array_map($only_non_empty,$this->containers));
    if ( $debug_method ) $this->recursive_dump($this->containers,'(marker)');

    if ( method_exists($this, 'promise_prepare_state') ) {
			if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Preparing promise states.");
      $this->promise_prepare_state();
    }
		else {
			if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) No promise state prep done.");
		}

    // Process deferred tag operations ("promises")
    $this->process_promise_stack();
  
    $dom = NULL;
    unset($dom);
    $this->promise_stack = NULL;
    gc_collect_cycles();
    $this->promise_stack = array();

    return $this->containers;

  }/*}}}*/

  function process_promise_stack() 
  {/*{{{*/
    // Process deferred tag operations ("promises")
    $debug_method = TRUE;
    if ( 0 < count($this->promise_stack) ) {
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Handle stacked tag promises: " . count($this->promise_stack) . " for " . get_class($this));
      $containerset =& $this->get_containers();
      // Ensure that hash table contains 'seq' in keys
      $this->reorder_with_sequence_tags($containerset);
      array_walk($containerset,create_function(
        '& $a, $k, $s', '$s->reorder_with_sequence_tags($a["children"]);'
      ),$this);
      // Remove any child that shares the same sequence number as it's parent
      array_walk( $containerset, create_function(
        '& $a, $k', 'if ( is_numeric($k) && array_key_exists($k,$a) ) unset($a[$k]); if ( is_numeric($k) && array_key_exists("children", $a) && array_key_exists($k,$a["children"]) ) unset($a["children"][$k]);'
      ));
      $seq = NULL;
      $this->process_promise_stack_worker($seq,$containerset);
    } else {
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) No post-processing promises stacked for " . get_class($this));
    }
  }/*}}}*/

  function process_promise_stack_worker(& $seqno, & $containerset, $promise_item = NULL, $depth = 0) 
  {/*{{{*/
    // Handle forward promise
    $debug_method = FALSE;
    if ( !is_null($promise_item) ) {
      foreach ( $promise_item as $ancestor => $promise ) {
        if ( array_key_exists('__TYPE__',$promise) ) {
          $promise_executor = "promise_{$promise['__TYPE__']}_executor";
          if ( method_exists($this, $promise_executor) ) {
            // This call modifies $containerset in place
            if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - Execute {$promise_executor}({$seqno},{$ancestor}) d = {$depth}" );
            $this->$promise_executor($containerset, $promise, $seqno, $ancestor);
          } else {
            if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) -*-*- No executor {$promise_executor}({$seqno},{$ancestor}) d = {$depth}" );
          }
        } else if ( $debug_method ) {
          $this->syslog( __FUNCTION__, __LINE__, "(marker) -*-*- No executor for promise type '{$promise['__TYPE__']}' ({$seqno},{$ancestor}) d = {$depth}" );
        }
      }
    }
    if ( is_array($containerset) ) {
      foreach ( $containerset as $seq => $children ) {
        $promise_item_n = array();
        if ( is_integer($seq) ) {
          $promise_item_n = array_element($this->promise_stack,$seq,NULL);
          if ( !is_null($promise_item_n) ) {
            list($key, $data) = each($promise_item_n);
            $data['__ANCESTOR__'] = $seqno;
            $promise_item_n = array($key => $data);
          }
        }
        $this->process_promise_stack_worker($seq, $children, $promise_item_n, $depth + 1);
        $containerset[$seq] = $children;
      }
    } else {
      if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - Leaf d = {$depth}, seq = [{$seqno}] (" . gettype($seqno) . ") containerset " . gettype($containerset)  );
    }
  }/*}}}*/

  function parse($data) 
  {/*{{{*/
    // Expects cURL response with headers prepended
    $rawhtml = $this->strip_headers($data);
    $this->parse_html($rawhtml);
  }/*}}}*/

  // ------------- XML parser callbacks for elements of interest in an HTML document  -------------

  function & pop_tagstack() 
  {/*{{{*/
    $this->current_tag = array_pop($this->tag_stack);
    return $this->current_tag;
  }/*}}}*/

  function push_tagstack($substitute = NULL) 
  {/*{{{*/
    array_push($this->tag_stack, is_null($substitute) ? $this->current_tag : $substitute);
  }/*}}}*/

  function get_stacktags() 
  {/*{{{*/
    $tagstack_tags = create_function('$a', 'return $a["tag"];');
    $tagstack_stack = array_filter(array_map($tagstack_tags, $this->tag_stack));
    $topmost = $this->tag_stack[count($this->tag_stack)-1]["tag"];
    $parent = $this->tag_stack[count($this->tag_stack)-2]["tag"];
    return "{$parent} <- {$topmost} [" . join(',',$tagstack_stack) . ']';
  }/*}}}*/

  function parent_tag() 
  {/*{{{*/
    return 2 < count($this->tag_stack) ? $this->tag_stack[count($this->tag_stack)-2]["tag"] : NULL;
  }/*}}}*/

  function & parent_item() 
  {/*{{{*/
    return 2 < count($this->tag_stack) ? $this->tag_stack[count($this->tag_stack)-2] : $this->current_tag;
  }/*}}}*/

  function ru_processing_instr( $parser , string $target , string $data ) 
  {/*{{{*/
    $this->syslog(__FUNCTION__,__LINE__,"(marker) {$target} {$data}" );
  }/*}}}*/

  function ru_start_namespace( $parser , string $prefix , string $uri ) 
  {/*{{{*/
    $this->syslog(__FUNCTION__,__LINE__,"(marker) {$prefix} {$uri}" );
  }/*}}}*/

  function ru_end_namespace( $parser , string $prefix ) 
  {/*{{{*/
    $this->syslog(__FUNCTION__,__LINE__,"(marker) {$prefix}" );
  }/*}}}*/

  function ru_external_entity_ref( $parser , string $open_entity_names , string $base , string $system_id , string $public_id ) 
  {/*{{{*/
    $this->syslog(__FUNCTION__,__LINE__,"(marker) ENs {$open_entity_names} Base {$base} SID {$system_id} PID {$public_id}" );
  }/*}}}*/

  function tag_stack_parent(& $e) 
  {/*{{{*/
    $e = NULL;
    if ( empty($this->tag_stack) || 2 > count($this->tag_stack) ) return FALSE;
    $top = array_pop($this->tag_stack);
    $e = array_pop($this->tag_stack);
    array_push($this->tag_stack, $e);
    array_push($this->tag_stack, $top);
    return TRUE;
  }/*}}}*/

  function ru_tag_open($parser, $tag, $attrs) 
  {/*{{{*/
    if ( $this->freewheel ) return TRUE;
    if ( $tag == 'HTTP:' ) return; 
    $tag = str_replace(':','_',strtoupper($tag));
    // if ( $this->debug_tags ) $this->syslog(__FUNCTION__,__LINE__,"(marker) >>>>>>>>>>>>>>>> {$tag}" );
    $tag_handler = strtolower("ru_{$tag}_open");
    $seq = intval($this->tag_counter);
    $current_tag = array(
      "tag" => $tag, 
      'attrs' => is_array($attrs) ? array_merge( $attrs, array('seq' => $seq) ) : array('seq' => $seq),
      'position' => NULL,
    );
    $this->current_tag = $current_tag;
    array_push($this->tag_stack, $current_tag);
    $result = ( method_exists($this, $tag_handler) )
      ? $this->$tag_handler($parser, $attrs, strtolower($tag))
      : TRUE 
      ;
    ////////////////////////
    $this->pop_tagstack();
    if ( $result) {
      if ( 0 < count(array_element($this->current_tag,'attrs',array())) ) {
        $attrs = $this->attributes_as_string($this->current_tag['attrs']);
        $tag .= " {$attrs}";
      }
      if ( $this->enable_filtered_doc_cache )
      $this->filtered_doc[] = <<<EOH
<{$tag}>
EOH;
    }
    $this->current_tag['position'] = count($this->filtered_doc);
    $this->push_tagstack();
    $this->tag_counter = intval($this->tag_counter) + 1;
    return TRUE;
  }/*}}}*/

  function get_container_by_hashid($container_id_hash, $key = NULL) 
  {/*{{{*/
    return array_key_exists($container_id_hash,$this->containers)
      ? is_null($key) 
        ? $this->containers[$container_id_hash]
        : $this->containers[$container_id_hash][$key]
      : NULL
      ;
  }/*}}}*/

  function ru_tag_close($parser, $tag) 
  {/*{{{*/
    if ( $this->freewheel ) return TRUE;
    $debug_method = FALSE;
    $tag = str_replace(':','_',strtoupper($tag));
    $tag_handler = strtolower("ru_{$tag}_close");
    $result = ( method_exists($this, $tag_handler) )
      ? $this->$tag_handler($parser, $tag)
      : TRUE 
      ;
    $this->pop_tagstack();
    if (is_array($this->current_tag) && array_key_exists('__LEGISCOPE__',$this->current_tag) ) {/*{{{*/
      $promise_items = $this->current_tag['__LEGISCOPE__'];
      unset($this->current_tag['__LEGISCOPE__']);
      if (!is_null(array_element($promise_items,'__TYPE__'))) {
        $promise_items = array($promise_items);
      }

      foreach ( $promise_items as $promise_item ) {
        switch ($promise_item['__TYPE__']) {
          case '__POSTPROC__':
            $promise_item = NULL;
            list($k, $promise_item) = each($promise_items);
            $postproc_data = array_element($promise_item,'__POSTPROC__'); 
            $sethash = array_element($postproc_data,'__SETHASH__','- - - -');
            if ( array_key_exists($sethash, $this->containers) ) {
              unset($postproc_data['__SETHASH__']);
              $this->containers[$sethash]['__POSTPROC__'] = $postproc_data;
            }
            break;
          case 'title':
            // This tag's CDATA content is a title for a container, identified by seq __NEXT__
            $promise_properties = $promise_item;
            unset($promise_properties['__NEXT__']);
            if ( $debug_method ) {
              $this->syslog(__FUNCTION__,__LINE__,"(marker) Stack '{$promise_item['__TYPE__']}' promise for tag {$tag}");
              $this->recursive_dump($this->current_tag,"(marker) - C - - -");
              $this->recursive_dump($promise_properties,"(marker) - - P - -");
            }
            $this->promise_stack[$promise_item['__NEXT__']] = array(
              $this->current_tag['attrs']['seq'] => $promise_properties
            );
            break;
          default:
            break;
        }
      }
    }/*}}}*/
    // The parser tag close method returns TRUE to cause this method 
    // to add the tag to the filtered markup result array.
    if ($result) {
      $tag_cdata = ( is_array($this->current_tag) && array_key_exists('cdata',$this->current_tag) && is_array($this->current_tag['cdata']) )
        ? join('',$this->current_tag['cdata'])
        : NULL
        ;
      if ( $this->enable_filtered_doc_cache )
      $this->filtered_doc[] = <<<EOH
{$tag_cdata}
</{$tag}>
EOH;
    }
    else if (is_array($this->current_tag)) {
      // Remove all content added to the filtered document array
      // since the opening tag was inserted into the tag stack
      $start = 0;
      if ( array_key_exists('position', $this->current_tag) ) {
        $start = $this->current_tag['position'];
      }
      $end = count($this->filtered_doc);
      $last_removed = "[NONE]";
      // $this->syslog(__FUNCTION__,__LINE__, "Removing children of {$tag} from {$start} to {$end}");
      for ( ; $start <= $end; $start++ ) { $last_removed = array_pop($this->filtered_doc); }
      // $this->syslog(__FUNCTION__,__LINE__, "Removed children of {$tag} from {$this->current_tag['position']} to {$end} ({$last_removed})");

      // If this tag is a container, remove the container stack entry associated with this tag
      if ( array_key_exists('CONTAINER', $this->current_tag) ) {
        $container_id_hash = $this->current_tag['CONTAINER'];
        if ( array_key_exists($container_id_hash, $this->containers) ) {
          // $this->syslog(__FUNCTION__,__LINE__, "Removing container {$this->current_tag["tag"]} with set hash {$container_id_hash}");
          unset($this->containers[$container_id_hash]);
        } else {
          // $this->syslog(__FUNCTION__,__LINE__, "Deferring removal of container {$container_id_hash}");
          $this->removable_containers[] = $container_id_hash;
        }
      }
    }
    return TRUE;
  }/*}}}*/

  function ru_cdata($parser, $cdata) 
  {/*{{{*/
    if ( $this->freewheel ) return TRUE;
    // Character data will always be contained within a parent container.
    // If there is no handler specified for a given tag, the topmost 
    // tag on the tag stack receives link content.
    $parser_result = TRUE;
    if ( 0 < count($this->tag_stack) ) {
      $stack_top = array_pop($this->tag_stack);
      array_push($this->tag_stack,$stack_top);
      $cdata_content_handler = strtolower("ru_{$stack_top["tag"]}_cdata");
      if ( method_exists($this, $cdata_content_handler) ) {
        $parser_result = $this->$cdata_content_handler($parser, $cdata);
      } else {
        $stack_top = array_pop($this->tag_stack);
        if ( !array_key_exists('cdata', $stack_top) ) $stack_top['cdata'] = array();
        $stack_top['cdata'][] = $cdata;
        array_push($this->tag_stack,$stack_top);
      }
    }
    return $parser_result;
  }/*}}}*/

  function ru_default($parser, & $cdata) 
  {/*{{{*/
    if ( $this->freewheel ) return TRUE;
    // $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$cdata}" );
    // $cdata = NULL;
    return FALSE;
  }/*}}}*/

  // Nested tag embedding: Placeholder cleanup 

  function cleanup_article(& $a, $k) 
  {/*{{{*/
    // Invoked to clean up placeholders for embedded tags 
    // Requires use of this->append_cdata() in ru_x_cdata() parser callbacks
    // where placeholders need to be embedded.
    //
    // The container for placeholder-embedded lines must be marked 
    // as containers using this->push_container_def($tag,$attrs).
    //
    // Lines must be contained in "text" entries
    // <seq> =>
    //   "text" => "<string containing placeholder '{{<seq>}}'>"
    //
    if ( array_key_exists("text",$a) ) {
      // Remove self-referencing placeholders
      $a["text"] = str_replace("{{{$k}}}","",$a["text"]);
      if (1 == preg_match("@\{\{([0-9]{1,})\}\}@i",$a["text"])) {
        $m = array(); 
        if ( 0 < intval(preg_match_all("@\{\{([0-9]{1,})\}\}@i",$a["text"],$m)) ) {
          $m = array_combine($m[1],$m[0]);
          $a["matches"] = $m;
          $keys = array_flip(array_pop($this->article));
          $a["matches"] = array();
          foreach ( $m as $index => $placeholder ) {
            if ( array_key_exists($index, $keys) ) {
              $a["matches"][$index] = $placeholder;
              continue;
            }
            $a["text"] = str_replace($placeholder,'',$a["text"]);
          }
          if ( !(0 < count($a["matches"]))) unset($a["matches"]);
          array_push($this->article,array_flip($keys));
        }
      }
    }
    else if ( array_key_exists("children", $a) ) {
      array_push($this->article,array_keys($a['children']));
      array_walk($a['children'], function(& $a, $k, $s) { $s->cleanup_article($a, $k);}, $this);
      array_pop($this->article);
    }
  }/*}}}*/

  function mark_container_placeholders(& $data) 
  {/*{{{*/

    $this->article = array();

    $this->reorder_with_sequence_tags($data);
    array_walk($data,function(& $a, $k, $s) { if ( is_array($a) && array_key_exists("children",$a) ) $s->reorder_with_sequence_tags($a["children"]); }, $this);

    array_push($this->article,array_keys($data['children']));
    array_walk($data['children'], function(& $a, $k, $s) { $s->cleanup_article($a, $k); }, $this);

    $this->article = array();

  }/*}}}*/

  function add_to_container_stack(& $link_data, $target_tag = NULL) 
  {/*{{{*/
    if ( !( 0 < count($this->container_stack) ) ) {
      $container_def = array(
        'tagname' => 'nil',
        'sethash' => $this->hash_generator_counter++,
        'class'   => NULL, 
        'id'      => '__LEGISCOPE__',
        'children' => array(),
        'seq'     => 0 
      );
      $container_sethash = sha1(base64_encode(print_r($container_def,TRUE) . ' ' . mt_rand(10000,100000)));
      $container_def['sethash'] = $container_sethash;
      array_push($this->container_stack, $container_def);
      if (C('DEBUG_'.get_class($this))) $this->syslog( __FUNCTION__, __LINE__, "(marker) -- - -- - !! Attempt to add tag to empty stack. Added faux container [{$container_sethash}]");
    }
    if ( is_null($target_tag) ) {
      $container = array_pop($this->container_stack);
      $container['children'][] = $link_data; 
      $link_data['sethash'] = array_element($container,'sethash');
      $link_data['found_index'] = NULL;
      array_push($this->container_stack, $container);
      if (C('DEBUG_'.get_class($this))) syslog( LOG_INFO, get_class() . '::' . __FUNCTION__ . '(' . __LINE__ . "): A" . count($this->container_stack) );
      return TRUE;
    }
    $found_index = NULL;
    foreach( $this->container_stack as $stack_index => $stacked_element ) {
      if ( !( strtolower($stacked_element['tagname']) == strtolower($target_tag) ) ) continue;
      $found_index = $stack_index; // Continue; find the uppermost tag
    }
    if ( !is_null($found_index) ) {
      $link_data['found_index'] = $found_index;
      $link_data['sethash'] = array_element($this->container_stack[$found_index],'sethash');
      $this->container_stack[$found_index]['children'][] = $link_data;
      if (C('DEBUG_'.get_class($this))) syslog( LOG_INFO, get_class() . '::' . __FUNCTION__ . '(' . __LINE__ . "): B" );
      return TRUE;
    } else {
      if (C('DEBUG_'.get_class($this))) $this->syslog( __FUNCTION__, __LINE__, "(marker) Lost tag content" );
    }
    if (C('DEBUG_'.get_class($this))) syslog( LOG_INFO, get_class() . '::' . __FUNCTION__ . '(' . __LINE__ . "): C" );
    return FALSE;
  }/*}}}*/

  function push_container_def($tagname, & $attrs) 
  {/*{{{*/
    // Invoked in ru_<tag>_open methods.
    $this->pop_tagstack();
    // This information is used to remove a container from the stack
    $container_def = array(
      'tagname' => $tagname,
      'sethash' => $this->hash_generator_counter++,
      'class'   => nonempty_array_element($attrs,'CLASS',nonempty_array_element($attrs,'class')),
      'id'      => nonempty_array_element($attrs,'ID',nonempty_array_element($attrs,'id')),
      'attrs'   => $attrs,
      'children' => array(),
      'seq'     => nonempty_array_element($this->current_tag['attrs'],'seq',0),
    );
    $container_sethash = sha1(base64_encode(print_r($container_def,TRUE) . ' ' . mt_rand(10000,100000)));
    $container_def['sethash'] = $container_sethash;
    $this->current_tag['CONTAINER'] = $container_sethash;
    if (C('DEBUG_'.get_class($this))) $this->syslog( __FUNCTION__, "<{$tagname}", join(',', array_keys($attrs)) . ' - ' . join(',', $attrs) );
    $this->push_tagstack();
    array_push($this->container_stack, $container_def);
  }/*}}}*/

  function & update_current_tag_url($k, $trim_path_slashes = TRUE) 
  {/*{{{*/
    if ( is_array($this->page_url_parts) && (0 < count($this->page_url_parts)) && is_array($this->current_tag) && array_key_exists('attrs', $this->current_tag) && array_key_exists($k, $this->current_tag['attrs'])) {
      $prev_url = $this->current_tag['attrs'][$k];
      $fixurl = array('url' => $prev_url);
      // Hack for URL normalization/parsing, change '/?' to '?'
      $url = UrlModel::normalize_url($this->page_url_parts, $fixurl, $trim_path_slashes);
      $url = str_replace('/?','?',$url);
      $this->current_tag['attrs'][$k] = $url;
      if ( $this->debug_tags ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Updated {$this->current_tag['tag']} {$this->current_tag['attrs'][$k]} <- {$prev_url}");
    }
    return $this;
  }/*}}}*/

  function collapse_current_tag_link_data() 
  {/*{{{*/
    $target = isset($this->current_tag['attrs']['HREF']) ? $this->current_tag['attrs']['HREF'] : NULL;
    $link_text = join('', $this->current_tag['cdata']);
    $link_data = array(
      'url'      => $target,
      'text'     => $link_text,
      'seq'      => $this->current_tag['attrs']['seq'],
    );
    return $link_data;
  }/*}}}*/

  function extract_form_controls($form_control_source) 
  {/*{{{*/

    $form_controls        = array();
    $select_options       = array();
    $select_name          = NULL;
    $select_option        = NULL;
    $userset              = array();

    if ((is_array($form_control_source) && (0 < count($form_control_source)))) {

      $extract_hidden_input = create_function('$a','return strtoupper(array_element($a,"tag")) == "INPUT" && is_array(array_element($a,"attrs")) && strtoupper(array_element($a["attrs"],"TYPE")) == "HIDDEN" ? array("name" => array_element(array_element($a,"attrs",array()),"NAME"), "value" => array_element(array_element($a,"attrs",array()),"VALUE")) : NULL;');
      $extract_radio_input  = create_function('$a','return strtoupper(array_element($a,"tag")) == "INPUT" && is_array(array_element($a,"attrs")) && strtoupper(array_element($a["attrs"],"TYPE")) == "RADIO"  ? array("name" => array_element(array_element($a,"attrs",array()),"NAME"), "value" => array_element(array_element($a,"attrs",array()),"VALUE")) : NULL;');
      $extract_text_input   = create_function('$a','return strtoupper(array_element($a,"tag")) == "INPUT" && is_array(array_element($a,"attrs")) && strtoupper(array_element($a["attrs"],"TYPE")) == "TEXT"   ? array("name" => array_element(array_element($a,"attrs",array()),"NAME"), "value" => array_element(array_element($a,"attrs",array()),"VALUE")) : NULL;');
      $extract_select       = create_function('$a','return strtoupper(array_element($a,"tagname")) == "SELECT" ? array("name" => array_element(array_element($a,"attrs",array()),"NAME"), "keys" => array_element($a,"children")) : NULL;');

      $radio_options  = array_values(array_filter(array_map($extract_radio_input,$form_control_source)));

      foreach ( array_merge(
        array_values(array_filter(array_map($extract_hidden_input,$form_control_source))),
        array_values(array_filter(array_map($extract_text_input, $form_control_source)))
      ) as $form_control ) {
        $form_controls[$form_control['name']] = $form_control['value'];
      };

      foreach ( $radio_options as $radio ) {
        if ( !array_key_exists($radio['name'],$form_controls) ) $form_controls[$radio['name']] = array();
        $form_controls[$radio['name']][] = $radio['value'];
      }

      $select_options = array_values(array_filter(array_map($extract_select, $form_control_source)));

      $userset = array();
      $selected = NULL;
      foreach ( $select_options as $select_option ) {
        //$this->recursive_dump($select_options,__LINE__);
        $select_name    = $select_option['name'];
        $select_option  = $select_option['keys'];
        foreach ( $select_option as $option ) {
          if ( is_null($selected) && (1 == intval(array_element($option,'selected')))) $selected = $option['value']; 
          if ( empty($option['value']) ) continue;
          $userset[$select_name][$option['value']] = $option['text'];
        }
      }
    }

    return array(
      'userset'        => $userset,
      'form_controls'  => $form_controls,
      'select_name'    => $select_name,
      'select_options' => $select_option,
      'select_active'  => $selected,
    );
  }/*}}}*/

  function fetch_body_generic_cleanup($pagecontent) 
  {/*{{{*/
    return preg_replace(
      array(
        '@^(.*)\<body([^>]*)\>(.*)\<\/body\>(.*)@mi',
        // Remove mouse event handlers
        '@(onmouseover|onmouseout)="([^"]*)"@',
        "@(onmouseover|onmouseout)='([^']*)'@",
      ),
      array(
        '$3', 
        '',
        '',
      ),
      $pagecontent
    );
  }/*}}}*/
  
  function current_tag_cdata() 
  {/*{{{*/
    $cdata = nonempty_array_element($this->current_tag,'cdata',array());
    $cdata = array_values(array_filter(explode('[BR]',trim(join('',$cdata)))));
    return is_array($cdata) ? $cdata : array();
  }/*}}}*/

  function embed_container_in_parent(& $parser,$tag,$remove_processed_container = FALSE) 
  {/*{{{*/
    $keep_container = TRUE;
    $this->pop_tagstack();
    if ( array_key_exists('CONTAINER',$this->current_tag) ) {
      $content = $this->stack_to_containers(TRUE);
      $container_hash = $content['hash_value'];
      $attrs = nonempty_array_element($this->current_tag,'attrs',array());
      $cdata = array_filter(nonempty_array_element($this->current_tag,'cdata',array()));
      if ( $container_hash == nonempty_array_element($this->current_tag,'sethash') ) {

        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- WARNING: Prevented container duplication. Double-check your parser output");
        $this->recursive_dump($this->current_tag,"(marker) +-");
        $this->recursive_dump($content,"(marker) -+");

      } else {
        $cdata = $this->current_tag_cdata();
        $this->reorder_with_sequence_tags($content['container']);
        if ( array_key_exists('children', $content['container']) ) {
          $this->reorder_with_sequence_tags($content['container']['children']);
        }
        $this->current_tag = array_merge(
          ((0 < count($cdata)) ? array('cdata' => $cdata) : array()),
          $content['container']
        );
        // Remove processed container
        if ( $remove_processed_container && array_key_exists($content['hash_value'], $this->containers) ) {
          unset($this->containers[$container_hash]);
        }
        unset($attrs['seq']);
        $this->current_tag['attrs'] = array_merge(
          $this->current_tag['attrs'],
          $attrs
        );
      }
      $keep_container = FALSE;
    }
    $ctag = array_filter($this->current_tag);
    $this->current_tag = $ctag;
    $ctag = NULL;
    $this->add_to_container_stack($this->current_tag);
    $this->push_tagstack();
    return $keep_container;
  }/*}}}*/

  function add_cdata_property() 
  {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->current_tag['_cdata_next_'] = $this->tag_counter;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function embed_nesting_placeholders() 
  {/*{{{*/
    // Method is called from two locations: 
    //   ru_tag_cdata, and ru_tag_close
    // BEFORE a tag is placed into a parent container
    // by invoking add_to_container_stack(childtag)
    if ( !is_null(nonempty_array_element($this->current_tag,'_cdata_next_'))) {
      // Insert placeholders for inserted tags
      if ( $this->tag_counter > intval($this->current_tag['_cdata_next_']) ) {
        // $this->syslog(__FUNCTION__,__LINE__,"(marker) [{$this->tag_counter},{$this->current_tag['_cdata_next_']}] {$this->current_tag['tag']}");
        while ( ( $this->current_tag['_cdata_next_'] + 1 ) < $this->tag_counter ) {
          $i = $this->current_tag['_cdata_next_'];
          $this->current_tag['_cdata_next_']++;
          if ( !array_key_exists($this->current_tag['_cdata_next_'],$this->current_tag['cdata']) ) {
            $this->current_tag['cdata'][$this->current_tag['_cdata_next_']] = "{{{$i}}}";
          }
        }
      }
    }
  }/*}}}*/

  function append_cdata($cdata) 
  {/*{{{*/
    $this->pop_tagstack();
    if (0 < mb_strlen(trim($cdata))) {
      $this->embed_nesting_placeholders();
      $this->tag_counter++;
      $this->current_tag['cdata'][$this->tag_counter] = $cdata;
      $this->current_tag['_cdata_next_'] = $this->tag_counter;
    }
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function standard_cdata_container_close() 
  {/*{{{*/
    $this->pop_tagstack();
    $this->embed_nesting_placeholders();
    $this->push_tagstack();
    if ( is_array(nonempty_array_element($this->current_tag,'cdata')) ) {
      $paragraph = array(
        'text' => join('', $this->current_tag['cdata']),
        'seq'  => $this->current_tag['attrs']['seq'],
      );
      if ( 0 < strlen($paragraph['text']) ) $this->add_to_container_stack($paragraph);
    }
    return TRUE;
  }/*}}}*/

  function add_current_tag_to_container_stack() 
  {/*{{{*/
    $this->pop_tagstack();
    $this->add_to_container_stack(array_filter(array_merge(
      $this->current_tag,
      array('seq' => nonempty_array_element($this->current_tag['attrs'],'seq')) 
    )));
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  // ------------ Specific HTML tag handler methods -------------

  function ru_x_open(& $parser, & $attrs, $tagname ) 
  {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/

  function ru_x_cdata(& $parser, & $cdata) 
  {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "CDATA: {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_x_close(& $parser, $tag) 
  {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function pop_container_stack() 
  {/*{{{*/
    $content = $this->stack_to_containers();
    $this->unset_container_by_hash(nonempty_array_element($content,'hash_value'));
    $content = nonempty_array_element($content,'container');
    unset($content['sethash']);
    return $content;
  }/*}}}*/

  function stack_to_containers($execute_cleanup = FALSE) 
  {/*{{{*/
    $container = array_pop($this->container_stack);
    $hash_value = (is_array($container) && array_key_exists('sethash',$container)) ? nonempty_array_element( $container,'sethash' ) : NULL;
    if ( is_null($hash_value) ) {
      $this->syslog(__FUNCTION__, __LINE__, "(warning) - Missing container hash");
      $this->recursive_dump($container, "(warning) " . __METHOD__);
    } else if ( !is_array($container) ) {
      $this->syslog(__FUNCTION__, __LINE__, "(warning) - Got non-container item from container stack! " . print_r($containers));
      $hash_value = NULL;
    } else if ( array_key_exists($hash_value,$this->containers) ) {
      $this->syslog(__FUNCTION__, __LINE__, "(warning) - Hash collision on tag {$container['tagname']} - {$hash_value}");
      $hash_value = NULL;
    } else if ( $this->no_store ) {
      // We are disallowed from placing nodes into the container list
      $hash_value = NULL;
    } else {
      if ( $execute_cleanup ) $this->mark_container_placeholders($container);
      $this->containers[$hash_value] = $container;
    }
    return is_null($hash_value)
      ? NULL
      : array(
          'hash_value' => $hash_value,
          'container' => $container,
        );
  }/*}}}*/

  function unset_container_by_hash($hash_value) 
  {/*{{{*/
    if (!is_array($this->containers)) return;
    if ( array_key_exists($hash_value, $this->containers) ) {
      unset($this->containers[$hash_value]);
    }
  }/*}}}*/

  function & set_freewheel($v = TRUE) 
  {/*{{{*/
    // Suspend parsing when $v := TRUE
    $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - - - Parse switch - freewheel = " . ($v ? "TRUE" : "FALSE"));
    $this->freewheel = $v;
    return $this;
  }/*}}}*/

  function & set_terminated($v = TRUE) 
  {/*{{{*/
    // Suspend parsing when $v := TRUE
    $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - - - Parse switch - terminated = " . ($v ? "TRUE" : "FALSE"));
    $this->terminated = $v;
    return $this;
  }/*}}}*/

  function & current_tag() 
  {/*{{{*/
    $this->pop_tagstack();
    $this->push_tagstack();
    return $this;
  }/*}}}*/

  function embed_cdata_in_parent() 
  {/*{{{*/
    $i = $this->pop_tagstack();
    $content = join('',$this->current_tag['cdata']);
    $parent = $this->pop_tagstack();
    $parent['cdata'][] = " {$content} ";
    $this->push_tagstack($parent);
    $this->push_tagstack($i);
    return FALSE;
  }/*}}}*/

  function cdata_cleanup() 
  {/*{{{*/
    if ( is_array($this->current_tag) && array_key_exists('cdata',$this->current_tag) && is_array($this->current_tag['cdata']) ) {
      $this->current_tag['cdata'] = array_filter(explode('[BR]',preg_replace('@(&nbsp([;]*))+@i',' ',join('',$this->current_tag['cdata']))));
    }
  }/*}}}*/

}/*}}}*/

class SvgParseUtility extends RawparseUtility
{/*{{{*/
  
  var $extracted_styles = array();
  var $image_filename = NULL;
  var $dom = NULL;

  function __construct($image = NULL) {/*{{{*/
    parent::__construct();
    $this->set_image_filename($image);
    $this->initialize_dom();
  }/*}}}*/

  function __destruct() {/*{{{*/
    $this->dom = NULL;
    unset($this->dom);
  }/*}}}*/

  function ru_svg_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    $this->push_container_def($tag,$attrs);
    return TRUE;
  }/*}}}*/
  function ru_svg_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_svg_close(& $parser, $tag) {/*{{{*/
    // This root container should NOT be discarded
    $this->embed_container_in_parent($parser,$tag);
    return TRUE;
  }/*}}}*/

  function ru_g_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_g_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_g_close(& $parser, $tag) {/*{{{*/
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_path_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_path_cdata(& $parser, & $cdata) {/*{{{*/
    $this->append_cdata($cdata);
    return TRUE;
  }/*}}}*/
  function ru_path_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['attrs']['class'] = 'svg-outline';
    $this->push_tagstack();
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_polygon_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    return TRUE;
  }/*}}}*/
  function ru_polygon_cdata(& $parser, & $cdata) {/*{{{*/
    $this->append_cdata($cdata);
    return TRUE;
  }/*}}}*/
  function ru_polygon_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->add_to_container_stack($this->current_tag);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_rdf_rdf_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_rdf_rdf_cdata(& $parser, & $cdata) {/*{{{*/
    $this->append_cdata($cdata);
    return TRUE;
  }/*}}}*/
  function ru_rdf_rdf_close(& $parser, $tag) {/*{{{*/
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_cc_work_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_cc_work_cdata(& $parser, & $cdata) {/*{{{*/
    $this->append_cdata($cdata);
    return TRUE;
  }/*}}}*/
  function ru_cc_work_close(& $parser, $tag) {/*{{{*/
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_dc_format_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    return TRUE;
  }/*}}}*/
  function ru_dc_format_cdata(& $parser, & $cdata) {/*{{{*/
    $this->append_cdata($cdata);
    return TRUE;
  }/*}}}*/
  function ru_dc_format_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->add_to_container_stack($this->current_tag);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_dc_type_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    return TRUE;
  }/*}}}*/
  function ru_dc_type_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_dc_type_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->add_to_container_stack($this->current_tag);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_dc_title_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    return TRUE;
  }/*}}}*/
  function ru_dc_title_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_dc_title_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->add_to_container_stack($this->current_tag);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_defs_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    $this->push_container_def($tag, $attrs);
     return TRUE;
  }/*}}}*/
  function ru_defs_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_defs_close(& $parser, $tag) {/*{{{*/
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_metadata_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_metadata_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_metadata_close(& $parser, $tag) {/*{{{*/
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_style_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    if ($this->debug_tags) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) ---------- {$this->current_tag['tag']}" );
      $this->recursive_dump($this->current_tag,"(marker) ---");
    }
    return TRUE;
  }/*}}}*/
  function ru_style_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_style_close(& $parser, $tag) {/*{{{*/
    return $this->add_current_tag_to_container_stack();
  }/*}}}*/

  function ru_title_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    return TRUE;
  }/*}}}*/
  function ru_title_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_title_close(& $parser, $tag) {/*{{{*/
    // Treat <title> tags embedded in <path> parents.
    $this->pop_tagstack();
    $text = array(
      'title' => str_replace(array('[BR]',"\n"),array(''," "),join('',$this->current_tag['cdata'])),
      'seq' => $this->current_tag['attrs']['seq'],
    );
    $this->add_to_container_stack($text);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function reconstruct_svg($svg, $pagecontent = NULL) {/*{{{*/
    $debug_method = FALSE;
    if ( is_null($pagecontent) ) {
      $this->extracted_styles = array();
    }
    $pagecontent = '';
    foreach( $svg as $e ) {
      $tagname  = strtolower(nonempty_array_element($e,'tagname',nonempty_array_element($e,'tag')));
      if ( !(0 < strlen($tagname) ) ) continue;
      $attrs    = nonempty_array_element($e,'attrs',array());
      $children = array_filter(nonempty_array_element($e,'children',array()));
      $id       = nonempty_array_element($attrs,'id');
      $style    = nonempty_array_element($attrs,'style');
      if ( 0 < strlen($style) ) {/*{{{*/
        // Generate style lookup table 
        unset($attrs['style']);
        $style = explode(';',$style);
        array_walk($style, function(& $a, $k) { $a = explode(":",$a); $a = array("attr" => $a[0], "val" => $a[1]); });
        $style = array_combine(
          array_map(function($a) {return $a["attr"];},$style),
          array_map(function($a) {return $a["val"];},$style)
        );
        ksort($style);
        // Keep all unique style instances
        $tag_style_hash = md5(json_encode($style));
        if ( !array_key_exists($tag_style_hash, $this->extracted_styles) )
          $this->extracted_styles[$tag_style_hash] = array(
            'idents' => array($id => $id),
            'styles' => $style
          );
        else
          $this->extracted_styles[$tag_style_hash]['idents'][$id] = $id;
        if ( array_key_exists('fill',$style) ) {
          $attrs['oldfill'] = $style['fill'];
        }
      }/*}}}*/
      if ( 0 == count($children) ) {/*{{{*/
        // No children to process, skip to next SVG node
      }/*}}}*/
      else if ( 'path' == $tagname ) {/*{{{*/
        // Extract a sole title node, and use it as a title attribute
        $path_children = $children;
        $this->filter_nested_array($path_children,'title[title*=.*|i]',0);
        $attrs['title'] = nonempty_array_element($path_children,0);
        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) {$path_children}");
          $this->recursive_dump($path_children,"(marker) {$tagname} {$path_children}");
        }
      }/*}}}*/
      $attributes = array_filter($attrs);
      array_walk($attributes,function(& $a, $k) { $a = "{$k}=\"{$a}\""; });
      $attributes = join(' ', $attributes);
      $has_closing_tag = $tagname == 'polygon' ? FALSE : TRUE;
      $closing_tag = $has_closing_tag ? NULL : " /";
      $pagecontent .= <<<EOH
<{$tagname} {$attributes}{$closing_tag}>

EOH;
      $pagecontent .= $this->reconstruct_svg($children, $pagecontent);
      if ( $has_closing_tag ) $pagecontent .= <<<EOH
</{$tagname}>

EOH;
      // Generate tag at this nesting depth
    }
    return $pagecontent;
  }/*}}}*/

  function transform_svgimage($image = NULL) 
  {/*{{{*/

    $debug_method = FALSE;
    $this->debug_tags = TRUE; // $debug_method; // FALSE;
    $this->reset();

    // Retain case for all tags
    xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0 );

    if ( is_null($image) ) $image = $this->image_filename;

    if ( !file_exists($image) ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Missing file {$image}");
      return FALSE;
    }

    $loadresult = $this->dom->loadXML(file_get_contents($image));

    if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,
      "(marker) Load result (" .gettype($loadresult) . ")" . print_r($loadresult,TRUE) .
      " {$image}");

    $this->dom->normalizeDocument();

    $full_length  = mb_strlen($this->dom->saveXML());
    $chunk_length = 16384;
    if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) --------------------------- mb_strlen " . $full_length );

    for ( $offset = 0 ; $offset < $full_length ; $offset += $chunk_length ) {
      $is_final = ($offset + $chunk_length) >= $full_length;
      xml_parse($this->parser, mb_substr($this->dom->saveXML(), $offset, $chunk_length), $is_final);
      if ( $this->terminated ) {
        $this->syslog(__FUNCTION__,__LINE__,"(warning) Terminated.");
        break;
      }
    }

  }/*}}}*/

  function set_image_filename($image) {/*{{{*/
    if ( !is_null($image) ) {
      $this->image_filename = file_exists($image) ? $image : NULL; 
    }
  }/*}}}*/

  private function initialize_dom() {/*{{{*/
    $this->dom                      = new DOMDocument();
    $this->dom->recover             = TRUE;
    $this->dom->resolveExternals    = TRUE;
    $this->dom->preserveWhiteSpace  = FALSE;
    $this->dom->strictErrorChecking = FALSE;
    $this->dom->substituteEntities  = TRUE;

    if (0) {
      $this->parser = xml_parser_create();
      xml_set_object($this->parser, $this);
      xml_set_element_handler($this->parser, 'ru_tag_open', 'ru_tag_close');
      xml_set_character_data_handler($this->parser, 'ru_cdata');
      xml_set_default_handler($this->parser, 'ru_default');
      /* Diagnostics */
      xml_set_start_namespace_decl_handler($this->parser,'ru_start_namespace');
      xml_set_end_namespace_decl_handler($this->parser, 'ru_end_namespace');
      xml_set_processing_instruction_handler($this->parser, 'ru_processing_instr');
      xml_set_external_entity_ref_handler($this->parser, 'ru_external_entity_ref');

      /* Options. Do not change these XML_OPTION_CASE_FOLDING */
      xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 1 );
      xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1 );
      xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
    }


  }/*}}}*/

}/*}}}*/

class LegiscopeBase extends RawparseUtility 
{/*{{{*/

  public static $singleton       = NULL;
  public static $enable_debug    = FALSE;

  function __construct()
  {/*{{{*/
  }/*}}}*/

  static public function __callStatic($methodname, array $arguments)
  {/*{{{*/

    $arglist = join(',', array_keys($arguments));

    syslog(LOG_INFO, __METHOD__ . ": (marker) - ------- Inaccessible method {$methodname}({$arglist})");

  }/*}}}*/

  function get_template_filename($template_name, $for_class = NULL)
  {/*{{{*/
    if ( is_null($for_class) ) {
      $for_class = get_class($this);
    } 
    $for_class = join('.',array_reverse(camelcase_to_array($for_class)));
    $template_filename = array(SYSTEM_BASE,'templates',$for_class,$template_name);
    return join('/',$template_filename); 
  }/*}}}*/

  function get_template($template_name, $for_class = NULL)
  {/*{{{*/
    $fn = $this->get_template_filename($template_name, $for_class);
    return file_exists($fn) ? @file_get_contents($fn) : NULL;
  }/*}}}*/

  function transform_svgimage($image)
  {/*{{{*/
    $svg = new SvgParseUtility();
    $svg->transform_svgimage($image);
    $image = $svg->get_containers('attrs,children[tagname*=svg]',0);
    $image = nonempty_array_element($image,'children',array());
    $this->reorder_with_sequence_tags($image);
    $this->filter_nested_array($image,'tagname,attrs,children[tagname*=path|g|i]');
    $transformed = $svg->reconstruct_svg($image);
    $svg = NULL;
    return $transformed;
  }/*}}}*/

  static public function transform_svg(& $parameters, $imagepath = SYSTEM_BASE . "/../images/admin/philippines-4c.svg", $template_set = 'global', $template_basename = 'map.html' )
  {/*{{{*/
    $patterns = [];
    $replacements = [];

    if ( is_null( static::$singleton ) )
      static::$singleton = new LegiscopeBase;

    foreach ($parameters as $keys => $values) {
      $patterns[] = "{{$keys}}";
      $replacements[] = $values;
    }

    $search_for = array_merge(
      $patterns,
      [ '{svg_inline}' ]
    );
    
    $replace_with = array_merge(
      $replacements,
      [ static::$singleton->transform_svgimage($imagepath) ]
    );

    $template_subject = static::$singleton->get_template($template_basename,$template_set);

    return str_replace(
      $search_for,
      $replace_with,
      $template_subject 
    );
  }/*}}}*/


}/*}}}*/


$svg_fn    = "/var/www/html/qr.avahilario.net/images/regions/11/34/barangays-47.svg";
$bounds_fn = "/var/www/html/qr.avahilario.net/images/regions/11/34/barangays-47.svg.bounds";
$cache_fn  = "/var/www/html/qr.avahilario.net/images/regions/11/34/barangays-47.svg.cache";

$bounds    = json_decode( file_get_contents( $bounds_fn ), TRUE );

$lb        = new LegiscopeBase;
$c         = new ArrayFilterUtility;

$docpath = "attrs,children[tagname*=svg]";
$docpath = "tagname,attrs,children[tagname*=path|g|i]";

if (0) {
  $cbs = $c->get_map_functions( $docpath );
  recursive_dump($cbs, 'Callbacks:');

  $attr = $c->component_attribute_parts('attr'); // .$c->component_attribute_parts('prop');
  printf("Attr: {$attr}\n");
  $splittance = [];
  $res = preg_match_all("@\[['\"]*([^'\"]{1,})['\"]*\]@i", $attr, $splittance);
  printf("Result: %d, n = %d\n", $res, count($splittance) );
  print_r( $splittance );
}
else {
  $imagedata = LegiscopeBase::transform_svg($bounds, $svg_fn, 'global', 'map-bare.html' ); 
  printf( "%s\n", $imagedata );
}
