<?php

/*
 * Class SenateCommonParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommonParseUtility extends GenericParseUtility {
  
  protected $have_toc = FALSE;
  var $desc_stack = array();

  function __construct() {
    parent::__construct();
  }

  function ru_head_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/

  function ru_head_close(& $parser, $tag) {/*{{{*/
    array_pop($this->container_stack);
    return FALSE;
  }/*}}}*/

  function ru_div_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . "--- {$this->current_tag['tag']}" );
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ( $this->have_toc && (2 == count($attrs)) && 
      ($attrs["ALIGN"] == 'justify') &&
      ($attrs["CLASS"] == 'h3_uline') ) {
        $this->current_tag['link'] = NULL; 
        if ( 0 < count($this->desc_stack) ) {
          $desc = array_pop($this->desc_stack);
          $desc = array("link" => $desc["link"], "description" => $desc["description"]);
          array_push($this->desc_stack, $desc);
        }
         array_push($this->desc_stack, $this->current_tag);
      }
    if ($this->debug_tags) $this->recursive_dump($attrs,__LINE__);
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/

  function ru_div_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ( $this->have_toc ) {
      $desc = array_pop($this->desc_stack);
      $desc['cdata'][] = $cdata;
      array_push($this->desc_stack, $desc);
    }
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_div_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    if ( is_array($this->current_tag) && array_key_exists('attrs', $this->current_tag) ) {
      if ( array_key_exists($this->current_tag['attrs']['CLASS'],array_flip(array(
        'nav_dropdown',
        'div_hidden',
        'more',
        'sidepane_nav',
      )))) $skip = TRUE;
      if ( array_key_exists($this->current_tag['attrs']['ID'],array_flip(array(
        'nav_top',
        'nav_bottom',
      )))) $skip = TRUE;
    }
		if (is_array($this->current_tag) && !$skip ) {
		 	$this->stack_to_containers();
			// $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . "--- {$this->current_tag['tag']} " . join(' ', $this->current_tag['attrs']) . '-' . join(' ', $this->current_tag['cdata']) );
			if ( array_key_exists('cdata', $this->current_tag) ) {
				$this->current_tag['cdata'] = join('', array_filter($this->current_tag['cdata']));
				if ( $this->have_toc ) {
					$desc = array_pop($this->desc_stack);
					if ( 2 == count($this->current_tag['attrs']) &&
						$this->current_tag['attrs']['ALIGN'] == 'justify' &&
						$this->current_tag['attrs']['CLASS'] == 'h3_uline' ) {
						} else if (1 == count($this->current_tag['attrs']) &&
							$this->current_tag['attrs']['ALIGN'] == 'justify' ) {
								$desc['description'] = $this->current_tag['cdata'];
							}
					array_push($this->desc_stack, $desc);
				}
			}
		}
    $this->push_tagstack();
    
    return !$skip;
  }/*}}}*/

  function ru_br_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_br_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_br_close(& $parser, $tag) {/*{{{*/
		$me     = $this->pop_tagstack();
		$parent = $this->pop_tagstack();
		if ( array_key_exists('cdata', $parent) ) {
			// $this->syslog(__FUNCTION__,__LINE__,"Adding line break to {$parent['tag']} (" . join(' ', $parent['cdata']) . ")" );
			$parent['cdata'][] = "\n[BR]";
		}
		$this->push_tagstack($parent);
		$this->push_tagstack($me);
    return FALSE;
  }/*}}}*/

  function ru_img_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
		$this->pop_tagstack();
		$this->update_current_tag_url('SRC');
		// Add capability to cache images as well
		$this->current_tag['attrs']['HREF'] = $this->current_tag['attrs']['SRC'];
    if ( !array_key_exists('CLASS', $this->current_tag['attrs']) ) {
      // $this->syslog(__FUNCTION__,__LINE__,"Setting CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] = 'legiscope-remote';
    } else {
      // $this->syslog(__FUNCTION__,__LINE__,"Adding CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] .= ' legiscope-remote';
    }
		$this->push_tagstack();
    return TRUE;
  }  /*}}}*/

  function ru_img_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_img_close(& $parser, $tag) {/*{{{*/
		$skip = FALSE;
		$this->pop_tagstack();
		if ( 1 == preg_match('@(nav_logo)@',$this->current_tag['attrs']['CLASS']) ) $skip = TRUE;
		if ( !$skip ) {
			$image = array('image' => $this->current_tag['attrs']['SRC']);
			$this->add_to_container_stack($image);
		} else {
			// $this->recursive_dump($this->current_tag,__LINE__);
		}
		$this->push_tagstack();
    return !$skip;
  }/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->update_current_tag_url('HREF');
    if ( !array_key_exists('CLASS', $this->current_tag['attrs']) ) {
      // $this->syslog(__FUNCTION__,__LINE__,"Setting CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] = 'legiscope-remote';
			$this->current_tag['attrs']['ID'] = UrlModel::get_url_hash($this->current_tag['attrs']['HREF']);
    } else {
      // $this->syslog(__FUNCTION__,__LINE__,"Adding CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] .= ' legiscope-remote';
    }
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/

  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
		$this->add_to_container_stack($this->current_tag);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

}

